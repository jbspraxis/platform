<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Finance\CashRequestManager;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Cash requests: only a request still awaiting approval can be approved or rejected, never by the person
 * who asked for the money, and each decision is in the audit log with the request it was about.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class CashRequestControlsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function cashRequest(User $requester, string $status = 'Pending Auth'): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('cash_requests', [
            'id' => $id, 'reference' => 'CR-TEST-' . strtoupper(Str::random(6)), 'requester_id' => $requester->id,
            'financial_dictionary_id' => DB::table('mdm_financial_dictionary')->value('id'),
            'amount_requested' => 120, 'purpose' => 'Taxi to the client site (test)', 'disbursement_method' => 'Cash',
            'status' => $status,
        ]);

        return $id;
    }

    public function test_nobody_decides_on_their_own_request(): void
    {
        $finance = $this->staff('finance', ['finance']);
        $own = $this->cashRequest($finance);
        $this->actingAs($finance);

        Livewire::test(CashRequestManager::class)
            ->call('openAuthModal', $own)->set('authorizer_signature', self::SIGNATURE)->call('authorizeRequest')
            ->assertHasErrors('decision')
            ->assertSee('You can’t approve or reject your own cash request. Ask another approver to review it.');
        Livewire::test(CashRequestManager::class)
            ->call('openAuthModal', $own)->call('rejectRequest')
            ->assertHasErrors('decision');

        $this->assertSame('Pending Auth', DB::table('cash_requests')->where('id', $own)->value('status'));
        $this->assertSame(0, DB::table('system_audit_logs')->where('target_table', 'cash_requests')->count());
    }

    public function test_only_a_request_awaiting_approval_can_be_decided(): void
    {
        $requester = $this->staff('engineer', ['engineer']);
        $decided = $this->cashRequest($requester);
        $this->actingAs($this->staff('finance', ['finance']));

        $screen = Livewire::test(CashRequestManager::class)->call('openAuthModal', $decided);
        DB::table('cash_requests')->where('id', $decided)->update(['status' => 'Rejected']);
        $screen->set('authorizer_signature', self::SIGNATURE)->call('authorizeRequest')
            ->assertHasErrors('decision')
            ->assertSee('This request has already been approved or rejected, so it was not changed.');

        $this->assertSame('Rejected', DB::table('cash_requests')->where('id', $decided)->value('status'));
        $this->assertNull(DB::table('cash_requests')->where('id', $decided)->value('authorizer_id'));
    }

    public function test_approvals_and_rejections_are_logged_with_the_request(): void
    {
        $requester = $this->staff('engineer', ['engineer']);
        $approved = $this->cashRequest($requester);
        $rejected = $this->cashRequest($requester);
        $finance = $this->staff('finance', ['finance']);
        $this->actingAs($finance);

        Livewire::test(CashRequestManager::class)
            ->call('openAuthModal', $approved)->set('authorizer_signature', self::SIGNATURE)->call('authorizeRequest')
            ->assertHasNoErrors();
        Livewire::test(CashRequestManager::class)->call('openAuthModal', $rejected)->call('rejectRequest')->assertHasNoErrors();

        $this->assertSame('Authorized', DB::table('cash_requests')->where('id', $approved)->value('status'));
        $this->assertSame('Rejected', DB::table('cash_requests')->where('id', $rejected)->value('status'));

        $approval = DB::table('system_audit_logs')->where('action', 'AUTHORIZED_CASH_REQUEST')->first();
        $this->assertSame($approved, $approval->target_id);
        $this->assertSame($finance->id, $approval->user_id);
        $this->assertEquals(['status' => 'Authorized'], json_decode($approval->new_values, true));

        $rejection = DB::table('system_audit_logs')->where('action', 'REJECTED_CASH_REQUEST')->first();
        $this->assertNotNull($rejection, 'a rejection is logged too');
        $this->assertSame($rejected, $rejection->target_id);
        $this->assertEquals(['status' => 'Pending Auth'], json_decode($rejection->old_values, true));
    }

    public function test_closing_a_request_is_logged_and_never_done_by_the_requester(): void
    {
        $finance = $this->staff('finance', ['finance']);
        $own = $this->cashRequest($finance, 'Authorized');
        $this->actingAs($finance);

        Livewire::test(CashRequestManager::class)->call('setTab', 'pending_clearance')->call('openClearanceModal', $own)
            ->assertSee('You can’t check the receipt for your own cash request. Ask another approver to close it.');
        Livewire::test(CashRequestManager::class)->call('setTab', 'pending_clearance')->call('openClearanceModal', $own)->call('clearLedger');
        $this->assertSame('Authorized', DB::table('cash_requests')->where('id', $own)->value('status'));

        $this->actingAs($this->staff('auditor', ['finance']));
        Livewire::test(CashRequestManager::class)->call('setTab', 'pending_clearance')->call('openClearanceModal', $own)->call('clearLedger')
            ->assertHasNoErrors();
        $this->assertSame('Cleared', DB::table('cash_requests')->where('id', $own)->value('status'));

        $log = DB::table('system_audit_logs')->where('target_table', 'cash_requests')->where('target_id', $own)->get();
        $this->assertSame(['CLEARED_CASH_REQUEST'], $log->pluck('action')->all());
        $this->assertEquals(['status' => 'Cleared'], json_decode($log[0]->new_values, true));
    }
}
