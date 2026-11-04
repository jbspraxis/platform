<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Campaigns\OutreachReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Not now reminders (owner's decision, 3 Nov 2026; docs/CAMPAIGNS.md section 12). outreach:remind runs on working days
 * at 07:30 Lagos time, as leads:morning-bell does: each prospect who said not now and whose day has come (or passed,
 * over a weekend) rings the campaign's sender once, opening their reply. Nothing is ever sent to them by itself: the
 * sender writes again from Mail, or makes a lead.
 */
final class Reminders
{
    /**
     * @param  bool  $dryRun  list who would be rung, ring nobody
     * @return list<string> one line per bell
     */
    public static function ring(bool $dryRun = false): array
    {
        $today = Limits::local()->format('Y-m-d');
        $due = OutreachProspect::where('reply_label', 'not_now')->whereNotNull('remind_on')->where('remind_on', '<=', $today)->whereNull('reminded_at')
            ->orderBy('remind_on')->orderBy('id')->get();
        $lines = [];
        foreach ($due as $prospect) {
            $campaign = OutreachCampaign::find($prospect->campaign_id);
            $sender = $campaign ? User::where('id', $campaign->sender_id)->where('status', 'Active')->whereNull('deleted_at')->first() : null;
            if (! $sender || ! Outreach::canView($sender)) {
                continue;
            }
            $reply = OutreachReply::where('prospect_id', $prospect->id)->where('kind', 'reply')->where('label', 'not_now')->latest('label_at')->first();
            $said = $reply?->label_at ? ' said not now on ' . $reply->received_at?->copy()->timezone(Limits::TIMEZONE)->format('j M Y') : ' said not now';
            $message = $prospect->fullName() . ' (' . $prospect->company . ')' . $said . '.'
                . ($reply?->ai_reason ? ' ' . rtrim($reply->ai_reason, '.') . '.' : '')
                . ' Write to them yourself from Mail when you are ready: nothing goes to them by itself.';
            $lines[] = ($dryRun ? '[dry run] ' : '') . Senders::firstName($sender) . ': ' . $message;
            if ($dryRun) {
                continue;
            }
            // Once: the row is claimed before the bell rings, so a second run rings nobody
            if (! OutreachProspect::whereKey($prospect->id)->whereNull('reminded_at')->update(['reminded_at' => now()])) {
                continue;
            }
            Senders::bell([$sender->id], 'Time to get back to ' . $prospect->first_name, $message, self::link($prospect, $reply));
        }

        return $lines;
    }

    /** The campaign's Replies with this reply open (or the campaign's replies) */
    public static function link(OutreachProspect $prospect, ?OutreachReply $reply): string
    {
        return route('hq.campaigns.outreach.campaign', array_filter(['campaign' => $prospect->campaign_id, 'show' => 'replies', 'reply' => $reply?->id]), false);
    }

    /** How many reminders are set and still to ring, for the Replies view */
    public static function waiting(array $campaignIds): int
    {
        return $campaignIds ? DB::table('outreach_prospects')->whereIn('campaign_id', $campaignIds)->where('reply_label', 'not_now')
            ->whereNotNull('remind_on')->whereNull('reminded_at')->count() : 0;
    }
}
