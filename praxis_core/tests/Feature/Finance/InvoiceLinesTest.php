<?php

namespace Tests\Feature\Finance;

use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Support\Finance\InvoicePdf;
use App\Support\Finance\MilestoneInvoices;
use App\Support\Finance\Vat;
use App\Support\Projects\Milestones;
use App\Support\Projects\ProjectPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * One invoice, several milestones (owner, 10 Nov 2026): Create invoice ticks any milestones not invoiced yet, each
 * becomes its own line with its agreed amount less deposit (editable), "Add line" adds free lines, and every line is
 * stored with its milestone, so the board shows each of them Invoiced and none can be invoiced twice.
 */
class InvoiceLinesTest extends TestCase
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
        // The company that bills it (Nigeria's) and its naira account (D8)
        DB::table('mdm_legal_entities')->where('region_code', 'NG')->update(['region_code' => 'XX']);
        $this->insertRow('mdm_legal_entities', ['id' => (string) \Illuminate\Support\Str::uuid(), 'region_code' => 'NG', 'legal_name' => 'JBS Praxis LLC', 'is_active' => 1,
            'registered_address' => '21B Bode Ajakaiye Street, Lekki, Lagos', 'tax_identification_number' => 'TIN-JBS-1']);
        $this->project = $this->project(['total_budget' => 1000000]);
        $this->m[1] = $this->phase($this->project, 1, 30, ['status' => 'Completed']);
        $this->m[2] = $this->phase($this->project, 2, 30, ['status' => 'QA']);
        $this->m[3] = $this->phase($this->project, 3, 40);
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

    private function phaseRow(string $id): object
    {
        return DB::table('project_phases')->where('id', $id)->first();
    }

    public function test_one_invoice_bills_several_milestones_a_line_each_plus_a_free_line(): void
    {
        $this->form()
            ->assertSee('Milestones to invoice')->assertSee('Accepted')->assertSee('not accepted yet')
            // A title that starts with its code ("M1 · …", as imported) shows the code once
            ->assertSee('M1 · Milestone 1')->assertDontSee('M1 · M1 ·')
            ->set('picked', [$this->m[2], $this->m[1]])
            // Milestone order, each with its agreed amount (no deposit on this project)
            ->assertSet('lines.0.phase_id', $this->m[1])->assertSet('lines.1.phase_id', $this->m[2])
            ->assertSet('lines.0.amount', 300000.0)->assertSet('lines.1.amount', 300000.0)
            ->assertSet('lines.0.description', 'M1 · Milestone 1, on written acceptance')
            ->call('addLine')->set('lines.2.description', 'Change order: three extra reports')->set('lines.2.amount', '50000')
            // Editable: half of M2 this time
            ->set('lines.1.amount', '150000')
            ->assertSee('NGN 500,000.00')
            ->call('draftSubsequentInvoice')->assertHasNoErrors();

        $invoice = DB::table('invoices')->where('project_id', $this->project)->first();
        $this->assertNull($invoice->phase_id, 'With several milestones, each line names its own');
        $this->assertEquals(500000, (float) $invoice->amount_due);
        $lines = DB::table('invoice_lines')->where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertSame([$this->m[1], $this->m[2], null], $lines->pluck('phase_id')->all());
        $this->assertEquals([300000, 150000, 50000], $lines->pluck('total_price')->map(fn ($v) => (float) $v)->all());
        $this->assertSame('Change order: three extra reports', $lines[2]->description);

        // Both show Invoiced on the board, can't be deleted and aren't offered again; M3 still is
        $latest = MilestoneInvoices::latest(array_values($this->m));
        $this->assertSame($invoice->id, $latest->get($this->m[1])->id);
        $this->assertSame($invoice->id, $latest->get($this->m[2])->id);
        $this->assertNull($latest->get($this->m[3]));
        $this->assertSame('invoiced', ProjectPlan::column($this->phaseRow($this->m[2]), $latest->get($this->m[2])));
        $this->assertFalse(Milestones::canDelete($this->phaseRow($this->m[1])));
        $offered = collect($this->form()->get('projectPhases'))->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertSame([$this->m[3]], $offered);
        $this->assertSame([1, 2], MilestoneInvoices::milestoneNumbers([$invoice->id])->get($invoice->id)->all());

        // The PDF carries every line
        [$html] = InvoicePdf::html($invoice);
        foreach (['M1 · Milestone 1, on written acceptance', 'M2 · Milestone 2, on written acceptance', 'Change order: three extra reports'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
    }

    public function test_one_milestone_still_names_it_and_its_line_follows_the_tick(): void
    {
        $form = $this->form()->set('picked', [$this->m[1]])->assertCount('lines', 1)
            ->call('addLine')->assertCount('lines', 2)
            // Unticking drops the milestone's line and keeps the free one; removing a milestone's line unticks it
            ->set('picked', [])->assertCount('lines', 1)->assertSet('lines.0.phase_id', null)
            ->call('removeLine', 0)->assertCount('lines', 0)
            ->set('picked', [$this->m[1]])->call('removeLine', 0)->assertSet('picked', [])
            // Nothing to bill
            ->call('draftSubsequentInvoice')->assertHasErrors(['lines'])
            ->set('picked', [$this->m[1]])->call('draftSubsequentInvoice')->assertHasNoErrors();

        $invoice = DB::table('invoices')->where('project_id', $this->project)->first();
        $this->assertSame($this->m[1], $invoice->phase_id);
        $this->assertSame($this->m[1], DB::table('invoice_lines')->where('invoice_id', $invoice->id)->value('phase_id'));
    }

    public function test_a_milestone_of_another_project_or_one_invoiced_already_is_refused(): void
    {
        $other = $this->phase($this->project(['title' => 'Another project']), 1, 100);
        $this->form()->call('addLine')->set('lines.0.description', 'Sneaky')->set('lines.0.amount', '1000')->set('lines.0.phase_id', $other)
            ->call('draftSubsequentInvoice')->assertHasErrors(['lines.0.phase_id']);

        $this->invoice($this->project, $this->m[1], 'Pending');
        $this->form()->call('addLine')->set('lines.0.description', 'Again')->set('lines.0.amount', '1000')->set('lines.0.phase_id', $this->m[1])
            ->call('draftSubsequentInvoice')->assertHasErrors(['lines.0.phase_id']);
        $this->assertSame(1, DB::table('invoices')->where('project_id', $this->project)->count());
    }

    public function test_a_cancelled_invoice_frees_its_milestones_but_still_keeps_them_from_deletion(): void
    {
        $this->form()->set('picked', [$this->m[1], $this->m[2]])->call('draftSubsequentInvoice')->assertHasNoErrors();
        $invoice = DB::table('invoices')->where('project_id', $this->project)->first();
        DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'Cancelled']);

        $this->assertSame([], MilestoneInvoices::billed([$this->m[1], $this->m[2]]));
        $this->assertTrue(MilestoneInvoices::any($this->m[1]));
        $offered = collect($this->form()->get('projectPhases'))->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertSame([$this->m[1], $this->m[2], $this->m[3]], $offered);
    }

    public function test_existing_invoice_lines_take_their_invoices_milestone(): void
    {
        $invoice = $this->invoice($this->project, $this->m[3], 'Pending');
        DB::table('invoice_lines')->where('invoice_id', $invoice)->delete();
        DB::table('invoice_lines')->insert(['invoice_id' => $invoice, 'description' => 'Old line', 'quantity' => 1, 'unit_price' => 1, 'total_price' => 1]);
        $migration = require database_path('migrations/2026_11_10_900000_add_phase_id_to_invoice_lines.php');
        $migration->up();

        $this->assertSame($this->m[3], DB::table('invoice_lines')->where('invoice_id', $invoice)->value('phase_id'));
    }
}
