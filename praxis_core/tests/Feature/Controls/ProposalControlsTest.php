<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Intelligence\ProposalBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Proposals: whether a proposal is locked comes from the database, never from the page, so a signed
 * proposal can't be edited from the browser; signing and unlocking are in the audit log.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class ProposalControlsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function proposal(string $status, string $title = 'Signed scope (test)'): string
    {
        $orgId = (string) Str::uuid();
        $this->insertRow('organizations', [
            'id' => $orgId, 'name' => 'Proposal client (test)', 'status' => 'Lead',
            'assigned_to' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);
        $id = (string) Str::uuid();
        $this->insertRow('proposals', [
            'id' => $id, 'organization_id' => $orgId, 'title' => $title, 'proposal_ref' => 'SOW-TEST-' . strtoupper(Str::random(4)),
            'token_hash' => 'TKN-' . strtoupper(Str::random(10)), 'status' => $status, 'payment_schedule_type' => '40_30_30',
            'created_by' => null, 'project_id' => null,
        ]);
        DB::table('proposal_ai_narratives')->insert(['proposal_id' => $id, 'ai_executive_summary' => '', 'ai_operational_strategy' => '', 'ai_phases_json' => '[]', 'generated_at' => now()]);

        return $id;
    }

    public function test_the_browser_cannot_unlock_a_signed_proposal(): void
    {
        $signed = $this->proposal('Pending Auth');
        $this->actingAs($this->staff('sales', ['sales']));

        $screen = Livewire::test(ProposalBuilder::class, ['id' => $signed])->assertSet('isLocked', true);
        foreach (['isLocked' => false, 'proposalId' => (string) Str::uuid()] as $property => $value) {
            try {
                $screen->set($property, $value);
                $this->fail("{$property} should be locked");
            } catch (CannotUpdateLockedPropertyException) {
                // expected
            }
        }

        $screen->set('title', 'Changed after signing')->call('saveBlueprintProgress');
        $this->assertSame('Signed scope (test)', DB::table('proposals')->where('id', $signed)->value('title'));
    }

    public function test_a_proposal_signed_elsewhere_is_not_saved_over(): void
    {
        $draft = $this->proposal('Draft', 'Draft scope (test)');
        $this->actingAs($this->staff('sales', ['sales']));

        $screen = Livewire::test(ProposalBuilder::class, ['id' => $draft])->assertSet('isLocked', false);
        DB::table('proposals')->where('id', $draft)->update(['status' => 'Pending Auth']);
        $screen->set('title', 'Changed after signing')->call('switchTab', 'ledger');

        $this->assertSame('Draft scope (test)', DB::table('proposals')->where('id', $draft)->value('title'));
    }

    public function test_signing_and_unlocking_are_logged(): void
    {
        $draft = $this->proposal('Draft', 'Draft scope (test)');
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);

        Livewire::test(ProposalBuilder::class, ['id' => $draft])
            ->set('hqSignatureBase64', self::SIGNATURE)->call('compileAndSecure')->assertHasNoErrors()
            ->assertSet('isLocked', true)
            ->call('unlockProposal')->assertSet('isLocked', false);
        $this->assertSame('Draft', DB::table('proposals')->where('id', $draft)->value('status'));

        $log = DB::table('system_audit_logs')->where('target_table', 'proposals')->where('target_id', $draft)->orderBy('id')->get();
        $this->assertSame(['PROPOSAL_SIGNED_BY_HQ', 'PROPOSAL_UNLOCKED'], $log->pluck('action')->all());
        $this->assertSame([$sales->id, $sales->id], $log->pluck('user_id')->all());
        $this->assertEquals(['status' => 'Pending Auth'], json_decode($log[1]->old_values, true));
        $this->assertEquals(['status' => 'Draft'], json_decode($log[1]->new_values, true));
    }
}
