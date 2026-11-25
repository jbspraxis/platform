<?php

namespace Tests\Feature\Finance;

use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Mail\InvoiceMail;
use App\Models\ClientUser;
use App\Support\Finance\InvoiceDrafts;
use App\Support\Finance\InvoicePdf;
use App\Support\Finance\Vat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Invoice drafts (owner, 25 Nov 2026: "Do not send any yet. I am going to review and send."): Create invoice › Save as
 * draft keeps the invoice apart (invoice_drafts), so the client's portal, email, alerts and the general ledger see
 * nothing; Drafts shows its PDF marked DRAFT, Edit changes it, Issue and send sends it the usual way, Discard drops it.
 */
class InvoiceDraftsTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $project;

    private string $org;

    private array $m = [];

    private string $account;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        DB::table('mdm_legal_entities')->where('region_code', 'NG')->update(['region_code' => 'XX']);
        $this->insertRow('mdm_legal_entities', ['id' => (string) Str::uuid(), 'region_code' => 'NG', 'legal_name' => 'JBS Praxis LLC', 'is_active' => 1,
            'registered_address' => '21B Bode Ajakaiye Street, Lekki, Lagos', 'tax_identification_number' => 'TIN-JBS-1']);
        $this->project = $this->project(['total_budget' => 1000000]);
        $this->org = (string) DB::table('projects')->where('id', $this->project)->value('organization_id');
        $this->m[1] = $this->phase($this->project, 1, 30, ['status' => 'Completed']);
        $this->m[2] = $this->phase($this->project, 2, 30, ['status' => 'QA']);
        $this->m[3] = $this->phase($this->project, 3, 40);
        $company = Vat::issuer(Vat::projectEntity($this->project))->id;
        $this->account = (string) (DB::table('mdm_remittance_accounts')->where('currency', 'NGN')->where('legal_entity_id', $company)->where('is_active', 1)->value('id')
            ?? DB::table('mdm_remittance_accounts')->insertGetId(['type' => 'Bank', 'provider_name' => 'Test bank', 'account_name' => 'JBS Praxis',
                'account_reference' => '4440001111', 'is_active' => 1, 'currency' => 'NGN', 'legal_entity_id' => $company]));
        // The client's billing contact, who gets every invoice issued
        ClientUser::forceCreate([
            'organization_id' => $this->org, 'role_id' => DB::table('roles')->where('slug', 'client-admin')->value('id'), 'first_name' => 'Ada',
            'last_name' => 'City', 'email' => 'ada@city.test', 'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1,
        ]);
        $this->actingAs($this->staff('finance', ['finance']));
    }

    private function form()
    {
        return Livewire::test(InvoiceLedger::class)->set('showCreateModal', true)->set('new_project_id', $this->project)
            ->set('new_remittance_id', $this->account)->set('new_payment_terms', 'Due on Receipt');
    }

    private function draft(): ?object
    {
        return DB::table('invoice_drafts')->where('project_id', $this->project)->whereNull('deleted_at')->first();
    }

    /** What reaches the client or the books: invoices, emails, portal alerts, ledger entries */
    private function assertNothingReachedTheClient(): void
    {
        $this->assertSame(0, DB::table('invoices')->where('project_id', $this->project)->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('client_notifications')->count());
        $this->assertSame(0, DB::table('journal_entries')->where('source_module', 'Invoicing')->count());
    }

    public function test_save_as_draft_creates_nothing_the_client_or_the_ledger_sees_and_holds_its_milestones(): void
    {
        $this->form()->set('picked', [$this->m[1], $this->m[2]])
            ->call('addDiscount')->set('lines.2.description', 'Long-term client discount')->set('lines.2.amount', '50000')
            ->call('saveDraft')->assertHasNoErrors()
            ->assertSet('showCreateModal', false)->assertSet('activeTab', 'Drafts')->assertSet('draft_id', null)
            ->assertDispatched('praxis-toast', $this->toast('Nothing was sent'))
            ->assertSee('Draft, not sent')->assertSee('₦550,000.00')->assertSee('Issue and send');

        $this->assertNothingReachedTheClient();
        $draft = $this->draft();
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{4}$/', $draft->invoice_number);
        $this->assertSame([$this->m[1], $this->m[2]], InvoiceDrafts::milestones($draft));
        $this->assertSame(['charge', 'charge', 'discount'], array_column(InvoiceDrafts::lines($draft), 'kind'));

        // Its milestones aren't offered to another invoice; M3 still is
        $offered = collect($this->form()->get('projectPhases'))->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertSame([$this->m[3]], $offered);
        $this->form()->call('addLine')->set('lines.0.description', 'Again')->set('lines.0.amount', '1000')->set('lines.0.phase_id', $this->m[1])
            ->call('draftSubsequentInvoice')->assertHasErrors(['lines.0.phase_id']);
        $this->assertNothingReachedTheClient();

        // The draft's PDF: its lines, its total and DRAFT; HQ finance only
        [$invoice, $lines] = InvoiceDrafts::preview($draft);
        $this->assertEquals(550000, (float) $invoice->total_amount);
        [$html] = InvoicePdf::html($invoice, 'en', $lines);
        foreach (['Long-term client discount', 'Draft, not issued', 'DRAFT', $draft->invoice_number] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
        $this->get(route('hq.finance.invoice-draft-pdf', $draft->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->staff('engineer', ['engineer']));
        $this->get(route('hq.finance.invoice-draft-pdf', $draft->id))->assertForbidden();
    }

    public function test_issue_and_send_sends_it_the_usual_way_with_the_number_the_draft_kept(): void
    {
        $this->form()->set('picked', [$this->m[1]])->call('saveDraft')->assertHasNoErrors();
        $draft = $this->draft();

        Livewire::test(InvoiceLedger::class)->set('activeTab', 'Drafts')->call('issueDraft', $draft->id)->assertHasNoErrors()
            ->assertSet('showCreateModal', false)->assertSet('activeTab', 'Pending')->assertSet('draft_id', null)
            ->assertDispatched('praxis-toast', $this->toast("Invoice {$draft->invoice_number} issued"))
            ->assertDispatched('praxis-toast', $this->toast('Emailed with its PDF to ada@city.test'));

        $invoice = DB::table('invoices')->where('project_id', $this->project)->first();
        $this->assertSame($draft->invoice_number, $invoice->invoice_number);
        $this->assertSame('Pending', $invoice->status);
        $this->assertSame($this->m[1], $invoice->phase_id);
        $this->assertSame(now()->toDateString(), substr((string) $invoice->due_date, 0, 10));
        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasTo('ada@city.test'));
        $this->assertSame(1, DB::table('journal_entries')->where('source_module', 'Invoicing')->where('source_id', $invoice->id)->count());

        // The draft is done: marked issued, out of Drafts, and can't be issued twice
        $row = DB::table('invoice_drafts')->where('id', $draft->id)->first();
        $this->assertSame($invoice->id, $row->issued_invoice_id);
        $this->assertNotNull($row->deleted_at);
        $this->assertNull($this->draft());
        Livewire::test(InvoiceLedger::class)->call('issueDraft', $draft->id)->assertDispatched('praxis-toast', $this->toast('issued or discarded'));
        $this->assertSame(1, DB::table('invoices')->where('project_id', $this->project)->count());
    }

    public function test_edit_keeps_the_same_draft_and_number_and_closing_the_form_changes_nothing(): void
    {
        $this->form()->set('picked', [$this->m[1]])->call('saveDraft');
        $draft = $this->draft();

        // Opened from Drafts: its milestone ticked and still offered to it
        $form = Livewire::test(InvoiceLedger::class)->call('editDraft', $draft->id)
            ->assertSet('showCreateModal', true)->assertSet('draft_id', $draft->id)->assertSet('picked', [$this->m[1]])
            ->assertSee('Draft ' . $draft->invoice_number)->assertSee('Issue and send');
        $this->assertContains($this->m[1], collect($form->get('projectPhases'))->pluck('id')->map(fn ($id) => (string) $id)->all());
        $form->set('lines.0.amount', '123456')->set('picked', [$this->m[1], $this->m[2]])->call('saveDraft')->assertHasNoErrors();

        $this->assertSame(1, DB::table('invoice_drafts')->where('project_id', $this->project)->count());
        $again = $this->draft();
        $this->assertSame($draft->invoice_number, $again->invoice_number);
        $this->assertEquals([123456, 300000], array_column(InvoiceDrafts::lines($again), 'amount'));

        // Closing an edit leaves the draft alone and the form empty for the next invoice
        Livewire::test(InvoiceLedger::class)->call('editDraft', $draft->id)->set('lines.0.amount', '1')
            ->set('showCreateModal', false)->assertSet('draft_id', null)->assertSet('lines', [])->assertSet('new_project_id', '');
        $this->assertEquals(123456, InvoiceDrafts::lines($this->draft())[0]['amount']);
        $this->assertNothingReachedTheClient();
    }

    public function test_discard_frees_its_milestones_and_is_kept_in_the_audit_log(): void
    {
        $this->form()->set('picked', [$this->m[2]])->call('saveDraft');
        $draft = $this->draft();

        Livewire::test(InvoiceLedger::class)->set('activeTab', 'Drafts')->call('discardDraft', $draft->id)
            ->assertDispatched('praxis-toast', $this->toast('discarded. It was never sent'))->assertSee('No drafts');
        $this->assertNull($this->draft());
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'INVOICE_DRAFT_DISCARDED')->where('target_id', $draft->id)->exists());
        $offered = collect($this->form()->get('projectPhases'))->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertSame([$this->m[1], $this->m[2], $this->m[3]], $offered);
        $this->get(route('hq.finance.invoice-draft-pdf', $draft->id))->assertNotFound();
        $this->assertNothingReachedTheClient();
    }

    public function test_an_advance_can_be_a_draft_too(): void
    {
        $this->form()->set('new_kind', 'advance')->set('advance_phase_id', $this->m[3])->set('advance_amount', '100000')
            ->call('saveDraft')->assertHasNoErrors();
        $draft = $this->draft();
        $this->assertSame('advance', $draft->kind);
        $this->assertSame([], InvoiceDrafts::milestones($draft), 'An advance does not bill its milestone');
        $this->assertNothingReachedTheClient();

        Livewire::test(InvoiceLedger::class)->call('issueDraft', $draft->id)->assertHasNoErrors();
        $invoice = DB::table('invoices')->where('project_id', $this->project)->first();
        $this->assertSame(\App\Support\Finance\Advances::INVOICE_TYPE, $invoice->invoice_type);
        $this->assertEquals(100000, (float) $invoice->amount_due);
    }

    public function test_only_finance_create_saves_edits_issues_or_discards_drafts(): void
    {
        $this->form()->set('picked', [$this->m[1]])->call('saveDraft');
        $draft = $this->draft();
        $viewer = $this->staff('viewer', ['auditor']);
        $this->actingAs($viewer);
        foreach (['editDraft', 'issueDraft', 'discardDraft'] as $action) {
            Livewire::test(InvoiceLedger::class)->call($action, $draft->id)->assertForbidden();
        }
        Livewire::test(InvoiceLedger::class)->call('saveDraft')->assertForbidden();
        $this->assertNotNull($this->draft());
        $this->assertNothingReachedTheClient();
    }
}
