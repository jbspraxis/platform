<?php

namespace Tests\Feature;

use App\Livewire\Hq\Opportunities\OpportunityPage;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * qa:wipe (docs/QA-PORTAL.md): a test organisation goes with everything tied to it, after a backup and its Google
 * events are cancelled; a real organisation next to it keeps every row; anything not flagged as a test is refused.
 */
class QaWipeTest extends TestCase
{
    use UsesThrowawayDatabase;

    private string $staffId;

    private int $account;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->staffId = $this->staff('sales', ['sales'])->id;
        $this->account = (int) DB::table('chart_of_accounts')->value('id');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/qa-wipes'));
        parent::tearDown();
    }

    /** A whole engagement, from the first meeting to paid milestone invoices, in the organisation's name */
    private function engagement(bool $test, string $name): array
    {
        $id = fn () => (string) Str::uuid();
        $ids = ['org' => $id()];
        $this->insertRow('organizations', ['id' => $ids['org'], 'name' => $name, 'status' => 'Active Client', 'is_test' => $test ? 1 : 0]);
        $ids['admin'] = $id();
        $ids['member'] = $id();
        foreach (['admin', 'member'] as $who) {
            $this->insertRow('client_users', ['id' => $ids[$who], 'organization_id' => $ids['org'], 'first_name' => ucfirst($who), 'last_name' => $name,
                'email' => $who . '.' . Str::random(6) . '@client.test', 'is_primary_client' => $who === 'admin' ? 1 : 0]);
        }
        $ids['opportunity'] = $id();
        $this->insertRow('opportunities', ['id' => $ids['opportunity'], 'organization_id' => $ids['org'], 'title' => 'Fleet portal', 'stage' => 'won', 'lead_user_id' => $this->staffId]);
        $this->insertRow('opportunity_members', ['opportunity_id' => $ids['opportunity'], 'user_id' => $this->staffId]);

        $ids['run'] = $id();
        $this->insertRow('ai_runs', ['id' => $ids['run'], 'purpose' => 'questionnaire', 'subject_type' => 'opportunity', 'subject_id' => $ids['opportunity'], 'status' => 'done']);
        $ids['questionnaire'] = $id();
        $this->insertRow('questionnaires', ['id' => $ids['questionnaire'], 'opportunity_id' => $ids['opportunity'], 'ai_run_id' => $ids['run']]);
        $this->insertRow('questionnaire_questions', ['id' => $id(), 'questionnaire_id' => $ids['questionnaire'], 'question' => 'Which fleet?']);

        $ids['appointment'] = $id();
        $this->insertRow('appointments', ['id' => $ids['appointment'], 'organization_id' => $ids['org'], 'title' => 'Kick-off']);
        $ids['meeting'] = $id();
        $ids['event'] = 'evt' . Str::random(10);
        $this->insertRow('meetings', ['id' => $ids['meeting'], 'opportunity_id' => $ids['opportunity'], 'organization_id' => $ids['org'], 'kind' => 'client',
            'title' => 'Kick-off', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'status' => 'scheduled',
            'appointment_id' => $ids['appointment'], 'google_event_id' => $ids['event']]);
        $this->insertRow('meeting_attendees', ['meeting_id' => $ids['meeting'], 'client_user_id' => $ids['admin'], 'email' => 'admin@client.test']);
        $ids['transcript'] = $id();
        $this->insertRow('meeting_transcripts', ['id' => $ids['transcript'], 'meeting_id' => $ids['meeting'], 'text' => 'We need tracking.']);
        $ids['notes_run'] = $id();
        $this->insertRow('ai_runs', ['id' => $ids['notes_run'], 'purpose' => 'meeting_notes', 'subject_type' => 'meeting_transcript', 'subject_id' => $ids['transcript'], 'status' => 'done']);

        $ids['proposal'] = $id();
        $this->insertRow('proposals', ['id' => $ids['proposal'], 'organization_id' => $ids['org'], 'opportunity_id' => $ids['opportunity'], 'proposal_ref' => 'P-' . Str::random(6), 'title' => 'Fleet portal']);
        $this->insertRow('proposal_comments', ['id' => $id(), 'proposal_id' => $ids['proposal'], 'section_key' => 'scope', 'body' => 'Add drivers.']);
        $this->insertRow('proposal_line_items', ['proposal_id' => $ids['proposal']]);

        $ids['project'] = $id();
        $this->insertRow('projects', ['id' => $ids['project'], 'organization_id' => $ids['org'], 'opportunity_id' => $ids['opportunity'], 'lead_user_id' => $this->staffId, 'title' => 'Fleet portal']);
        DB::table('opportunities')->where('id', $ids['opportunity'])->update(['project_id' => $ids['project']]);
        $ids['phase'] = $id();
        $this->insertRow('project_phases', ['id' => $ids['phase'], 'project_id' => $ids['project'], 'phase_number' => 1, 'title' => 'M1']);
        $ids['task'] = $id();
        $this->insertRow('tasks', ['id' => $ids['task'], 'phase_id' => $ids['phase'], 'title' => 'Map view', 'is_client_visible' => 1]);
        $this->insertRow('task_people', ['task_id' => $ids['task'], 'user_id' => $this->staffId]);
        Storage::disk('local')->put("task_files/{$ids['task']}/spec.pdf", 'spec');
        $this->insertRow('task_comments', ['task_id' => $ids['task'], 'user_id' => $ids['admin'], 'author_type' => 'client', 'comment_text' => 'Looks good',
            'attachment_path' => "task_files/{$ids['task']}/spec.pdf"]);
        $ids['ticket'] = $id();
        $this->insertRow('qa_tickets', ['id' => $ids['ticket'], 'project_id' => $ids['project'], 'task_id' => $ids['task'], 'submitted_by' => $ids['admin'], 'title' => 'Map blank']);
        $this->insertRow('qa_comments', ['id' => $id(), 'ticket_id' => $ids['ticket'], 'user_id' => $ids['admin'], 'author_type' => 'client', 'comment_text' => 'Still blank']);
        Storage::disk('local')->put("qa_evidence/{$ids['ticket']}.png", 'png');
        $this->insertRow('qa_attachments', ['id' => $id(), 'ticket_id' => $ids['ticket'], 'uploaded_by' => $ids['admin'], 'uploader_type' => 'client', 'file_path' => "qa_evidence/{$ids['ticket']}.png"]);
        $this->insertRow('meeting_points', ['meeting_id' => $ids['meeting'], 'qa_ticket_id' => $ids['ticket'], 'task_id' => $ids['task'], 'title' => 'Map blank']);

        $ids['plan'] = $id();
        $this->insertRow('milestone_plans', ['id' => $ids['plan'], 'project_id' => $ids['project'], 'phase_id' => $ids['phase'], 'transcript_meeting_id' => $ids['meeting']]);
        $this->insertRow('plan_suggestions', ['plan_id' => $ids['plan'], 'phase_id' => $ids['phase'], 'title' => 'Driver app']);
        $this->insertRow('change_reports', ['id' => $id(), 'project_id' => $ids['project'], 'number' => 1, 'title' => 'CR-01']);

        $ids['deposit'] = $id();
        $ids['milestone_invoice'] = $id();
        foreach (['deposit', 'milestone_invoice'] as $which) {
            $this->insertRow('invoices', ['id' => $ids[$which], 'project_id' => $ids['project'], 'phase_id' => $which === 'deposit' ? null : $ids['phase'],
                'invoice_number' => 'INV-' . Str::random(8), 'total_amount' => 1000, 'status' => 'Paid']);
            $this->insertRow('invoice_lines', ['invoice_id' => $ids[$which], 'description' => 'Work', 'total_price' => 1000]);
        }
        $ids['sow'] = $id();
        $this->insertRow('statements_of_work', ['id' => $ids['sow'], 'opportunity_id' => $ids['opportunity'], 'proposal_id' => $ids['proposal'], 'reference' => 'SOW-' . Str::random(6),
            'project_id' => $ids['project'], 'deposit_invoice_id' => $ids['deposit'], 'status' => 'signed']);
        $this->insertRow('project_drafts', ['id' => $id(), 'opportunity_id' => $ids['opportunity'], 'sow_id' => $ids['sow'], 'created_project_id' => $ids['project']]);
        $ids['payment'] = $id();
        Storage::disk('local')->put("pop_receipts/{$ids['payment']}.pdf", 'pop');
        $this->insertRow('transactions', ['id' => $ids['payment'], 'invoice_id' => $ids['milestone_invoice'], 'client_user_id' => $ids['admin'], 'amount_claimed' => 1000,
            'proof_file_path' => "pop_receipts/{$ids['payment']}.pdf"]);
        foreach ([['Invoicing', $ids['milestone_invoice']], ['Receipts', $ids['payment']]] as [$module, $source]) {
            $entry = $id();
            $this->insertRow('journal_entries', ['id' => $entry, 'reference_number' => 'JRN-' . Str::random(10), 'entry_date' => now()->toDateString(),
                'description' => $name, 'source_module' => $module, 'source_id' => $source, 'region_code' => 'NG']);
            $this->insertRow('journal_lines', ['journal_entry_id' => $entry, 'account_id' => $this->account, 'debit_amount' => 1000, 'credit_amount' => 0]);
            $ids['journal'][] = $entry;
        }

        $ids['bell'] = $id();
        $this->insertRow('notifications', ['id' => $ids['bell'], 'user_id' => $this->staffId, 'title' => 'Proposal approved', 'link_url' => "https://hq.test/opportunities/{$ids['opportunity']}?tab=proposals"]);
        $ids['client_bell'] = $id();
        $this->insertRow('client_notifications', ['id' => $ids['client_bell'], 'client_user_id' => $ids['admin'], 'organization_id' => $ids['org'], 'kind' => 'proposal']);
        foreach (['bell', 'client_bell'] as $which) {
            $this->insertRow('push_deliveries', ['notification_id' => $ids[$which], 'status' => 'sent']);
        }
        $this->insertRow('push_subscriptions', ['client_user_id' => $ids['admin'], 'endpoint' => 'https://push.test/' . Str::random(8), 'endpoint_hash' => Str::random(40)]);
        $this->insertRow('passkeys', ['guard' => 'client', 'owner_id' => $ids['admin'], 'credential_id' => Str::random(30)]);
        $this->insertRow('sign_in_pins', ['guard' => 'client', 'owner_id' => $ids['admin']]);
        $ids['session'] = Str::random(40);
        DB::table('sessions')->insert(['id' => $ids['session'], 'payload' => base64_encode(serialize(['_token' => 'x', 'login_client_' . sha1('Illuminate\Auth\SessionGuard') => $ids['admin']])),
            'last_activity' => time()]);
        $this->insertRow('client_portal_settings', ['organization_id' => $ids['org'], 'logo_path' => "client_logos/{$ids['org']}.png"]);
        Storage::disk('public')->put("client_logos/{$ids['org']}.png", 'logo');
        $this->insertRow('crm_notes', ['id' => $id(), 'organization_id' => $ids['org'], 'author_id' => $this->staffId, 'note_text' => 'Called']);
        $this->insertRow('system_audit_logs', ['user_id' => $this->staffId, 'action' => 'PROPOSAL_SENT', 'target_table' => 'opportunities', 'target_id' => $ids['opportunity']]);

        return $ids;
    }

    /** Every uuid column of every table, checked for these ids: what the wipe must have left nowhere */
    private function leftovers(array $ids): array
    {
        $found = [];
        $columns = DB::select("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.columns WHERE table_schema = DATABASE() AND COLUMN_TYPE = 'char(36)'");
        foreach ($columns as $col) {
            $query = DB::table($col->t)->whereIn($col->c, $ids);
            if ($col->t === 'system_audit_logs') {
                $query->where('action', '!=', 'QA_WIPE');
            }
            if ($n = $query->count()) {
                $found[] = "{$col->t}.{$col->c}: {$n}";
            }
        }

        return $found;
    }

    private function uuids(array $ids): array
    {
        return array_values(array_filter(array_merge(...array_map(fn ($v) => (array) $v, array_values($ids))), fn ($v) => is_string($v) && Str::isUuid($v)));
    }

    public function test_without_force_it_only_shows_what_it_would_delete(): void
    {
        $test = $this->engagement(true, 'QA Test Client (delete)');
        $before = DB::table('tasks')->count();

        $this->artisan('qa:wipe', ['organisation' => 'qa test client (delete)'])
            ->expectsOutputToContain('Would delete QA Test Client (delete)')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();

        $this->assertSame($before, DB::table('tasks')->count());
        $this->assertDatabaseHas('organizations', ['id' => $test['org']]);
        $this->assertDirectoryDoesNotExist(storage_path('app/private/qa-wipes'));
    }

    public function test_it_refuses_an_organisation_not_flagged_as_a_test(): void
    {
        $real = $this->engagement(false, 'Real Client Ltd');

        $this->artisan('qa:wipe', ['organisation' => 'Real Client Ltd', '--force' => true])
            ->expectsOutputToContain("isn't flagged as a test organisation")
            ->assertFailed();
        $this->artisan('qa:wipe', ['organisation' => $real['org'], '--force' => true])->assertFailed();

        $this->assertDatabaseHas('organizations', ['id' => $real['org']]);
        $this->assertDatabaseHas('tasks', ['id' => $real['task']]);
    }

    public function test_two_organisations_with_the_name_must_be_told_apart_by_id(): void
    {
        $this->engagement(true, 'QA Twin');
        $this->engagement(true, 'QA Twin');

        $this->artisan('qa:wipe', ['organisation' => 'QA Twin', '--force' => true])
            ->expectsOutputToContain('Several organisations are called')
            ->assertFailed();
        $this->assertSame(2, DB::table('organizations')->where('name', 'QA Twin')->count());
    }

    public function test_force_backs_up_cancels_google_and_deletes_everything_tied_to_the_test_client_only(): void
    {
        $google = FakeGoogle::install();
        $test = $this->engagement(true, 'QA Test Client (delete)');
        $real = $this->engagement(false, 'Real Client Ltd');
        $realRows = collect(DB::select("SELECT TABLE_NAME t FROM information_schema.tables WHERE table_schema = DATABASE()"))
            ->mapWithKeys(fn ($r) => [$r->t => DB::table($r->t)->count()]);

        $this->artisan('qa:wipe', ['organisation' => 'QA Test Client (delete)', '--force' => true])
            ->expectsOutputToContain('Backed up to')
            ->assertSuccessful();

        // Nothing of the test client is left anywhere, and its Google event is cancelled
        $this->assertSame([], $this->leftovers($this->uuids($test)));
        $this->assertSame([$test['event']], $google->cancelled);
        $this->assertDatabaseMissing('sessions', ['id' => $test['session']]);
        $this->assertDatabaseMissing('journal_entries', ['id' => $test['journal'][0]]);
        $this->assertDatabaseMissing('journal_entries', ['id' => $test['journal'][1]]);
        Storage::disk('local')->assertMissing("task_files/{$test['task']}/spec.pdf");
        Storage::disk('local')->assertMissing("qa_evidence/{$test['ticket']}.png");
        Storage::disk('local')->assertMissing("pop_receipts/{$test['payment']}.pdf");
        Storage::disk('public')->assertMissing("client_logos/{$test['org']}.png");

        // The real client keeps every row and file
        foreach (['organizations' => 'org', 'client_users' => 'admin', 'opportunities' => 'opportunity', 'meetings' => 'meeting', 'proposals' => 'proposal',
                  'statements_of_work' => 'sow', 'projects' => 'project', 'tasks' => 'task', 'qa_tickets' => 'ticket', 'invoices' => 'milestone_invoice',
                  'transactions' => 'payment', 'ai_runs' => 'run', 'notifications' => 'bell', 'client_notifications' => 'client_bell', 'sessions' => 'session'] as $table => $key) {
            $this->assertDatabaseHas($table, ['id' => $real[$key]]);
        }
        $this->assertDatabaseHas('journal_entries', ['id' => $real['journal'][0]]);
        $this->assertSame(1, DB::table('system_audit_logs')->where('target_id', $real['opportunity'])->count());
        Storage::disk('local')->assertExists("qa_evidence/{$real['ticket']}.png");
        Storage::disk('public')->assertExists("client_logos/{$real['org']}.png");

        // One history line says the wipe ran; the backup holds the rows
        $line = DB::table('system_audit_logs')->where('action', 'QA_WIPE')->sole();
        $this->assertSame($test['org'], $line->target_id);
        $folder = json_decode($line->new_values, true)['backup'];
        $rows = json_decode(File::get($folder . '/rows.json'), true);
        $this->assertSame($test['org'], $rows['organizations'][0]['id']);
        $this->assertCount(2, $rows['client_users']);
        $this->assertSame($test['event'], $rows['google_events'][0]['google_event_id']);
        $this->assertFileExists($folder . "/files/local/qa_evidence/{$test['ticket']}.png");

        // Only the test client's rows went: one of each it had, two people, and its history line for the QA_WIPE one
        $gone = $realRows->map(fn ($n, $t) => $n - DB::table($t)->count())->filter();
        $this->assertSame(1, $gone['organizations']);
        $this->assertSame(2, $gone['client_users']);
        $this->assertSame(2, $gone['invoices']);
        $this->assertArrayNotHasKey('system_audit_logs', $gone->all());
    }

    public function test_only_the_owner_flags_an_organisation_as_a_test_client(): void
    {
        $org = $this->engagement(false, 'QA Test Client (delete)');
        $opportunity = Opportunity::findOrFail($org['opportunity']);

        // The opportunity's lead may edit its details, but the checkbox isn't theirs to set
        $this->actingAs(User::findOrFail($this->staffId));
        Livewire::test(OpportunityPage::class, ['opportunity' => $opportunity])->call('edit')->assertDontSee('Only the owner can set this')
            ->set('testClient', true)->call('saveDetails')->assertHasNoErrors();
        $this->assertSame(0, (int) DB::table('organizations')->where('id', $org['org'])->value('is_test'));

        $this->actingAs($this->staff('owner', ['owner']));
        Livewire::test(OpportunityPage::class, ['opportunity' => $opportunity])->call('edit')->assertSee('Only the owner can set this')
            ->set('testClient', true)->call('saveDetails')->assertHasNoErrors();
        $this->assertSame(1, (int) DB::table('organizations')->where('id', $org['org'])->value('is_test'));
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'ORGANISATION_TEST_FLAG_CHANGED', 'target_id' => $org['opportunity']]);

        $this->artisan('qa:wipe', ['organisation' => 'QA Test Client (delete)'])->expectsOutputToContain('Would delete')->assertSuccessful();
    }

    public function test_force_waits_for_running_ai_and_needs_google_or_skip_google(): void
    {
        FakeGoogle::install()->disconnected();
        $test = $this->engagement(true, 'QA Test Client (delete)');

        $this->artisan('qa:wipe', ['organisation' => $test['org'], '--force' => true])
            ->expectsOutputToContain("Google isn't connected")
            ->assertFailed();
        $this->assertDatabaseHas('organizations', ['id' => $test['org']]);

        DB::table('ai_runs')->where('id', $test['run'])->update(['status' => 'running']);
        $this->artisan('qa:wipe', ['organisation' => $test['org'], '--force' => true, '--skip-google' => true])
            ->expectsOutputToContain('still queued or running')
            ->assertFailed();
        $this->assertDatabaseHas('organizations', ['id' => $test['org']]);

        DB::table('ai_runs')->where('id', $test['run'])->update(['status' => 'done']);
        $this->artisan('qa:wipe', ['organisation' => $test['org'], '--force' => true, '--skip-google' => true])->assertSuccessful();
        $this->assertDatabaseMissing('organizations', ['id' => $test['org']]);
    }
}
