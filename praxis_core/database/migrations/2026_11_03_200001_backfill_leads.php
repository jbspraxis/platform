<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill for Leads PR 1 (docs/LEADS.md section 4), per organisation (JBS Praxis itself, status Internal, is left out):
 *
 *   lead_status   Active Client or Past Client → client; the old board's Won → client (marked by hand, and the
 *                 organisation becomes an Active Client), Lost → lost (reason "other", the disqualification as the note);
 *                 Proposal sent and Free audit (In Consultation) → qualified; Qualifying → contacted; else new
 *   client        since its first signed proposal or SOW (client_via signed, with its reference), else client_via existing
 *   source        from its first contact's source (contact form, booking, cost report, HQ), else added in HQ when staff
 *                 created it or it has a cost estimate but no contact (the old intake form), else not recorded
 *   country       website enquiries stored the dialling code (+234): it becomes the country's name
 *   main contact  the portal's Client Admin, else the first active contact, when the lead has none
 *   timeline      "Lead added", every crm_notes row (website messages as website enquiries), each opportunity opened,
 *                 and how it became a client; last_activity_at is the latest of them
 *
 * Idempotent: an organisation whose "Lead added" row (dedupe_key backfill:created:<id>) exists is skipped, and every
 * timeline row it writes has a dedupe_key, so a second run adds nothing and changes nothing someone edited since.
 */
return new class extends Migration
{
    private const DIAL_CODES = [
        '+234' => 'Nigeria', '+237' => 'Cameroon', '+229' => 'Benin', '+236' => 'Central African Republic', '+235' => 'Chad',
        '+242' => 'Congo', '+243' => 'DR Congo', '+225' => 'Côte d’Ivoire', '+240' => 'Equatorial Guinea', '+241' => 'Gabon',
        '+233' => 'Ghana', '+254' => 'Kenya', '+250' => 'Rwanda', '+221' => 'Senegal', '+27' => 'South Africa', '+228' => 'Togo',
        '+33' => 'France', '+971' => 'United Arab Emirates', '+44' => 'United Kingdom', '+1' => 'United States',
    ];

    private const CONTACT_SOURCES = ['contact_form' => 'website_enquiry', 'booking' => 'website_booking', 'cost_report' => 'cost_report', 'hq' => 'manual'];

    private const SOURCE_LABELS = [
        'website_enquiry' => 'website enquiry', 'website_booking' => 'website free audit booking', 'cost_report' => 'cost calculator',
        'manual' => 'added in HQ',
    ];

    private const STAGES = [
        'Lead Captured' => 'new', 'Qualifying' => 'contacted', 'In Consultation' => 'qualified', 'Proposal Sent' => 'qualified',
        'Won' => 'client', 'Lost' => 'lost', 'Won / Lost' => 'qualified',
    ];

    private const NOTE_TYPES = ['Call' => 'call', 'Email' => 'email', 'Meeting' => 'meeting', 'Internal Note' => 'note'];

    public function up(): void
    {
        if (! Schema::hasTable('lead_timeline') || ! Schema::hasColumn('organizations', 'lead_status')) {
            return;
        }

        DB::table('organizations')->where('status', 'Internal')->update(['lead_status' => null]);

        $orgIds = DB::table('organizations')->where('status', '!=', 'Internal')->orderBy('created_at')->pluck('id');
        foreach ($orgIds as $orgId) {
            DB::transaction(fn () => $this->backfill($orgId));
        }
    }

    private function backfill(string $orgId): void
    {
        $org = DB::table('organizations')->where('id', $orgId)->lockForUpdate()->first();
        if (! $org || DB::table('lead_timeline')->where('dedupe_key', "backfill:created:{$orgId}")->exists()) {
            return;
        }
        $now = now();
        $set = [];

        // Status
        $isClient = in_array($org->status, ['Active Client', 'Past Client'], true);
        $status = $isClient ? 'client' : (self::STAGES[$org->pipeline_stage] ?? 'new');
        $set['lead_status'] = $status;
        $client = null;
        if ($status === 'client') {
            $client = $this->firstSignature($orgId);
            $set['client_since'] = $client['at'] ?? ($isClient ? $this->firstProject($orgId) : $org->updated_at);
            $set['client_via'] = $client ? 'signed' : ($isClient ? 'existing' : 'manual');
            $set['client_reference'] = $client['reference'] ?? null;
            if (! $isClient) {
                $set['status'] = 'Active Client'; // the old board's Won, moved by hand: a client marked by hand
            }
        } elseif ($status === 'lost') {
            $set['lost_reason'] = 'other';
            $set['lost_note'] = $org->disqualification_reason ?: 'Moved to Lost on the old Leads board.';
            $set['lost_at'] = $org->updated_at;
        }

        // Source
        $contacts = DB::table('client_users')->where('organization_id', $orgId)->whereNull('deleted_at')->orderBy('created_at')->get();
        $source = null;
        if (! $org->source) {
            $first = $contacts->first();
            $source = self::CONTACT_SOURCES[$first->source ?? ''] ?? null;
            if (! $source && ($org->created_by || ($contacts->isEmpty() && DB::table('copq_assessments')->where('organization_id', $orgId)->exists()))) {
                $source = 'manual';
            }
            $set['source'] = $source;
            $utm = collect(['utm_source' => $org->utm_source, 'utm_campaign' => $org->utm_campaign])->filter()->map(fn ($v, $k) => "{$k}={$v}")->implode(', ');
            if ($utm !== '' && ! $org->source_detail) {
                $set['source_detail'] = Str::limit($utm, 255, '');
            }
        }

        // Country: the dialling code the website stored
        if (is_string($org->country) && str_starts_with(trim($org->country), '+')) {
            $set['country'] = self::DIAL_CODES[preg_replace('/\s+/', '', $org->country)] ?? $org->country;
        }

        // Main contact
        if ($contacts->isNotEmpty() && ! $contacts->contains(fn ($c) => (bool) $c->is_main_contact)) {
            $main = $contacts->firstWhere('is_primary_client', 1) ?? $contacts->firstWhere('status', 'Active') ?? $contacts->first();
            DB::table('client_users')->where('id', $main->id)->update(['is_main_contact' => 1]);
        }

        // Timeline
        $rows = [];
        $label = self::SOURCE_LABELS[$source ?? $org->source ?? ''] ?? null;
        $rows[] = $this->row($orgId, 'created', $label ? "Lead added ({$label})." : 'Lead added.', $org->created_at ?? $now, "backfill:created:{$orgId}", [
            'actor_type' => 'system', 'payload' => json_encode(['backfill' => true, 'source' => $source ?? $org->source]),
        ]);
        foreach (DB::table('crm_notes')->where('organization_id', $orgId)->whereNull('deleted_at')->orderBy('created_at')->get() as $note) {
            $text = (string) $note->note_text;
            $fromWebsite = ($note->author_type ?? 'staff') === 'client' && str_starts_with($text, '>> INTAKE MESSAGE');
            $type = $fromWebsite ? 'website_enquiry' : (self::NOTE_TYPES[$note->interaction_type ?? ''] ?? 'note');
            $rows[] = $this->row($orgId, $type, $this->summary($type, $text), $note->created_at ?? $now, "crm_note:{$note->id}", [
                'actor_type' => ($note->author_type ?? 'staff') === 'client' ? 'client' : 'staff',
                'actor_id' => $note->author_id && Str::isUuid($note->author_id) ? $note->author_id : null,
                'client_user_id' => ($note->author_type ?? 'staff') === 'client' && $contacts->contains('id', $note->author_id) ? $note->author_id : null,
                'body' => $text,
                'related_type' => 'crm_note',
                'related_id' => $note->id,
            ]);
        }
        foreach (DB::table('opportunities')->where('organization_id', $orgId)->whereNull('deleted_at')->orderBy('created_at')->get(['id', 'title', 'created_by', 'created_at']) as $o) {
            $rows[] = $this->row($orgId, 'opportunity_opened', Str::limit("Opportunity opened: {$o->title}.", 255, ''), $o->created_at ?? $now, "opportunity:{$o->id}", [
                'actor_type' => $o->created_by ? 'staff' : 'system', 'actor_id' => $o->created_by, 'opportunity_id' => $o->id,
            ]);
        }
        if ($status === 'client') {
            $how = match ($set['client_via']) {
                'signed' => "Became a client: signed {$client['what']} {$client['reference']}.",
                'manual' => 'Marked as a client (moved to Won on the old Leads board).',
                default => 'A client before the Leads revamp.',
            };
            $rows[] = $this->row($orgId, 'marked_client', Str::limit($how, 255, ''), $set['client_since'] ?? $org->updated_at ?? $now, "backfill:client:{$orgId}", [
                'actor_type' => 'system', 'payload' => json_encode(['via' => $set['client_via'], 'reference' => $set['client_reference']]),
            ]);
        } elseif ($status === 'lost') {
            $rows[] = $this->row($orgId, 'marked_lost', 'Marked as lost (moved to Lost on the old Leads board).', $set['lost_at'] ?? $now, "backfill:lost:{$orgId}", [
                'actor_type' => 'system', 'body' => $set['lost_note'], 'payload' => json_encode(['reason' => 'other']),
            ]);
        }
        DB::table('lead_timeline')->insertOrIgnore($rows);

        $set['last_activity_at'] = collect($rows)->max('occurred_at');
        // updated_at stays: the backfill is not someone's change
        DB::table('organizations')->where('id', $orgId)->update($set + ['updated_at' => DB::raw('updated_at')]);
    }

    /** The first signed proposal (legacy signing) or SOW of the organisation: when, what and its reference */
    private function firstSignature(string $orgId): ?array
    {
        $proposal = DB::table('proposals')->where('organization_id', $orgId)->whereIn('status', ['Authorized', 'Converted'])->whereNotNull('signed_at')
            ->whereNull('deleted_at')->orderBy('signed_at')->first(['signed_at', 'proposal_ref']);
        $sow = Schema::hasTable('statements_of_work') ? DB::table('statements_of_work')->join('opportunities', 'opportunities.id', '=', 'statements_of_work.opportunity_id')
            ->where('opportunities.organization_id', $orgId)->where('statements_of_work.status', 'signed')->whereNotNull('statements_of_work.signed_at')
            ->whereNull('statements_of_work.deleted_at')->orderBy('statements_of_work.signed_at')->first(['statements_of_work.signed_at', 'statements_of_work.reference']) : null;
        $found = collect([
            $proposal ? ['at' => $proposal->signed_at, 'reference' => $proposal->proposal_ref, 'what' => 'the proposal'] : null,
            $sow ? ['at' => $sow->signed_at, 'reference' => $sow->reference, 'what' => 'the statement of work'] : null,
        ])->filter()->sortBy('at')->first();

        return $found;
    }

    private function firstProject(string $orgId): ?string
    {
        return DB::table('projects')->where('organization_id', $orgId)->whereNull('deleted_at')->min('created_at');
    }

    private function summary(string $type, string $text): string
    {
        $first = trim((string) preg_replace('/\s+/u', ' ', preg_replace('/^>>[^\n]*\n/u', '', $text)));
        $prefix = match ($type) {
            'website_enquiry' => 'Website enquiry: ', 'call' => 'Call: ', 'email' => 'Email: ', 'meeting' => 'Meeting notes: ', default => 'Note: ',
        };

        return Str::limit($prefix . ($first !== '' ? $first : '(empty)'), 250);
    }

    private function row(string $orgId, string $type, string $summary, $at, string $key, array $extra = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid(), 'organization_id' => $orgId, 'type' => $type, 'actor_type' => 'staff', 'actor_id' => null,
            'summary' => $summary, 'body' => null, 'payload' => null, 'client_user_id' => null, 'opportunity_id' => null, 'meeting_id' => null,
            'related_type' => null, 'related_id' => null, 'occurred_at' => $at, 'dedupe_key' => $key, 'created_at' => now(), 'updated_at' => now(),
        ], $extra);
    }

    public function down(): void
    {
        // The columns and the table go with the previous migration's down(); nothing to undo row by row
    }
};
