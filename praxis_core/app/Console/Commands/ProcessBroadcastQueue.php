<?php

namespace App\Console\Commands;

use App\Mail\IntelligenceBroadcast;
use App\Models\Campaigns\Broadcast;
use App\Services\Site\TrackingService;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Renderer;
use App\Support\Campaigns\Tracking;
use App\Support\MailLocale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Throwable;

/**
 * The broadcast sender (docs/CAMPAIGNS.md 4.6), every 5 minutes from the scheduler, never two at once:
 *   0. emails a run claimed but never finished (it died mid-send: a PHP timeout, an SMTP hang cPanel killed) fail after
 *      INTERRUPTED_MINUTES; they are not sent again, because the first try may have reached the person;
 *   1. scheduled broadcasts whose time has come start (their audience is worked out now);
 *   2. the next 30 queued emails go out, oldest broadcast first, each in its person's language, unless the address is
 *      on the do-not-email list by now (Skipped); an address the mail server refuses for good is Bounced and goes on
 *      the list; anything else is Failed;
 *   3. broadcasts with nothing left to send are completed, with a bell to whoever sent them.
 * 30 every 5 minutes is the pace the cPanel server has always kept, whatever the number of broadcasts: they queue
 * behind each other rather than run side by side.
 */
class ProcessBroadcastQueue extends Command
{
    protected $signature = 'broadcast:process';

    protected $description = 'Sends the next broadcast emails (30 a run) and starts scheduled broadcasts.';

    /** A row claimed this long ago and still Sending belongs to a run that died */
    public const INTERRUPTED_MINUTES = 15;

    public const INTERRUPTED_MESSAGE = 'Interrupted while sending; not sent again so nobody gets it twice.';

    public function handle(): int
    {
        $this->failInterrupted();
        Broadcasts::startDue();

        $ids = DB::table('broadcast_logs')->where('status', 'Queued')->orderBy('id')->limit(Broadcasts::PER_RUN)->pluck('id');
        $campaigns = [];
        foreach ($ids as $id) {
            // Claimed row by row, so a second sender running by mistake can never send the same email twice
            if (! DB::table('broadcast_logs')->where('id', $id)->where('status', 'Queued')->update(['status' => 'Sending', 'updated_at' => now()])) {
                continue;
            }
            $log = DB::table('broadcast_logs')->where('id', $id)->first();
            $campaign = $campaigns[$log->campaign_id] ??= Broadcast::find($log->campaign_id);

            try {
                if (! $campaign) {
                    throw new \RuntimeException('The broadcast no longer exists.');
                }
                $campaign->isLegacy() ? $this->sendLegacy($log, $campaign) : $this->send($log, $campaign);
            } catch (Throwable $e) {
                $this->failed($log, $campaign, $e);
            }
        }

        Broadcasts::finishDone();

        return self::SUCCESS;
    }

    /** Rows a dead run left in Sending: Failed, counted on their broadcast, never queued again */
    private function failInterrupted(): void
    {
        $stuck = DB::table('broadcast_logs')->where('status', 'Sending')->where('updated_at', '<', now()->subMinutes(self::INTERRUPTED_MINUTES));
        foreach ((clone $stuck)->groupBy('campaign_id')->selectRaw('campaign_id, COUNT(*) AS n')->pluck('n', 'campaign_id') as $campaignId => $n) {
            $failed = (clone $stuck)->where('campaign_id', $campaignId)
                ->update(['status' => 'Failed', 'error_message' => self::INTERRUPTED_MESSAGE, 'updated_at' => now()]);
            if ($failed) {
                DB::table('broadcast_campaigns')->where('id', $campaignId)->increment('failed_count', $failed);
            }
        }
    }

    private function send(object $log, Broadcast $campaign): void
    {
        if (! Marketing::canEmail($log->email)) {
            $this->mark($log, 'Skipped', 'On the do-not-email list');

            return;
        }

        $links = DB::table('broadcast_links')->where('campaign_id', $campaign->id)->where('locale', $log->locale)->get(['id', 'url'])
            ->mapWithKeys(fn ($l) => [$l->url => Tracking::clickUrl($log->token, $l->id)])->all();
        $person = ['first_name' => $log->first_name, 'last_name' => $log->last_name, 'company' => $log->company, 'kind' => $log->kind];

        Mail::to($log->email)->send(Renderer::mail($campaign, $log->locale, $person,
            unsubscribeUrl: Tracking::unsubscribeUrl($log->token), pixelUrl: Tracking::openUrl($log->token), links: $links));

        DB::table('broadcast_logs')->where('id', $log->id)->update(['status' => 'Sent', 'sent_at' => now(), 'error_message' => null, 'updated_at' => now()]);
        DB::table('broadcast_campaigns')->where('id', $campaign->id)->increment('sent_count');
    }

    /** A broadcast written before 3 Nov 2026 (one HTML body, one language), still queued when this version shipped */
    private function sendLegacy(object $log, Broadcast $campaign): void
    {
        $user = DB::table('client_users')->where('id', $log->user_id)->first();
        if (! $user || ! $user->is_subscribed || $user->deleted_at || ! Marketing::canEmail($user->email)) {
            $this->mark($log, 'Skipped', 'Recipient unsubscribed or removed.');

            return;
        }
        $org = DB::table('organizations')->where('id', $user->organization_id)->first();
        $locale = MailLocale::forRecipient($campaign->language ?? null);

        // "Hello {{ first_name }}," becomes "Hello Ada," or "Hello,"; French never greets by first name alone
        $first = trim((string) ($user->first_name ?? ''));
        $body = preg_replace('/\s*\{\{\s*first_name\s*\}\}/i', ($locale === 'fr' || $first === '') ? '' : ' ' . e($first), (string) $campaign->body_html);
        $vars = [
            'company_name' => filled($org->name ?? null) ? $org->name : __('emails.broadcast.fallback_company', [], $locale),
            'industry' => filled($org->industry ?? null) ? $org->industry : __('emails.broadcast.fallback_industry', [], $locale),
            'pipeline_stage' => '',
        ];
        $body = preg_replace_callback('/\{\{\s*(company_name|industry|pipeline_stage)\s*\}\}/i', fn ($m) => e($vars[strtolower($m[1])]), $body);

        $t = TrackingService::token($campaign->id, $user->id);
        $pixelUrl = route('tracking.open', ['campaign_id' => $campaign->id, 'user_id' => $user->id, 't' => $t]);
        $unsubUrl = route('tracking.unsubscribe', ['user_id' => $user->id, 'campaign' => $campaign->id, 't' => $t]);
        Mail::to($user->email)->locale($locale)->send(new IntelligenceBroadcast($campaign->subject, $body, $unsubUrl, $pixelUrl));

        DB::table('broadcast_logs')->where('id', $log->id)->update(['status' => 'Sent', 'sent_at' => now(), 'updated_at' => now()]
            + (TrackingService::canSign() ? ['signed_links' => 1] : []));
        DB::table('broadcast_campaigns')->where('id', $campaign->id)->increment('sent_count');
    }

    private function failed(object $log, ?Broadcast $campaign, Throwable $e): void
    {
        if (self::isPermanent($e)) {
            $this->mark($log, 'Bounced', $e->getMessage());
            Marketing::suppress($log->email, 'bounced', ['source' => 'broadcast', 'broadcast_id' => $log->campaign_id, 'note' => mb_substr($e->getMessage(), 0, 200)]);
        } else {
            $this->mark($log, 'Failed', $e->getMessage());
            report($e);
        }
        if ($campaign) {
            DB::table('broadcast_campaigns')->where('id', $campaign->id)->increment('failed_count');
        }
    }

    private function mark(object $log, string $status, string $message): void
    {
        DB::table('broadcast_logs')->where('id', $log->id)->update(['status' => $status, 'error_message' => mb_substr($message, 0, 250), 'updated_at' => now()]);
    }

    /**
     * The mail server refused the address itself (unknown mailbox, invalid address): sending again would fail the same
     * way. Only a mailer's own error can say so; any other exception (a bug in HQ) is Failed and reported, so it can
     * never put people on the do-not-email list.
     */
    public static function isPermanent(Throwable $e): bool
    {
        if ($e instanceof RfcComplianceException) {
            return true;
        }
        if (! $e instanceof TransportExceptionInterface) {
            return false;
        }
        $message = $e->getMessage();

        return (bool) preg_match('/\b55[0-4]\b|\b5\.1\.[0-9]\b|user unknown|no such user|mailbox (unavailable|not found)|does not exist|address rejected|recipient (address )?rejected/i', $message)
            && ! preg_match('/\b(4[0-9]{2}|5\.7\.[0-9])\b|rate|try again|temporar/i', $message);
    }
}
