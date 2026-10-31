<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill (docs/OPPORTUNITIES.md section 7): one opportunity per organisation that already has proposals,
 * projects or appointments, so the work done before opportunities existed shows on the new board.
 *
 *   stage     a project or an Authorized/Converted proposal → won; any other proposal → proposal_sent;
 *             no proposal but a recorded disqualification → lost; else discovery
 *   lead      the first proposal's creator, else the first appointment's creator or host, else the organisation's
 *             assigned staff member (staff accounts only: website bookings store the client contact in created_by)
 *   language  the organisation's preferred_language (FR → fr, anything else → en)
 *
 * Its proposals and projects get its opportunity_id; past appointments with a meeting link become meetings
 * (transcript_status manual: no transcript was recorded for them). Declined or expired requests that never had a
 * link don't count, so spam bookings don't become opportunities. JBS Praxis itself (status Internal) is skipped.
 *
 * Idempotent: an organisation that already has a backfilled opportunity keeps it; only records still without an
 * opportunity are linked, and an appointment that already has a meeting is not copied again.
 */
return new class extends Migration
{
    private const TZ = 'Africa/Lagos';

    public function up(): void
    {
        if (! Schema::hasTable('opportunities') || ! Schema::hasColumn('proposals', 'opportunity_id')) {
            return;
        }

        $staff = fn (?string $id) => $id && DB::table('users')->where('id', $id)->exists() ? $id : null;
        $nowLagos = Carbon::now(self::TZ)->format('Y-m-d H:i:s');

        $orgIds = DB::table('organizations')->whereNull('deleted_at')->where('status', '!=', 'Internal')
            ->where(function ($q) {
                $q->whereExists(fn ($s) => $s->from('proposals')->whereColumn('proposals.organization_id', 'organizations.id')->whereNull('proposals.deleted_at'))
                    ->orWhereExists(fn ($s) => $s->from('projects')->whereColumn('projects.organization_id', 'organizations.id')->whereNull('projects.deleted_at'))
                    ->orWhereExists(fn ($s) => $s->from('appointments')->whereColumn('appointments.organization_id', 'organizations.id')->whereNull('appointments.deleted_at')
                        ->where(fn ($a) => $a->where('appointments.status', '!=', 'Cancelled')->orWhereNotNull('appointments.meeting_link')));
            })
            ->orderBy('created_at')->pluck('id');

        foreach ($orgIds as $orgId) {
            DB::transaction(function () use ($orgId, $staff, $nowLagos) {
                $org = DB::table('organizations')->where('id', $orgId)->first();
                $proposals = DB::table('proposals')->where('organization_id', $orgId)->whereNull('deleted_at')->orderBy('created_at')->get();
                $projects = DB::table('projects')->where('organization_id', $orgId)->whereNull('deleted_at')->orderBy('created_at')->get();
                $appointments = DB::table('appointments')->where('organization_id', $orgId)->whereNull('deleted_at')->orderBy('start_time')->get();

                $opportunityId = DB::table('opportunities')->where('organization_id', $orgId)->where('source', 'backfill')->value('id');
                if (! $opportunityId) {
                    $signed = $proposals->whereIn('status', ['Authorized', 'Converted']);
                    $won = $projects->isNotEmpty() || $signed->isNotEmpty();
                    $stage = match (true) {
                        $won => 'won',
                        $proposals->isNotEmpty() => 'proposal_sent',
                        filled($org->disqualification_reason) => 'lost',
                        default => 'discovery',
                    };

                    $lead = $staff($proposals->first()?->created_by)
                        ?? $appointments->map(fn ($a) => $staff($a->created_by) ?? $staff($a->host_id))->filter()->first()
                        ?? $staff($org->assigned_to);

                    $project = $projects->last();
                    $title = $project->title ?? $proposals->filter(fn ($p) => filled($p->title))->last()?->title ?? $org->name;
                    $started = collect([$proposals->min('created_at'), $projects->min('created_at'), $appointments->min('created_at')])->filter()->min();
                    $closedAt = match ($stage) {
                        'won' => collect([$signed->max('signed_at'), $projects->min('created_at')])->filter()->min(),
                        'lost' => $org->updated_at,
                        default => null,
                    };

                    $opportunityId = (string) Str::uuid();
                    DB::table('opportunities')->insert([
                        'id' => $opportunityId,
                        'organization_id' => $orgId,
                        'title' => Str::limit((string) $title, 200, ''),
                        'stage' => $stage,
                        'lead_user_id' => $lead,
                        'language' => str_starts_with(strtolower(trim((string) $org->preferred_language)), 'fr') ? 'fr' : 'en',
                        'source' => 'backfill',
                        'project_id' => $project->id ?? null,
                        'lost_reason' => $stage === 'lost' ? $org->disqualification_reason : null,
                        'closed_at' => $closedAt,
                        'created_at' => $started ?? now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('proposals')->where('organization_id', $orgId)->whereNull('opportunity_id')->update(['opportunity_id' => $opportunityId]);
                DB::table('projects')->where('organization_id', $orgId)->whereNull('opportunity_id')->update(['opportunity_id' => $opportunityId]);

                // Past appointments with a link become meetings; the transcript can only be added by hand
                foreach ($appointments as $appt) {
                    if (blank($appt->meeting_link) || $appt->end_time >= $nowLagos
                        || DB::table('meetings')->where('appointment_id', $appt->id)->exists()) {
                        continue;
                    }
                    $meetingId = (string) Str::uuid();
                    preg_match('~meet\.google\.com/([a-z]{3}-[a-z]{4}-[a-z]{3})~i', (string) $appt->meeting_link, $code);
                    DB::table('meetings')->insert([
                        'id' => $meetingId,
                        'opportunity_id' => $opportunityId,
                        'organization_id' => $orgId,
                        'kind' => 'client',
                        'title' => Str::limit((string) $appt->title, 200, ''),
                        'starts_at' => Carbon::parse($appt->start_time, self::TZ)->utc(),
                        'ends_at' => Carbon::parse($appt->end_time, self::TZ)->utc(),
                        'time_zone' => self::TZ,
                        'status' => in_array($appt->status, ['Cancelled', 'No-Show'], true) ? 'cancelled' : 'held',
                        'appointment_id' => $appt->id,
                        'meet_link' => Str::limit((string) $appt->meeting_link, 255, ''),
                        'meet_code' => isset($code[1]) ? strtolower($code[1]) : null,
                        'transcript_status' => 'manual',
                        'created_by' => $staff($appt->created_by),
                        'created_at' => $appt->created_at ?? now(),
                        'updated_at' => now(),
                    ]);

                    $attendees = [];
                    if ($host = $staff($appt->host_id)) {
                        $u = DB::table('users')->where('id', $host)->first();
                        $attendees[] = ['user_id' => $u->id, 'client_user_id' => null, 'email' => $u->email, 'name' => trim("{$u->first_name} {$u->last_name}"), 'role' => 'staff'];
                    }
                    if ($appt->created_by && ($c = DB::table('client_users')->where('id', $appt->created_by)->first())) {
                        $attendees[] = ['user_id' => null, 'client_user_id' => $c->id, 'email' => $c->email, 'name' => trim("{$c->first_name} {$c->last_name}"), 'role' => 'client'];
                    }
                    foreach ($attendees as $a) {
                        DB::table('meeting_attendees')->insertOrIgnore($a + ['id' => (string) Str::uuid(), 'meeting_id' => $meetingId, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('opportunities')) {
            return;
        }
        $ids = DB::table('opportunities')->where('source', 'backfill')->pluck('id');
        DB::table('proposals')->whereIn('opportunity_id', $ids)->update(['opportunity_id' => null]);
        DB::table('projects')->whereIn('opportunity_id', $ids)->update(['opportunity_id' => null]);
        DB::table('meetings')->whereIn('opportunity_id', $ids)->whereNotNull('appointment_id')->where('transcript_status', 'manual')->delete();
        DB::table('opportunities')->whereIn('id', $ids)->delete();
    }
};
