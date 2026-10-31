<?php

namespace Tests\Feature\Opportunities;

use App\Support\Google\Transcript;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * opportunities:import (docs/OPPORTUNITIES.md 12): an engagement that began before the pipeline comes in whole,
 * with its Google meetings, without emailing or notifying anyone; existing records stop it before any write.
 */
class ImportEngagementTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private function bundle(string $leadEmail, array $changes = []): string
    {
        $bundle = array_replace([
            'organization' => ['name' => 'Northwind Ltd', 'industry' => 'Technology', 'city' => 'Lagos'],
            'opportunity' => ['title' => 'Northwind ERP', 'lead_email' => $leadEmail, 'language' => 'en', 'won_at' => '2026-10-02'],
            'sow' => ['reference' => 'SOW/NW/1', 'signed_at' => '2026-10-02', 'signed_by_name' => 'Client CEO', 'hq_signer_name' => 'Managing Partner',
                'sections' => [['key' => 'scope', 'title' => 'Scope of work', 'body' => 'Two workstreams.']]],
            'project' => ['title' => 'Northwind ERP', 'total_budget' => 1000000, 'currency' => 'NGN', 'start_date' => '2026-10-03', 'target_end_date' => '2026-12-18', 'status' => 'Active'],
            'phases' => [
                ['key' => 'M1', 'number' => 1, 'title' => 'Core operations', 'weight' => 60, 'status' => 'Completed'],
                ['key' => 'M2', 'number' => 2, 'title' => 'Payroll', 'weight' => 40, 'status' => 'Pending'],
            ],
            'sprints' => [
                ['key' => 'M1', 'title' => 'M1 · Core operations', 'start' => '2026-10-05', 'end' => '2026-10-09', 'status' => 'Completed'],
                ['key' => 'M2', 'title' => 'M2 · Payroll', 'start' => '2026-11-02', 'end' => '2026-11-06', 'status' => 'Planned'],
            ],
            'tasks' => [
                ['phase' => 'M1', 'sprint' => 'M1', 'title' => 'Dashboard', 'status' => 'Done'],
                ['phase' => 'M1', 'sprint' => 'M1', 'title' => 'Clock-in', 'status' => 'Done'],
                ['phase' => 'M2', 'sprint' => 'M2', 'title' => 'Payslips', 'status' => 'To-Do'],
            ],
            'invoices' => [
                ['number' => 'INV/NW/001', 'type' => 'Deposit', 'reference' => 'SOW/NW/1', 'status' => 'Paid', 'issued_at' => '2026-10-02', 'due_date' => '2026-10-09',
                    'paid_at' => '2026-10-03', 'deposit' => true, 'lines' => [['description' => 'Deposit', 'amount' => 200000]]],
                ['number' => 'INV/NW/002', 'phase' => 'M1', 'reference' => 'SOW/NW/1', 'status' => 'Pending', 'issued_at' => '2026-10-22', 'due_date' => '2026-11-02',
                    'lines' => [['description' => 'Milestone 1', 'amount' => 480000]]],
            ],
            'qa_tickets' => [
                ['title' => 'Dark theme too green', 'description' => 'Four dark themes added.', 'status' => 'Closed', 'raised_at' => '2026-10-21 12:30', 'resolved_at' => '2026-10-22'],
                ['title' => 'Server access', 'description' => 'At handover.', 'status' => 'Open', 'raised_at' => '2026-10-21 12:40'],
            ],
            'meetings' => [
                ['kind' => 'client', 'title' => 'Discovery call', 'starts_at' => '2026-09-02T12:30:00+01:00', 'ends_at' => '2026-09-02T13:30:00+01:00',
                    'attendees' => [['name' => 'Lead', 'email' => $leadEmail], ['name' => 'Client CEO', 'email' => 'ceo@northwind.test']],
                    'transcript' => ['source' => 'upload', 'text' => 'Summary: they want one system.', 'language' => 'en']],
                ['kind' => 'client', 'title' => 'Northwind M1 handover', 'starts_at' => '2026-10-21T12:00:00+01:00', 'ends_at' => '2026-10-21T14:00:00+01:00',
                    'transcript' => ['source' => 'upload', 'text' => 'Ten points raised.']],
            ],
            'google' => ['from' => '2026-10-15T00:00:00+01:00', 'to' => '2026-11-01T00:00:00+01:00', 'match' => ['northwind'], 'exclude' => ['dinner']],
        ], $changes);
        $path = tempnam(sys_get_temp_dir(), 'bundle');
        file_put_contents($path, json_encode($bundle));

        return $path;
    }

    private function google(): FakeGoogle
    {
        return FakeGoogle::install()->events([
            // The bundle already has this one (same start): its own transcript is kept
            ['id' => 'ev-m1', 'title' => 'Northwind M1 handover', 'start' => '2026-10-21 12:05', 'end' => '2026-10-21 14:00'],
            ['id' => 'ev-m2', 'title' => 'Northwind: Milestone 2 Handover', 'start' => '2026-10-28 14:00', 'end' => '2026-10-28 15:30', 'meet_code' => 'abc-defg-hij',
                'attendees' => [['email' => 'ceo@northwind.test', 'name' => 'Client CEO', 'response' => 'accepted']]],
            ['id' => 'ev-internal', 'title' => 'Internal meeting - Northwind workspace', 'start' => '2026-10-26 10:00', 'end' => '2026-10-26 11:00', 'docs' => 0],
            ['id' => 'ev-dinner', 'title' => 'Northwind team dinner', 'start' => '2026-10-30 19:00', 'end' => '2026-10-30 21:00'],
            ['id' => 'ev-other', 'title' => 'Another client call', 'start' => '2026-10-27 09:00', 'end' => '2026-10-27 10:00'],
        ])->transcript('calendar_doc', 'ev-m2', new Transcript('calendar_doc', 'Notes by Gemini: M2 accepted with two fixes.', null, 'en', 'doc-123'));
    }

    public function test_an_engagement_comes_in_whole_without_emailing_or_notifying_anyone(): void
    {
        Mail::fake();
        Notification::fake();
        $lead = $this->staff('owner', ['owner']);
        $this->google();

        $this->artisan('opportunities:import', ['bundle' => $this->bundle($lead->email)])->assertSuccessful();

        $org = DB::table('organizations')->where('name', 'Northwind Ltd')->first();
        $this->assertSame('Active Client', $org->status);
        $opportunity = DB::table('opportunities')->where('organization_id', $org->id)->first();
        $this->assertSame(['won', $lead->id], [$opportunity->stage, $opportunity->lead_user_id]);
        $project = DB::table('projects')->where('id', $opportunity->project_id)->first();
        $this->assertSame(['Northwind ERP', 'Active', 'NGN'], [$project->title, $project->status, $project->currency]);

        $this->assertSame(2, DB::table('project_phases')->where('project_id', $project->id)->count());
        $this->assertSame(2, DB::table('sprints')->where('project_id', $project->id)->count());
        $tasks = DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')->where('project_phases.project_id', $project->id)
            ->whereNotNull('tasks.sprint_id')->pluck('tasks.status', 'tasks.title')->all();
        $this->assertSame(['Clock-in' => 'Done', 'Dashboard' => 'Done', 'Payslips' => 'To-Do'], collect($tasks)->sortKeys()->all());

        $invoices = DB::table('invoices')->where('project_id', $project->id)->orderBy('invoice_number')->get();
        $this->assertSame(['Paid', 'Pending'], $invoices->pluck('status')->all());
        $this->assertEquals([200000, 480000], $invoices->pluck('total_amount')->map(fn ($v) => (float) $v)->all());
        $sow = DB::table('statements_of_work')->where('opportunity_id', $opportunity->id)->first();
        $this->assertSame(['signed', $invoices[0]->id], [$sow->status, $sow->deposit_invoice_id]);

        $tickets = DB::table('qa_tickets')->where('project_id', $project->id)->orderBy('created_at')->get();
        $this->assertSame(['Closed', 'Open'], $tickets->pluck('status')->all());
        $this->assertSame('2026-10-21 12:30:00', (string) $tickets[0]->created_at, 'when the point was raised');

        // Two meetings from the bundle and two from Google: the M1 event duplicates the bundle's, the dinner is
        // excluded, and the other client's call does not match
        $meetings = DB::table('meetings')->where('opportunity_id', $opportunity->id)->orderBy('starts_at')->get();
        $this->assertSame(['Discovery call', 'Northwind M1 handover', 'Internal meeting - Northwind workspace', 'Northwind: Milestone 2 Handover'], $meetings->pluck('title')->all());
        $this->assertSame(['client', 'client', 'internal', 'client'], $meetings->pluck('kind')->all());
        $this->assertSame('2026-09-02 11:30:00', (string) $meetings[0]->starts_at, 'stored in UTC');
        $this->assertSame(['ready', 'ready', 'manual', 'ready'], $meetings->pluck('transcript_status')->all());
        $m2 = DB::table('meeting_transcripts')->where('meeting_id', $meetings[3]->id)->first();
        $this->assertSame(['calendar_doc', 'doc-123'], [$m2->source, $m2->google_doc_id]);
        $this->assertSame('abc-defg-hij', $meetings[3]->meet_code);
        $attendees = DB::table('meeting_attendees')->where('meeting_id', $meetings[0]->id)->pluck('role', 'email')->all();
        $this->assertEquals(['ceo@northwind.test' => 'client', strtolower($lead->email) => 'staff'], $attendees, 'emails are stored in lower case');

        Mail::assertNothingSent();
        Notification::assertNothingSent();
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'import', 'target_id' => $opportunity->id]);
    }

    public function test_a_dry_run_lists_the_google_meetings_and_writes_nothing(): void
    {
        $lead = $this->staff('owner', ['owner']);
        $this->google();

        $this->artisan('opportunities:import', ['bundle' => $this->bundle($lead->email), '--dry-run' => true])
            ->expectsOutputToContain('Northwind: Milestone 2 Handover')
            ->doesntExpectOutputToContain('team dinner')
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('organizations', ['name' => 'Northwind Ltd']);
        $this->assertSame(0, DB::table('meetings')->count());
    }

    public function test_an_existing_organisation_or_invoice_number_stops_it_before_any_write(): void
    {
        $lead = $this->staff('owner', ['owner']);
        $this->google();
        $this->organisation(['name' => 'Northwind Ltd']);

        $this->artisan('opportunities:import', ['bundle' => $this->bundle($lead->email)])
            ->expectsOutputToContain('Northwind Ltd is already in HQ')->assertFailed();

        $bundle = $this->bundle($lead->email, ['organization' => ['name' => 'Southwind Ltd']]);
        $projectId = (string) Str::uuid();
        DB::table('projects')->insert(['id' => $projectId, 'organization_id' => DB::table('organizations')->value('id'), 'title' => 'Other', 'total_budget' => 0]);
        DB::table('invoices')->insert(['id' => (string) Str::uuid(), 'project_id' => $projectId, 'invoice_number' => 'INV/NW/002',
            'amount_due' => 1, 'vat_amount' => 0, 'total_amount' => 1, 'currency' => 'NGN', 'due_date' => '2026-11-01']);

        $this->artisan('opportunities:import', ['bundle' => $bundle])
            ->expectsOutputToContain('These invoice numbers are already used: INV/NW/002')->assertFailed();
        $this->assertDatabaseMissing('organizations', ['name' => 'Southwind Ltd']);
        $this->assertSame(0, DB::table('opportunities')->count());
    }
}
