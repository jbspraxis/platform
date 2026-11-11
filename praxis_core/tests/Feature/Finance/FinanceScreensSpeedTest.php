<?php

namespace Tests\Feature\Finance;

use App\Livewire\Hq\Finance\CashRequestManager;
use App\Livewire\Hq\Finance\CashRequestSubmitter;
use App\Livewire\Hq\Finance\ComplianceMatrix;
use App\Livewire\Hq\Finance\ExecutiveBiDashboard;
use App\Livewire\Hq\Finance\GeneralLedgerManager;
use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Livewire\Hq\Finance\ProjectBudgetManager;
use App\Mail\InvoiceMail;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Finance\InvoiceMailer;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The Finance screens' speed (performance pass, 11 Nov 2026). A screen's state goes to the browser and back on every
 * click, so it holds the filters and ids, never the lists: never the cash requests' signature images (2 MB for 55
 * closed requests, now under 1 KB), never every paid invoice (220 KB for 200). The general ledger reads a page of
 * entries and their lines in 3 queries (it was one per entry), the BI overview its figures in 3 (17), the budget lines
 * and the compliance matrix no longer query once per line or per employee, and the overdue run looks up the approvers
 * once and writes their notifications in one go.
 */
class FinanceScreensSpeedTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    /** A drawn signature is a 5-30 KB base64 PNG */
    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoSignatureImage';

    private function queries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function cashRequest(string $requesterId, string $status, int $n): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('cash_requests', ['id' => $id, 'reference' => 'CR-SPEED-' . $n, 'requester_id' => $requesterId,
            'financial_dictionary_id' => DB::table('mdm_financial_dictionary')->value('id'), 'amount_requested' => 10 + $n, 'currency' => 'USD',
            'purpose' => "Taxi {$n} (test)", 'disbursement_method' => 'Cash', 'status' => $status, 'created_at' => now()->subMinutes($n),
            'requester_signature' => str_repeat(self::SIGNATURE, 400), 'authorizer_signature' => $status === 'Pending Auth' ? null : str_repeat(self::SIGNATURE, 400)]);

        return $id;
    }

    public function test_cash_requests_never_carry_signatures_and_the_history_is_paged(): void
    {
        $requester = $this->staff('engineer', ['engineer']);
        $pending = $this->cashRequest($requester->id, 'Pending Auth', 0);
        foreach (range(1, 30) as $n) {
            $this->cashRequest($requester->id, 'Cleared', $n);
        }
        $this->actingAs($this->staff('finance', ['finance']));

        $screen = Livewire::test(CashRequestManager::class)->assertSee('CR-SPEED-0');
        $this->assertStringNotContainsString('SignatureImage', json_encode($screen->snapshot));
        $screen->call('setTab', 'history')->assertSee('CR-SPEED-1')->assertSee('CR-SPEED-25')->assertDontSee('CR-SPEED-26');
        $this->assertStringNotContainsString('SignatureImage', json_encode($screen->snapshot) . $screen->html());
        $this->assertLessThan(2000, strlen(json_encode($screen->snapshot)), 'Only the tab, the page and the dialog state');
        $screen->call('nextPage')->assertSee('CR-SPEED-26')->assertSee('CR-SPEED-30')->assertDontSee('CR-SPEED-25');

        // Approving: the signature drawn is sent with Approve request, stored with the request, and not kept in the page
        $screen->call('setTab', 'pending_auth')->call('openAuthModal', $pending)->assertSee('Review cash request CR-SPEED-0')
            ->set('authorizer_signature', 'data:image/png;base64,NewApproverSignature')->call('authorizeRequest')->assertHasNoErrors()
            ->assertSet('authorizer_signature', null);
        $this->assertSame('data:image/png;base64,NewApproverSignature', DB::table('cash_requests')->where('id', $pending)->value('authorizer_signature'));
        $this->assertSame('Authorized', DB::table('cash_requests')->where('id', $pending)->value('status'));

        // The person's own requests: what the list shows, without their signature
        $this->actingAs($requester);
        $mine = Livewire::test(CashRequestSubmitter::class);
        $this->assertStringNotContainsString('CR-SPEED', json_encode($mine->snapshot), 'My requests are read only when that tab is open');
        $mine->call('setTab', 'ledger')->assertSee('CR-SPEED-30');
        $this->assertStringNotContainsString('SignatureImage', json_encode($mine->snapshot) . $mine->html());
    }

    public function test_a_cash_request_notifies_every_approver_in_one_insert(): void
    {
        $this->staff('finance', ['finance']);
        $this->staff('auditor', ['finance']);
        $approvers = Access::staffWith('expenses.approve');
        $this->actingAs($this->staff('engineer', ['engineer']));

        $form = Livewire::test(CashRequestSubmitter::class)->set('financial_dictionary_id', DB::table('mdm_financial_dictionary')->value('id'))
            ->set('amount_requested', '25')->set('purpose', 'Printer ink (test)')->set('disbursement_method', 'Cash')
            ->set('requester_signature', self::SIGNATURE);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $form->call('submitRequest')->assertHasNoErrors()->assertSet('showSuccessModal', true)->assertSet('requester_signature', null);
        $inserts = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'insert into `notifications`'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $inserts);
        $this->assertCount(2, $approvers);
        $this->assertEqualsCanonicalizing($approvers, DB::table('notifications')->where('title', 'Cash request to approve')->pluck('user_id')->all());
        $this->assertSame(self::SIGNATURE, DB::table('cash_requests')->where('purpose', 'Printer ink (test)')->value('requester_signature'));
    }

    public function test_invoices_are_paged_and_typing_an_amount_costs_the_same_however_many_there_are(): void
    {
        $project = $this->project(['title' => 'Speed project']);
        $this->actingAs($this->staff('finance', ['finance']));
        $keystroke = function () use ($project) {
            $form = Livewire::test(InvoiceLedger::class)->set('showCreateModal', true)->set('new_project_id', $project)->call('addLine');

            return $this->queries(fn () => $form->set('lines.0.amount', '1250'));
        };

        $few = $keystroke();
        foreach (range(1, 60) as $n) {
            $this->invoice($project, null, 'Paid');
        }
        $this->assertSame($few, $keystroke(), 'No query per invoice');

        $list = Livewire::test(InvoiceLedger::class)->call('setTab', 'Paid');
        $this->assertSame(50, substr_count($list->html(), 'Download PDF'), 'A page of 50');
        $this->assertLessThan(2000, strlen(json_encode($list->snapshot)), 'The invoices are not kept in the page');
        $list->call('nextPage');
        $this->assertSame(10, substr_count($list->html(), 'Download PDF'));
    }

    public function test_the_overdue_run_looks_up_the_approvers_once_and_writes_their_notifications_in_one_insert(): void
    {
        $project = $this->project();
        foreach (range(1, 5) as $n) {
            DB::table('invoices')->where('id', $this->invoice($project, null, 'Pending'))->update(['due_date' => now()->subDays(3)->toDateString()]);
        }
        $this->actingAs($this->staff('finance', ['finance']));
        $this->staff('auditor', ['finance']);
        $approvers = Access::staffWith('finance.approve');
        $screen = Livewire::test(InvoiceLedger::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $screen->call('enforceGuillotineProtocol')->assertDispatched('praxis-toast', $this->toast('5 invoices were marked overdue.'));
        $log = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(5, DB::table('invoices')->where('status', 'Overdue')->count());
        $this->assertSame(1, $log->filter(fn ($q) => str_contains($q, 'inner join `staff_roles`'))->count(), 'The approvers, looked up once');
        $this->assertSame(1, $log->filter(fn ($q) => str_starts_with($q, 'insert into `notifications`'))->count());
        $this->assertCount(2, $approvers);
        $this->assertSame(5 * 2, DB::table('notifications')->where('title', 'Invoice overdue')->count());
        $this->assertEqualsCanonicalizing($approvers, DB::table('notifications')->where('title', 'Invoice overdue')->distinct()->pluck('user_id')->all());
    }

    public function test_the_general_ledger_reads_a_page_of_entries_and_their_lines_in_three_queries(): void
    {
        $accounts = DB::table('chart_of_accounts')->pluck('id', 'account_code');
        $entry = function (int $n) use ($accounts) {
            $id = (string) Str::uuid();
            DB::table('journal_entries')->insert(['id' => $id, 'reference_number' => 'JRN-SPEED-' . $n, 'entry_date' => now()->toDateString(),
                'description' => "Entry {$n}", 'source_module' => 'Invoicing', 'source_id' => (string) Str::uuid(), 'region_code' => 'NG', 'created_at' => now()->subMinutes($n)]);
            DB::table('journal_lines')->insert([
                ['journal_entry_id' => $id, 'account_id' => $accounts['4000'], 'memo' => "Revenue {$n}", 'debit_amount' => 0, 'credit_amount' => 100, 'created_at' => now()],
                ['journal_entry_id' => $id, 'account_id' => $accounts['1100'], 'memo' => "Receivable {$n}", 'debit_amount' => 100, 'credit_amount' => 0, 'created_at' => now()],
            ]);
        };
        $this->actingAs($this->staff('owner', ['owner']));

        foreach (range(1, 3) as $n) {
            $entry($n);
        }
        Livewire::test(GeneralLedgerManager::class); // the person's permissions, read once
        $this->assertSame(3, $this->queries(fn () => Livewire::test(GeneralLedgerManager::class)));
        foreach (range(4, 60) as $n) {
            $entry($n);
        }
        $this->assertSame(3, $this->queries(fn () => Livewire::test(GeneralLedgerManager::class)), 'The count, the page of entries, their lines');

        $ledger = Livewire::test(GeneralLedgerManager::class);
        $this->assertLessThan(2000, strlen(json_encode($ledger->snapshot)));
        $html = $ledger->html();
        $this->assertSame(50, substr_count($html, 'Reverse entry'));
        // Debits above credits within each entry, each entry with its own lines
        $this->assertLessThan(strpos($html, 'Revenue 1<'), strpos($html, 'Receivable 1<'));
        $this->assertStringNotContainsString('JRN-SPEED-51', $html);
        $ledger->call('nextPage')->assertSee('JRN-SPEED-51')->assertSee('Revenue 60');

        // The filters apply with Show entries, as before
        $ledger->set('regionFilter', 'CM')->call('triggerReversal', DB::table('journal_entries')->value('id'))->assertSee('JRN-SPEED-51');
        $ledger->call('filterLedger')->assertSee('No journal entries in this period');
    }

    public function test_the_bi_overview_reads_its_figures_in_three_queries(): void
    {
        $accounts = DB::table('chart_of_accounts')->pluck('id', 'account_code');
        $post = function (string $date, string $region, array $lines) use ($accounts) {
            $id = (string) Str::uuid();
            DB::table('journal_entries')->insert(['id' => $id, 'reference_number' => 'JRN-BI-' . Str::random(6), 'entry_date' => $date,
                'description' => 'BI (test)', 'source_module' => 'Test', 'region_code' => $region, 'created_at' => now()]);
            foreach ($lines as [$code, $debit, $credit]) {
                DB::table('journal_lines')->insert(['journal_entry_id' => $id, 'account_id' => $accounts[$code], 'debit_amount' => $debit, 'credit_amount' => $credit, 'created_at' => now()]);
            }
        };
        $today = now()->toDateString();
        $lastMonth = now()->startOfMonth()->subMonthNoOverflow()->addDays(4)->toDateString();
        $post($today, 'NG', [['1100', 1075.10, 0], ['4000', 0, 1000.10], ['2300', 0, 75]]);
        $post($today, 'CM', [['6300', 200.20, 0], ['1010', 0, 200.20]]);
        $post($lastMonth, 'NG', [['1010', 500, 0], ['1100', 0, 500], ['5100', 50.05, 0], ['1010', 0, 50.05]]);
        $this->actingAs($this->staff('owner', ['owner']));

        $bi = Livewire::test(ExecutiveBiDashboard::class);
        $this->assertSame(3, $this->queries(fn () => $bi->call('compileTelemetry')), 'The period by account group, the balances, six months of trends');

        $pnl = $bi->get('pnl');
        $this->assertSame([1000.10, 50.05, 950.05, 200.20, 749.85], [$pnl['total_revenue'], $pnl['cogs'], round($pnl['gross_profit'], 2), $pnl['opex'], round($pnl['net_income'], 2)]);
        $cash = $bi->get('cashFlow');
        $this->assertSame([-575.10, 75.0], [round($cash['adjustment_ar'], 2), round($cash['adjustment_liabilities'], 2)]);
        $kpis = $bi->get('kpis');
        $this->assertSame([249.75, 575.10], [round($kpis['cash_on_hand'], 2), round($kpis['outstanding_ar'], 2)]);
        $trends = collect($bi->get('trends'))->keyBy('month');
        $this->assertSame([1000.10, 200.20], [$trends[now()->format('M y')]['revenue'], $trends[now()->format('M y')]['expenses']]);
        $this->assertSame([0.0, 50.05], [$trends[now()->subMonthNoOverflow()->format('M y')]['revenue'], $trends[now()->subMonthNoOverflow()->format('M y')]['expenses']]);

        // One region: the statements follow it
        $bi->set('regionFilter', 'CM')->call('filterMatrix');
        $this->assertSame([0.0, 200.20], [$bi->get('pnl')['total_revenue'], $bi->get('pnl')['opex']]);
    }

    public function test_budget_lines_and_the_compliance_matrix_dont_query_once_per_row(): void
    {
        $project = $this->project(['title' => 'Budget speed']);
        DB::table('projects')->where('id', '!=', $project)->update(['created_at' => now()->subYear()]);
        DB::table('projects')->where('id', $project)->update(['created_at' => now()]);
        $items = DB::table('mdm_financial_dictionary')->orderBy('id')->limit(10)->pluck('id');
        $requester = $this->staff('engineer', ['engineer']);
        foreach ($items as $i => $item) {
            DB::table('project_budgets')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'dict_id' => $item, 'description' => "Line {$i}",
                'unit_price' => 100, 'total_allocated' => 1000, 'total_spent' => 100]);
        }
        $this->insertRow('cash_requests', ['id' => (string) Str::uuid(), 'reference' => 'CR-BUDGET-1', 'requester_id' => $requester->id, 'project_id' => $project,
            'financial_dictionary_id' => $items[0], 'amount_requested' => 150, 'purpose' => 'Held (test)', 'disbursement_method' => 'Cash', 'status' => 'Authorized']);
        $this->insertRow('cash_requests', ['id' => (string) Str::uuid(), 'reference' => 'CR-BUDGET-2', 'requester_id' => $requester->id, 'project_id' => $project,
            'financial_dictionary_id' => $items[0], 'amount_requested' => 999, 'purpose' => 'Closed (test)', 'disbursement_method' => 'Cash', 'status' => 'Cleared']);
        $this->actingAs($this->staff('owner', ['owner']));

        $budgets = Livewire::test(ProjectBudgetManager::class)->assertSee('Budget speed');
        $this->assertSame(4, $this->queries(fn () => $budgets->call('openSweepModal')), 'The projects, the project, its lines, the requests holding money');
        $this->assertStringContainsString('750.00 available', $budgets->html(), 'Line 0: 1,000 less 100 spent and 150 held');
        $this->assertLessThan(1000, strlen(json_encode($budgets->snapshot)));

        // Compliance matrix: two employees, then six; each extra one costs only its proration (2 queries)
        $employee = function (int $n) {
            $user = $this->staff('payroll' . $n, ['engineer']);
            $this->insertRow('mdm_employees', ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'employee_code' => 'E-SPEED-' . $n, 'job_classification' => 'Grade 1',
                'tax_region' => 'NG', 'is_active' => 1, 'line_manager_id' => null, 'created_by' => null, 'updated_by' => null, 'loan_advance_account_id' => null]);
        };
        $employee(1);
        $employee(2);
        $two = $this->queries(fn () => Livewire::test(ComplianceMatrix::class));
        foreach (range(3, 6) as $n) {
            $employee($n);
        }
        $six = $this->queries(fn () => Livewire::test(ComplianceMatrix::class));
        $this->assertSame(4 * 2, $six - $two);
        $this->assertSame(6, Livewire::test(ComplianceMatrix::class)->get('matrix_kpis')['headcount']);
    }

    public function test_an_invoice_emailed_after_the_response_goes_out_only_once_the_response_is_sent(): void
    {
        Mail::fake();
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'Later Client', 'status' => 'Active Client', 'preferred_language' => 'EN', 'country' => 'Nigeria']);
        ClientUser::forceCreate(['organization_id' => $org, 'role_id' => Access::clientAdminRoleId(), 'first_name' => 'Ada', 'last_name' => 'Later',
            'email' => 'ada@later.test', 'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1]);
        $invoice = $this->invoice($this->project(['organization_id' => $org]), null, 'Pending');

        InvoiceMailer::sendAfterResponse($invoice);
        Mail::assertNothingSent();
        $this->assertNull(DB::table('invoices')->where('id', $invoice)->value('emailed_at'));

        app(DeferredCallbackCollection::class)->invoke(); // what Laravel does once the response is sent
        Mail::assertSent(InvoiceMail::class, fn ($m) => $m->hasTo('ada@later.test') && str_starts_with((string) $m->pdf, '%PDF'));
        $this->assertNotNull(DB::table('invoices')->where('id', $invoice)->value('emailed_at'));
    }

    public function test_the_finance_filters_are_indexed(): void
    {
        $this->assertTrue(Schema::hasIndex('invoices', ['status', 'due_date']));
        $this->assertTrue(Schema::hasIndex('cash_requests', ['status', 'created_at']));
        $this->assertTrue(Schema::hasIndex('transactions', ['status', 'created_at']));
        $this->assertTrue(Schema::hasIndex('journal_entries', ['entry_date', 'created_at']));
        $this->assertTrue(Schema::hasIndex('journal_entries', ['source_module', 'source_id']));
    }
}
