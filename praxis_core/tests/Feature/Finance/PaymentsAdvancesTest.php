<?php

namespace Tests\Feature\Finance;

use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Support\Finance\Advances;
use App\Support\Finance\InvoicePayments;
use App\Support\Finance\InvoicePdf;
use App\Support\Finance\MilestoneInvoices;
use App\Support\Finance\Vat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Money HQ couldn't record (owner, 13 Nov 2026): payments Finance records itself (part payments add up), discount
 * lines, advances on a milestone taken off its invoice by themselves, cancelling reversing the ledger, and the
 * client's details on the invoice.
 */
class PaymentsAdvancesTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $project;

    private array $m = [];

    private string $account;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        DB::table('mdm_legal_entities')->where('region_code', 'NG')->update(['region_code' => 'XX']);
        $this->insertRow('mdm_legal_entities', ['id' => (string) Str::uuid(), 'region_code' => 'NG', 'legal_name' => 'JBS Praxis LLC', 'is_active' => 1,
            'registered_address' => '21B Bode Ajakaiye Street, Lekki, Lagos', 'tax_identification_number' => 'TIN-JBS-1']);
        $this->project = $this->project(['total_budget' => 6000000]);
        $this->m[1] = $this->phase($this->project, 1, 15, ['amount' => 900000]);
        $this->m[2] = $this->phase($this->project, 2, 10, ['amount' => 600000]);
        $company = Vat::issuer(Vat::projectEntity($this->project))->id;
        $this->account = (string) (DB::table('mdm_remittance_accounts')->where('currency', 'NGN')->where('legal_entity_id', $company)->where('is_active', 1)->value('id')
            ?? DB::table('mdm_remittance_accounts')->insertGetId(['type' => 'Bank', 'provider_name' => 'Test bank', 'account_name' => 'JBS Praxis',
                'account_reference' => '4440001111', 'is_active' => 1, 'currency' => 'NGN', 'legal_entity_id' => $company]));
        $this->actingAs($this->staff('finance', ['finance']));
    }

    private function form()
    {
        return Livewire::test(InvoiceLedger::class)->set('showCreateModal', true)->set('new_project_id', $this->project)
            ->set('new_remittance_id', $this->account);
    }

    private function latestInvoice(): object
    {
        return DB::table('invoices')->where('project_id', $this->project)->orderByDesc('created_at')->orderByDesc('issued_at')->first();
    }

    public function test_finance_records_part_payments_and_the_invoice_is_paid_when_they_reach_its_total(): void
    {
        $invoice = $this->invoice($this->project, null, 'Pending', 450000);

        Livewire::test(InvoiceLedger::class)->call('openRecordPayment', $invoice)
            ->assertSet('pay_amount', 450000.0)
            ->set('pay_amount', '300000')->set('pay_received_on', now('Africa/Lagos')->toDateString())->set('pay_reference', 'Nomba transfer 12 Nov')
            ->call('recordPayment')->assertHasNoErrors()->assertSet('pay_invoice_id', null);

        $this->assertSame('Pending', DB::table('invoices')->where('id', $invoice)->value('status'), 'A part payment leaves it unpaid');
        $this->assertEquals(300000, InvoicePayments::settled([$invoice])->get($invoice));
        $tx = DB::table('transactions')->where('invoice_id', $invoice)->first();
        $this->assertSame(['hq', 'Verified', 'Bank Transfer'], [$tx->source, $tx->status, $tx->method]);
        $this->assertTrue(DB::table('journal_entries')->where('source_id', $tx->id)->where('source_module', 'Receipts')->exists(), 'The receipt is in the ledger');

        // The list says what is left; the PDF too
        Livewire::test(InvoiceLedger::class)->assertSee('Part paid')->assertSee('₦150,000.00 left');
        [$html] = InvoicePdf::html(DB::table('invoices')->where('id', $invoice)->first());
        $this->assertStringContainsString('Balance due', $html);

        // More than what is left is refused; the rest pays it
        Livewire::test(InvoiceLedger::class)->call('openRecordPayment', $invoice)->assertSet('pay_amount', 150000.0)
            ->set('pay_amount', '200000')->set('pay_reference', 'Too much')->call('recordPayment')->assertHasErrors(['pay_amount'])
            ->set('pay_amount', '150000')->set('pay_method', 'Cash')->set('pay_reference', 'Cash at the office')->call('recordPayment')->assertHasNoErrors();
        $this->assertSame('Paid', DB::table('invoices')->where('id', $invoice)->value('status'));

        // A paid invoice takes no more
        Livewire::test(InvoiceLedger::class)->call('openRecordPayment', $invoice)->assertSet('pay_invoice_id', null);
    }

    public function test_only_finance_approvers_record_payments_and_a_future_date_is_refused(): void
    {
        $invoice = $this->invoice($this->project, null, 'Pending', 10000);
        Livewire::test(InvoiceLedger::class)->call('openRecordPayment', $invoice)
            ->set('pay_received_on', now('Africa/Lagos')->addDays(2)->toDateString())->set('pay_reference', 'x')
            ->call('recordPayment')->assertHasErrors(['pay_received_on']);

        $this->actingAs($this->staff('viewer', []));
        Livewire::test(InvoiceLedger::class)->call('openRecordPayment', $invoice)->assertForbidden();
    }

    public function test_a_discount_line_comes_off_the_total(): void
    {
        $this->form()
            ->call('addLine')->set('lines.0.description', 'Balance of the agreed scope')->set('lines.0.amount', '250000')
            ->call('addLine')->set('lines.1.description', 'Change order, items 1–9')->set('lines.1.amount', '300000')
            ->call('addDiscount')->set('lines.2.description', 'Long-term client discount')->set('lines.2.amount', '100000')
            ->assertSee('NGN 450,000.00')
            ->call('draftSubsequentInvoice')->assertHasNoErrors();

        $invoice = $this->latestInvoice();
        $this->assertEquals(450000, (float) $invoice->total_amount);
        $lines = DB::table('invoice_lines')->where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertSame(['charge', 'charge', 'discount'], $lines->pluck('kind')->all());
        $this->assertEquals(-100000, (float) $lines[2]->total_price);
        [$html] = InvoicePdf::html($invoice);
        $this->assertStringContainsString("\u{2212}NGN", $html, 'The discount prints with a minus');

        // Only a discount is refused; a discount bigger than the charges too
        $this->form()->call('addDiscount')->set('lines.0.description', 'Nothing')->set('lines.0.amount', '5')
            ->call('draftSubsequentInvoice')->assertHasErrors(['lines']);
        $this->form()->call('addLine')->set('lines.0.description', 'Small')->set('lines.0.amount', '100')
            ->call('addDiscount')->set('lines.1.description', 'Big')->set('lines.1.amount', '500')
            ->call('draftSubsequentInvoice')->assertHasErrors(['lines']);
    }

    public function test_an_advance_on_a_milestone_is_taken_off_its_invoice_by_itself(): void
    {
        // ₦300,000 of M1's ₦900,000 ahead of acceptance
        $this->form()->set('new_kind', 'advance')->set('advance_phase_id', $this->m[1])
            ->assertSet('advance_description', 'Advance on M1 · Milestone 1')
            ->assertSee('Up to NGN 900,000.00')
            ->set('advance_amount', '300000')->call('draftSubsequentInvoice')->assertHasNoErrors();
        $advance = $this->latestInvoice();
        $this->assertSame(Advances::INVOICE_TYPE, $advance->invoice_type);
        $this->assertNull($advance->phase_id, 'An advance does not bill the milestone');
        $this->assertEquals(300000, (float) $advance->total_amount);
        $this->assertSame([], MilestoneInvoices::billed([$this->m[1]]), 'M1 is still to invoice');
        $this->assertFalse(\App\Support\Projects\Milestones::canDelete(DB::table('project_phases')->where('id', $this->m[1])->first()));

        // More than what is left on M1 is refused
        $this->form()->set('new_kind', 'advance')->set('advance_phase_id', $this->m[1])->set('advance_amount', '600000.01')
            ->call('draftSubsequentInvoice')->assertHasErrors(['advance_amount']);

        // M1's own invoice: the advance comes off by itself
        $this->form()->set('picked', [$this->m[1]])->assertSee('Less advance ' . $advance->invoice_number)->assertSee('NGN 600,000.00')
            ->call('draftSubsequentInvoice')->assertHasNoErrors();
        $final = DB::table('invoices')->where('project_id', $this->project)->where('id', '!=', $advance->id)->first();
        $this->assertSame($this->m[1], $final->phase_id);
        $this->assertEquals(600000, (float) $final->total_amount);
        $deduction = DB::table('invoice_lines')->where('invoice_id', $final->id)->where('kind', 'less_advance')->first();
        $this->assertSame($advance->id, $deduction->advance_invoice_id);
        $this->assertEquals(-300000, (float) $deduction->total_price);
        $this->assertSame([], Advances::open([$this->m[1]])->all(), 'The advance is used up');
        $this->assertSame([$this->m[1]], MilestoneInvoices::billed([$this->m[1]]));

        // The advance can't be cancelled while M1's invoice takes it off; cancelling that one frees it again
        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $advance->id);
        $this->assertSame('Pending', DB::table('invoices')->where('id', $advance->id)->value('status'));
        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $final->id);
        $this->assertSame('Cancelled', DB::table('invoices')->where('id', $final->id)->value('status'));
        $this->assertCount(1, Advances::open([$this->m[1]]));
    }

    public function test_cancelling_reverses_the_ledger_and_a_paid_in_part_invoice_cant_be_cancelled(): void
    {
        $this->form()->call('addLine')->set('lines.0.description', 'Work')->set('lines.0.amount', '550000')->call('draftSubsequentInvoice')->assertHasNoErrors();
        $invoice = $this->latestInvoice();
        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $invoice->id);
        $this->assertSame('Cancelled', DB::table('invoices')->where('id', $invoice->id)->value('status'));
        $entries = DB::table('journal_entries')->where('source_module', 'Invoicing')->where('source_id', $invoice->id)->pluck('id');
        $this->assertCount(2, $entries, 'Issued, then reversed');
        $net = DB::table('journal_lines')->whereIn('journal_entry_id', $entries)->selectRaw('SUM(debit_amount) - SUM(credit_amount) as net, SUM(debit_amount) as dr')->first();
        $this->assertEquals(0, round((float) $net->net, 2));
        $this->assertEquals(1100000, (float) $net->dr, 'Each side reversed in full');

        $paid = $this->invoice($this->project, null, 'Pending', 1000);
        InvoicePayments::recordFromHq($paid, ['amount' => 400, 'received_on' => now()->toDateString(), 'method' => 'Bank Transfer', 'reference' => 'r'], (string) auth()->id());
        Livewire::test(InvoiceLedger::class)->call('cancelInvoice', $paid);
        $this->assertSame('Pending', DB::table('invoices')->where('id', $paid)->value('status'));
    }

    public function test_the_invoice_carries_the_clients_details(): void
    {
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'Tales Technology Consulting Limited', 'status' => 'Active Client', 'country' => 'Nigeria',
            'billing_address' => "51 George Tiene Crescent, Hilltop Estate\nAboru, Alimosho, Lagos", 'registration_number' => 'RC 7319824',
            'email' => 'hello@talesconsulting.com', 'phone' => '+234 814 454 4735']);
        DB::table('projects')->where('id', $this->project)->update(['organization_id' => $org]);
        $this->form()->call('addLine')->set('lines.0.description', 'Work')->set('lines.0.amount', '1000')->call('draftSubsequentInvoice')->assertHasNoErrors();

        [$html] = InvoicePdf::html($this->latestInvoice());
        foreach (['Tales Technology Consulting Limited', '51 George Tiene Crescent', 'RC 7319824', 'hello@talesconsulting.com', '+234 814 454 4735'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
    }
}
