<?php

namespace Tests\Feature\Dashboards;

use App\Livewire\Hq\Dashboards\AuditTerminal;
use App\Livewire\Hq\Dashboards\GlobalTelemetry;
use App\Livewire\Hq\Dashboards\MyWorkspace;
use App\Support\Hosts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The overviews after the performance pass (11 Nov 2026): My workspace, Company overview and the Audit log keep no
 * rows in the page's state (they read what they show when the page draws), the chart comes from our own build, and
 * the lists show only live records with the same figures as before.
 */
class OverviewsSpeedTest extends TestCase
{
    use ProjectFixtures, UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'UTC'));
    }

    public function test_my_workspace_lists_my_open_tasks_but_not_deleted_ones_and_keeps_no_rows_in_its_state(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $phase = $this->phase($this->project(['title' => 'Payroll system (test)']), 1, 100);
        $this->task($phase, ['title' => 'Write the payslip export', 'assigned_to' => $me->id, 'description' => 'LONG-DESCRIPTION ' . str_repeat('x', 3000)]);
        $this->task($phase, ['title' => 'Removed duplicate task', 'assigned_to' => $me->id, 'deleted_at' => now()]);

        $page = Livewire::actingAs($me)->test(MyWorkspace::class)
            ->assertSee('Write the payslip export')->assertSee('Payroll system (test)')
            ->assertDontSee('Removed duplicate task')
            ->assertSeeInOrder(['My open tasks', '1', 'Hours logged this week']);

        // Nothing but Livewire's own keys in the snapshot: no task rows, no descriptions
        $this->assertSame([], $page->snapshot['data']);
        $this->assertStringNotContainsString('LONG-DESCRIPTION', json_encode($page->snapshot));
    }

    public function test_my_workspace_timer_starts_stops_and_counts_a_timer_started_elsewhere(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $phase = $this->phase($this->project(), 1, 100);
        $first = $this->task($phase, ['title' => 'First task', 'assigned_to' => $me->id]);
        $second = $this->task($phase, ['title' => 'Second task', 'assigned_to' => $me->id]);

        $page = Livewire::actingAs($me)->test(MyWorkspace::class)->call('startTimer', $first)->assertSee('Logging time for');
        $this->assertSame(1, DB::table('task_time_logs')->where('user_id', $me->id)->whereNull('end_time')->count());
        $this->assertSame('In-Progress', DB::table('tasks')->where('id', $first)->value('status'));

        // A second timer is refused, read from the database rather than from what the page showed
        $stale = Livewire::actingAs($me)->test(MyWorkspace::class);
        DB::table('task_time_logs')->where('user_id', $me->id)->delete();
        DB::table('task_time_logs')->insert(['task_id' => $first, 'user_id' => $me->id, 'start_time' => now()->subMinutes(30), 'created_at' => now()]);
        $stale->call('startTimer', $second)->assertDispatched('praxis-toast', $this->toast('A timer is already running.'));
        $this->assertSame(1, DB::table('task_time_logs')->where('user_id', $me->id)->count());

        // Stop stops the running timer, with its minutes
        $page->call('stopTimer')->assertSee('Not running');
        $log = DB::table('task_time_logs')->where('user_id', $me->id)->sole();
        $this->assertNotNull($log->end_time);
        $this->assertSame(30, (int) $log->duration_minutes);
    }

    public function test_company_overview_draws_its_chart_from_our_own_build_and_keeps_no_figures_in_its_state(): void
    {
        $owner = $this->staff('own', ['owner']);
        $project = $this->project();
        $paid = $this->invoice($project, null, 'Paid', 250000);
        DB::table('invoices')->where('id', $paid)->update(['paid_at' => '2026-03-15 10:00:00', 'currency' => 'NGN']);
        // Paid last year: not in this year's chart
        $old = $this->invoice($project, null, 'Paid', 99000);
        DB::table('invoices')->where('id', $old)->update(['paid_at' => '2025-12-31 23:59:59', 'currency' => 'NGN']);

        $html = $this->actingAs($owner)->get('https://' . Hosts::hq() . '/dashboard/admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html, 'No third-party Chart.js');
        $this->assertStringNotContainsString('livewire:navigated', $html, 'No listener left behind on each visit');
        $this->assertStringContainsString(basename(Vite::asset('resources/js/charts.js')), $html, 'Chart.js from our build, fetched by this page');

        $page = Livewire::actingAs($owner)->test(GlobalTelemetry::class);
        $this->assertSame([], $page->snapshot['data']);
        // March has the 2026 payment only; the chart's data is written into the page's script
        $this->assertStringContainsString('NGN', json_encode($page->effects['scripts'] ?? []));
        $this->assertStringContainsString('250000', json_encode($page->effects['scripts'] ?? []));
        $this->assertStringNotContainsString('99000', json_encode($page->effects['scripts'] ?? []));

        // The CSV has the figures as they are now; the page isn't drawn again for it
        $page->call('exportTelemetry')->assertFileDownloaded();
        $csv = base64_decode($page->effects['download']['content']);
        $this->assertStringContainsString('"Paid invoices, total",NGN,349000', $csv);
        $this->assertStringContainsString('"Active projects",,1', $csv);
        $this->assertArrayNotHasKey('html', $page->effects);
    }

    public function test_the_audit_log_counts_this_years_events_and_keeps_no_recorded_values_in_its_state(): void
    {
        $owner = $this->staff('own', ['owner']);
        foreach (['2025-12-31 23:59:59', '2026-01-01 00:00:00', '2026-11-11 09:00:00'] as $at) {
            DB::table('system_audit_logs')->insert(['user_id' => $owner->id, 'action' => 'TASK_MOVED', 'target_table' => 'tasks', 'target_id' => (string) \Illuminate\Support\Str::uuid(),
                'old_values' => json_encode(['description' => 'SECRET-OLD-VALUE']), 'new_values' => json_encode(['status' => 'Done']), 'created_at' => $at]);
        }
        // The same number YEAR(created_at) gave before
        $expected = DB::table('system_audit_logs')->whereYear('created_at', 2026)->count();

        $page = Livewire::actingAs($owner)->test(AuditTerminal::class)
            ->assertSeeInOrder(['Logged events, 2026', number_format($expected)])
            ->assertSee('Task moved');
        $this->assertSame([], $page->snapshot['data']);
        $this->assertStringNotContainsString('SECRET-OLD-VALUE', json_encode($page->snapshot) . $page->html());

        $page->call('exportBooks')->assertFileDownloaded();
        $this->assertArrayNotHasKey('html', $page->effects);
    }

    public function test_the_lookups_have_their_indexes(): void
    {
        foreach ([
            'system_audit_logs' => ['system_audit_logs_target_index', 'system_audit_logs_created_at_index'],
            'appointments' => ['appointments_start_time_index'],
            'tasks' => ['tasks_status_updated_index'],
            'task_time_logs' => ['task_time_logs_end_start_index', 'task_time_logs_user_start_index'],
            'tracker_messages' => ['tracker_messages_tracker_created_index'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertTrue(Schema::hasIndex($table, $index), "{$table}.{$index}");
            }
        }
    }
}
