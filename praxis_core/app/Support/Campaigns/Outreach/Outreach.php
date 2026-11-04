<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\User;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\RichText;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Campaigns › Outreach (docs/CAMPAIGNS.md section 10): who may do what, campaigns, the review of drafts, unsubscribes
 * and the numbers. Sending is Sender's, writing is Drafts', the limits are Limits'.
 *
 *   outreach.view     see campaigns, prospects, drafts and numbers
 *   outreach.create   make campaigns, import prospects and review the drafts of campaigns they own or send
 *   outreach.approve  the limits, resuming a paused sender, and every campaign (the owner)
 *
 * "Approve the rest" unlocks only after HAND_APPROVALS first emails of the campaign were approved one by one
 * (owner's decision 3, 3 Nov 2026).
 */
final class Outreach
{
    public const HAND_APPROVALS = 10;

    public static function canView(?User $user): bool
    {
        return (bool) $user?->can('outreach.view');
    }

    public static function canCreate(?User $user): bool
    {
        return (bool) $user?->can('outreach.create');
    }

    public static function canApprove(?User $user): bool
    {
        return (bool) $user?->can('outreach.approve');
    }

    /** Import into it and review its drafts: its owner or sender (with outreach.create), or outreach.approve */
    public static function canReview(?User $user, OutreachCampaign $campaign): bool
    {
        if (! $user) {
            return false;
        }
        if (self::canApprove($user)) {
            return true;
        }

        return self::canCreate($user) && in_array($user->id, [$campaign->owner_id, $campaign->sender_id], true);
    }

    /** C3: campaigns whose replies this person sorts, archived ones too (their own or sent from their mailbox; all with outreach.approve): ids */
    public static function reviewableIds(?User $user): array
    {
        if (! $user || (! self::canCreate($user) && ! self::canApprove($user))) {
            return [];
        }

        return OutreachCampaign::query()
            ->when(! self::canApprove($user), fn ($q) => $q->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('sender_id', $user->id)))
            ->pluck('id')->all();
    }

    /** Campaigns this person may import into, newest first: id => name */
    public static function importable(User $user): array
    {
        return OutreachCampaign::query()->where('status', '!=', 'archived')
            ->when(! self::canApprove($user), fn ($q) => $q->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('sender_id', $user->id)))
            ->orderByDesc('created_at')->pluck('name', 'id')->all();
    }

    public static function create(User $by, string $name, ?string $brief = null, ?string $senderId = null): OutreachCampaign
    {
        $senderId = $senderId && self::canApprove($by) ? $senderId : $by->id;

        return OutreachCampaign::create([
            'name' => mb_substr(trim($name), 0, 150),
            'brief' => trim((string) $brief) !== '' ? mb_substr(trim($brief), 0, 2000) : null,
            'owner_id' => $by->id,
            'sender_id' => $senderId,
        ]);
    }

    public static function setStatus(OutreachCampaign $campaign, string $status, User $by): void
    {
        if (! isset(OutreachCampaign::STATUSES[$status])) {
            return;
        }
        $campaign->forceFill([
            'status' => $status,
            'paused_by' => $status === 'paused' ? $by->id : null,
            'paused_at' => $status === 'paused' ? now() : null,
        ])->save();
        self::log('OUTREACH_CAMPAIGN_' . strtoupper($status), "Outreach campaign {$campaign->name}: {$status}.", 'outreach_campaigns', $campaign->id);
    }

    // ------------------------------------------------------------------ review

    /** Approve one first email (and its follow-up) by hand */
    public static function approve(OutreachEmail $first, User $by, string $how = 'hand'): bool
    {
        if (! $first->isFirst() || $first->status !== 'draft' || RichText::isBlank($first->body_html) || trim((string) $first->subject) === '') {
            return false;
        }
        $now = now();
        $done = OutreachEmail::whereKey($first->id)->where('status', 'draft')
            ->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => $now, 'approval' => $how, 'note' => null, 'updated_at' => $now]) > 0;
        $followUp = $done ? OutreachEmail::where('prospect_id', $first->prospect_id)->where('kind', 'follow_up')->where('status', 'draft')->first() : null;
        if ($followUp) {
            // A follow-up left empty isn't sent: approving the first email with it means "no follow-up"
            $followUp->forceFill(RichText::isBlank($followUp->body_html)
                ? ['status' => 'skipped', 'note' => 'No follow-up written', 'updated_at' => $now]
                : ['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => $now, 'approval' => $how, 'updated_at' => $now])->save();
        }

        return $done;
    }

    /** How many first emails of the campaign were approved one by one (Approve the rest needs HAND_APPROVALS) */
    public static function handApproved(OutreachCampaign $campaign): int
    {
        return OutreachEmail::where('campaign_id', $campaign->id)->where('kind', 'first')->where('approval', 'hand')->count();
    }

    /** Approves every draft left in the campaign; returns how many. Only after HAND_APPROVALS by hand. */
    public static function approveRest(OutreachCampaign $campaign, User $by): int
    {
        if (self::handApproved($campaign) < self::HAND_APPROVALS) {
            return 0;
        }
        $n = 0;
        foreach (OutreachEmail::where('campaign_id', $campaign->id)->where('kind', 'first')->where('status', 'draft')->orderBy('id')->get() as $first) {
            $n += self::approve($first, $by, 'bulk') ? 1 : 0;
        }
        if ($n) {
            self::log('OUTREACH_APPROVED_REST', "Approved the rest of outreach campaign {$campaign->name}: {$n} emails.", 'outreach_campaigns', $campaign->id, [], ['count' => $n]);
        }

        return $n;
    }

    /** Back to review: an approved email that hasn't gone yet, or a skipped one */
    public static function unapprove(OutreachEmail $first): bool
    {
        if (! $first->isFirst() || ! in_array($first->status, ['approved', 'skipped'], true)) {
            return false;
        }
        $reset = ['status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'approval' => null, 'note' => null, 'updated_at' => now()];
        $done = OutreachEmail::whereKey($first->id)->whereIn('status', ['approved', 'skipped'])->update($reset) > 0;
        if ($done) {
            OutreachEmail::where('prospect_id', $first->prospect_id)->where('kind', 'follow_up')->whereIn('status', ['approved', 'skipped'])->update($reset);
            OutreachProspect::whereKey($first->prospect_id)->where('status', 'skipped')->update(['status' => 'new']);
        }

        return $done;
    }

    /** A person decided not to email this prospect */
    public static function skip(OutreachEmail $first, User $by): bool
    {
        if (! $first->isFirst() || ! in_array($first->status, ['draft', 'approved', 'not_written'], true)) {
            return false;
        }
        $note = 'Skipped by ' . Senders::firstName($by);
        $done = OutreachEmail::whereKey($first->id)->whereIn('status', ['draft', 'approved', 'not_written'])
            ->update(['status' => 'skipped', 'note' => $note, 'approval' => null, 'updated_at' => now()]) > 0;
        if ($done) {
            OutreachEmail::where('prospect_id', $first->prospect_id)->where('kind', 'follow_up')->whereIn('status', ['writing', 'draft', 'approved', 'not_written'])
                ->update(['status' => 'skipped', 'note' => $note, 'approval' => null, 'updated_at' => now()]);
            OutreachProspect::whereKey($first->prospect_id)->where('status', 'new')->update(['status' => 'skipped']);
        }

        return $done;
    }

    /**
     * The person's own words: the first email's subject and text and the follow-up's text (rich text, cleaned). A
     * draft that Claude couldn't write becomes a draft to review.
     */
    public static function saveEdit(OutreachEmail $first, string $subject, string $body, string $followUp): bool
    {
        if (! $first->isFirst() || ! in_array($first->status, ['draft', 'not_written'], true)) {
            return false;
        }
        $now = now();
        $first->forceFill([
            'subject' => mb_substr(trim(preg_replace('/\s+/u', ' ', $subject)), 0, 200),
            'body_html' => RichText::clean($body),
            'status' => 'draft', 'edited' => true, 'ai_error' => null, 'updated_at' => $now,
        ])->save();
        OutreachEmail::where('prospect_id', $first->prospect_id)->where('kind', 'follow_up')->whereIn('status', ['draft', 'not_written', 'writing'])
            ->update(['body_html' => RichText::clean($followUp), 'status' => 'draft', 'edited' => true, 'ai_error' => null, 'updated_at' => $now]);

        return true;
    }

    // ------------------------------------------------------------------ stopping

    /**
     * Stops what is still to go to this prospect: an unsent first email is skipped, a follow-up cancelled. $why is
     * the note on each.
     */
    public static function stop(int $prospectId, string $why): void
    {
        $now = now();
        OutreachEmail::where('prospect_id', $prospectId)->where('kind', 'first')->whereIn('status', ['writing', 'not_written', 'draft', 'approved'])
            ->update(['status' => 'skipped', 'note' => $why, 'updated_at' => $now]);
        OutreachEmail::where('prospect_id', $prospectId)->where('kind', 'follow_up')->whereIn('status', ['writing', 'not_written', 'draft', 'approved', 'scheduled'])
            ->update(['status' => 'cancelled', 'note' => $why, 'updated_at' => $now]);
    }

    /** The outreach email's unsubscribe link or the inbox's button: all marketing to the address stops */
    public static function unsubscribe(OutreachEmail $email, string $how): void
    {
        $prospect = OutreachProspect::find($email->prospect_id);
        if (! $prospect) {
            return;
        }
        Marketing::suppress($prospect->email, 'unsubscribed', ['source' => 'outreach', 'note' => $how === 'one_click' ? 'Inbox unsubscribe button (outreach)' : 'Unsubscribe page (outreach)']);
        OutreachProspect::whereKey($prospect->id)->whereNull('unsubscribed_at')->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        self::stop($prospect->id, 'Unsubscribed');
    }

    public static function emailByToken(string $token): ?OutreachEmail
    {
        return strlen($token) === 40 && ctype_alnum($token) ? OutreachEmail::where('token', $token)->first() : null;
    }

    // ------------------------------------------------------------------ numbers

    /**
     * The campaign page's numbers (owner's scope: drafted, approved, sent, follow-ups, bounces, unsubscribes), for one
     * campaign or several (keyed by campaign id). C3 adds the replies (section 12): replies (prospects who wrote back),
     * reply_rate (% of the first emails that arrived: sent, not bounced), to_sort (replies nobody has confirmed),
     * interested, not_now, not_interested and unsubscribe (prospects by the answer a person confirmed), and leads (made).
     */
    public static function stats(array $campaignIds): array
    {
        $out = [];
        foreach ($campaignIds as $id) {
            $out[$id] = ['prospects' => 0, 'writing' => 0, 'not_written' => 0, 'to_review' => 0, 'approved' => 0, 'sent' => 0, 'follow_ups_sent' => 0,
                'follow_ups_waiting' => 0, 'bounced' => 0, 'unsubscribed' => 0, 'skipped' => 0, 'failed' => 0, 'replied' => 0,
                'replies' => 0, 'reply_rate' => 0, 'to_sort' => 0, 'interested' => 0, 'not_now' => 0, 'not_interested' => 0, 'unsubscribe' => 0, 'leads' => 0];
        }
        if (! $campaignIds) {
            return $out;
        }
        $emails = DB::table('outreach_emails')->whereIn('campaign_id', $campaignIds)->groupBy('campaign_id')->selectRaw("campaign_id,
            SUM(kind = 'first' AND status = 'writing') AS writing, SUM(kind = 'first' AND status = 'not_written') AS not_written,
            SUM(kind = 'first' AND status = 'draft') AS to_review, SUM(kind = 'first' AND status IN ('approved', 'sending')) AS approved,
            SUM(kind = 'first' AND status = 'sent') AS sent, SUM(kind = 'follow_up' AND status = 'sent') AS follow_ups_sent,
            SUM(kind = 'follow_up' AND status = 'scheduled') AS follow_ups_waiting, SUM(status = 'bounced') AS bounced,
            SUM(kind = 'first' AND status = 'skipped') AS skipped, SUM(status = 'failed') AS failed")->get();
        foreach ($emails as $row) {
            foreach ((array) $row as $key => $value) {
                if ($key !== 'campaign_id') {
                    $out[$row->campaign_id][$key] = (int) $value;
                }
            }
        }
        $prospects = DB::table('outreach_prospects')->whereIn('campaign_id', $campaignIds)->groupBy('campaign_id')
            ->selectRaw("campaign_id, COUNT(*) AS prospects, SUM(unsubscribed_at IS NOT NULL) AS unsubscribed, SUM(replied_at IS NOT NULL) AS replied,
                SUM(reply_label = 'interested') AS interested, SUM(reply_label = 'not_now') AS not_now, SUM(reply_label = 'not_interested') AS not_interested,
                SUM(reply_label = 'unsubscribe') AS unsubscribe, SUM(organization_id IS NOT NULL) AS leads")->get();
        foreach ($prospects as $row) {
            foreach ((array) $row as $key => $value) {
                if ($key !== 'campaign_id') {
                    $out[$row->campaign_id][$key] = (int) $value;
                }
            }
        }
        $replies = DB::table('outreach_replies')->whereIn('campaign_id', $campaignIds)->where('kind', 'reply')->groupBy('campaign_id')
            ->selectRaw('campaign_id, COUNT(DISTINCT prospect_id) AS replies, SUM(label IS NULL) AS to_sort')->get();
        foreach ($replies as $row) {
            $out[$row->campaign_id]['replies'] = (int) $row->replies;
            $out[$row->campaign_id]['to_sort'] = (int) $row->to_sort;
        }
        foreach ($out as $id => $s) {
            $out[$id]['reply_rate'] = $s['sent'] > 0 ? (int) min(100, round(100 * $s['replies'] / $s['sent'])) : 0;
        }

        return $out;
    }

    /** One line in system_audit_logs, by whoever is signed in */
    public static function log(string $action, string $description, string $table, string $id, array $old = [], array $new = []): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 2000),
            'target_table' => $table,
            'target_id' => $id,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
