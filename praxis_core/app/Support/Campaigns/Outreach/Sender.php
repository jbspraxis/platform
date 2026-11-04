<?php

namespace App\Support\Campaigns\Outreach;

use App\Console\Commands\ProcessBroadcastQueue;
use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\RichText;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailSendException;
use App\Support\Mail\MailTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Throwable;

/**
 * The outreach sender (outreach:send, every minute from the scheduler, never two at once). Each run:
 *
 *   0. emails a run took but never finished (it died mid-send) fail after INTERRUPTED_MINUTES and are not sent
 *      again, because the first try may have reached the person;
 *   1. replies stop their follow-ups (Replies::sweep) and spam complaints pause their sender (Replies::complaints);
 *   2. outside the window (weekdays 08:30–17:30 WAT by default) nothing goes;
 *   3. each sender who isn't paused, whose mailbox is connected, who is under today's limit and whose last email
 *      went at least gap_minutes ago sends ONE email: a follow-up that is due first, else the oldest approved first
 *      email of their active campaigns. Follow-ups count toward the limit.
 *
 * Every email is taken row by row (approved → sending, one update) before it goes, so it can never go twice. At the
 * last moment the do-not-email list is asked again (Marketing::canEmail), and a follow-up checks once more for a reply.
 * A permanent refusal of the address is Bounced and goes on the list; the sender pauses when more of the day's
 * emails bounced than the limit allows. A refused password pauses the sender and puts the email back.
 */
final class Sender
{
    public const INTERRUPTED_MINUTES = 15;

    public const INTERRUPTED_MESSAGE = 'Interrupted while sending; not sent again so nobody gets it twice.';

    /** Temporary refusals (4xx) put the email back this many times before it is marked not sent */
    public const MAX_ATTEMPTS = 3;

    /** @return array{sent: int, skipped: int, bounced: int, failed: int, cancelled: int} */
    public static function run(): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'bounced' => 0, 'failed' => 0, 'cancelled' => 0];
        self::failInterrupted();
        $result['cancelled'] += Replies::sweep();
        Replies::complaints();

        if (! Limits::inWindow()) {
            return $result;
        }
        foreach (self::senders() as $senderId) {
            $outcome = self::next($senderId);
            if ($outcome !== null) {
                $result[$outcome] = ($result[$outcome] ?? 0) + 1;
            }
        }

        return $result;
    }

    /** People with something to send now: approved first emails of active campaigns, or follow-ups that are due */
    private static function senders(): array
    {
        $now = now();

        return DB::table('outreach_emails AS e')->join('outreach_campaigns AS c', 'c.id', '=', 'e.campaign_id')
            ->where('c.status', 'active')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('e.kind', 'first')->where('e.status', 'approved'))
                ->orWhere(fn ($q) => $q->where('e.kind', 'follow_up')->where('e.status', 'scheduled')->where('e.send_after', '<=', $now)))
            ->distinct()->orderBy('c.sender_id')->pluck('c.sender_id')->all();
    }

    /** One email from this sender, if the rules allow it now; returns its outcome (sent, skipped, …) or null */
    private static function next(string $senderId): ?string
    {
        $sender = User::find($senderId);
        if (! $sender || $sender->status !== 'Active' || Senders::isPaused($senderId)) {
            return null;
        }
        $account = Senders::mailbox($senderId);
        if (! $account || $account->status !== 'active' || ! MailAccess::can($sender, $account, 'send')) {
            return null;
        }
        $now = now();
        if (Senders::triedToday($senderId) >= Senders::limitToday($senderId)) {
            return null;
        }
        $last = Senders::lastTriedAt($senderId);
        if ($last && $last->gt($now->copy()->subMinutes((int) Limits::get('gap_minutes')))) {
            return null;
        }

        $email = self::due($senderId, $now);
        if (! $email) {
            return null;
        }
        $from = $email->status;
        // Taken before it goes: a second sender running by mistake finds it no longer approved or scheduled
        $claimed = OutreachEmail::whereKey($email->id)->where('status', $from)->update([
            'status' => 'sending', 'tried_at' => $now, 'attempts' => DB::raw('attempts + 1'), 'sender_id' => $senderId,
            'mail_account_id' => $account->id, 'message_id' => $email->message_id ?: MailTransport::newMessageId($account), 'updated_at' => $now,
        ]);
        if (! $claimed) {
            return null;
        }
        Senders::started($senderId, $now);

        return self::deliver($email->fresh(), $from, $sender, $account);
    }

    /** The follow-up that is due first, else the oldest approved first email of the sender's active campaigns */
    private static function due(string $senderId, Carbon $now): ?OutreachEmail
    {
        $campaigns = OutreachCampaign::where('sender_id', $senderId)->where('status', 'active')->select('id');

        return OutreachEmail::whereIn('campaign_id', $campaigns)->where('kind', 'follow_up')->where('status', 'scheduled')
            ->where('send_after', '<=', $now)->orderBy('send_after')->orderBy('id')->first()
            ?? OutreachEmail::whereIn('campaign_id', $campaigns)->where('kind', 'first')->where('status', 'approved')
                ->orderBy('approved_at')->orderBy('id')->first();
    }

    private static function deliver(OutreachEmail $email, string $from, User $sender, MailAccount $account): string
    {
        $prospect = OutreachProspect::find($email->prospect_id);
        $first = $email->isFirst() ? null : OutreachEmail::where('prospect_id', $email->prospect_id)->where('kind', 'first')->first();

        // The last moment: the list, the prospect's state, and for a follow-up a reply that arrived since the last sweep
        if (! $prospect || ! Marketing::canEmail($prospect->email)) {
            return self::stopped($email, 'On the do-not-email list');
        }
        if (in_array($prospect->status, ['replied', 'bounced', 'unsubscribed'], true)) {
            return self::stopped($email, OutreachProspect::STATUSES[$prospect->status]);
        }
        if (! $email->isFirst() && (! $first || $first->status !== 'sent')) {
            return self::stopped($email, 'The first email didn’t go');
        }
        if (! $email->isFirst() && Replies::found($prospect, $first)) {
            Replies::mark($prospect);

            return 'cancelled';
        }
        if (RichText::isBlank($email->body_html) || ($email->isFirst() && trim((string) $email->subject) === '')) {
            return self::stopped($email, 'Nothing to send: the email is empty');
        }

        try {
            $result = MailTransport::make()->send($sender, $account, Message::build($email, $prospect, $sender, $account, $first));
        } catch (Throwable $e) {
            return self::failed($email, $from, $prospect, $e);
        }

        $now = now();
        OutreachEmail::whereKey($email->id)->update(['status' => 'sent', 'sent_at' => $now, 'message_id' => $result['message_id'], 'note' => null, 'updated_at' => $now]);
        if ($email->isFirst()) {
            OutreachProspect::whereKey($prospect->id)->where('status', 'new')->update(['status' => 'emailed']);
            OutreachEmail::where('prospect_id', $prospect->id)->where('kind', 'follow_up')->where('status', 'approved')->update([
                'status' => 'scheduled', 'send_after' => Limits::addWorkingDays($now, (int) Limits::get('follow_up_days')), 'updated_at' => $now,
            ]);
        }

        return 'sent';
    }

    /** Not sent, and never will be: a first email is skipped, a follow-up cancelled (and the first's follow-up with it) */
    private static function stopped(OutreachEmail $email, string $why): string
    {
        $status = $email->isFirst() ? 'skipped' : 'cancelled';
        OutreachEmail::whereKey($email->id)->update(['status' => $status, 'note' => $why, 'tried_at' => null, 'updated_at' => now()]);
        if ($email->isFirst()) {
            OutreachEmail::where('prospect_id', $email->prospect_id)->where('kind', 'follow_up')->whereIn('status', ['draft', 'approved', 'scheduled'])
                ->update(['status' => 'cancelled', 'note' => $why, 'updated_at' => now()]);
        }

        return $email->isFirst() ? 'skipped' : 'cancelled';
    }

    private static function failed(OutreachEmail $email, string $from, OutreachProspect $prospect, Throwable $e): string
    {
        $now = now();
        if (self::isBounce($e)) {
            OutreachEmail::whereKey($email->id)->update(['status' => 'bounced', 'note' => mb_substr($e->getMessage(), 0, 250), 'updated_at' => $now]);
            Marketing::suppress($prospect->email, 'bounced', ['source' => 'outreach', 'note' => mb_substr($e->getMessage(), 0, 200)]);
            OutreachProspect::whereKey($prospect->id)->update(['status' => 'bounced', 'bounced_at' => $now]);
            Outreach::stop($prospect->id, 'Bounced');
            self::checkBounces($email->sender_id);

            return 'bounced';
        }

        if ($e instanceof MailSendException && $e->reason === 'auth') {
            // Refused before anything was sent: back where it was, and the sender waits for their password
            OutreachEmail::whereKey($email->id)->update(['status' => $from, 'note' => null, 'updated_at' => $now]);
            Senders::pause($email->sender_id, 'mailbox', 'The mailbox ' . ($email->mail_account_id ? MailAccount::whereKey($email->mail_account_id)->value('email') : '') . ' refused its password when an outreach email was sent. Reconnect it in Mail › Mailboxes.');

            return 'failed';
        }
        if ($e instanceof MailSendException && $e->reason === 'temporary' && $e->smtpCode >= 400 && $e->smtpCode < 500 && $email->attempts < self::MAX_ATTEMPTS) {
            // The server said "try later" (4xx) and kept nothing: back in the queue for a later turn
            OutreachEmail::whereKey($email->id)->update(['status' => $from, 'note' => mb_substr($e->getMessage(), 0, 250), 'updated_at' => $now]);

            return 'failed';
        }

        OutreachEmail::whereKey($email->id)->update(['status' => 'failed', 'note' => mb_substr($e->getMessage(), 0, 250), 'updated_at' => $now]);
        if ($email->isFirst()) {
            OutreachEmail::where('prospect_id', $prospect->id)->where('kind', 'follow_up')->whereIn('status', ['draft', 'approved', 'scheduled'])
                ->update(['status' => 'cancelled', 'note' => 'The first email didn’t go', 'updated_at' => $now]);
        }
        if (! $e instanceof MailSendException) {
            report($e);
        }

        return 'failed';
    }

    /**
     * More of today's emails bounced than the limit allows, and at least Senders::MIN_BOUNCES_TO_PAUSE of them: the
     * sender pauses (once), with a bell to them and the owner
     */
    private static function checkBounces(?string $senderId): void
    {
        if (! $senderId) {
            return;
        }
        [$bounced, $tried] = Senders::bouncesToday($senderId);
        $limit = (int) Limits::get('bounce_pause_percent');
        if ($bounced >= Senders::MIN_BOUNCES_TO_PAUSE && $bounced * 100 > $limit * $tried) {
            Senders::pause($senderId, 'bounces', "{$bounced} of today’s {$tried} outreach " . ($tried === 1 ? 'email' : 'emails') . ' bounced (' . round(100 * $bounced / $tried) . "%, more than {$limit}%). Check the list for out-of-date addresses before resuming.");
        }
    }

    /**
     * The mail server refused the address itself (unknown mailbox, invalid address): sending again would fail the same
     * way, so it is a bounce. Only the mailer's own errors can say so (as ProcessBroadcastQueue::isPermanent), so a bug in
     * HQ never puts an address on the do-not-email list.
     */
    public static function isBounce(Throwable $e): bool
    {
        if ($e instanceof RfcComplianceException) {
            return true;
        }
        if ($e instanceof MailSendException) {
            return $e->reason === 'rejected' && $e->permanent && ProcessBroadcastQueue::refusesAddress($e->getMessage());
        }

        return $e instanceof TransportExceptionInterface && ProcessBroadcastQueue::isPermanent($e);
    }

    /** Emails a dead run left in Sending: not sent again (the first try may have reached them) */
    private static function failInterrupted(): void
    {
        $stuck = OutreachEmail::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(self::INTERRUPTED_MINUTES))->get();
        foreach ($stuck as $email) {
            if (OutreachEmail::whereKey($email->id)->where('status', 'sending')->update(['status' => 'failed', 'note' => self::INTERRUPTED_MESSAGE, 'updated_at' => now()]) && $email->isFirst()) {
                OutreachEmail::where('prospect_id', $email->prospect_id)->where('kind', 'follow_up')->whereIn('status', ['draft', 'approved'])
                    ->update(['status' => 'cancelled', 'note' => 'The first email may not have gone', 'updated_at' => now()]);
            }
        }
    }
}
