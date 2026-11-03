<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use Illuminate\Support\Facades\DB;

/**
 * Stopping the follow-up (owner's decision 4, 3 Nov 2026). The follow-up is cancelled when the prospect replies,
 * bounces or unsubscribes. A reply is found in HQ Mail's copy of the mailboxes (mail:sync, every minute): mail from
 * the prospect's address that arrived after the first email went, or mail in the same conversation (its In-Reply-To
 * or References name the first email's Message-ID). C2 only stops the follow-up; C3 sorts the replies.
 *
 * Also the spam check: an address of a prospect who got an outreach email that is on the do-not-email list as
 * "complained" (C3, or the owner from a mail provider's report) pauses the sender who wrote to them.
 */
final class Replies
{
    /** Prospects whose follow-up is still to go: replied ones are marked and their follow-up cancelled. Returns how many. */
    public static function sweep(): int
    {
        $n = 0;
        $pending = OutreachEmail::where('kind', 'follow_up')->whereIn('status', ['approved', 'scheduled'])->pluck('prospect_id');
        foreach ($pending->chunk(200) as $ids) {
            $firsts = OutreachEmail::whereIn('prospect_id', $ids)->where('kind', 'first')->where('status', 'sent')->get()->keyBy('prospect_id');
            foreach (OutreachProspect::whereIn('id', $firsts->keys())->whereNull('replied_at')->get() as $prospect) {
                if (self::found($prospect, $firsts[$prospect->id])) {
                    self::mark($prospect);
                    $n++;
                }
            }
        }

        return $n;
    }

    /** Has the prospect written back since the first email went? */
    public static function found(OutreachProspect $prospect, OutreachEmail $first): bool
    {
        if (! $first->sent_at) {
            return false;
        }
        $mid = trim((string) $first->message_id, '<> ');

        return DB::table('mail_messages')->where('is_outbound', 0)
            ->where(function ($q) use ($prospect, $first, $mid) {
                $q->where(fn ($q) => $q->where('from_email', $prospect->email)->where('received_at', '>=', $first->sent_at->copy()->subMinute()));
                if ($mid !== '') {
                    $q->orWhere('in_reply_to', $mid)->orWhere('references', 'like', '%' . addcslashes($mid, '%_\\') . '%');
                }
            })->exists();
    }

    public static function mark(OutreachProspect $prospect): void
    {
        OutreachProspect::whereKey($prospect->id)->whereNull('replied_at')->update(['status' => 'replied', 'replied_at' => now()]);
        Outreach::stop($prospect->id, 'They replied');
    }

    /**
     * Spam complaints: a prospect who got an outreach email whose address is now on the list as "complained". Their
     * follow-up stops and the sender of that email pauses. Returns how many were found.
     */
    public static function complaints(): int
    {
        $rows = DB::table('outreach_prospects AS p')
            ->join('marketing_suppressions AS s', 's.email', '=', 'p.email')
            ->join('outreach_emails AS e', fn ($j) => $j->on('e.prospect_id', '=', 'p.id')->where('e.status', 'sent'))
            ->where('s.reason', 'complained')->whereNull('p.complained_at')
            ->groupBy('p.id', 'p.email')->selectRaw('p.id, p.email, MAX(e.sender_id) AS sender_id')->get();
        foreach ($rows as $row) {
            if (! OutreachProspect::whereKey($row->id)->whereNull('complained_at')->update(['complained_at' => now(), 'status' => 'unsubscribed', 'unsubscribed_at' => now()])) {
                continue;
            }
            Outreach::stop((int) $row->id, 'Marked as spam');
            if ($row->sender_id) {
                Senders::pause($row->sender_id, 'complaint', 'Someone marked an outreach email as spam (' . $row->email . ').');
            }
        }

        return $rows->count();
    }
}
