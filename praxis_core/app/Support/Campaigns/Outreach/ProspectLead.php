<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\ClientUser;
use App\Models\Organization;
use App\Models\User;
use App\Support\Leads\Contacts;
use App\Support\Leads\Duplicates;
use App\Support\Leads\LeadEmails;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\Leads;
use App\Support\Leads\LeadTimeline;
use Illuminate\Support\Facades\DB;

/**
 * Make a lead from a prospect who replied (owner's decision, 3 Nov 2026; docs/CAMPAIGNS.md section 12), in one click:
 *
 *   - the company is already a lead or client (Duplicates: the prospect's address on it, or the same name key, as the
 *     import's "Already exists"): the person is added there as a contact (source outreach), or found already there;
 *   - else Leads::create: the company, country and industry, the person as its main contact (source outreach), lead
 *     source outreach, owned by the campaign's sender;
 *   - either way the research (What we found, the website, Claude's insight) is a note on the lead's timeline, the
 *     prospect row stays (its emails are the history) and points at the lead, and the lead's Emails tab and timeline
 *     pick the thread up by the contact's address (LeadEmails::fileFor at once, leads:file-emails after).
 *
 * From then on they are a lead and can get broadcasts, unless their address is on the do-not-email list.
 */
final class ProspectLead
{
    /**
     * @return array{organization: Organization, existing: bool, contact: string, message: string} contact: added, already, new
     *
     * @throws \Illuminate\Validation\ValidationException|\App\Support\Leads\LeadRuleBroken when Leads refuses (the screen shows why)
     */
    public static function make(OutreachProspect $prospect, User $by): array
    {
        $result = DB::transaction(function () use ($prospect, $by) {
            $prospect = OutreachProspect::whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            if ($prospect->organization_id && ($org = Organization::find($prospect->organization_id))) {
                return ['organization' => $org, 'existing' => true, 'contact' => 'already', 'message' => self::name($prospect) . ' is already on ' . $org->name . '.'];
            }
            $campaign = OutreachCampaign::findOrFail($prospect->campaign_id);
            $person = ['name' => mb_substr(self::name($prospect), 0, 100), 'job_title' => $prospect->job_title ? mb_substr($prospect->job_title, 0, 100) : null,
                'email' => $prospect->email];

            if ($taken = Contacts::emailTaken($prospect->email)) {
                $org = Organization::findOrFail($taken->organization_id);
                [$existing, $contact, $contactId] = [true, 'already', $taken->id];
                $message = self::name($prospect) . ' is already a contact of ' . $org->name . ', so nothing new was made. Their outreach emails show on it.';
            } elseif ($match = self::existing($prospect)) {
                $org = Organization::findOrFail($match['id']);
                $added = Contacts::add($org, $person, $by, 'outreach');
                [$existing, $contact, $contactId] = [true, 'added', $added->id];
                $message = $org->name . ' is already ' . ($org->status === 'Lead' ? 'a lead' : 'in HQ (' . strtolower($org->status) . ')')
                    . ($match['owner'] ? ' owned by ' . $match['owner'] : '') . ', so ' . self::name($prospect) . ' was added there as a contact.';
            } else {
                $org = Leads::create([
                    'name' => mb_substr($prospect->company, 0, 150),
                    'country' => $prospect->country,
                    'industry' => mb_substr($prospect->industry, 0, 100),
                    'preferred_language' => $prospect->language === 'fr' ? 'FR' : 'EN',
                    'source' => 'outreach',
                    'source_detail' => mb_substr('Outreach: ' . $campaign->name, 0, 255),
                ], [$person + ['main' => true]], $by, ['owner_id' => $campaign->sender_id, 'source' => 'outreach', 'contact_source' => 'outreach']);
                $contactId = ClientUser::where('organization_id', $org->id)->whereRaw('LOWER(email) = ?', [$prospect->email])->value('id');
                [$existing, $contact] = [false, 'new'];
                $message = 'Lead made: ' . $org->name . ', owned by ' . LeadOwner::name(User::find($campaign->sender_id)) . '.';
            }

            LeadTimeline::record($org->id, 'note', 'From outreach: ' . $campaign->name . ' (' . self::name($prospect) . ' replied)', [
                'actor' => $by, 'body' => self::research($prospect), 'client_user_id' => $contactId,
                'payload' => ['outreach_prospect_id' => $prospect->id, 'campaign_id' => $campaign->id, 'reply' => $prospect->reply_label],
            ]);
            $prospect->forceFill(['organization_id' => $org->id, 'lead_made_at' => now(), 'lead_made_by' => $by->id])->save();
            Outreach::log('OUTREACH_LEAD_MADE', $message, 'outreach_prospects', (string) $prospect->id, [], ['organization_id' => $org->id, 'existing' => $existing, 'contact' => $contact]);

            // The lead's owner hears about it when someone else made it
            $ownerId = $org->assigned_to;
            if ($ownerId && $ownerId !== $by->id && LeadOwner::activeStaff($ownerId)) {
                LeadOwner::bell($ownerId, ($existing ? 'A contact from outreach: ' : 'A lead from outreach: ') . $org->name,
                    LeadOwner::name($by) . ' made ' . self::name($prospect) . ' a ' . ($existing ? 'contact of ' . $org->name : 'lead') . ' from the outreach campaign ' . $campaign->name . '.', $org->id);
            }

            return ['organization' => $org, 'existing' => $existing, 'contact' => $contact, 'message' => $message];
        });
        // Past mail with the person on the lead's timeline at once (leads:file-emails picks up what comes later)
        LeadEmails::fileFor($result['organization']->id);

        return $result;
    }

    /** The lead or client this company already is, as the import decides "Already exists": its address, or the same name key */
    public static function existing(OutreachProspect $prospect): ?array
    {
        return Duplicates::find($prospect->company, [$prospect->email])->first(fn ($m) => in_array('email', $m['matched'], true)
            || (in_array('name', $m['matched'], true) && Duplicates::nameKey($m['name']) === Duplicates::nameKey($prospect->company)));
    }

    /** The first note: what we found, the website and Claude's insight, as the salesperson saw them */
    public static function research(OutreachProspect $prospect): string
    {
        $insight = OutreachEmail::where('prospect_id', $prospect->id)->where('kind', 'first')->value('insight');
        $lines = array_filter([
            $prospect->findings ? 'What we found: ' . trim($prospect->findings) : null,
            $prospect->website ? 'Website: ' . $prospect->website . ($prospect->website_note ? ' (' . $prospect->website_note . ')' : '') : null,
            $insight ? 'Claude’s insight: ' . trim($insight) : null,
            'Contact: ' . self::name($prospect) . ($prospect->job_title ? ', ' . $prospect->job_title : '') . ', ' . $prospect->email,
        ]);

        return implode("\n\n", $lines);
    }

    private static function name(OutreachProspect $prospect): string
    {
        return $prospect->fullName() ?: $prospect->email;
    }
}
