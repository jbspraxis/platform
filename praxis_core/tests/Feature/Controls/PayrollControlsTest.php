<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Finance\PayrollManager;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Payroll: three people per run (one prepares, another checks, a third approves), only the next status in
 * the chain, nobody marks their own run as paid, one run per month and country, and every step in the audit log.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class PayrollControlsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private User $maker;
    private User $checker;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maker = $this->staff('maker', ['finance']);
        $this->checker = $this->staff('checker', ['finance']);
        $this->approver = $this->staff('approver', ['finance']);
    }

    private function payrollRun(array $values): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('payroll_runs', array_merge([
            'id' => $id, 'reference_number' => 'PRX-PAY-TEST-' . strtoupper(Str::random(6)), 'region_code' => 'NG',
            'pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30', 'payment_date' => '2026-09-30',
            'status' => 'Draft', 'maker_id' => $this->maker->id,
        ], $values));

        return $id;
    }

    private function runStatus(string $runId): string
    {
        return DB::table('payroll_runs')->where('id', $runId)->value('status');
    }

    public function test_the_preparer_can_neither_check_nor_approve_their_own_run(): void
    {
        $draft = $this->payrollRun([]);
        $this->actingAs($this->maker);
        Livewire::test(PayrollManager::class)->call('submitForReview', $draft)
            ->assertDispatched('praxis-toast', $this->toast('You prepared this payroll run, so someone else must check it.'));
        $this->assertSame('Draft', $this->runStatus($draft));

        $checked = $this->payrollRun(['status' => 'Pending_Review', 'checker_id' => $this->checker->id, 'pay_period_start' => '2026-08-01']);
        Livewire::test(PayrollManager::class)->call('approveRun', $checked)
            ->assertDispatched('praxis-toast', $this->toast('You prepared this payroll run, so you can’t approve it. Ask another approver to approve it.'));
        $this->assertSame('Pending_Review', $this->runStatus($checked));

        $this->actingAs($this->checker);
        Livewire::test(PayrollManager::class)->call('approveRun', $checked)
            ->assertDispatched('praxis-toast', $this->toast('You checked this payroll run, so you can’t approve it. Ask another approver to approve it.'));
        $this->assertSame('Pending_Review', $this->runStatus($checked));
        $this->assertSame(0, DB::table('system_audit_logs')->where('target_id', $checked)->count());
    }

    public function test_a_third_person_approves_and_each_step_is_logged(): void
    {
        $run = $this->payrollRun([]);

        $this->actingAs($this->checker);
        Livewire::test(PayrollManager::class)->call('submitForReview', $run)->assertSet('showSuccessModal', true);
        $this->assertSame('Pending_Review', $this->runStatus($run));

        $this->actingAs($this->approver);
        Livewire::test(PayrollManager::class)->call('approveRun', $run)->assertSet('showSuccessModal', true);
        $row = DB::table('payroll_runs')->where('id', $run)->first();
        $this->assertSame('Approved', $row->status);
        $this->assertSame($this->approver->id, $row->approver_id);

        $log = DB::table('system_audit_logs')->where('target_table', 'payroll_runs')->where('target_id', $run)->orderBy('id')->get();
        $this->assertSame(['PAYROLL_RUN_CHECKED', 'PAYROLL_RUN_APPROVED'], $log->pluck('action')->all());
        $this->assertSame([$this->checker->id, $this->approver->id], $log->pluck('user_id')->all());
        $this->assertEquals(['status' => 'Pending_Review'], json_decode($log[1]->old_values, true));
        $this->assertEquals(['status' => 'Approved'], json_decode($log[1]->new_values, true));
    }

    public function test_only_the_next_status_in_the_chain(): void
    {
        $this->actingAs($this->approver);

        // A draft nobody has checked can't be approved or marked as paid
        $draft = $this->payrollRun([]);
        Livewire::test(PayrollManager::class)->call('approveRun', $draft)
            ->assertDispatched('praxis-toast', $this->toast('This payroll run hasn’t been checked yet, so it can’t be approved.'));
        Livewire::test(PayrollManager::class)->call('disburseRun', $draft)
            ->assertDispatched('praxis-toast', $this->toast('Only an approved payroll run can be marked as paid.'));
        $this->assertSame('Draft', $this->runStatus($draft));

        // A paid run stays paid
        $paid = $this->payrollRun(['status' => 'Disbursed', 'checker_id' => $this->checker->id, 'pay_period_start' => '2026-07-01']);
        Livewire::test(PayrollManager::class)->call('approveRun', $paid)
            ->assertDispatched('praxis-toast', $this->toast('This payroll run has already been approved or paid, so it wasn’t changed.'));
        Livewire::test(PayrollManager::class)->call('rejectRun', $paid)
            ->assertDispatched('praxis-toast', $this->toast('Only a run awaiting approval or approved (not yet paid) can be returned to draft.'));
        $this->assertSame('Disbursed', $this->runStatus($paid));
        $this->assertSame(0, DB::table('system_audit_logs')->where('target_table', 'payroll_runs')->count());
    }

    public function test_the_preparer_cannot_mark_their_own_run_as_paid(): void
    {
        $run = $this->payrollRun(['status' => 'Approved', 'checker_id' => $this->checker->id, 'approver_id' => $this->approver->id]);

        $this->actingAs($this->maker);
        Livewire::test(PayrollManager::class)
            ->call('viewRun', $run)
            ->assertSee('You prepared this run, so someone else marks it as paid.')
            ->assertDontSee('Mark as paid</button>', false)
            ->call('disburseRun', $run)
            ->assertDispatched('praxis-toast', $this->toast('You prepared this payroll run, so someone else must mark it as paid.'));
        $this->assertSame('Approved', $this->runStatus($run));

        $this->actingAs($this->approver);
        Livewire::test(PayrollManager::class)->call('disburseRun', $run)->assertSet('showSuccessModal', true);
        $this->assertSame('Disbursed', $this->runStatus($run));
        $this->assertSame('PAYROLL_RUN_PAID', DB::table('system_audit_logs')->where('target_id', $run)->value('action'));
    }

    public function test_a_month_already_paid_or_covered_cannot_be_run_again(): void
    {
        $this->payrollRun(['status' => 'Disbursed', 'checker_id' => $this->checker->id, 'approver_id' => $this->approver->id]);
        $this->payrollRun(['region_code' => 'CM', 'pay_period_start' => '2026-10-01', 'pay_period_end' => '2026-10-31']);
        $this->actingAs($this->maker);

        // September has been paid for Nigeria
        Livewire::test(PayrollManager::class)
            ->set('draftPeriodMonth', '9')->set('draftPeriodYear', '2026')->set('targetRegion', 'NG')
            ->call('generateDraftRun')
            ->assertDispatched('praxis-toast', $this->toast('A payroll run for September 2026 that covers Nigeria already exists.'));

        // October has a Cameroon run, so a run for every country would pay Cameroon twice
        Livewire::test(PayrollManager::class)
            ->set('draftPeriodMonth', '10')->set('draftPeriodYear', '2026')->set('targetRegion', 'ALL')
            ->call('generateDraftRun')
            ->assertDispatched('praxis-toast', $this->toast('A payroll run for October 2026 that covers all countries already exists.'));

        $this->assertSame(2, DB::table('payroll_runs')->count());
    }

    public function test_a_new_month_starts_as_a_draft_prepared_by_whoever_created_it(): void
    {
        // One active employee in Nigeria, with a current pay record (utility allowance included)
        $employee = $this->staff('employee', []);
        $employeeId = (string) Str::uuid();
        $this->insertRow('mdm_employees', [
            'id' => $employeeId, 'user_id' => $employee->id, 'employee_code' => 'EMP-TEST-1', 'job_classification' => 'Engineer',
            'tax_region' => 'NG', 'line_manager_id' => null, 'created_by' => null, 'updated_by' => null,
        ]);
        $this->insertRow('mdm_employee_compensations', [
            'id' => (string) Str::uuid(), 'mdm_employee_id' => $employeeId, 'base_salary' => 1000, 'allowance_housing' => 200,
            'allowance_transport' => 100, 'allowance_utility' => 50, 'valid_from' => '2026-01-01', 'valid_to' => null, 'created_by' => null,
        ]);

        $this->actingAs($this->maker);
        Livewire::test(PayrollManager::class)
            ->set('draftPeriodMonth', '11')->set('draftPeriodYear', '2026')->set('targetRegion', 'NG')
            ->call('generateDraftRun')
            ->assertSet('showSuccessModal', true);

        $run = DB::table('payroll_runs')->where('pay_period_start', '2026-11-01')->first();
        $this->assertNotNull($run, 'the draft run is created');
        $this->assertSame('Draft', $run->status);
        $this->assertSame($this->maker->id, $run->maker_id);
        $this->assertSame('2026-11-30', (string) $run->payment_date);

        $line = DB::table('payroll_lines')->where('payroll_run_id', $run->id)->first();
        $this->assertNotNull($line, 'the employee has a payroll line');
        $this->assertSame($employee->id, $line->user_id);
        $this->assertEquals(1350, $line->gross_pay);
    }
}
