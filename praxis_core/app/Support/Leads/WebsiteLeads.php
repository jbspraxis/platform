<?php

namespace App\Support\Leads;

use Illuminate\Support\Facades\DB;

/**
 * What the website's forms (App\Services\Site: the contact form, free audit bookings, the cost calculator) do to a
 * lead, inside their own transaction, once they have found or created its organisation and contact:
 *
 *   - a new lead gets its source, status New, and the default owner (Leads › Lead settings), who gets a bell;
 *   - the contact becomes the main contact when the lead has none;
 *   - the timeline gets the enquiry, the booking or the estimate (the visitor's own words in body).
 *
 * The forms keep their other bells (everyone with crm.edit, and Free audits for bookings) and their emails.
 */
final class WebsiteLeads
{
    /** The next step a new website lead starts with, due the next working day */
    public const FIRST_STEP = [
        'website_enquiry' => 'Reply to their enquiry',
        'website_booking' => 'Confirm the free audit',
        'cost_report' => 'Follow up on their cost report',
    ];

    private const NEW_BELL = [
        'website_enquiry' => 'sent a message through the contact form',
        'website_booking' => 'asked for a free audit',
        'cost_report' => 'ran the cost of poor quality calculator',
    ];

    /**
     * @param  string  $source  website_enquiry, website_booking or cost_report (Sources::WEBSITE)
     * @param  bool  $isNew  the form created the organisation just now
     * @param  string|null  $contactId  the client_users row of the visitor (new or found)
     * @param  string  $summary  one line for the timeline
     * @param  string|null  $body  what the visitor wrote
     */
    public static function received(string $organizationId, bool $isNew, string $source, ?string $contactId, string $summary, ?string $body = null, array $payload = []): void
    {
        $lead = DB::table('organizations')->where('id', $organizationId)->first();
        if (! $lead || $lead->status === 'Internal') {
            return; // someone typed JBS Praxis's own name: not a lead
        }

        $ownerId = null;
        if ($isNew) {
            $ownerId = $lead->assigned_to ? null : LeadOwner::defaultOwnerId();
            // Every open lead has a next step: a reply by the next working day
            $step = $lead->next_step ? null : (self::FIRST_STEP[$source] ?? 'Make first contact');
            DB::table('organizations')->where('id', $organizationId)->update(array_filter([
                'source' => $lead->source ?: $source,
                'lead_status' => $lead->lead_status ?: 'new',
                'assigned_to' => $ownerId,
                'next_step' => $step,
                'next_step_due' => $step ? NextStep::suggestion()['due'] : null,
                'updated_at' => DB::raw('updated_at'),
            ], fn ($v) => $v !== null));
            LeadTimeline::record($organizationId, 'created', 'Lead added (' . mb_strtolower(Sources::label($source)) . ').', [
                'client_user_id' => $contactId, 'payload' => ['source' => $source, 'owner' => $ownerId],
            ]);
        }

        if ($contactId && ! DB::table('client_users')->where('organization_id', $organizationId)->where('is_main_contact', 1)->whereNull('deleted_at')->exists()) {
            DB::table('client_users')->where('id', $contactId)->where('organization_id', $organizationId)->update(['is_main_contact' => 1]);
        }

        $actor = $contactId ? \App\Models\ClientUser::find($contactId) : null;
        LeadTimeline::record($organizationId, $source, $summary, [
            'actor' => $actor?->organization_id === $organizationId ? $actor : null,
            'client_user_id' => $actor?->organization_id === $organizationId ? $contactId : null,
            'body' => $body, 'payload' => $payload,
        ]);

        if ($ownerId) {
            $who = $actor ? trim($actor->first_name . ' ' . $actor->last_name) : 'A visitor';
            LeadOwner::bell($ownerId, 'A website lead was assigned to you', "{$who} ({$lead->name}) " . (self::NEW_BELL[$source] ?? 'reached us through the website')
                . '. You are the default owner of website leads.', $organizationId);
        }
    }
}
