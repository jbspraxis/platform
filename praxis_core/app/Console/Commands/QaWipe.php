<?php

namespace App\Console\Commands;

use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deletes a test organisation and everything tied to it, after a QA run on the live site (docs/QA-PORTAL.md):
 * its portal accounts and their sessions, sign-in methods and bells; its opportunities with their meetings,
 * transcripts, questionnaires, proposals, statements of work and project drafts; its projects with their
 * milestones, tasks, QA tickets, plans, change reports, invoices, payments and ledger postings; the content of the
 * AI runs about any of these; the staff bells that link to them; their history lines; and the files uploaded for it.
 *
 * Without --force it only shows what it would delete, table by table. It refuses any organisation that the owner
 * hasn't flagged as a test (organizations.is_test, set on the opportunity's Edit details), and one added more than
 * MAX_AGE_DAYS ago (a test client is added on the day of its QA) unless --allow-old-organisation says so. The preview
 * shows who flagged it and when, and how old the rows are.
 *
 * With --force the person types the organisation's exact name (or gives it as --confirm="…" without questions). The
 * rows (and files) are then copied to storage/app/private/qa-wipes/<time>-<name>/, readable by the owner's account
 * only; the organisation's Google Calendar events are cancelled (guests of upcoming meetings are told; past ones go
 * quietly); then everything is deleted in one transaction, and one history line records that the wipe ran (QA_WIPE,
 * with the counts, the backup folder, who flagged it and who ran it). Its AI runs stay, emptied of their content:
 * their cost was really spent and counts towards the month's AI budget. Invoice numbers the test used stay as gaps.
 */
class QaWipe extends Command
{
    protected $signature = 'qa:wipe
        {organisation : The test organisation, by its exact name or its id}
        {--force : Back up, cancel its Google events and delete; without it nothing changes}
        {--confirm= : The organisation\'s exact name, to delete without being asked for it (with --no-interaction)}
        {--allow-old-organisation : Also wipe an organisation added more than 30 days ago}
        {--skip-google : Delete even when its Google events can\'t be cancelled (they stay in the calendar)}';

    protected $description = 'Delete a test organisation and everything tied to it (only organisations flagged as a test)';

    /** A test client is added on the day of its QA: anything older is refused unless --allow-old-organisation */
    private const MAX_AGE_DAYS = 30;

    /** AI runs stay for the budget (Budget::spent sums their cost); what they were about and their content go */
    private const RUN_SCRUB = ['subject_type' => null, 'subject_id' => null, 'input' => null, 'output' => null, 'error' => null];

    /** AI run subjects (morph aliases, AppServiceProvider) => the id set they point to */
    private const SUBJECTS = [
        'opportunity' => 'opportunities', 'meeting' => 'meetings', 'meeting_transcript' => 'transcripts',
        'questionnaire' => 'questionnaires', 'proposal' => 'proposals', 'statement_of_work' => 'sows',
        'project_draft' => 'drafts', 'milestone_plan' => 'plans',
    ];

    public function handle(GoogleWorkspace $google): int
    {
        $org = $this->organisation(trim((string) $this->argument('organisation')));
        if (! $org) {
            return self::FAILURE;
        }
        if (! $org->is_test) {
            $this->error("{$org->name} isn't flagged as a test organisation, so nothing was deleted.");
            $this->line('Only the owner flags one, on an opportunity of it: Edit details › Test client.');

            return self::FAILURE;
        }
        if ($org->status === 'Internal' || DB::table('users')->where('organization_id', $org->id)->exists()) {
            $this->error("{$org->name} has staff accounts, so it can't be a test client. Nothing was deleted.");

            return self::FAILURE;
        }
        $added = $org->created_at ? \Carbon\Carbon::parse($org->created_at) : null;
        if ((! $added || $added->lt(now()->subDays(self::MAX_AGE_DAYS))) && ! $this->option('allow-old-organisation')) {
            $this->error(sprintf('%s was added %s, more than %d days ago: a test client is added on the day of its QA. Nothing was deleted.',
                $org->name, $added ? 'on ' . $added->format('j F Y') : 'on an unknown date', self::MAX_AGE_DAYS));
            $this->line('Only if you are sure every row of it is test data, run it again with --allow-old-organisation.');

            return self::FAILURE;
        }

        $ids = $this->ids($org);
        $steps = $this->steps($org, $ids);
        $files = $this->files($ids);
        $events = DB::table('meetings')->whereIn('id', $ids['meetings'] ?: [''])->whereNotNull('google_event_id')
            ->where('status', '!=', 'cancelled')->get(['id', 'title', 'google_event_id', 'starts_at']);

        $counts = [];
        $dates = [];
        foreach ($steps as [$table, $query]) {
            $counts[$table] = ($counts[$table] ?? 0) + (clone $query)->count();
            if (Schema::hasColumn($table, 'created_at')) {
                $dates[] = (clone $query)->min('created_at');
                $dates[] = (clone $query)->max('created_at');
            }
        }
        $dates = array_filter($dates);
        $flag = $this->flaggedBy($ids['opportunities']);
        $this->info(($this->option('force') ? 'Deleting' : 'Would delete') . " {$org->name} ({$org->id}):");
        $this->table(['Table', 'Rows', ''], collect($counts)->filter()->map(fn ($n, $t) => [$t, $n, $t === 'ai_runs' ? 'kept for the AI budget, content emptied' : 'deleted'])->values()->all());
        $this->line(sprintf('%d uploaded file(s), %d Google Calendar event(s) to cancel.', count($files), $events->count()));
        $this->line('Added on ' . ($added ? $added->format('j F Y, H:i') : 'an unknown date') . ($dates ? sprintf('; its rows are dated %s to %s.', \Carbon\Carbon::parse(min($dates))->format('j F Y'), \Carbon\Carbon::parse(max($dates))->format('j F Y')) : '.'));
        $this->line($flag ? "Flagged as a test by {$flag['by']} on " . \Carbon\Carbon::parse($flag['at'])->format('j F Y, H:i') . '.' : 'No history line says who flagged it as a test.');

        if (! $this->option('force')) {
            $this->line('Nothing was changed. Run it again with --force to back up and delete.');

            return self::SUCCESS;
        }

        // The person types the exact name: a wrong tick on a real organisation's opportunity can't wipe it alone
        $typed = $this->option('confirm');
        if ($typed === null) {
            if (! $this->input->isInteractive()) {
                $this->error('Without questions, give the organisation\'s exact name: --confirm="' . $org->name . '". Nothing was deleted.');

                return self::FAILURE;
            }
            $typed = $this->ask('Type the organisation\'s exact name to delete it');
        }
        if (trim((string) $typed) !== trim((string) $org->name)) {
            $this->error('That isn\'t the organisation\'s exact name. Nothing was deleted.');

            return self::FAILURE;
        }

        if ($busy = DB::table('ai_runs')->whereIn('id', $ids['runs'] ?: [''])->whereIn('status', ['queued', 'running'])->count()) {
            $this->error("{$busy} AI run(s) for it are still queued or running. Try again when they finish; nothing was deleted.");

            return self::FAILURE;
        }
        if ($events->isNotEmpty() && ! $this->option('skip-google') && ! $google->isConnected()) {
            $this->error('Google isn\'t connected, so its events can\'t be cancelled. Connect it in System › Integrations, or add --skip-google. Nothing was deleted.');

            return self::FAILURE;
        }

        $folder = $this->backup($org, $steps, $files, $events);
        $this->line("Backed up to {$folder}");

        $cancelled = 0;
        if (! $this->option('skip-google')) {
            foreach ($events as $event) {
                try {
                    // Guests of a meeting still to come hear it's off; a past one goes quietly
                    $google->cancelMeeting($event->google_event_id, now()->lt($event->starts_at));
                    $cancelled++;
                } catch (GoogleUnavailable $e) {
                    $this->error("Google refused to cancel “{$event->title}” ({$e->getMessage()}). {$cancelled} event(s) were cancelled; nothing was deleted. Try again, or add --skip-google.");

                    return self::FAILURE;
                }
            }
        }

        $deleted = [];
        DB::transaction(function () use ($steps, &$deleted) {
            foreach ($steps as $step) {
                [$table, $query] = $step;
                $deleted[$table] = ($deleted[$table] ?? 0) + (isset($step[2]) ? (clone $query)->update($step[2]) : (clone $query)->delete());
            }
        });

        $removed = 0;
        foreach ($files as [$disk, $path]) {
            $removed += Storage::disk($disk)->delete($path) ? 1 : 0;
        }
        foreach ($ids['tasks'] as $taskId) {
            Storage::disk('local')->deleteDirectory('task_files/' . $taskId);
        }

        $total = array_sum(array_diff_key($deleted, ['ai_runs' => 1]));
        DB::table('system_audit_logs')->insert([
            'user_id' => null,
            'action' => 'QA_WIPE',
            'description' => mb_substr(sprintf('Wiped the test organisation “%s” with qa:wipe: %d rows, %d files, %d Google events. Backup: %s', $org->name, $total, $removed, $cancelled, $folder), 0, 2000),
            'target_table' => 'organizations',
            'target_id' => $org->id,
            'old_values' => null,
            'new_values' => json_encode(['rows' => array_filter(array_diff_key($deleted, ['ai_runs' => 1])), 'ai_runs_kept' => $deleted['ai_runs'] ?? 0,
                'files' => $removed, 'google_events' => $cancelled, 'backup' => $folder,
                'flagged_by' => $flag['by'] ?? null, 'flagged_by_id' => $flag['id'] ?? null, 'flagged_at' => $flag['at'] ?? null,
                'run_by' => get_current_user(), 'allowed_old' => (bool) $this->option('allow-old-organisation')], JSON_UNESCAPED_UNICODE),
            'ip_address' => null,
            'created_at' => now(),
        ]);

        $this->info("Deleted {$total} rows, {$removed} file(s) and cancelled {$cancelled} Google event(s).");

        return self::SUCCESS;
    }

    /** Who last flagged one of these opportunities' organisation as a test, and when (its history line) */
    private function flaggedBy(array $opportunityIds): ?array
    {
        $line = DB::table('system_audit_logs')->leftJoin('users', 'users.id', '=', 'system_audit_logs.user_id')
            ->where('system_audit_logs.action', 'ORGANISATION_TEST_FLAG_CHANGED')->whereIn('system_audit_logs.target_id', $opportunityIds ?: [''])
            ->orderByDesc('system_audit_logs.created_at')->orderByDesc('system_audit_logs.id')
            ->first(['system_audit_logs.user_id', 'system_audit_logs.new_values', 'system_audit_logs.created_at', 'users.first_name', 'users.last_name']);
        if (! $line || ! (json_decode((string) $line->new_values, true)['is_test'] ?? false)) {
            return null;
        }

        return ['id' => $line->user_id, 'by' => trim($line->first_name . ' ' . $line->last_name) ?: 'an unknown account', 'at' => (string) $line->created_at];
    }

    /** By id, or by exact name (case and spacing aside); several with that name must be told apart by id */
    private function organisation(string $wanted): ?object
    {
        if (Str::isUuid($wanted)) {
            $org = DB::table('organizations')->where('id', $wanted)->first();
        } else {
            $needle = mb_strtolower(preg_replace('/\s+/u', ' ', $wanted));
            $found = DB::table('organizations')->whereRaw('LOWER(TRIM(name)) = ?', [$needle])->get();
            if ($found->count() > 1) {
                $this->error("Several organisations are called “{$wanted}”. Give the id of the one to wipe:");
                $found->each(fn ($o) => $this->line("  {$o->id}  " . ($o->is_test ? 'test' : 'not a test') . ($o->deleted_at ? ', archived' : '')));

                return null;
            }
            $org = $found->first();
        }
        if (! $org) {
            $this->error("No organisation is called “{$wanted}”. Nothing was deleted.");
        }

        return $org;
    }

    /** Every id tied to the organisation, collected before anything is deleted */
    private function ids(object $org): array
    {
        $o = (string) $org->id;
        $ids = ['org' => [$o]];
        $ids['clients'] = $this->pluck($this->any('client_users', ['organization_id' => $o]));
        $ids['opportunities'] = $this->pluck($this->any('opportunities', ['organization_id' => $o]));
        $ids['projects'] = $this->pluck($this->any('projects', ['organization_id' => $o, 'opportunity_id' => $ids['opportunities']]));
        $ids['phases'] = $this->pluck($this->any('project_phases', ['project_id' => $ids['projects']]));
        $ids['sprints'] = $this->pluck($this->any('sprints', ['project_id' => $ids['projects']]));
        $ids['tasks'] = $this->pluck($this->any('tasks', ['phase_id' => $ids['phases'], 'sprint_id' => $ids['sprints']]));
        $ids['tickets'] = $this->pluck($this->any('qa_tickets', ['project_id' => $ids['projects'], 'task_id' => $ids['tasks']]));
        $ids['invoices'] = $this->pluck($this->any('invoices', ['project_id' => $ids['projects']]));
        $ids['transactions'] = $this->pluck($this->any('transactions', ['invoice_id' => $ids['invoices'], 'client_user_id' => $ids['clients']]));
        $ids['proposals'] = $this->pluck($this->any('proposals', ['organization_id' => $o, 'opportunity_id' => $ids['opportunities'], 'project_id' => $ids['projects']]));
        $ids['sows'] = $this->pluck($this->any('statements_of_work', ['opportunity_id' => $ids['opportunities'], 'proposal_id' => $ids['proposals'], 'project_id' => $ids['projects']]));
        $ids['questionnaires'] = $this->pluck($this->any('questionnaires', ['opportunity_id' => $ids['opportunities']]));
        $ids['meetings'] = $this->pluck($this->any('meetings', ['organization_id' => $o, 'opportunity_id' => $ids['opportunities'], 'phase_id' => $ids['phases']]));
        $ids['transcripts'] = $this->pluck($this->any('meeting_transcripts', ['meeting_id' => $ids['meetings']]));
        $ids['drafts'] = $this->pluck($this->any('project_drafts', ['opportunity_id' => $ids['opportunities'], 'sow_id' => $ids['sows'], 'created_project_id' => $ids['projects']]));
        $ids['plans'] = $this->pluck($this->any('milestone_plans', ['project_id' => $ids['projects']]));
        $ids['appointments'] = $this->pluck($this->any('appointments', ['organization_id' => $o,
            'id' => $this->pluck($this->any('meetings', ['id' => $ids['meetings']]), 'appointment_id')]));

        // AI runs: by their subject, and the runs the rows above point to
        $runs = DB::table('ai_runs')->where(function (Builder $w) use ($ids) {
            $w->whereRaw('1 = 0');
            foreach (self::SUBJECTS as $alias => $set) {
                if ($ids[$set]) {
                    $w->orWhere(fn (Builder $s) => $s->whereIn('subject_type', [$alias, (string) \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($alias)])
                        ->whereIn('subject_id', $ids[$set]));
                }
            }
        })->pluck('id')->all();
        foreach ([['proposals', 'proposals', 'ai_run_id'], ['proposals', 'proposals', 'changes_run_id'], ['statements_of_work', 'sows', 'ai_run_id'],
                  ['questionnaires', 'questionnaires', 'ai_run_id'], ['project_drafts', 'drafts', 'ai_run_id'], ['milestone_plans', 'plans', 'ai_run_id']] as [$table, $set, $column]) {
            $runs = [...$runs, ...$this->pluck($this->any($table, ['id' => $ids[$set]]), $column)];
        }
        $runs = [...$runs, ...$this->pluck($this->any('questionnaire_questions', ['questionnaire_id' => $ids['questionnaires']]), 'ai_run_id')];
        $runs = [...$runs, ...$this->pluck($this->any('meeting_points', ['meeting_id' => $ids['meetings']]), 'ai_run_id')];
        $ids['runs'] = array_values(array_unique(array_map('strval', array_filter($runs))));

        // Ledger postings of its invoices and payments, and any reversal of them
        $entries = $this->pluck(DB::table('journal_entries')->where(function (Builder $w) use ($ids) {
            $w->whereRaw('1 = 0');
            if ($ids['invoices']) {
                $w->orWhere(fn (Builder $s) => $s->where('source_module', 'Invoicing')->whereIn('source_id', $ids['invoices']));
            }
            if ($ids['transactions']) {
                $w->orWhere(fn (Builder $s) => $s->where('source_module', 'Receipts')->whereIn('source_id', $ids['transactions']));
            }
        }));
        $ids['entries'] = [...$entries, ...$this->pluck($this->any('journal_entries', ['reverses_entry_id' => $entries]))];

        // Staff bells that link to any of it
        $linked = [...$ids['opportunities'], ...$ids['projects'], ...$ids['proposals'], ...$ids['sows'], ...$ids['meetings'],
            ...$ids['transcripts'], ...$ids['tickets'], ...$ids['invoices'], ...$ids['tasks']];
        $ids['bells'] = $linked ? $this->pluck(DB::table('notifications')->where(function (Builder $w) use ($linked) {
            foreach ($linked as $id) {
                $w->orWhere('link_url', 'like', '%' . $id . '%');
            }
        })) : [];
        $ids['client_bells'] = $this->pluck($this->any('client_notifications', ['client_user_id' => $ids['clients'], 'organization_id' => $o]));
        $ids['sessions'] = $this->clientSessions($ids['clients']);

        return $ids;
    }

    /** [table, query] in the order they are deleted: rows that point at others first */
    private function steps(object $org, array $ids): array
    {
        $o = (string) $org->id;
        $emails = $this->pluck($this->any('client_users', ['id' => $ids['clients']]), 'email');
        $staffEmails = $emails ? DB::table('users')->whereIn('email', $emails)->pluck('email')->all() : [];
        // History lines name their row by id: only uuids are unique across tables (a task comment's 5 is not)
        $everything = array_values(array_filter(array_unique(array_merge(...array_values($ids))), fn ($id) => Str::isUuid($id)));

        return [
            ['journal_lines', $this->any('journal_lines', ['journal_entry_id' => $ids['entries']])],
            ['journal_entries', $this->any('journal_entries', ['id' => $ids['entries']])],
            ['chart_of_accounts', $this->any('chart_of_accounts', ['client_id' => $o])],
            ['push_deliveries', $this->any('push_deliveries', ['notification_id' => [...$ids['bells'], ...$ids['client_bells']]])],
            ['notifications', $this->any('notifications', ['id' => $ids['bells']])],
            ['client_notifications', $this->any('client_notifications', ['id' => $ids['client_bells']])],
            ['push_subscriptions', $this->any('push_subscriptions', ['client_user_id' => $ids['clients']])],
            ['passkeys', $this->any('passkeys', ['owner_id' => $ids['clients']])->where('guard', 'client')],
            ['sign_in_pins', $this->any('sign_in_pins', ['owner_id' => $ids['clients']])->where('guard', 'client')],
            ['password_reset_tokens', $this->any('password_reset_tokens', ['email' => array_values(array_diff($emails, $staffEmails))])],
            ['sessions', $this->any(config('session.table', 'sessions'), ['id' => $ids['sessions']])],
            ['meeting_points', $this->any('meeting_points', ['meeting_id' => $ids['meetings']])],
            ['meeting_attendees', $this->any('meeting_attendees', ['meeting_id' => $ids['meetings']])],
            ['meeting_transcripts', $this->any('meeting_transcripts', ['id' => $ids['transcripts']])],
            ['plan_suggestions', $this->any('plan_suggestions', ['plan_id' => $ids['plans']])],
            ['milestone_plans', $this->any('milestone_plans', ['id' => $ids['plans']])],
            ['qa_comments', $this->any('qa_comments', ['ticket_id' => $ids['tickets']])],
            ['qa_attachments', $this->any('qa_attachments', ['ticket_id' => $ids['tickets']])],
            ['qa_tickets', $this->any('qa_tickets', ['id' => $ids['tickets']])],
            ['task_comments', $this->any('task_comments', ['task_id' => $ids['tasks']])],
            ['task_dependencies', $this->any('task_dependencies', ['task_id' => $ids['tasks'], 'depends_on_task_id' => $ids['tasks']])],
            ['task_people', $this->any('task_people', ['task_id' => $ids['tasks']])],
            ['task_steps', $this->any('task_steps', ['task_id' => $ids['tasks']])],
            ['task_time_logs', $this->any('task_time_logs', ['task_id' => $ids['tasks']])],
            ['time_correction_requests', $this->any('time_correction_requests', ['task_id' => $ids['tasks']])],
            ['tasks', $this->any('tasks', ['id' => $ids['tasks']])],
            ['sprints', $this->any('sprints', ['id' => $ids['sprints']])],
            ['phase_dependencies', $this->any('phase_dependencies', ['phase_id' => $ids['phases'], 'depends_on_phase_id' => $ids['phases']])],
            ['change_reports', $this->any('change_reports', ['project_id' => $ids['projects']])],
            ['invoice_lines', $this->any('invoice_lines', ['invoice_id' => $ids['invoices']])],
            ['transactions', $this->any('transactions', ['id' => $ids['transactions']])],
            ['project_drafts', $this->any('project_drafts', ['id' => $ids['drafts']])],
            ['statements_of_work', $this->any('statements_of_work', ['id' => $ids['sows']])],
            ['invoices', $this->any('invoices', ['id' => $ids['invoices']])],
            ['meetings', $this->any('meetings', ['id' => $ids['meetings']])],
            ['appointments', $this->any('appointments', ['id' => $ids['appointments']])],
            ['project_budgets', $this->any('project_budgets', ['project_id' => $ids['projects']])],
            ['project_magic_links', $this->any('project_magic_links', ['project_id' => $ids['projects']])],
            ['project_signoffs', $this->any('project_signoffs', ['project_id' => $ids['projects']])],
            ['kaizen_case_studies', $this->any('kaizen_case_studies', ['project_id' => $ids['projects']])],
            ['project_phases', $this->any('project_phases', ['id' => $ids['phases']])],
            ['proposal_comments', $this->any('proposal_comments', ['proposal_id' => $ids['proposals']])],
            ['proposal_line_items', $this->any('proposal_line_items', ['proposal_id' => $ids['proposals']])],
            ['proposal_tech_stack', $this->any('proposal_tech_stack', ['proposal_id' => $ids['proposals']])],
            ['proposal_ai_narratives', $this->any('proposal_ai_narratives', ['proposal_id' => $ids['proposals']])],
            ['proposals', $this->any('proposals', ['id' => $ids['proposals']])],
            ['questionnaire_questions', $this->any('questionnaire_questions', ['questionnaire_id' => $ids['questionnaires']])],
            ['questionnaires', $this->any('questionnaires', ['id' => $ids['questionnaires']])],
            ['opportunity_members', $this->any('opportunity_members', ['opportunity_id' => $ids['opportunities']])],
            ['ai_runs', $this->any('ai_runs', ['id' => $ids['runs']]), self::RUN_SCRUB],
            ['projects', $this->any('projects', ['id' => $ids['projects']])],
            ['opportunities', $this->any('opportunities', ['id' => $ids['opportunities']])],
            ['broadcast_logs', $this->any('broadcast_logs', ['user_id' => $ids['clients']])],
            ['redline_sos_requests', $this->any('redline_sos_requests', ['organization_id' => $o, 'user_id' => $ids['clients']])],
            ['crm_notes', $this->any('crm_notes', ['organization_id' => $o])],
            ['copq_assessments', $this->any('copq_assessments', ['organization_id' => $o])],
            ['whitepaper_downloads', $this->any('whitepaper_downloads', ['organization_id' => $o])],
            ['client_portal_settings', $this->any('client_portal_settings', ['organization_id' => $o])],
            ['client_users', $this->any('client_users', ['id' => $ids['clients']])],
            ['organizations', $this->any('organizations', ['id' => $o])],
            // Its history goes with it; one QA_WIPE line says the wipe ran
            ['system_audit_logs', $this->any('system_audit_logs', ['target_id' => $everything])],
        ];
    }

    /** Uploaded files: [disk, path] */
    private function files(array $ids): array
    {
        $files = [];
        $add = function (string $disk, iterable $paths) use (&$files) {
            foreach ($paths as $path) {
                if (is_string($path) && $path !== '' && Storage::disk($disk)->exists($path)) {
                    $files[] = [$disk, $path];
                }
            }
        };
        $add('local', $this->pluck($this->any('qa_attachments', ['ticket_id' => $ids['tickets']]), 'file_path'));
        $add('local', $this->pluck($this->any('qa_tickets', ['id' => $ids['tickets']]), 'screenshot_path'));
        $add('local', $this->pluck($this->any('transactions', ['id' => $ids['transactions']]), 'proof_file_path'));
        $add('local', $this->pluck($this->any('transactions', ['id' => $ids['transactions']]), 'wht_proof_file_path'));
        $add('local', $this->pluck($this->any('task_comments', ['task_id' => $ids['tasks']]), 'attachment_path'));
        foreach ($ids['tasks'] as $taskId) {
            $add('local', Storage::disk('local')->allFiles('task_files/' . $taskId));
        }
        $add('public', $this->pluck($this->any('client_portal_settings', ['organization_id' => $ids['org'][0]]), 'logo_path'));

        return array_values(array_unique($files, SORT_REGULAR));
    }

    /** Copies every row (as JSON) and file to a folder only the account running the command can read */
    private function backup(object $org, array $steps, array $files, $events): string
    {
        $folder = storage_path('app/private/qa-wipes/' . now()->format('Ymd-His') . '-' . Str::slug($org->name));
        File::ensureDirectoryExists($folder, 0700);
        @chmod(dirname($folder), 0700);
        @chmod($folder, 0700);

        $rows = [];
        foreach ($steps as [$table, $query]) {
            $rows[$table] = [...($rows[$table] ?? []), ...(clone $query)->get()->map(fn ($r) => (array) $r)->all()];
        }
        $rows['google_events'] = $events->map(fn ($e) => (array) $e)->all();
        File::put($folder . '/rows.json', json_encode(array_filter($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        @chmod($folder . '/rows.json', 0600);

        foreach ($files as [$disk, $path]) {
            try {
                $target = $folder . '/files/' . $disk . '/' . $path;
                File::ensureDirectoryExists(dirname($target), 0700);
                File::put($target, Storage::disk($disk)->get($path));
            } catch (Throwable $e) {
                $this->warn("Couldn't copy {$disk}:{$path} to the backup ({$e->getMessage()}).");
            }
        }

        return $folder;
    }

    /** Portal sessions signed in as one of these accounts (the client guard keeps its id in the payload) */
    private function clientSessions(array $clientIds): array
    {
        if (! $clientIds || ! Schema::hasTable(config('session.table', 'sessions'))) {
            return [];
        }
        $found = [];
        DB::table(config('session.table', 'sessions'))->orderBy('id')->chunk(200, function ($rows) use ($clientIds, &$found) {
            foreach ($rows as $row) {
                $raw = base64_decode((string) $row->payload, true);
                if ($raw !== false && config('session.encrypt')) {
                    try {
                        $raw = app('encrypter')->decrypt($raw, false);
                    } catch (Throwable) {
                        continue;
                    }
                }
                $data = $raw === false ? null : @unserialize($raw, ['allowed_classes' => false]);
                foreach (is_array($data) ? $data : [] as $key => $value) {
                    if (str_starts_with((string) $key, 'login_client_') && in_array((string) $value, $clientIds, true)) {
                        $found[] = (string) $row->id;
                        break;
                    }
                }
            }
        });

        return $found;
    }

    /** Rows of $table matching any of these column => value(s); none when every list is empty */
    private function any(string $table, array $where): Builder
    {
        return DB::table($table)->where(function (Builder $w) use ($where) {
            $w->whereRaw('1 = 0');
            foreach ($where as $column => $values) {
                $values = array_values(array_unique(array_filter(array_map(fn ($v) => $v === null ? '' : (string) $v, (array) $values), fn ($v) => $v !== '')));
                if ($values) {
                    $w->orWhereIn($column, $values);
                }
            }
        });
    }

    private function pluck(Builder $query, string $column = 'id'): array
    {
        return array_values(array_unique(array_filter((clone $query)->pluck($column)->map(fn ($v) => $v === null ? '' : (string) $v)->all(), fn ($v) => $v !== '')));
    }
}
