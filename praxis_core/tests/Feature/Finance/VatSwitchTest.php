<?php

namespace Tests\Feature\Finance;

use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Livewire\Hq\Intelligence\ProposalBuilder;
use App\Livewire\Hq\Mdm\LegalEntityRegistry;
use App\Livewire\Hq\Mdm\TaxCompliance;
use App\Livewire\Hq\Opportunities\OpportunityBoard;
use App\Livewire\Hq\Opportunities\OpportunityPage;
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
        // An account in the project's currency held by its billing company (D8)
        $currency = DB::table('projects')->where('id', $projectId)->value('currency');
        $company = Vat::issuer(Vat::projectEntity($projectId))->id;
        $account = DB::table('mdm_remittance_accounts')->where('currency', $currency)->where('legal_entity_id', $company)->value('id')
            ?? DB::table('mdm_remittance_accounts')->insertGetId(['type' => 'Bank', 'provider_name' => 'Test bank', 'account_name' => 'JBS Praxis',
                'account_reference' => (string) random_int(1000, 99999), 'is_active' => 1, 'currency' => $currency, 'legal_entity_id' => $company]);
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

    public function test_the_billing_company_decides_and_a_client_abroad_is_zero_rated(): void
    {
        $ng = $this->organisation(['country' => 'Nigeria']);
        $cm = $this->organisation(['country' => 'Cameroun', 'tax_identification_number' => 'M0123456789X']);
        $this->assertSame(['no_company', null], [Vat::for($ng)['why'], Vat::for($ng)['region']], 'No company at all: no VAT');

        $ngId = $this->company('NG');
        $cmId = $this->company('CM');
        $this->assertNull(Vat::note(Vat::for($cm)), 'Switches off: no VAT line and no note');

        // Nigeria registered, billing by default: its Nigerian client at 7.5%; a Cameroonian client zero-rated, with the note
        $this->chargeVat('NG', '2026-11-01');
        $home = Vat::for($ng);
        $this->assertSame([true, 7.5, false, $ngId], [$home['applies'], $home['rate'], $home['zero_rated'], $home['entity_id']]);
        $abroad = Vat::for($cm);
        $this->assertSame([true, 0.0, true, 'NG'], [$abroad['applies'], $abroad['rate'], $abroad['zero_rated'], $abroad['region']]);
        $this->assertSame('Zero-rated export of services. Customer to account for any VAT under the reverse charge in their country. Customer’s VAT or tax number: M0123456789X.',
            Vat::note($abroad));
        $this->assertFalse(Vat::for($ng, '2026-10-31')['applies'], 'Before the start date');
        // Billed by the Cameroonian company, which doesn't charge VAT: nothing for either client
        $this->assertSame([false, 'off', 'CM'], [Vat::for($cm, null, $cmId)['applies'], Vat::for($cm, null, $cmId)['why'], Vat::for($cm, null, $cmId)['region']]);
        $this->assertNull(Vat::note(Vat::for($ng, null, $cmId)));

        // Cameroon registered, Nigeria off: Cameroon bills its client at 19.25% and a Nigerian client at 0%; the default bills none
        $this->chargeVat('CM', '2026-11-01');
        $this->chargeVat('NG', null);
        $this->assertSame([true, 19.25, false], [Vat::for($cm, null, $cmId)['applies'], Vat::for($cm, null, $cmId)['rate'], Vat::for($cm, null, $cmId)['zero_rated']]);
        $this->assertSame([true, 0.0, true], [Vat::for($ng, null, $cmId)['applies'], Vat::for($ng, null, $cmId)['rate'], Vat::for($ng, null, $cmId)['zero_rated']]);
        $this->assertFalse(Vat::for($cm)['applies']);

        // Without an active rate a company bills none; a chosen company that was deactivated leaves the work to Nigeria's
        DB::table('mdm_tax_compliance')->where('country_code', 'CM')->update(['is_active' => 0]);
        $this->assertSame([false, 'no_rate'], [Vat::for($cm, null, $cmId)['applies'], Vat::for($cm, null, $cmId)['why']]);
        DB::table('mdm_legal_entities')->where('id', $cmId)->update(['is_active' => 0]);
        $this->assertSame('NG', Vat::for($cm, null, $cmId)['region']);
    }

    public function test_the_start_date_turns_at_midnight_in_lagos(): void
    {
        $org = $this->organisation(['country' => 'Nigeria']);
        $this->company('NG');
        $this->chargeVat('NG', '2026-12-01');
        $this->assertFalse(Vat::for($org, CarbonImmutable::parse('2026-11-30 22:59:59', 'UTC'))['applies'], '23:59:59 on 30 November in Lagos');
        $this->assertTrue(Vat::for($org, CarbonImmutable::parse('2026-11-30 23:00:00', 'UTC'))['applies'], 'Midnight on 1 December in Lagos');
        $this->travelTo(CarbonImmutable::parse('2026-11-30 23:30:00', 'UTC'));
        $this->assertTrue(Vat::for($org)['applies'], 'Today is Lagos’s date');
    }

    public function test_the_billing_company_is_chosen_on_the_opportunity_and_documents_keep_their_issuer(): void
    {
        $ngId = $this->company('NG');
        $cmId = $this->company('CM');
        $this->chargeVat('CM', '2026-11-01', 'CM-NIU-0001');
        $lead = $this->staff('lead', ['sales']);
        $member = $this->staff('member', ['sales']);
        $org = $this->organisation(['name' => 'Delta SARL', 'country' => 'Cameroon']);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@kilo.test']);

        // The lead opens it, billed by the Cameroonian company; someone opening it for another lead can't choose
        $this->actingAs($lead);
        Livewire::test(OpportunityBoard::class)->call('openCreate', $org)->assertSee('Billing company')->assertSee('JBS Praxis Nigeria Ltd (default)')
            ->set('title', 'Payroll')->set('billingEntityId', $cmId)->call('create')->assertHasNoErrors();
        $o = Opportunity::where('title', 'Payroll')->sole();
        $this->assertSame($cmId, $o->billing_entity_id);
        $this->assertSame($cmId, json_decode(DB::table('system_audit_logs')->where('action', 'OPPORTUNITY_OPENED')->where('target_id', $o->id)->value('new_values'), true)['billing_entity_id']);
        $this->actingAs($member);
        Livewire::test(OpportunityBoard::class)->call('openCreate', $org)->set('title', 'Other')->set('leadUserId', $lead->id)->set('billingEntityId', $cmId)
            ->call('create')->assertHasErrors('billingEntityId');
        $this->assertFalse(Opportunity::where('title', 'Other')->exists());
        // A team member who isn't the lead can't change it on the edit form
        Livewire::test(OpportunityPage::class, ['opportunity' => $o])->call('edit')->set('billingEntityId', '')->call('saveDetails')
            ->assertHasErrors('billingEntityId')->assertSee('Only the owner or the opportunity’s lead chooses the billing company.');
        $this->assertSame($cmId, $o->fresh()->billing_entity_id);

        // The documents: issued by the Cameroonian company at its rate, and each records it
        $this->actingAs($lead);
        $sent = $this->sendProposal($this->draftProposal($o, $lead), $lead);
        $this->assertSame([$cmId, '19.25', true, '1192.50'], [$sent->billing_entity_id, $sent->vat_rate, $sent->vat_applied, $sent->total_value]);
        $this->assertTrue(Proposals::approveForClient($sent, ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'CFO'));
        $sow = $this->approvedSow($o, $lead);
        $this->assertSame([$cmId, 'JBS Praxis Cameroun SARL', '19.25'], [$sow->billing_entity_id, $sow->provider_legal_name, $sow->vat_rate]);
        $this->assertSame('JBS Praxis Cameroun SARL', Sows::partyName($sow));

        // The lead moves the work to Nigeria's company (the default) and records the client's tax number, each with a history line
        Livewire::test(OpportunityPage::class, ['opportunity' => $o])->call('edit')->assertSet('billingEntityId', $cmId)
            ->set('billingEntityId', '')->set('clientTaxId', 'M0123456789X')->call('saveDetails')->assertHasNoErrors()
            ->assertSee('JBS Praxis Nigeria Ltd');
        $this->assertNull($o->fresh()->billing_entity_id);
        $this->assertSame('Changed the billing company of “Payroll” from JBS Praxis Cameroun SARL to JBS Praxis Nigeria Ltd.',
            DB::table('system_audit_logs')->where('action', 'OPPORTUNITY_BILLING_COMPANY_CHANGED')->value('description'));
        $this->assertSame('M0123456789X', DB::table('organizations')->where('id', $org)->value('tax_identification_number'));
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'ORGANISATION_TAX_ID_CHANGED')->where('target_id', $o->id)->exists());

        // What was issued keeps its company; the SOW HQ signed for Cameroon must be signed again, then by Nigeria's company
        $this->assertSame([$cmId, 19.25, 'JBS Praxis Cameroun SARL'], [Proposals::vat($sent->fresh())['entity_id'], Proposals::vat($sent->fresh())['rate'], Proposals::vat($sent->fresh())['company']]);
        $this->assertTrue(Sows::vatChanged($sow->fresh()));
        $this->assertTrue(Sows::reopen($sow->fresh(), $lead));
        $this->assertNull(Sows::approve($sow->fresh(), $lead, 'Lead Test', self::PNG, '203.0.113.5'));
        $this->assertSame([$ngId, 'JBS Praxis Nigeria Ltd', false], [$sow->fresh()->billing_entity_id, $sow->fresh()->provider_legal_name, $sow->fresh()->vat_applied]);
        $sow = $this->signSow($sow->fresh(), $lead);
        $deposit = DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first();
        $this->assertSame([$ngId, 'NG', '0.00'], [$deposit->billing_entity_id, $deposit->region_code, $deposit->vat_amount]);
    }

    public function test_a_client_abroad_gets_zero_rated_documents_with_the_note_only_while_vat_is_on(): void
    {
        $ngId = $this->company('NG');
        $this->chargeVat('NG', '2026-11-01');
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead, 'Cameroon');
        DB::table('organizations')->where('id', $o->organization_id)->update(['tax_identification_number' => 'M0123456789X']);
        $note = 'Zero-rated export of services. Customer to account for any VAT under the reverse charge in their country.';

        $p = $this->draftProposal($o, $lead);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSee('VAT (0%)')->assertSee($note)->assertSee('M0123456789X');
        $p = $this->sendProposal($p, $lead);
        $this->assertSame([$ngId, '0.00', true, '1000.00'], [$p->billing_entity_id, $p->vat_rate, $p->vat_applied, $p->total_value]);
        $html = view('pdf.proposal', (new \ReflectionMethod(ProposalPdf::class, 'data'))->invoke(null, $p, 'en'))->render();
        $this->assertStringContainsString('VAT (0%)', $html);
        $this->assertStringContainsString($note, $html);

        $this->assertTrue(Proposals::approveForClient($p, ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'CFO'));
        $sow = $this->signSow($this->approvedSow($o, $lead), $lead);
        $this->assertSame(['0.00', true, $ngId], [$sow->vat_rate, $sow->vat_applied, $sow->billing_entity_id]);
        $this->assertStringContainsString($note, view('pdf.sow', (new \ReflectionMethod(SowPdf::class, 'data'))->invoke(null, $sow, 'en'))->render());
        $deposit = DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first();
        $this->assertSame(['400.00', '0.00', '400.00', '0.00', 1], [$deposit->amount_due, $deposit->vat_amount, $deposit->total_amount, $deposit->vat_rate, $deposit->vat_applied]);

        // In French, and not at all while the switch is off
        app()->setLocale('fr');
        $this->assertStringStartsWith('Exportation de services au taux zéro.', Vat::note(Proposals::vat($p->fresh())));
        app()->setLocale('en');
        $this->chargeVat('NG', null);
        $other = $this->opportunity($lead, 'Cameroon', 'bola@lima.test');
        $this->draftProposal($other, $lead);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $other->id])->assertDontSee('VAT (')->assertDontSee($note);
    }

    public function test_a_rate_in_use_and_a_company_billing_open_work_are_protected(): void
    {
        $ngId = $this->company('NG');
        $cmId = $this->company('CM');
        $this->chargeVat('NG', '2026-11-01');
        $this->actingAs($this->staff('finance', ['finance']));

        // Nigeria's company charges VAT: its rate can't be deactivated or set to 0; Cameroon's can
        $ngRate = DB::table('mdm_tax_compliance')->where('country_code', 'NG')->value('id');
        Livewire::test(TaxCompliance::class)->call('toggleTaxStatus', $ngRate, 1)->assertHasErrors('tax_guard')
            ->assertSee('JBS Praxis Nigeria Ltd charges VAT at this rate.');
        Livewire::test(TaxCompliance::class)->call('editTax', $ngRate)->set('vat_rate', 0)->call('saveTax')->assertHasErrors('vat_rate');
        $this->assertSame([1, '7.50'], [(int) DB::table('mdm_tax_compliance')->where('id', $ngRate)->value('is_active'), DB::table('mdm_tax_compliance')->where('id', $ngRate)->value('vat_rate')]);
        $cmRate = DB::table('mdm_tax_compliance')->where('country_code', 'CM')->value('id');
        Livewire::test(TaxCompliance::class)->call('toggleTaxStatus', $cmRate, 1)->assertHasNoErrors();
        $this->assertSame(0, (int) DB::table('mdm_tax_compliance')->where('id', $cmRate)->value('is_active'));

        // A company billing open work stays active; once that work is closed it can go, with an audit line
        $org = $this->organisation(['country' => 'Cameroon']);
        $o = $this->opportunityFor($org, ['stage' => 'proposal', 'billing_entity_id' => $cmId]);
        Livewire::test(LegalEntityRegistry::class)->call('toggleStatus', $cmId, 1)->assertHasErrors('form_error')
            ->assertSee('JBS Praxis Cameroun SARL bills 1 open opportunity, so it stays active.');
        $o->forceFill(['stage' => 'lost'])->save();
        Livewire::test(LegalEntityRegistry::class)->call('toggleStatus', $cmId, 1)->assertHasNoErrors();
        $this->assertSame(0, (int) DB::table('mdm_legal_entities')->where('id', $cmId)->value('is_active'));
        $this->assertSame('JBS Praxis Cameroun SARL (CM) deactivated.', DB::table('system_audit_logs')->where('action', 'LEGAL_ENTITY_DEACTIVATED')->value('description'));
        // Nigeria's company also bills every open opportunity without a billing company
        $this->opportunityFor($org, ['stage' => 'discovery']);
        Livewire::test(LegalEntityRegistry::class)->call('toggleStatus', $ngId, 1)->assertHasErrors('form_error');
        $this->assertSame(1, (int) DB::table('mdm_legal_entities')->where('id', $ngId)->value('is_active'));
    }

    public function test_switching_vat_on_while_adding_a_company_needs_master_data_editing(): void
    {
        $role = $this->insertRow('roles', ['name' => 'Master data clerk', 'slug' => 'mdm-clerk', 'audience' => 'staff'])['id'] ?? DB::table('roles')->where('slug', 'mdm-clerk')->value('id');
        foreach (['mdm.view', 'mdm.create'] as $slug) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')]);
        }
        $this->actingAs($this->staff('clerk', ['mdm-clerk']));
        $fill = fn ($page) => $page->set('region_code', 'KE')->set('legal_name', 'JBS Praxis Kenya Ltd')->set('registration_number', 'RC-KE')
            ->set('tax_identification_number', 'TIN-KE')->set('employer_social_security_code', 'SS-KE')->set('registered_address', 'Nairobi')
            ->set('legal_representative_name', 'Ada Director')->set('legal_representative_title', 'Managing Director');

        $fill(Livewire::test(LegalEntityRegistry::class))->set('charges_vat', true)->set('vat_number', 'KE-1')->set('vat_starts_on', '2026-12-01')
            ->call('saveEntity')->assertForbidden();
        $this->assertFalse(DB::table('mdm_legal_entities')->where('region_code', 'KE')->exists());
        $fill(Livewire::test(LegalEntityRegistry::class))->call('saveEntity')->assertHasNoErrors();
        $this->assertSame(0, (int) DB::table('mdm_legal_entities')->where('region_code', 'KE')->value('charges_vat'));
    }

    public function test_the_legacy_builder_keeps_what_isnt_a_draft_and_signs_with_the_vat_of_the_day(): void
    {
        $ngId = $this->company('NG');
        $this->chargeVat('NG', '2026-11-01');
        $org = $this->organisation(['country' => 'Nigeria']);
        $legacy = function (string $status, array $values = []) use ($org) {
            $id = (string) Str::uuid();
            $this->insertRow('proposals', $values + ['id' => $id, 'organization_id' => $org, 'title' => "Legacy {$status}", 'status' => $status, 'signing_mode' => 'legacy',
                'proposal_ref' => 'SOW-' . Str::random(6), 'token_hash' => 'TKN-' . Str::random(10), 'payment_schedule_type' => '40_30_30',
                'subtotal' => 1000, 'vat_rate' => 0, 'vat_applied' => 0, 'total_value' => 1000]);
            DB::table('proposal_ai_narratives')->insert(['proposal_id' => $id, 'ai_executive_summary' => '', 'ai_operational_strategy' => '', 'ai_phases_json' => '[]', 'generated_at' => now()]);
            DB::table('proposal_line_items')->insert(['proposal_id' => $id, 'code' => 'CUSTOM', 'description' => 'Work', 'quantity' => '1', 'unit_price' => 1000, 'total_price' => 1000, 'sort_order' => 0]);

            return $id;
        };

        // Rejected before the switch: opening and leaving it (which saves an unlocked one) never gives it VAT
        $rejected = $legacy('Rejected');
        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(ProposalBuilder::class, ['id' => $rejected])->assertSet('vatApplies', false)->assertSet('vatRate', 0.0)->call('switchTab', 'ledger');
        $this->assertSame(['0.00', 0, '1000.00'], [DB::table('proposals')->where('id', $rejected)->value('vat_rate'),
            (int) DB::table('proposals')->where('id', $rejected)->value('vat_applied'), DB::table('proposals')->where('id', $rejected)->value('total_value')]);

        // Signed by someone who may approve but not edit: the VAT of the day is written with the signature, on the saved lines
        $role = $this->insertRow('roles', ['name' => 'Signer', 'slug' => 'signer', 'audience' => 'staff'])['id'] ?? DB::table('roles')->where('slug', 'signer')->value('id');
        foreach (['crm.view', 'crm.approve'] as $slug) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')]);
        }
        $draft = $legacy('Draft');
        $this->actingAs($this->staff('signer', ['signer']));
        Livewire::test(ProposalBuilder::class, ['id' => $draft])->set('hqSignatureBase64', self::PNG)->call('compileAndSecure')->assertHasNoErrors();
        $row = DB::table('proposals')->where('id', $draft)->first();
        $this->assertSame(['Pending Auth', '7.50', 1, '1075.00', $ngId], [$row->status, $row->vat_rate, (int) $row->vat_applied, $row->total_value, $row->billing_entity_id]);
    }

    public function test_the_migration_keeps_what_was_issued_and_resets_only_what_was_never_sent(): void
    {
        $org = $this->organisation(['country' => 'Nigeria']);
        $o = $this->opportunityFor($org);
        $before = '2026-10-20 10:00:00';
        $proposal = function (array $values) use ($org, $before) {
            $id = (string) Str::uuid();
            $this->insertRow('proposals', $values + ['id' => $id, 'organization_id' => $org, 'payment_schedule_type' => '40_30_30',
                'subtotal' => 1000, 'vat_rate' => 7.5, 'total_value' => 1075, 'created_at' => $before, 'updated_at' => $before]);

            return $id;
        };
        $sent = $proposal(['status' => 'Sent', 'signing_mode' => 'approve', 'sent_at' => $before]);
        $pending = $proposal(['status' => 'Pending Auth', 'signing_mode' => 'legacy']);
        $approved = $proposal(['status' => 'Client Approved', 'signing_mode' => 'approve', 'sent_at' => $before]);
        $hqApproved = $proposal(['status' => 'Draft', 'signing_mode' => 'approve', 'approved_at' => $before]);
        $draft = $proposal(['status' => 'Draft', 'signing_mode' => 'approve']);
        $sow = fn (array $values) => tap(StatementOfWork::create($values + ['opportunity_id' => $o->id, 'reference' => 'SOW-' . Str::random(4), 'version' => 1]),
            fn ($s) => DB::table('statements_of_work')->where('id', $s->id)->update(['updated_at' => $before]))->id;
        $sowApproved = $sow(['proposal_id' => $approved, 'status' => 'draft', 'approved_at' => $before]);
        $sowImported = $sow(['status' => 'signed', 'signed_at' => $before]);
        $sowDraft = $sow(['proposal_id' => $approved, 'status' => 'draft']);
        $project = (string) Str::uuid();
        $this->insertRow('projects', ['id' => $project, 'organization_id' => $org, 'title' => 'P', 'total_budget' => 1000, 'currency' => 'NGN']);
        $invoice = (string) Str::uuid();
        $this->insertRow('invoices', ['id' => $invoice, 'project_id' => $project, 'invoice_number' => 'INV-OLD-1', 'amount_due' => 1000, 'vat_amount' => 75,
            'total_amount' => 1075, 'currency' => 'NGN', 'due_date' => '2026-11-01', 'updated_at' => $before]);

        (require database_path('migrations/2026_11_01_970000_add_vat_switch_per_company.php'))->backfill();

        $p = fn ($id) => DB::table('proposals')->where('id', $id)->first();
        foreach ([$sent, $pending, $approved] as $id) {
            $this->assertSame(['7.50', 1, '1075.00', $before], [$p($id)->vat_rate, $p($id)->vat_applied, $p($id)->total_value, $p($id)->updated_at], 'Issued: kept');
        }
        foreach ([$hqApproved, $draft] as $id) {
            $this->assertSame(['0.00', 0, '1000.00', $before], [$p($id)->vat_rate, $p($id)->vat_applied, $p($id)->total_value, $p($id)->updated_at], 'Never sent: no VAT');
        }
        $s = fn ($id) => DB::table('statements_of_work')->where('id', $id)->first();
        $this->assertSame(['7.50', 1, $before], [$s($sowApproved)->vat_rate, $s($sowApproved)->vat_applied, $s($sowApproved)->updated_at], 'Signed by HQ: its proposal’s VAT');
        $this->assertSame(['0.00', 0], [$s($sowImported)->vat_rate, $s($sowImported)->vat_applied], 'Imported: none');
        $this->assertSame([null, null], [$s($sowDraft)->vat_rate, $s($sowDraft)->vat_applied], 'A draft follows the switch');
        $i = DB::table('invoices')->where('id', $invoice)->first();
        $this->assertSame(['1000.00', '75.00', '1075.00', null, null, $before], [$i->amount_due, $i->vat_amount, $i->total_amount, $i->vat_rate, $i->vat_applied, $i->updated_at]);
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
