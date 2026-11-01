<?php

namespace App\Console\Commands;

use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Opportunities\OpportunityAccess;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brings an engagement that began before the opportunity pipeline into HQ in one go (docs/OPPORTUNITIES.md 12):
 * the organisation, its opportunity (won), the signed statement of work, the project with its milestones (phases),
 * sprints and tasks, the invoices as they stand, QA points, and the meetings with their transcripts, from the bundle
 * and from Gemini's notes on matching events in the connected Google calendar.
 *
 * Rows are written directly in one transaction, so nobody is emailed or notified and no client gets portal access.
 * An organisation that already exists, or an invoice number already used, stops the import before anything is
 * written. --dry-run shows the counts and the Google meetings it would bring in.
 *
 * Bundle (JSON): organization {name, industry, country, city, preferred_language}; opportunity {title, lead_email,
 * language, won_at}; sow {reference, signed_at, signed_by_name, hq_signer_name, sections [{key, title, body}]};
 * project {title, description, total_budget, currency, start_date, target_end_date, status}; phases [{key, number,
 * title, description, weight, status}]; sprints [{key, title, start, end, status}]; tasks [{phase, sprint, title,
 * description, status, points}]; invoices [{number, type, phase, reference, currency, status, issued_at, due_date,
 * paid_at, deposit, lines [{description, amount}]}]; qa_tickets [{phase, title, description, severity, status,
 * raised_at, resolved_at}]; meetings [{kind, title, starts_at, ends_at, attendees [{name, email, role}], transcript
 * {source, text, language}}]; google {from, to, match [words in the event title], exclude [words]}.
 */
class ImportEngagement extends Command
{
    protected $signature = 'opportunities:import
        {bundle : Path to the engagement bundle (JSON)}
        {--dry-run : Show what would be created, and the Google meetings found, without writing anything}';

    protected $description = 'Import an engagement that began before the opportunity pipeline (organisation to project, with meetings)';

    /** A bundle meeting this close to a Google event is the same meeting: the bundle's transcript wins */
    private const SAME_MEETING_MINUTES = 90;

    public function handle(GoogleWorkspace $google): int
    {
        $bundle = json_decode((string) @file_get_contents((string) $this->argument('bundle')), true);
        if (! is_array($bundle)) {
            $this->error('The bundle could not be read as JSON: ' . $this->argument('bundle'));
            return self::FAILURE;
        }
        foreach (['organization', 'opportunity', 'project', 'phases', 'sprints'] as $part) {
            if (empty($bundle[$part])) {
                $this->error("The bundle has no {$part}.");
                return self::FAILURE;
            }
        }

        $leadId = DB::table('users')->where('email', strtolower(trim($bundle['opportunity']['lead_email'] ?? '')))->whereNull('deleted_at')->value('id');
        if (! $leadId) {
            $this->error('No staff account has the lead email ' . ($bundle['opportunity']['lead_email'] ?? '(none)') . '.');
            return self::FAILURE;
        }
        $name = trim($bundle['organization']['name']);
        if (DB::table('organizations')->where('name', $name)->whereNull('deleted_at')->exists()) {
            $this->error("{$name} is already in HQ: nothing was imported.");
            return self::FAILURE;
        }
        $numbers = array_column($bundle['invoices'] ?? [], 'number');
        if ($used = DB::table('invoices')->whereIn('invoice_number', $numbers)->pluck('invoice_number')->all()) {
            $this->error('These invoice numbers are already used: ' . implode(', ', $used) . '. Nothing was imported.');
            return self::FAILURE;
        }
        foreach ($bundle['tasks'] ?? [] as $task) {
            if (! collect($bundle['phases'])->contains('key', $task['phase'] ?? null)) {
                $this->error("A task names a phase the bundle doesn't have: " . ($task['phase'] ?? '(none)') . ' (' . ($task['title'] ?? '') . ').');
                return self::FAILURE;
            }
        }

        $fromGoogle = $this->googleMeetings($google, $bundle['google'] ?? null, $bundle['meetings'] ?? []);
        $this->report($bundle, $fromGoogle);
        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was written.');
            return self::SUCCESS;
        }

        // Transcripts are read before the transaction: Google can be slow, and the database should not wait for it
        foreach ($fromGoogle as $i => $event) {
            try {
                $fromGoogle[$i]['transcript'] = $event['docs'] > 0 ? $google->eventAttachmentText($event['id']) : null;
            } catch (GoogleUnavailable $e) {
                $this->warn("No transcript for {$event['title']}: {$e->getMessage()}");
                $fromGoogle[$i]['transcript'] = null;
            }
        }

        $opportunityId = DB::transaction(fn () => $this->write($bundle, $fromGoogle, $leadId));
        $this->info("Imported {$name}: opportunity {$opportunityId}.");

        return self::SUCCESS;
    }

    /** The calendar events in the bundle's window whose title matches, less the meetings the bundle already has */
    private function googleMeetings(GoogleWorkspace $google, ?array $spec, array $bundleMeetings): array
    {
        if (! $spec) {
            return [];
        }
        if (! $google->isConnected()) {
            $this->warn('Google is not connected: only the bundle\'s meetings will be imported.');
            return [];
        }
        $known = array_map(fn ($m) => CarbonImmutable::parse($m['starts_at']), $bundleMeetings);
        $match = fn (string $title, array $words) => collect($words)->contains(fn ($w) => Str::contains(Str::lower($title), Str::lower($w)));

        return array_values(array_filter(
            $google->listEvents(CarbonImmutable::parse($spec['from']), CarbonImmutable::parse($spec['to'])),
            fn ($e) => $match($e['title'], $spec['match'] ?? [])
                && ! $match($e['title'], $spec['exclude'] ?? [])
                && ! collect($known)->contains(fn ($at) => abs($at->diffInMinutes($e['start'])) < self::SAME_MEETING_MINUTES),
        ));
    }

    private function report(array $bundle, array $fromGoogle): void
    {
        $tasks = collect($bundle['tasks'] ?? []);
        $this->table(['Part', 'Count'], [
            ['Phases (milestones)', count($bundle['phases'])],
            ['Sprints', count($bundle['sprints'])],
            ['Tasks', $tasks->count() . ' (' . $tasks->countBy('status')->map(fn ($n, $s) => "{$s} {$n}")->implode(', ') . ')'],
            ['Invoices', count($bundle['invoices'] ?? [])],
            ['QA tickets', count($bundle['qa_tickets'] ?? [])],
            ['Meetings from the bundle', count($bundle['meetings'] ?? [])],
            ['Meetings from Google', count($fromGoogle)],
        ]);
        foreach ($fromGoogle as $event) {
            $this->line(sprintf('  Google: %s  %s  (%d attendee(s), %d doc(s) attached)', $event['start']->format('Y-m-d H:i'), $event['title'], count($event['attendees']), $event['docs']));
        }
    }

    private function write(array $b, array $fromGoogle, string $leadId): string
    {
        $now = now();
        $stamp = fn () => ['created_by' => $leadId, 'updated_by' => $leadId, 'created_at' => $now, 'updated_at' => $now];
        $date = fn (?string $value) => $value ? CarbonImmutable::parse($value) : null;

        $orgId = (string) Str::uuid();
        DB::table('organizations')->insert([
            'id' => $orgId,
            'name' => trim($b['organization']['name']),
            'industry' => $b['organization']['industry'] ?? null,
            'status' => 'Active Client',
            'pipeline_stage' => 'Won',
            'assigned_to' => $leadId,
            'country' => $b['organization']['country'] ?? 'Nigeria',
            'city' => $b['organization']['city'] ?? null,
            'preferred_language' => strtoupper($b['organization']['preferred_language'] ?? 'EN'),
        ] + $stamp());

        $projectId = (string) Str::uuid();
        $p = $b['project'];
        DB::table('projects')->insert([
            'id' => $projectId,
            'organization_id' => $orgId,
            'title' => $p['title'],
            'description' => $p['description'] ?? null,
            'total_budget' => $p['total_budget'] ?? 0,
            'currency' => $p['currency'] ?? 'NGN',
            'locked_exchange_rate' => 1,
            'status' => $p['status'] ?? 'Active',
            'start_date' => $p['start_date'] ?? null,
            'target_end_date' => $p['target_end_date'] ?? null,
        ] + $stamp());

        $opportunityId = (string) Str::uuid();
        DB::table('opportunities')->insert([
            'id' => $opportunityId,
            'organization_id' => $orgId,
            'title' => $b['opportunity']['title'],
            'stage' => 'won',
            'lead_user_id' => $leadId,
            'language' => $b['opportunity']['language'] ?? 'en',
            'source' => 'manual',
            'project_id' => $projectId,
            'closed_at' => $date($b['opportunity']['won_at'] ?? null),
        ] + $stamp());

        $phases = [];
        foreach ($b['phases'] as $phase) {
            $phases[$phase['key']] = (string) Str::uuid();
            DB::table('project_phases')->insert([
                'id' => $phases[$phase['key']],
                'project_id' => $projectId,
                'phase_number' => $phase['number'],
                'title' => $phase['title'],
                'description' => $phase['description'] ?? null,
                'weight_percentage' => $phase['weight'] ?? 0,
                'status' => $phase['status'] ?? 'Pending',
            ] + $stamp());
        }

        $sprints = [];
        foreach ($b['sprints'] as $sprint) {
            $sprints[$sprint['key']] = (string) Str::uuid();
            DB::table('sprints')->insert([
                'id' => $sprints[$sprint['key']],
                'project_id' => $projectId,
                'title' => $sprint['title'],
                'start_date' => $sprint['start'],
                'end_date' => $sprint['end'],
                'status' => $sprint['status'] ?? 'Planned',
            ] + $stamp());
        }

        foreach (array_chunk($b['tasks'] ?? [], 200) as $chunk) {
            DB::table('tasks')->insert(array_map(fn ($task) => [
                'id' => (string) Str::uuid(),
                'phase_id' => $phases[$task['phase']],
                'sprint_id' => isset($task['sprint']) ? ($sprints[$task['sprint']] ?? null) : null,
                'title' => Str::limit($task['title'], 252),
                'description' => $task['description'] ?? null,
                'story_points' => $task['points'] ?? 1,
                'status' => $task['status'] ?? 'To-Do',
            ] + $stamp(), $chunk));
        }

        $depositInvoiceId = null;
        foreach ($b['invoices'] ?? [] as $invoice) {
            $invoiceId = (string) Str::uuid();
            $total = array_sum(array_column($invoice['lines'], 'amount'));
            $issued = $date($invoice['issued_at']);
            DB::table('invoices')->insert([
                'id' => $invoiceId,
                'project_id' => $projectId,
                'phase_id' => isset($invoice['phase']) ? ($phases[$invoice['phase']] ?? null) : null,
                'invoice_type' => $invoice['type'] ?? 'Tax Invoice',
                'invoice_number' => $invoice['number'],
                'po_sow_reference' => $invoice['reference'] ?? null,
                'amount_due' => $total,
                'vat_amount' => 0,
                'wht_amount' => 0,
                'total_amount' => $total,
                'currency' => $invoice['currency'] ?? 'NGN',
                'status' => $invoice['status'] ?? 'Pending',
                'due_date' => $invoice['due_date'],
                'payment_terms' => $invoice['terms'] ?? null,
                'jbs_signature_hash' => 'SHA256-INV-' . strtoupper(hash('sha256', $invoiceId . $total . $issued)),
                'issued_at' => $issued,
                'paid_at' => $date($invoice['paid_at'] ?? null),
            ] + $stamp());
            DB::table('invoice_lines')->insert(array_map(fn ($line) => [
                'invoice_id' => $invoiceId,
                'description' => Str::limit($line['description'], 252),
                'metric_of_billing' => 'Fixed Milestone',
                'quantity' => 1,
                'unit_price' => $line['amount'],
                'total_price' => $line['amount'],
            ], $invoice['lines']));
            if (! empty($invoice['deposit'])) {
                $depositInvoiceId = $invoiceId;
            }
        }

        if (! empty($b['sow'])) {
            $s = $b['sow'];
            DB::table('statements_of_work')->insert([
                'id' => (string) Str::uuid(),
                'opportunity_id' => $opportunityId,
                'reference' => $s['reference'] ?? null,
                'version' => 1,
                'sections' => json_encode($s['sections'] ?? [], JSON_UNESCAPED_UNICODE),
                'status' => 'signed',
                'approved_by' => $leadId,
                'approved_at' => $date($s['signed_at'] ?? null),
                'sent_at' => $date($s['signed_at'] ?? null),
                'hq_signer_name' => $s['hq_signer_name'] ?? null,
                'hq_signed_at' => $date($s['signed_at'] ?? null),
                'signed_by_name' => $s['signed_by_name'] ?? null,
                'signed_at' => $date($s['signed_at'] ?? null),
                'deposit_invoice_id' => $depositInvoiceId,
            ] + $stamp());
        }

        foreach ($b['qa_tickets'] ?? [] as $ticket) {
            $resolved = in_array($ticket['status'] ?? 'Open', ['Resolved', 'Closed'], true);
            // When the point was raised, not when it was imported (the first value of a key wins in +)
            DB::table('qa_tickets')->insert(['created_at' => $date($ticket['raised_at'] ?? null) ?? $now] + [
                'id' => (string) Str::uuid(),
                'project_id' => $projectId,
                'submitted_by' => $leadId,
                'title' => Str::limit($ticket['title'], 197),
                'description' => $ticket['description'] ?? '',
                'severity' => $ticket['severity'] ?? 'Low',
                'status' => $ticket['status'] ?? 'Open',
                'resolved_at' => $resolved ? $date($ticket['resolved_at'] ?? null) : null,
                'resolved_by' => $resolved ? $leadId : null,
            ] + $stamp());
        }

        $staffEmails = DB::table('users')->whereNull('deleted_at')->pluck('id', 'email')->mapWithKeys(fn ($id, $email) => [strtolower($email) => $id]);
        foreach ($b['meetings'] ?? [] as $meeting) {
            $this->meeting($opportunityId, $orgId, $stamp(), $staffEmails, [
                'kind' => $meeting['kind'] ?? 'client',
                'title' => $meeting['title'],
                'start' => CarbonImmutable::parse($meeting['starts_at']),
                'end' => CarbonImmutable::parse($meeting['ends_at'] ?? $meeting['starts_at'])->max(CarbonImmutable::parse($meeting['starts_at'])),
                'attendees' => $meeting['attendees'] ?? [],
                'google_event_id' => null,
                'meet_link' => null,
                'meet_code' => null,
                'transcript' => isset($meeting['transcript']['text']) ? [
                    'source' => $meeting['transcript']['source'] ?? 'upload',
                    'text' => $meeting['transcript']['text'],
                    'language' => $meeting['transcript']['language'] ?? null,
                    'doc' => null,
                ] : null,
            ]);
        }
        foreach ($fromGoogle as $event) {
            $t = $event['transcript'] ?? null;
            $this->meeting($opportunityId, $orgId, $stamp(), $staffEmails, [
                'kind' => preg_match('/\binternal\b/i', $event['title']) ? 'internal' : 'client',
                'title' => $event['title'],
                'start' => $event['start'],
                'end' => $event['end'],
                'attendees' => $event['attendees'],
                'google_event_id' => $event['id'],
                'meet_link' => $event['meet_link'],
                'meet_code' => $event['meet_code'],
                'transcript' => $t ? ['source' => $t->source, 'text' => $t->text, 'language' => $t->language, 'doc' => $t->googleDocId] : null,
            ]);
        }

        OpportunityAccess::log('import', sprintf('Imported %s from a bundle: %d phase(s), %d sprint(s), %d task(s), %d invoice(s), %d meeting(s).',
            $b['opportunity']['title'], count($b['phases']), count($b['sprints']), count($b['tasks'] ?? []), count($b['invoices'] ?? []),
            count($b['meetings'] ?? []) + count($fromGoogle)), 'opportunities', $opportunityId);

        return $opportunityId;
    }

    /** One past meeting, held, with its attendees and transcript (times are stored in UTC) */
    private function meeting(string $opportunityId, string $orgId, array $stamp, $staffEmails, array $m): void
    {
        $meetingId = (string) Str::uuid();
        DB::table('meetings')->insert([
            'id' => $meetingId,
            'opportunity_id' => $opportunityId,
            'organization_id' => $orgId,
            'kind' => $m['kind'],
            'title' => Str::limit($m['title'], 197),
            'starts_at' => $m['start']->utc(),
            'ends_at' => $m['end']->utc(),
            'time_zone' => GoogleWorkspace::TIME_ZONE,
            'status' => 'held',
            'google_event_id' => $m['google_event_id'],
            'meet_link' => $m['meet_link'],
            'meet_code' => $m['meet_code'],
            'transcript_status' => $m['transcript'] ? 'ready' : 'manual',
        ] + $stamp);

        $seen = [];
        foreach ($m['attendees'] as $a) {
            $email = strtolower(trim($a['email'] ?? ''));
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $staffId = $staffEmails[$email] ?? null;
            DB::table('meeting_attendees')->insert([
                'id' => (string) Str::uuid(),
                'meeting_id' => $meetingId,
                'user_id' => $staffId,
                'email' => $email,
                'name' => $a['name'] ?? null,
                'role' => $staffId ? 'staff' : ($a['role'] ?? 'client'),
                'response' => $a['response'] ?? null,
                'created_at' => $stamp['created_at'],
                'updated_at' => $stamp['updated_at'],
            ]);
        }

        if ($m['transcript']) {
            DB::table('meeting_transcripts')->insert([
                'id' => (string) Str::uuid(),
                'meeting_id' => $meetingId,
                'source' => $m['transcript']['source'],
                'google_doc_id' => $m['transcript']['doc'],
                'language' => $m['transcript']['language'],
                'text' => $m['transcript']['text'],
                'collected_at' => $stamp['created_at'],
            ] + $stamp);
        }
    }
}
