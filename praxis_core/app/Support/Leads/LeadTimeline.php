<?php

namespace App\Support\Leads;

use App\Models\ClientUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A lead's timeline (table lead_timeline, docs/LEADS.md section 5): one row per thing that happened, newest first on the
 * Activity tab. Everything that changes a lead records here through record(); PR 2 adds meetings, emails and
 * opportunities, PR 3 imports and follow-ups, each with its own type in TYPES.
 *
 *   LeadTimeline::record($orgId, 'call', 'Called Ada about the audit.', ['actor' => $user, 'body' => '…', 'client_user_id' => $id]);
 *
 * $with keys: actor (User = staff, ClientUser = client, null = system), body, payload (array), client_user_id,
 * opportunity_id, meeting_id, related_type + related_id (a mail message, a proposal…), at (when it happened, default
 * now), dedupe_key (a backfill or an import: the same key twice records once). Every row moves the lead's
 * last_activity_at forward (never back), without touching its updated_at.
 */
final class LeadTimeline
{
    /** type => label (the Activity tab's filter and each row's tag); its icon is in partials/timeline-icon */
    public const TYPES = [
        'created' => 'Lead added',
        'website_enquiry' => 'Website enquiry',
        'website_booking' => 'Free audit booked on the website',
        'cost_report' => 'Cost calculator',
        'status_changed' => 'Status',
        'marked_client' => 'Became a client',
        'marked_lost' => 'Lost',
        'owner_changed' => 'Owner',
        'details_changed' => 'Details',
        'source_changed' => 'Source',
        'contact_added' => 'Contact added',
        'contact_updated' => 'Contact changed',
        'contact_removed' => 'Contact removed',
        'next_step_set' => 'Next step',
        'copq_calculated' => 'Cost of poor quality',
        'records_merged' => 'Assessments merged',
        'note' => 'Note',
        'call' => 'Call',
        'whatsapp' => 'WhatsApp',
        'visit' => 'Visit',
        // PR 2 records these (meetings, emails, opportunities); the backfill already writes opportunity_opened and email notes
        'email' => 'Email',
        'meeting' => 'Meeting',
        'opportunity_opened' => 'Opportunity opened',
        'signed' => 'Signed',
        'free_audit_booked' => 'Free audit booked',
        // PR 3
        'imported' => 'Imported',
        // Intel reports (docs/LEADS.md section 13): the line says only that one was added or removed, never what is in it,
        // because everyone with Leads access reads the timeline and only crm.edit reads the intel
        'intel_added' => 'Intel report added',
        'intel_removed' => 'Intel report removed',
    ];

    /** What "Log activity" on the lead page offers */
    public const LOGGABLE = ['call' => 'Call', 'whatsapp' => 'WhatsApp', 'visit' => 'Visit', 'note' => 'Note'];

    public static function record(string $organizationId, string $type, string $summary, array $with = []): string
    {
        $actor = $with['actor'] ?? null;
        $at = isset($with['at']) ? Carbon::parse($with['at']) : now();
        $id = (string) Str::uuid();

        $row = [
            'id' => $id,
            'organization_id' => $organizationId,
            'type' => Str::limit($type, 40, ''),
            'actor_type' => $actor instanceof User ? 'staff' : ($actor instanceof ClientUser ? 'client' : 'system'),
            'actor_id' => $actor?->id,
            'summary' => Str::limit(trim(preg_replace('/\s+/u', ' ', $summary)), 250),
            'body' => isset($with['body']) && trim((string) $with['body']) !== '' ? (string) $with['body'] : null,
            'payload' => isset($with['payload']) ? json_encode($with['payload'], JSON_UNESCAPED_UNICODE) : null,
            'client_user_id' => $with['client_user_id'] ?? null,
            'opportunity_id' => $with['opportunity_id'] ?? null,
            'meeting_id' => $with['meeting_id'] ?? null,
            'related_type' => $with['related_type'] ?? null,
            'related_id' => $with['related_id'] ?? null,
            'occurred_at' => $at->format('Y-m-d H:i:s.u'), // the column keeps microseconds: lines recorded together stay in order
            'dedupe_key' => $with['dedupe_key'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($row['dedupe_key'] !== null) {
            if (! DB::table('lead_timeline')->insertOrIgnore([$row])) {
                return (string) DB::table('lead_timeline')->where('dedupe_key', $row['dedupe_key'])->value('id');
            }
        } else {
            DB::table('lead_timeline')->insert($row);
        }

        // last_activity_at only moves forward; updated_at is set to itself so ON UPDATE doesn't count this as an edit
        DB::table('organizations')->where('id', $organizationId)
            ->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', $at))
            ->update(['last_activity_at' => $at, 'updated_at' => DB::raw('updated_at')]);

        return $id;
    }

    /**
     * The lead's rows, newest first, with who did it (staff name, or the contact's) and the contact it concerns.
     * $types narrows to those types; $limit caps the list.
     */
    public static function for(string $organizationId, ?array $types = null, int $limit = 50): Collection
    {
        return DB::table('lead_timeline as t')
            ->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 't.actor_id')->where('t.actor_type', '=', 'staff'))
            ->leftJoin('client_users as a', fn ($j) => $j->on('a.id', '=', 't.actor_id')->where('t.actor_type', '=', 'client'))
            ->leftJoin('client_users as c', 'c.id', '=', 't.client_user_id')
            ->where('t.organization_id', $organizationId)->whereNull('t.deleted_at')
            ->when($types, fn ($q) => $q->whereIn('t.type', $types))
            ->orderByDesc('t.occurred_at')->orderByDesc('t.created_at')->limit($limit)
            ->get(['t.*', 'u.first_name as staff_first', 'u.last_name as staff_last', 'u.avatar_path as staff_avatar',
                'a.first_name as client_first', 'a.last_name as client_last', 'c.first_name as contact_first', 'c.last_name as contact_last'])
            ->map(function ($row) {
                $row->actor_name = match ($row->actor_type) {
                    'staff' => $row->staff_first ? trim($row->staff_first . ' ' . $row->staff_last) : 'A former colleague',
                    'client' => $row->client_first ? trim($row->client_first . ' ' . $row->client_last) : 'The contact',
                    default => 'HQ',
                };
                $row->contact_name = $row->contact_first ? trim($row->contact_first . ' ' . $row->contact_last) : null;
                $row->payload = $row->payload ? json_decode($row->payload, true) : [];

                return $row;
            });
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? Str::headline($type);
    }
}
