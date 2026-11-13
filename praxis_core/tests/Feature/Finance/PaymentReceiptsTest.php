<?php

namespace Tests\Feature\Finance;

use App\Livewire\ClientOs\Dashboard;
use App\Livewire\Hq\Finance\EscrowVerification;
use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Mail\ReceiptMail;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Finance\PaymentReceipts;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * A receipt for every payment (owner, 13 Nov 2026; docs/RECEIPTS.md): numbered in order, a PDF on the letterhead in
 * the client's language, emailed to the client's billing contacts as soon as the payment is recorded or verified,
 * sent again from HQ, downloaded in HQ and the portal by those who may.
 */
class PaymentReceiptsTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $org;
    private string $project;
    private ClientUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'Tales Consulting (test)', 'status' => 'Active Client', 'preferred_language' => 'EN',
            'country' => 'Nigeria', 'billing_address' => '51 George Tiene Crescent, Lagos', 'registration_number' => 'RC 7319824']);
        $this->project = $this->project(['organization_id' => $this->org, 'title' => 'Fidson FieldForce programme']);
        $this->admin = $this->contact('client-admin', 'bolu@tales.test', ['is_primary_client' => 1]);
        $this->contact('client-member', 'member@tales.test'); // follows the project, doesn't handle invoices
        $this->actingAs($this->staff('finance', ['finance']));
    }

    private function contact(string $role, string $email, array $values = []): ClientUser
    {
        return ClientUser::forceCreate($values + ['organization_id' => $this->org, 'role_id' => DB::table('roles')->where('audience', 'client')->where('slug', $role)->value('id'),
            'first_name' => Str::before($email, '@'), 'last_name' => 'Tales', 'email' => $email, 'password' => Str::random(24), 'status' => 'Active',
            'lifecycle_stage' => 'Active', 'is_primary_client' => 0]);
    }

    private function record(string $invoice, string $amount, string $reference)
    {
        return Livewire::test(InvoiceLedger::class)->call('openRecordPayment', $invoice)
            ->set('pay_amount', $amount)->set('pay_reference', $reference)->call('recordPayment')->assertHasNoErrors();
    }

    public function test_a_recorded_payment_gets_a_numbered_receipt_emailed_to_the_billing_contacts(): void
    {
        $invoice = $this->invoice($this->project, null, 'Pending', 900000);
        $month = now('Africa/Lagos')->format('Ym');

        $this->record($invoice, '300000', 'AAP-WALLET_T-55D6D')
            ->assertDispatched('praxis-toast', fn ($name, $params) => str_contains(json_encode($params), "Receipt RCT-{$month}-0001 is being emailed to bolu@tales.test."));
        $tx = DB::table('transactions')->where('invoice_id', $invoice)->first();
        $this->assertSame("RCT-{$month}-0001", $tx->receipt_number);

        // Emailed once the response is out, with its PDF, to the contacts who handle invoices only
        Mail::assertNothingSent();
        app(DeferredCallbackCollection::class)->invoke();
        Mail::assertSent(ReceiptMail::class, 1);
        $mail = Mail::sent(ReceiptMail::class)->first();
        $this->assertTrue($mail->hasTo('bolu@tales.test'));
        $this->assertStringStartsWith('%PDF', $mail->pdf);
        $this->assertSame("RCT-{$month}-0001.pdf", $mail->filename);
        $this->assertEquals(600000, $mail->left);
        $this->assertSame("Receipt RCT-{$month}-0001: payment received for invoice " . DB::table('invoices')->where('id', $invoice)->value('invoice_number'),
            $mail->locale('en')->envelope()->subject);
        $this->assertNotNull(DB::table('transactions')->where('id', $tx->id)->value('receipt_emailed_at'));

        // The receipt itself: who paid, how, and what is left
        [$html] = PaymentReceipts::html(PaymentReceipts::find($tx->id), 'en');
        $html = str_replace("\u{00A0}", ' ', $html); // amounts are written with a no-break space
        foreach (['Receipt', "RCT-{$month}-0001", 'Tales Consulting (test)', 'AAP-WALLET_T-55D6D', 'Bank transfer', 'NGN 300,000.00', 'NGN 600,000.00', 'Thank you for your payment.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // The second payment: the next number, the invoice paid
        $this->record($invoice, '600000', 'Second transfer');
        $second = DB::table('transactions')->where('invoice_id', $invoice)->where('tx_hash_or_ref', 'Second transfer')->first();
        $this->assertSame("RCT-{$month}-0002", $second->receipt_number);
        [$html] = PaymentReceipts::html(PaymentReceipts::find($second->id), 'en');
        $this->assertStringContainsString('is fully paid.', $html);
        // The first receipt, opened again, still shows the balance as it was then
        [$html] = PaymentReceipts::html(PaymentReceipts::find($tx->id), 'en');
        $this->assertStringContainsString('NGN 600,000.00', str_replace("\u{00A0}", ' ', $html));
        $this->assertStringNotContainsString('is fully paid.', $html);
    }

    public function test_a_verified_proof_of_payment_gets_its_receipt_and_hq_can_send_it_again(): void
    {
        $invoice = $this->invoice($this->project, null, 'Pending', 100000);
        $tx = (string) Str::uuid();
        $this->insertRow('transactions', ['id' => $tx, 'invoice_id' => $invoice, 'source' => 'portal', 'method' => 'Bank Transfer', 'tx_hash_or_ref' => 'Client ref 7788',
            'amount_claimed' => 100000, 'wht_deducted' => 0, 'tolerance_writeoff' => 0, 'status' => 'Pending Verification', 'client_user_id' => $this->admin->id]);
        $this->assertNull(DB::table('transactions')->where('id', $tx)->value('receipt_number'), 'No receipt before it is verified');

        Livewire::test(EscrowVerification::class)->call('openVerifyModal', $tx)->set('tolerance_writeoff', 0)->set('extend_guillotine_days', 0)
            ->call('approveTransaction')->assertHasNoErrors();
        $this->assertMatchesRegularExpression('/^RCT-\d{6}-0001$/', (string) DB::table('transactions')->where('id', $tx)->value('receipt_number'));
        app(DeferredCallbackCollection::class)->invoke();
        Mail::assertSent(ReceiptMail::class, fn ($m) => $m->hasTo('bolu@tales.test') && $m->left == 0.0);

        // Receipts in HQ: listed, sent again
        Livewire::test(InvoiceLedger::class)->call('setTab', 'Paid')->assertSee('Receipts')->call('openReceipts', $invoice)
            ->assertSee(DB::table('transactions')->where('id', $tx)->value('receipt_number'))->assertSee('Email again')
            ->call('emailReceipt', $tx)->assertDispatched('praxis-toast', fn ($name, $params) => str_contains(json_encode($params), 'Emailed with its PDF to bolu@tales.test.'));
        Mail::assertSent(ReceiptMail::class, 2);

        // Without the right to send, no sending
        $this->actingAs($this->staff('viewer', ['auditor']));
        Livewire::test(InvoiceLedger::class)->call('emailReceipt', $tx)->assertForbidden();
    }

    public function test_the_receipt_downloads_in_hq_and_in_the_portal_for_those_who_may_and_in_french(): void
    {
        $invoice = $this->invoice($this->project, null, 'Pending', 50000);
        Livewire::test(InvoiceLedger::class)->call('openRecordPayment', $invoice)->set('pay_amount', '50000')->set('pay_method', 'Cash')
            ->set('pay_reference', 'Cash at the office')->call('recordPayment')->assertHasNoErrors();
        $tx = DB::table('transactions')->where('invoice_id', $invoice)->value('id');

        $this->get(route('hq.finance.receipt-pdf', $tx))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // The portal: the invoice lists its receipt; billing contacts download it; others don't
        $portal = Livewire::actingAs($this->admin, 'client')->test(Dashboard::class)->call('switchTab', 'financials')->assertSee('Download receipt');
        $this->assertSame($tx, collect($portal->get('invoices'))->firstWhere('id', $invoice)->receipts[0]['id']);
        $this->actingAs($this->admin, 'client')->get(route('portal.receipt.pdf', $tx))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $member = ClientUser::where('email', 'member@tales.test')->first();
        $this->actingAs($member, 'client')->get(route('portal.receipt.pdf', $tx))->assertForbidden();
        $otherOrg = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $otherOrg, 'name' => 'Other Client', 'status' => 'Active Client', 'preferred_language' => 'EN']);
        $stranger = ClientUser::forceCreate(['organization_id' => $otherOrg, 'role_id' => Access::clientAdminRoleId(), 'first_name' => 'X', 'last_name' => 'Y',
            'email' => 'x@other.test', 'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1]);
        $this->actingAs($stranger, 'client')->get(route('portal.receipt.pdf', $tx))->assertNotFound();

        // A French client gets it in French
        [$html] = PaymentReceipts::html(PaymentReceipts::find($tx), 'fr');
        $this->assertStringContainsString('Reçu', $html);
        $this->assertStringContainsString('Règlement de la facture', $html);
        $this->assertStringContainsString('Espèces', $html);
        $this->assertStringContainsString('entièrement réglée', $html);
    }

    public function test_payments_verified_before_receipts_existed_get_numbers_in_order_without_an_email(): void
    {
        $invoice = $this->invoice($this->project, null, 'Pending', 300000);
        foreach ([['2026-10-30 09:00:00', 'Oct'], ['2026-11-02 09:00:00', 'Nov A'], ['2026-11-12 09:00:00', 'Nov B']] as [$at, $ref]) {
            $this->insertRow('transactions', ['id' => (string) Str::uuid(), 'invoice_id' => $invoice, 'source' => 'hq', 'method' => 'Bank Transfer', 'tx_hash_or_ref' => $ref,
                'amount_claimed' => 1000, 'wht_deducted' => 0, 'tolerance_writeoff' => 0, 'status' => 'Verified', 'verified_at' => $at, 'created_at' => $at]);
        }
        DB::table('transactions')->update(['receipt_number' => null]);

        $migration = require database_path('migrations/2026_11_13_950000_add_receipts_to_payments.php');
        $migration->down();
        $migration->up();

        $this->assertSame(['Oct' => 'RCT-202610-0001', 'Nov A' => 'RCT-202611-0001', 'Nov B' => 'RCT-202611-0002'],
            DB::table('transactions')->orderBy('verified_at')->pluck('receipt_number', 'tx_hash_or_ref')->all());
        Mail::assertNothingSent();
        // The next one this month follows them
        $this->assertSame('RCT-202611-0003', DB::transaction(function () use ($invoice) {
            $id = (string) Str::uuid();
            $this->insertRow('transactions', ['id' => $id, 'invoice_id' => $invoice, 'source' => 'hq', 'method' => 'Cash', 'tx_hash_or_ref' => 'Next',
                'amount_claimed' => 1000, 'wht_deducted' => 0, 'tolerance_writeoff' => 0, 'status' => 'Verified', 'verified_at' => '2026-11-13 09:00:00']);

            return PaymentReceipts::number($id);
        }));
    }
}
