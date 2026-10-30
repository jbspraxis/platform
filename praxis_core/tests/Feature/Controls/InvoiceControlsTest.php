<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Finance\InvoiceLedger;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Invoices: only an unpaid invoice (pending or overdue) can be cancelled, never a paid one and never twice,
 * and each cancellation is in the audit log with the status it had before.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class InvoiceControlsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private string $projectId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectId = (string) Str::uuid();
        $this->insertRow('projects', [
            'id' => $this->projectId, 'organization_id' => CoreSeeder::INTERNAL_ORG_ID, 'title' => 'Invoice controls (test)',
            'status' => 'Active', 'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);
    }

    private function invoice(string $status): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('invoices', [
            'id' => $id, 'project_id' => $this->projectId, 'invoice_number' => 'INV-TEST-' . strtoupper(Str::random(6)),
            'amount_due' => 1000, 'vat_amount' => 75, 'total_amount' => 1075, 'currency' => 'USD',
            'status' => $status, 'due_date' => '2026-10-15', 'phase_id' => null, 'remittance_account_id' => null,
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);

        return $id;
    }

    private function invoiceStatus(string $invoiceId): string
    {
        return DB::table('invoices')->where('id', $invoiceId)->value('status');
    }

    public function test_a_paid_or_cancelled_invoice_stays_as_it_is(): void
    {
        $paid = $this->invoice('Paid');
        $cancelled = $this->invoice('Cancelled');
        $this->actingAs($this->staff('finance', ['finance']));

        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $paid)->assertSee('is paid, so it can’t be cancelled.');
        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $cancelled)->assertSee('is already cancelled.');

        $this->assertSame('Paid', $this->invoiceStatus($paid));
        $this->assertSame('Cancelled', $this->invoiceStatus($cancelled));
        $this->assertSame(0, DB::table('system_audit_logs')->where('target_table', 'invoices')->count());
    }

    public function test_pending_and_overdue_invoices_can_be_cancelled_and_each_is_logged(): void
    {
        $pending = $this->invoice('Pending');
        $overdue = $this->invoice('Overdue');
        $finance = $this->staff('finance', ['finance']);
        $this->actingAs($finance);

        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $pending)->assertSee('cancelled.');
        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $overdue);
        $this->assertSame('Cancelled', $this->invoiceStatus($pending));
        $this->assertSame('Cancelled', $this->invoiceStatus($overdue));

        $log = DB::table('system_audit_logs')->where('action', 'INVOICE_CANCELLED')->orderBy('id')->get();
        $this->assertSame([$pending, $overdue], $log->pluck('target_id')->all());
        $this->assertSame([$finance->id, $finance->id], $log->pluck('user_id')->all());
        $this->assertEquals(['status' => 'Pending'], json_decode($log[0]->old_values, true));
        $this->assertEquals(['status' => 'Overdue'], json_decode($log[1]->old_values, true));
        $this->assertEquals(['status' => 'Cancelled'], json_decode($log[1]->new_values, true));
    }

    public function test_cancelling_needs_the_finance_delete_permission(): void
    {
        $pending = $this->invoice('Pending');
        $this->actingAs($this->staff('auditor', ['auditor']));

        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $pending)->assertForbidden();
        $this->assertSame('Pending', $this->invoiceStatus($pending));
    }
}
