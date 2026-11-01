<?php

namespace Tests\Feature\Finance;

use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Livewire\Hq\Mdm\LegalEntityRegistry;
use App\Livewire\Hq\Opportunities\ProposalsTab;
use App\Livewire\Hq\Opportunities\SowTab;
use App\Mail\ProposalMail;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\StatementOfWork;
use App\Models\User;
use App\Support\Finance\Vat;
use App\Support\Opportunities\ProposalPdf;
use App\Support\Opportunities\Proposals;
use App\Support\Opportunities\SowPdf;
use App\Support\Opportunities\Sows;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\Feature\Opportunities\OpportunityFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * The VAT switch per JBS Praxis company (owner, 1 Nov 2026: "We are not billing VAT until we fully register JBS
 * Praxis"; App\Support\Finance\Vat). Switches start off; a company that charges VAT does so from its start date for
 * its own country's clients (and, for Nigeria's, clients of countries without a company); documents keep the VAT they
 * were issued with. Tax compliance rates come from the seeded live export (NG 7.5%, CM 19.25%, GB 20%).
 */
class VatSwitchTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
        FakeAi::install()->unconfigured();
    }

    /** A JBS Praxis company in Master data › Legal entities, VAT off as the migration leaves it unless $values say otherwise */
    private function company(string $region, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('mdm_legal_entities', $values + [
            'id' => $id, 'region_code' => $region, 'legal_name' => $region === 'NG' ? 'JBS Praxis Nigeria Ltd' : 'JBS Praxis Cameroun SARL',
            'registration_number' => 'RC-' . $region, 'tax_identification_number' => 'TIN-' . $region, 'employer_social_security_code' => 'SS-' . $region,
            'registered_address' => 'Lagos', 'legal_representative_name' => 'Ada Director', 'legal_representative_title' => 'Managing Director', 'is_active' => 1,
        ]);

        return $id;
    }

    private function chargeVat(string $region, ?string $from, string $number = '12345678-0001'): void
    {
        DB::table('mdm_legal_entities')->where('region_code', $region)
            ->update(['charges_vat' => $from !== null, 'vat_number' => $from !== null ? $number : null, 'vat_starts_on' => $from]);
    }

    /** An opportunity led by $lead for a client in $country, with a contact the proposal and the SOW go to */
    private function opportunity(User $lead, string $country = 'Nigeria', string $email = 'ngozi@kilo.test'): Opportunity
    {
        $org = $this->organisation(['name' => 'Kilo ' . Str::random(4), 'country' => $country]);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => $email]);

        return $this->opportunityFor($org, ['title' => 'Month-end close', 'lead_user_id' => $lead->id, 'stage' => 'proposal']);
    }

    /** Version 1, USD 1,000 (40/30/30), saved as a draft */
    private function draftProposal(Opportunity $o, User $lead): Proposal
    {
        $p = Proposals::start($o, 'USD', $lead);
        Proposals::save($p, ['title' => 'A faster close', 'sections' => [['key' => 'executive_summary', 'title' => 'Summary', 'body' => 'We recommend it.']],
            'lines' => [['code' => '#1292P', 'description' => 'Diagnostic & Audit', 'quantity' => '1 Phase', 'unit_price' => 1000]]], $lead);

        return $p->fresh();
    }

    private function sendProposal(Proposal $p, User $lead, string $email = 'ngozi@kilo.test'): Proposal
    {
        $this->assertNull(Proposals::approve($p, $lead));
        $this->assertSame([], Proposals::send($p->fresh(), [$email], $lead));

        return $p->fresh();
    }

    private function approvedSow(Opportunity $o, User $lead): StatementOfWork
    {
        $sow = Sows::start($o->fresh(), $lead);
        Sows::save($sow, ['title' => 'A faster close', 'sections' => [['key' => 'purpose', 'title' => 'Purpose', 'body' => 'The work.']],
            'milestones' => [['key' => 'diagnostic', 'title' => 'Diagnostic', 'description' => 'A map', 'weeks' => 2, 'weight' => 40],
                ['key' => 'build', 'title' => 'Build', 'description' => 'The close', 'weeks' => 3, 'weight' => 60]]], $lead);
        $this->assertNull(Sows::approve($sow, $lead, 'Lead Test', self::PNG, '203.0.113.5'));

        return $sow->fresh();
    }

    /** Sent to Ngozi, who signs it with the one-time code: the deposit invoice is raised */
    private function signSow(StatementOfWork $sow, User $lead, string $email = 'ngozi@kilo.test'): StatementOfWork
    {
        $this->assertSame([], Sows::send($sow, [$email], $lead));
        $this->assertTrue(Sows::holdSignature($sow->fresh(), ['name' => 'Ngozi Eze', 'title' => 'CFO', 'email' => $email], self::PNG, '424242'));
        $this->assertTrue(Sows::sign($sow->fresh(), '424242', '203.0.113.9'));

        return $sow->fresh();
    }

    private function vatPosted(string $invoiceId): float
    {
        return (float) DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.source_id', $invoiceId)->where('journal_lines.memo', 'VAT payable accrual')->sum('journal_lines.credit_amount');
    }

    /** Finance › Invoices › Create invoice for a project: the amount before VAT, the remittance account, as Finance fills it */
    private function financeInvoice(string $projectId, float $amount): object
    {
        $account = DB::table('mdm_remittance_accounts')->value('id') ?? DB::table('mdm_remittance_accounts')->insertGetId([
            'type' => 'Bank', 'provider_name' => 'Test bank', 'account_name' => 'JBS Praxis', 'account_reference' => '0001', 'is_active' => 1]);
        Livewire::test(InvoiceLedger::class)->set('showCreateModal', true)->set('new_project_id', $projectId)
            ->set('new_amount_due', $amount)->set('new_remittance_id', $account)->set('new_description', 'M1')
            ->call('draftSubsequentInvoice')->assertHasNoErrors();

        return DB::table('invoices')->where('project_id', $projectId)->where('invoice_type', 'Tax Invoice - Phase Milestone')->orderByDesc('created_at')->first();
    }

    public function test_with_every_switch_off_no_document_or_invoice_carries_vat(): void
    {
        $this->company('NG');
        $this->company('CM');
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);

        // The draft: no VAT line, the total is the subtotal, and HQ says why
        $p = $this->draftProposal($o, $lead);
        $this->assertSame([0.0, false, 1000.0], [(float) $p->vat_rate, $p->vat_applied, (float) $p->total_value]);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])
            ->assertSee("USD\u{00A0}1,000.00")->assertDontSee('VAT (')->assertSee('No VAT: JBS Praxis Nigeria Ltd doesn’t charge VAT yet.');

        // Sent: still none, in the PDF and the email
        $p = $this->sendProposal($p, $lead);
        $this->assertSame([Proposal::SENT, false], [$p->status, $p->vat_applied]);
        $html = view('pdf.proposal', (new \ReflectionMethod(ProposalPdf::class, 'data'))->invoke(null, $p, 'en'))->render();
        $this->assertStringNotContainsString('VAT (', $html);
        $this->assertStringNotContainsString('VAT included', $html);
        Mail::assertSent(ProposalMail::class, fn (ProposalMail $m) => str_contains($m->render(), 'Total') && ! str_contains($m->render(), 'VAT included'));

        // The SOW and its deposit invoice: none, and nothing posted to VAT payable
        $this->assertTrue(Proposals::approveForClient($p, ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'CFO'));
        $sow = $this->signSow($this->approvedSow($o, $lead), $lead);
        $this->assertSame(['0.00', false], [$sow->vat_rate, $sow->vat_applied]);
        $this->assertStringNotContainsString('VAT (', view('pdf.sow', (new \ReflectionMethod(SowPdf::class, 'data'))->invoke(null, $sow, 'en'))->render());
        $deposit = DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first();
        $this->assertSame(['400.00', '0.00', '400.00', '0.00', 0, 'NG'], [$deposit->amount_due, $deposit->vat_amount, $deposit->total_amount, $deposit->vat_rate, $deposit->vat_applied, $deposit->region_code]);
        $this->assertSame(0.0, $this->vatPosted($deposit->id));

        // Finance's milestone invoice (the pre-fill is before VAT, as MyCity's): no VAT added, and the modal says so
        $this->actingAs($finance = $this->staff('finance', ['finance']));
        Livewire::test(InvoiceLedger::class)->set('showCreateModal', true)->set('new_project_id', $sow->project_id)
            ->assertSee('No VAT: JBS Praxis Nigeria Ltd doesn’t charge VAT yet.');
        $invoice = $this->financeInvoice($sow->project_id, 240);
        $this->assertSame(['240.00', '0.00', '240.00', 0, 'NG'], [$invoice->amount_due, $invoice->vat_amount, $invoice->total_amount, $invoice->vat_applied, $invoice->region_code]);
        $this->assertSame(0.0, $this->vatPosted($invoice->id));
    }

    public function test_from_its_start_date_new_drafts_and_invoices_get_vat_and_what_was_sent_before_keeps_none(): void
    {
        $this->company('NG');
        $this->chargeVat('NG', '2026-12-01');
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);

        // 5 November, before the start date: A is sent without VAT; B is only a draft
        $a = $this->opportunity($lead, 'Nigeria', 'ngozi@kilo.test');
        $sentBefore = $this->sendProposal($this->draftProposal($a, $lead), $lead);
        $this->assertSame([0.0, false, 1000.0], [(float) $sentBefore->vat_rate, $sentBefore->vat_applied, (float) $sentBefore->total_value]);
        $this->assertTrue(Proposals::approveForClient($sentBefore, ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'CFO'));
        $b = $this->opportunity($lead, 'Nigeria', 'bola@lima.test');
        $draft = $this->draftProposal($b, $lead);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $b->id])->assertSee('No VAT: JBS Praxis Nigeria Ltd charges VAT from 1 December 2026.');

        // 2 December: what was sent keeps none; the draft takes 7.5% and keeps it once sent
        $this->travelTo(CarbonImmutable::parse('2026-12-02 09:00:00', 'UTC'));
        $this->assertFalse(Proposals::vat($sentBefore->fresh())['applies']);
        $this->assertSame('1000.00', $sentBefore->fresh()->total_value);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $b->id])
            ->assertSee('VAT (7.5%)')->assertSee("USD\u{00A0}1,075.00")
            ->assertSee('VAT at 7.5%: JBS Praxis Nigeria Ltd charges VAT from 1 December 2026 (VAT no. 12345678-0001).');
        $sentAfter = $this->sendProposal($draft, $lead, 'bola@lima.test');
        $this->assertSame(['7.50', true, '1075.00'], [$sentAfter->vat_rate, $sentAfter->vat_applied, $sentAfter->total_value]);
        $html = view('pdf.proposal', (new \ReflectionMethod(ProposalPdf::class, 'data'))->invoke(null, $sentAfter, 'en'))->render();
        $this->assertStringContainsString('VAT (7.5%)', $html);
        Mail::assertSent(ProposalMail::class, fn (ProposalMail $m) => $m->proposal->id === $sentAfter->id && str_contains($m->render(), 'Total, VAT included'));

        // A's SOW is a new document: drafted now it shows VAT, HQ signs it with 7.5%, and the deposit invoice is taxed
        $sow = Sows::start($a->fresh(), $lead);
        $this->assertSame([true, 7.5, 1075.0], [SowPdf::fees($sow)['vat']['applies'], SowPdf::fees($sow)['vat_rate'], SowPdf::fees($sow)['totals']['total']]);
        $sow = $this->signSow($this->approvedSow($a, $lead), $lead);
        $this->assertSame(['7.50', true], [$sow->vat_rate, $sow->vat_applied]);
        $deposit = DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first();
        $this->assertSame(['400.00', '30.00', '430.00', '7.50', 1, 'NG'], [$deposit->amount_due, $deposit->vat_amount, $deposit->total_amount, $deposit->vat_rate, $deposit->vat_applied, $deposit->region_code]);
        $this->assertSame(30.0, $this->vatPosted($deposit->id));
        $this->actingAs($finance = $this->staff('finance', ['finance']));
        $milestone = $this->financeInvoice($sow->project_id, 240);
        $this->assertSame(['240.00', '18.00', '258.00', '7.50', 1], [$milestone->amount_due, $milestone->vat_amount, $milestone->total_amount, $milestone->vat_rate, $milestone->vat_applied]);

        // Switched off again on 10 December: everything issued keeps its VAT; the next invoice has none
        $this->travelTo(CarbonImmutable::parse('2026-12-10 09:00:00', 'UTC'));
        $this->chargeVat('NG', null);
        $this->assertSame([true, 7.5], [Proposals::vat($sentAfter->fresh())['applies'], Proposals::vat($sentAfter->fresh())['rate']]);
        $this->assertSame([true, 7.5], [Sows::vat($sow->fresh())['applies'], Sows::vat($sow->fresh())['rate']]);
        $this->assertSame('430.00', DB::table('invoices')->where('id', $deposit->id)->value('total_amount'));
        $this->assertSame(['0.00', 0], [($next = $this->financeInvoice($sow->project_id, 100))->vat_amount, $next->vat_applied]);
    }

    public function test_an_approved_sow_whose_vat_changed_must_be_approved_again_before_it_is_sent(): void
    {
        $this->company('NG');
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $this->assertTrue(Proposals::approveForClient($this->sendProposal($this->draftProposal($o, $lead), $lead), ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'CFO'));
        $sow = $this->approvedSow($o, $lead);
        $this->assertSame(['0.00', false], [$sow->vat_rate, $sow->vat_applied], 'HQ signed it without VAT');

        // Registered today: the version HQ signed no longer has today's VAT, so it isn't sent
        $this->chargeVat('NG', '2026-11-05');
        $this->assertTrue(Sows::vatChanged($sow->fresh()));
        Livewire::test(SowTab::class, ['opportunityId' => $o->id])->set('sendTo', ['ngozi@kilo.test'])->call('send')
            ->assertSee('The VAT has changed since this version was signed for JBS Praxis.');
        try {
            Sows::send($sow->fresh(), ['ngozi@kilo.test'], $lead);
            $this->fail('Sent with the old VAT');
        } catch (LogicException) {
        }
        $this->assertSame(StatementOfWork::DRAFT, $sow->fresh()->status);

        // Back to draft (the VAT follows today's again), approved again with 7.5%, then sent
        $this->assertTrue(Sows::reopen($sow->fresh(), $lead));
        $this->assertNull($sow->fresh()->vat_applied);
        $this->assertNull(Sows::approve($sow->fresh(), $lead, 'Lead Test', self::PNG, '203.0.113.5'));
        $this->assertSame(['7.50', true], [$sow->fresh()->vat_rate, $sow->fresh()->vat_applied]);
        $this->assertSame([], Sows::send($sow->fresh(), ['ngozi@kilo.test'], $lead));
        $this->assertSame(StatementOfWork::SENT, $sow->fresh()->status);
    }

    public function test_each_company_decides_for_its_own_clients_only(): void
    {
        $ng = $this->organisation(['country' => 'Nigeria']);
        $cm = $this->organisation(['country' => 'Cameroun']);
        $gb = $this->organisation(['country' => 'United Kingdom']);
        $this->assertSame(['no_company', null], [Vat::for($ng)['why'], Vat::for($ng)['region']], 'No company at all: no VAT');

        $this->company('NG');
        $cmId = $this->company('CM');
        // Nigeria registered, Cameroon not: Nigeria's clients (and those of countries without a company) only
        $this->chargeVat('NG', '2026-11-01');
        $this->assertSame([true, 7.5, 'NG'], [Vat::for($ng)['applies'], Vat::for($ng)['rate'], Vat::for($ng)['region']]);
        $this->assertSame([false, 0.0, 'CM', 'off'], [Vat::for($cm)['applies'], Vat::for($cm)['rate'], Vat::for($cm)['region'], Vat::for($cm)['why']]);
        $this->assertSame([true, 7.5, 'NG'], [Vat::for($gb)['applies'], Vat::for($gb)['rate'], Vat::for($gb)['region']], 'Billed by the Nigerian company at its rate');
        $this->assertFalse(Vat::for($ng, '2026-10-31')['applies'], 'Before the start date');

        // Cameroon registered, Nigeria switched off: the other way round, at Cameroon's rate
        $this->chargeVat('CM', '2026-11-01');
        $this->chargeVat('NG', null);
        $this->assertSame([true, 19.25], [Vat::for($cm)['applies'], Vat::for($cm)['rate']]);
        $this->assertFalse(Vat::for($ng)['applies']);

        // A Cameroonian company without an active rate bills none; an inactive one leaves its clients to Nigeria's
        DB::table('mdm_tax_compliance')->where('country_code', 'CM')->update(['is_active' => 0]);
        $this->assertSame([false, 'no_rate'], [Vat::for($cm)['applies'], Vat::for($cm)['why']]);
        DB::table('mdm_legal_entities')->where('id', $cmId)->update(['is_active' => 0]);
        $this->assertSame('NG', Vat::for($cm)['region']);
    }

    public function test_only_people_who_may_edit_master_data_change_vat_and_each_change_is_audited(): void
    {
        $ng = $this->company('NG');
        $ke = $this->company('KE', ['legal_name' => 'JBS Praxis Kenya Ltd']);

        // No master data: no page. HR sees it, can't change VAT, and a forged request is refused
        $this->actingAs($this->staff('engineer', ['engineer']));
        $this->get(route('hq.mdm.legal'))->assertForbidden();
        $this->actingAs($this->staff('hr', ['hr']));
        $this->get(route('hq.mdm.legal'))->assertOk()->assertSee('No VAT');
        $page = Livewire::test(LegalEntityRegistry::class)->call('editEntity', $ng)->assertSee('Only people who may edit master data can change VAT.');
        $this->assertMatchesRegularExpression('/id="le-vat"[^>]*\sdisabled/', $page->html());
        $page->set('charges_vat', true)->set('vat_number', 'X')->set('vat_starts_on', '2026-12-01')->call('saveEntity')->assertForbidden();
        $this->assertSame(0, (int) DB::table('mdm_legal_entities')->where('id', $ng)->value('charges_vat'));

        // Finance switches it on: a number and a start date are needed; the change is audited and listed
        $finance = $this->staff('finance', ['finance']);
        $this->actingAs($finance);
        $page = Livewire::test(LegalEntityRegistry::class)->call('editEntity', $ng)
            ->assertSee('Switch on once the company is registered for VAT. Documents dated from the start date get VAT; anything already sent keeps what it had.')
            ->assertSee('Rate for Nigeria in Tax compliance: 7.5%.');
        $this->assertDoesNotMatchRegularExpression('/id="le-vat"[^>]*\sdisabled/', $page->html());
        $page->set('charges_vat', true)->call('saveEntity')
            ->assertHasErrors(['vat_number', 'vat_starts_on'])->assertSee('Enter the VAT number to switch VAT on.');
        $page->set('vat_number', '12345678-0001')->set('vat_starts_on', '2026-12-01')->call('saveEntity')->assertHasNoErrors()
            ->assertSee('Charges VAT from 1 December 2026')->assertSee('VAT no. 12345678-0001');
        $row = DB::table('mdm_legal_entities')->where('id', $ng)->first();
        $this->assertSame([1, '12345678-0001', '2026-12-01'], [(int) $row->charges_vat, $row->vat_number, substr((string) $row->vat_starts_on, 0, 10)]);
        $audit = DB::table('system_audit_logs')->where('action', 'LEGAL_ENTITY_VAT_CHANGED')->where('target_id', $ng)->sole();
        $this->assertSame($finance->id, $audit->user_id);
        $this->assertSame('JBS Praxis Nigeria Ltd (NG) now charges VAT from 2026-12-01 (VAT number 12345678-0001).', $audit->description);
        $this->assertEquals(['charges_vat' => false, 'vat_number' => null, 'vat_starts_on' => null], json_decode($audit->old_values, true)); // MySQL JSON reorders keys
        $this->assertEquals(['charges_vat' => true, 'vat_number' => '12345678-0001', 'vat_starts_on' => '2026-12-01'], json_decode($audit->new_values, true));

        // Off again: audited; saving without a VAT change writes no line
        $page->call('editEntity', $ng)->set('charges_vat', false)->call('saveEntity')->assertHasNoErrors();
        $this->assertSame('JBS Praxis Nigeria Ltd (NG) no longer charges VAT.', DB::table('system_audit_logs')->where('action', 'LEGAL_ENTITY_VAT_CHANGED')->orderByDesc('id')->value('description'));
        $page->call('editEntity', $ng)->set('legal_name', 'JBS Praxis Nigeria Limited')->call('saveEntity')->assertHasNoErrors();
        $this->assertSame(2, DB::table('system_audit_logs')->where('action', 'LEGAL_ENTITY_VAT_CHANGED')->count());

        // A country without an active rate in Tax compliance can't be switched on
        $page->call('editEntity', $ke)->set('charges_vat', true)->set('vat_number', 'KE-1')->set('vat_starts_on', '2026-12-01')->call('saveEntity')
            ->assertHasErrors('charges_vat')->assertSee('Add an active VAT rate for Kenya in Master data › Tax compliance first, then switch VAT on.');
        $this->assertSame(0, (int) DB::table('mdm_legal_entities')->where('id', $ke)->value('charges_vat'));
    }

    public function test_the_screen_and_the_documents_speak_french(): void
    {
        $ng = $this->company('NG');
        $this->chargeVat('NG', '2026-12-01');
        app()->setLocale('fr');
        $this->actingAs($this->staff('finance', ['finance']));
        Livewire::test(LegalEntityRegistry::class)->call('editEntity', $ng)
            ->assertSee('Facture la TVA')->assertSee('Numéro de TVA')->assertSee('Facture la TVA à partir du 1 décembre 2026');
        $this->assertSame("Pas de TVA\u{202F}: JBS Praxis Nigeria Ltd facture la TVA à partir du 1 décembre 2026.", Vat::describe(Vat::for($this->organisation(['country' => 'Nigeria']))));
    }

    public function test_the_vat_strings_have_the_same_keys_in_english_and_french(): void
    {
        $keys = fn (string $locale) => array_keys(\Illuminate\Support\Arr::dot(require lang_path("{$locale}/vat.php")));
        $this->assertSame($keys('en'), $keys('fr'));
    }
}
