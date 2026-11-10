<?php

namespace Tests\Feature\Finance;

use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Mail\InvoiceMail;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Finance\InvoicePdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Invoices as the client gets them (owner, 2 Nov 2026; docs/QA-PORTAL.md D11): a PDF titled "Invoice" unless it
 * carries VAT, with the billing company and the client and their tax numbers, the lines and the accounts to pay into
 * (D8); downloaded in HQ and in the portal, and emailed with the PDF to the client's billing contacts when issued.
 */
class InvoicePdfTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $ng;

    private string $org;

    private string $project;

    private string $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('mdm_legal_entities')->where('region_code', 'NG')->update(['region_code' => 'XX']);
        $this->ng = (string) Str::uuid();
        $this->insertRow('mdm_legal_entities', ['id' => $this->ng, 'region_code' => 'NG', 'legal_name' => 'JBS Praxis LLC', 'is_active' => 1,
            'registered_address' => '21B Bode Ajakaiye Street, Lekki, Lagos', 'tax_identification_number' => 'TIN-JBS-1']);
        DB::table('mdm_remittance_accounts')->delete();
        foreach ([['UBA', '1023456789', 'NGN', $this->ng], ['Other', '5550001111', 'USD', $this->ng]] as [$bank, $ref, $currency, $entity]) {
            DB::table('mdm_remittance_accounts')->insert(['type' => 'Bank', 'provider_name' => $bank, 'account_name' => 'JBS Praxis LLC',
                'account_reference' => $ref, 'currency' => $currency, 'legal_entity_id' => $entity, 'is_active' => 1]);
        }
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'City Client', 'status' => 'Active Client', 'preferred_language' => 'EN',
            'country' => 'Nigeria', 'tax_identification_number' => 'TIN-CITY-9']);
        $this->project = $this->project(['organization_id' => $this->org, 'title' => 'Fleet portal']);
        $this->invoice = $this->invoice($this->project, null, 'Pending', 250000);
        DB::table('invoice_lines')->insert(['invoice_id' => $this->invoice, 'description' => 'M1 · Design, less the deposit’s 40%', 'quantity' => 1, 'unit_price' => 250000, 'total_price' => 250000]);
    }

    private function contact(string $role, string $email, array $values = []): ClientUser
    {
        return ClientUser::forceCreate($values + [
            'organization_id' => $this->org, 'role_id' => DB::table('roles')->where('slug', $role)->value('id'), 'first_name' => ucfirst(strtok($email, '@')),
            'last_name' => 'City', 'email' => $email, 'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 0,
        ]);
    }

    private function invoiceRow(): object
    {
        return DB::table('invoices')->where('id', $this->invoice)->first();
    }

    public function test_it_says_invoice_without_vat_with_both_tax_numbers_and_only_the_matching_account(): void
    {
        [$html] = InvoicePdf::html($this->invoiceRow());

        $this->assertStringContainsString('<h1>Invoice</h1>', $html);
        $this->assertStringNotContainsString('Tax invoice', $html);
        foreach (['JBS Praxis LLC', '21B Bode Ajakaiye Street', 'Tax number: TIN-JBS-1', 'City Client', 'Tax number: TIN-CITY-9', 'Fleet portal',
                     'M1 · Design, less the deposit’s 40%', 'UBA', '1023456789'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertStringNotContainsString('5550001111', $html, 'A USD account is not for a naira invoice');
        $this->assertStringNotContainsString('VAT (', $html);
        $this->assertStringStartsWith('%PDF', InvoicePdf::render($this->invoiceRow()));
        $this->assertSame(InvoicePdf::filename($this->invoiceRow()), str_replace('/', '-', $this->invoiceRow()->invoice_number) . '.pdf');

        // With VAT it is a tax invoice, with the VAT line; in French for a French-speaking client
        DB::table('mdm_legal_entities')->where('id', $this->ng)->update(['vat_number' => 'VAT-77']);
        DB::table('invoices')->where('id', $this->invoice)->update(['vat_applied' => 1, 'vat_rate' => 7.5, 'vat_amount' => 18750, 'total_amount' => 268750, 'billing_entity_id' => $this->ng]);
        [$html] = InvoicePdf::html($this->invoiceRow());
        $this->assertStringContainsString('<h1>Tax invoice</h1>', $html);
        $this->assertStringContainsString('VAT number: VAT-77', $html);
        DB::table('organizations')->where('id', $this->org)->update(['preferred_language' => 'FR']);
        [$html] = InvoicePdf::html($this->invoiceRow());
        $this->assertStringContainsString('<h1>Facture</h1>', $html);
        $this->assertStringContainsString('Comment régler', $html);
    }

    /** Owner, 10 Nov 2026: "on invoice we don't need two accounts"; the client portal keeps listing every one */
    public function test_it_shows_only_the_account_the_invoice_names_and_every_matching_one_when_it_names_none(): void
    {
        DB::table('mdm_remittance_accounts')->insert(['type' => 'Bank', 'provider_name' => 'Nombank MFB', 'account_name' => 'JBS Praxis LLC',
            'account_reference' => '7770002222', 'currency' => 'NGN', 'legal_entity_id' => $this->ng, 'is_active' => 1]);
        $uba = DB::table('mdm_remittance_accounts')->where('account_reference', '1023456789')->value('id');
        $nombank = DB::table('mdm_remittance_accounts')->where('account_reference', '7770002222')->value('id');

        // Names none: both naira accounts, as before
        [$html] = InvoicePdf::html($this->invoiceRow());
        $this->assertStringContainsString('1023456789', $html);
        $this->assertStringContainsString('7770002222', $html);

        // Names Nombank: Nombank alone
        DB::table('invoices')->where('id', $this->invoice)->update(['remittance_account_id' => $nombank]);
        [$html] = InvoicePdf::html($this->invoiceRow());
        $this->assertStringContainsString('Nombank MFB', $html);
        $this->assertStringContainsString('7770002222', $html);
        $this->assertStringNotContainsString('1023456789', $html, 'The other naira account is not on the invoice');

        // The portal still lists both for the project
        $project = DB::table('projects')->where('id', $this->project)->first();
        $this->assertEqualsCanonicalizing([$uba, $nombank], \App\Support\Finance\ReceivingAccounts::forProject($project)->pluck('id')->all());

        // Nombank deactivated since: the client still has somewhere to pay
        DB::table('mdm_remittance_accounts')->where('id', $nombank)->update(['is_active' => 0]);
        [$html] = InvoicePdf::html($this->invoiceRow());
        $this->assertStringContainsString('1023456789', $html);
        $this->assertStringNotContainsString('7770002222', $html);
    }

    public function test_hq_downloads_it_with_finance_view_and_the_portal_for_billing_contacts_of_that_project(): void
    {
        $this->actingAs($this->staff('finance', ['finance']));
        $this->get(route('hq.finance.invoice-pdf', $this->invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->staff('engineer', ['engineer']));
        $this->get(route('hq.finance.invoice-pdf', $this->invoice))->assertForbidden();

        $admin = $this->contact('client-admin', 'ada@city.test', ['is_primary_client' => 1]);
        $member = $this->contact('client-member', 'bayo@city.test');
        $elsewhere = $this->contact('client-finance', 'chi@city.test', ['restricted_project_id' => $this->project(['organization_id' => $this->org])]);
        $this->actingAs($member, 'client')->get(route('portal.invoice.pdf', $this->invoice))->assertForbidden();
        $this->actingAs($elsewhere, 'client')->get(route('portal.invoice.pdf', $this->invoice))->assertNotFound();
        $this->assertNull($this->invoiceRow()->client_viewed_at);
        $this->actingAs($admin, 'client')->get(route('portal.invoice.pdf', $this->invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotNull($this->invoiceRow()->client_viewed_at);

        // Another organisation's invoice: not found
        $other = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $other, 'name' => 'Other Client', 'status' => 'Active Client', 'preferred_language' => 'EN']);
        $theirs = $this->invoice($this->project(['organization_id' => $other]), null, 'Pending');
        $this->actingAs($admin, 'client')->get(route('portal.invoice.pdf', $theirs))->assertNotFound();
    }

    public function test_an_issued_invoice_is_emailed_with_its_pdf_to_the_billing_contacts_and_can_be_sent_again(): void
    {
        Mail::fake();
        $this->contact('client-admin', 'ada@city.test', ['is_primary_client' => 1]);
        $this->contact('client-finance', 'fin@city.test');
        $this->contact('client-member', 'bayo@city.test');
        $this->contact('client-finance', 'gone@city.test', ['status' => 'Suspended']);
        $this->actingAs($this->staff('finance', ['finance']));

        Livewire::test(InvoiceLedger::class)->set('showCreateModal', true)->set('new_project_id', $this->project)
            ->call('addLine')->set('lines.0.description', 'M2')->set('lines.0.amount', 100000)->call('draftSubsequentInvoice')->assertHasNoErrors()
            ->assertDispatched('praxis-toast', $this->toast('Emailed with its PDF to ada@city.test, fin@city.test.'));
        $new = DB::table('invoices')->where('project_id', $this->project)->where('invoice_type', 'Tax Invoice - Phase Milestone')->first();
        $sent = Mail::sent(InvoiceMail::class);
        $this->assertEqualsCanonicalizing(['ada@city.test', 'fin@city.test'], $sent->map(fn ($m) => $m->to[0]['address'])->all());
        $this->assertSame($new->invoice_number, $sent->first()->invoice->invoice_number);
        $this->assertStringStartsWith('%PDF', $sent->first()->pdf);
        $this->assertSame('Invoice ' . $new->invoice_number . ': Fleet portal', $sent->first()->locale('en')->envelope()->subject);
        $this->assertNotNull(DB::table('invoices')->where('id', $new->id)->value('emailed_at'));

        // Email again, from the list
        Livewire::test(InvoiceLedger::class)->call('emailInvoice', $new->id);
        Mail::assertSentCount(4);
        // Nobody to send to: said plainly
        ClientUser::where('organization_id', $this->org)->update(['status' => 'Suspended']);
        Access::flush();
        Livewire::test(InvoiceLedger::class)->call('emailInvoice', $new->id)->assertDispatched('praxis-toast', $this->toast('Nobody at the client was emailed'));
        Mail::assertSentCount(4);
    }
}
