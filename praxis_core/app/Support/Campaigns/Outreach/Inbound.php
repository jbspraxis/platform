<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Campaigns\OutreachReply;
use App\Models\Mail\MailMessage;
use App\Support\Campaigns\Marketing;
use App\Support\Integrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What came back from outreach emails (docs/CAMPAIGNS.md section 12), found in HQ Mail's copy of the mailboxes as
 * mail:sync stores it. outreach:replies (every minute) reads the messages stored since its last run (a cursor on
 * mail_messages.id, kept in integration_settings) and files each one at most once per prospect:
 *
 *   a bounce notice (Notices::kind 'bounce')   naming an outreach email's Message-ID, or naming the prospect's address
 *                                              and arriving in the mailbox that emailed them: the email is Bounced, the
 *                                              address goes on the do-not-email list, the prospect is bounced, what was
 *                                              still to go stops, and the bounce counts toward the sender's pause;
 *   a spam complaint report (ARF)              the same matching: the address goes on the list as complained, and
 *                                              Replies::complaints() pauses the sender (C2);
 *   a reply                                    In-Reply-To or References naming an outreach email, or mail from the
 *                                              prospect's address after the email went (C2's Replies::found rule): the
 *                                              prospect has replied, the follow-up stops, and Claude is asked to sort it.
 *
 * "Delayed, still trying" notices, and mail from mail systems that says neither, are left alone. Only a notice's
 * text is read here (a person's mail is matched on its headers), through HQ Mail's own contract (ReplyText::body).
 */
final class Inbound
{
    /** integration_settings key: the last mail_messages.id outreach:replies has read */
    public const CURSOR = 'outreach.replies_cursor';

    /** Messages one run reads */
    public const BATCH = 1000;

    /** Mail stored in the last few seconds waits for the next run, so a message still being written is never skipped */
    public const SETTLE_SECONDS = 20;

    /** A notice that names an address (not a Message-ID) only counts for emails sent this many days before it */
    public const NOTICE_DAYS = 14;

    private const COLUMNS = ['id', 'account_id', 'message_id_hash', 'in_reply_to', 'references', 'subject', 'from_name', 'from_email',
        'received_at', 'is_outbound', 'snippet', 'body_state', 'has_attachments', 'created_at'];

    /** The run: new messages since the cursor; returns how many replies, bounces and complaints it filed */
    public static function collect(): int
    {
        $settled = now()->subSeconds(self::SETTLE_SECONDS);
        $cursor = Integrations::get(self::CURSOR);
        if ($cursor === null) {
            return self::firstRun($settled);
        }
        $rows = DB::table('mail_messages')->where('id', '>', (int) $cursor)->orderBy('id')->limit(self::BATCH)->get(self::COLUMNS);
        $ready = $rows->takeWhile(fn ($m) => $m->created_at === null || Carbon::parse($m->created_at)->lte($settled));
        if ($ready->isEmpty()) {
            return 0;
        }
        $filed = self::look($ready);
        Integrations::set(self::CURSOR, (string) $ready->last()->id);

        return $filed;
    }

    /** The first run after the release: only mail since the first outreach email went can be about one */
    private static function firstRun(Carbon $settled): int
    {
        $top = (int) DB::table('mail_messages')->where('created_at', '<=', $settled)->max('id');
        $since = DB::table('outreach_emails')->whereNotNull('sent_at')->min('sent_at');
        $filed = 0;
        if ($since) {
            DB::table('mail_messages')->where('is_outbound', 0)->where('received_at', '>=', Carbon::parse($since)->subMinute())->where('id', '<=', $top)
                ->orderBy('id')->select(self::COLUMNS)->chunk(500, function ($rows) use (&$filed) {
                    $filed += self::look($rows);
                });
        }
        Integrations::set(self::CURSOR, (string) $top);

        return $filed;
    }

    /** Each message: a notice, a reply, or nothing to do with outreach. Returns how many it filed. */
    public static function look(Collection $messages): int
    {
        $messages = $messages->filter(fn ($m) => ! $m->is_outbound && $m->received_at)->values();
        if ($messages->isEmpty()) {
            return 0;
        }
        // One query each for the batch: outreach emails its messages name, prospects who wrote, attachments' types
        $named = OutreachEmail::whereIn('message_id', $messages->flatMap(fn ($m) => self::refs($m))->unique()->values())->whereNotNull('sent_at')
            ->get()->groupBy('message_id');
        $writers = OutreachProspect::whereIn('email', $messages->map(fn ($m) => Marketing::normalize($m->from_email))->filter()->unique()->values())
            ->pluck('id', 'email');
        $mimes = DB::table('mail_attachments')->whereIn('message_id', $messages->where('has_attachments', 1)->pluck('id'))->get(['message_id', 'mime'])
            ->groupBy('message_id')->map(fn ($rows) => $rows->pluck('mime')->all());

        $filed = 0;
        foreach ($messages as $m) {
            $types = $mimes[$m->id] ?? [];
            if (Notices::fromMailSystem($m->from_email, $types) && ($done = self::asNotice($m, $types)) !== null) {
                $filed += $done ? 1 : 0;

                continue;
            }
            $email = self::replyTo($m, $named, $writers);
            $filed += $email && self::reply($email, $m) ? 1 : 0;
        }

        return $filed;
    }

    /** Message-IDs the message answers (References, In-Reply-To), without brackets */
    private static function refs(object $m): array
    {
        return array_values(array_filter(array_map(fn ($id) => trim($id, "<> \t"), array_merge(
            preg_split('/\s+/', trim((string) $m->references)) ?: [], [(string) $m->in_reply_to]))));
    }

    // ------------------------------------------------------------------ matching

    /** The outreach email a person's message answers: named in the conversation, or theirs and sent before it arrived */
    private static function replyTo(object $m, Collection $named, Collection $writers): ?OutreachEmail
    {
        $by = Carbon::parse($m->received_at)->addMinute();
        $email = collect(self::refs($m))->flatMap(fn ($id) => $named[$id] ?? [])->filter(fn (OutreachEmail $e) => $e->sent_at->lte($by))
            ->sortByDesc('sent_at')->first();
        if ($email) {
            return $email;
        }
        $prospectId = $writers[Marketing::normalize($m->from_email)] ?? null;

        return $prospectId ? OutreachEmail::where('prospect_id', $prospectId)->whereNotNull('sent_at')->where('sent_at', '<=', $by)
            ->orderByDesc('sent_at')->first() : null;
    }

    /**
     * A message from a mail system or a feedback loop: its text is read, then it is filed when it is a bounce or a
     * complaint about an outreach email. Null: not a notice after all (a person writing from an abuse@ or feedback@
     * address), so it may still be a reply.
     */
    private static function asNotice(object $m, array $types): ?bool
    {
        $model = MailMessage::find($m->id);
        $text = $model ? ReplyText::full(ReplyText::body($model)) : '';
        $text = trim($text) !== '' ? $text : (string) $m->snippet;
        $kind = Notices::kind($m->from_email, $m->subject, $text, $types);
        if ($kind === null) {
            return Notices::isMailSystem($m->from_email, $types) ? false : null;
        }
        if ($kind === 'delay') {
            return false;
        }
        $email = self::noticeAbout($m, $text);

        return $email ? self::notice($kind, $email, $m, $text) : false;
    }

    /**
     * The outreach email a notice is about, strictly: its Message-ID in the notice's headers or text; else exactly one
     * prospect's address named in it, emailed from the mailbox the notice arrived in, in the days before it
     */
    private static function noticeAbout(object $m, string $text): ?OutreachEmail
    {
        $received = Carbon::parse($m->received_at);
        $ids = array_values(array_unique(array_merge(self::refs($m), Notices::quotedIds($text))));
        if ($ids) {
            $email = OutreachEmail::whereIn('message_id', $ids)->whereNotNull('sent_at')->where('sent_at', '<=', $received->copy()->addMinute())->first();
            if ($email) {
                return $email;
            }
        }
        $sent = OutreachEmail::where('mail_account_id', $m->account_id)->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$received->copy()->subDays(self::NOTICE_DAYS), $received->copy()->addMinute()])
            ->with('prospect:id,email')->orderByDesc('sent_at')->get();
        $haystack = $m->subject . "\n" . $text;
        $about = $sent->filter(fn (OutreachEmail $e) => $e->prospect && Notices::names($haystack, $e->prospect->email));

        // Nobody, or several people: not ours to guess
        return $about->pluck('prospect_id')->unique()->count() === 1 ? $about->first() : null;
    }

    // ------------------------------------------------------------------ filing

    private static function row(OutreachEmail $email, object $m, string $kind, array $values = []): ?OutreachReply
    {
        $key = $m->message_id_hash ?: 'id:' . $m->id;
        if (OutreachReply::where('prospect_id', $email->prospect_id)->where('message_key', $key)->exists()) {
            return null; // another copy of a message already filed (a second folder or mailbox)
        }

        return OutreachReply::create($values + [
            'campaign_id' => $email->campaign_id, 'prospect_id' => $email->prospect_id, 'email_id' => $email->id, 'kind' => $kind,
            'mail_message_id' => $m->id, 'message_key' => $key, 'mail_account_id' => $m->account_id,
            'from_email' => mb_substr(Marketing::normalize($m->from_email), 0, 190) ?: null, 'from_name' => $m->from_name ? mb_substr($m->from_name, 0, 190) : null,
            'subject' => $m->subject ? mb_substr($m->subject, 0, 500) : null, 'snippet' => $m->snippet ? mb_substr($m->snippet, 0, 300) : null,
            'received_at' => $m->received_at,
        ]);
    }

    /** A person wrote back: replied, the follow-up stops (as C2's sweep would), and Claude is asked for a label */
    private static function reply(OutreachEmail $email, object $m): bool
    {
        $reply = self::row($email, $m, 'reply');
        if (! $reply) {
            return false;
        }
        OutreachProspect::whereKey($email->prospect_id)->whereNull('replied_at')->update(['replied_at' => $m->received_at]);
        OutreachProspect::whereKey($email->prospect_id)->whereIn('status', ['new', 'emailed'])->update(['status' => 'replied']);
        Outreach::stop($email->prospect_id, 'They replied');
        Sorting::start($reply);

        return true;
    }

    /** A mail server's notice: filed by HQ, because it is the server's fact and not a guess */
    private static function notice(string $kind, OutreachEmail $email, object $m, string $text): bool
    {
        $prospect = OutreachProspect::find($email->prospect_id);
        if (! $prospect) {
            return false;
        }
        $why = $kind === 'bounce' ? Notices::reason($text, $m->subject) : 'Spam complaint report from the mail provider';
        $reply = self::row($email, $m, $kind, ['label' => $kind === 'bounce' ? 'bounced' : 'complained', 'label_at' => now(), 'note' => $why]);
        if (! $reply) {
            return false;
        }
        if ($kind === 'complaint') {
            Marketing::suppress($prospect->email, 'complained', ['source' => 'outreach', 'note' => $why]);
            OutreachProspect::whereKey($prospect->id)->update(['reply_label' => 'complained']);
            Replies::complaints(); // C2: the prospect is marked, what was to go stops, and the sender who wrote to them pauses

            return true;
        }

        $now = now();
        OutreachEmail::whereKey($email->id)->whereIn('status', ['sent', 'bounced'])
            ->update(['status' => 'bounced', 'note' => mb_substr('Bounced after sending: ' . $why, 0, 250), 'updated_at' => $now]);
        Marketing::suppress($prospect->email, 'bounced', ['source' => 'outreach', 'note' => mb_substr('Bounce notice: ' . $why, 0, 200)]);
        // A notice C2's sweep took for a reply (Gmail's name the email in In-Reply-To) was never one
        $replied = OutreachReply::where('prospect_id', $prospect->id)->where('kind', 'reply')->exists();
        OutreachProspect::whereKey($prospect->id)->update(['bounced_at' => $prospect->bounced_at ?? $now]
            + ($replied ? [] : ['replied_at' => null, 'reply_label' => 'bounced',
                'status' => in_array($prospect->status, ['unsubscribed', 'skipped'], true) ? $prospect->status : 'bounced']));
        Outreach::stop($prospect->id, 'Bounced');
        Sender::checkBounces($email->sender_id);

        return true;
    }
}
