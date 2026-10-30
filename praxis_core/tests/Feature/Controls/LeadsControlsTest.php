<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Intelligence\LeadsRegister;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Leads: merging two assessments writes only the whitelisted columns (the browser can't name others),
 * the merge is in the audit log, a lead only moves to one of the register's own stages, and a lead someone
 * owns can't be taken over by another "Assign to me".
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadsControlsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private string $orgId;
    private string $older;
    private string $newer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgId = (string) Str::uuid();
        $this->insertRow('organizations', [
            'id' => $this->orgId, 'name' => 'Duplicate lead (test)', 'status' => 'Lead', 'is_duplicate_suspect' => 1,
            'assigned_to' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);
        $this->older = $this->assessment(['executive_name' => 'Older contact', 'report_token' => 'older-token', 'lead_tier' => 1, 'created_at' => now()->subDay()]);
        $this->newer = $this->assessment(['executive_name' => 'Newer contact', 'report_token' => 'newer-token', 'lead_tier' => 3, 'created_at' => now()]);
    }

    private function assessment(array $values): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('copq_assessments', array_merge([
            'id' => $id, 'organization_id' => $this->orgId, 'company_name' => 'Duplicate lead (test)',
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ], $values));

        return $id;
    }

    public function test_the_merge_columns_and_records_cannot_be_changed_from_the_browser(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        $screen = Livewire::test(LeadsRegister::class)->call('openDiffViewer', $this->orgId)->assertSet('showDiffModal', true);

        foreach (['diffFields', 'diffOld', 'diffNew', 'stages'] as $property) {
            try {
                $screen->set($property, ['report_token' => 'Token']);
                $this->fail("{$property} should be locked");
            } catch (CannotUpdateLockedPropertyException) {
                // expected
            }
        }
    }

    public function test_a_merge_writes_only_the_whitelisted_columns_and_is_logged(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);

        Livewire::test(LeadsRegister::class)
            ->call('openDiffViewer', $this->orgId)
            ->set('mergeSelections.executive_name', 'old')
            ->set('mergeSelections.report_token', 'old')
            ->set('mergeSelections.lead_tier', 'old')
            ->set('mergeSelections.organization_id', 'old')
            ->call('executeMerge');

        $merged = DB::table('copq_assessments')->where('id', $this->newer)->first();
        $this->assertSame('Older contact', $merged->executive_name);
        $this->assertSame('newer-token', $merged->report_token);
        $this->assertSame(3, (int) $merged->lead_tier);
        $this->assertSame(0, (int) DB::table('organizations')->where('id', $this->orgId)->value('is_duplicate_suspect'));

        $log = DB::table('system_audit_logs')->where('action', 'LEAD_RECORDS_MERGED')->first();
        $this->assertNotNull($log, 'the merge is logged');
        $this->assertSame($this->newer, $log->target_id);
        $this->assertSame($sales->id, $log->user_id);
        $this->assertEquals(['merged_from' => $this->older, 'kept_from_older_record' => ['executive_name']], json_decode($log->new_values, true));
    }

    public function test_a_lead_only_moves_to_one_of_the_register_stages(): void
    {
        $internal = CoreSeeder::INTERNAL_ORG_ID;
        $internalStage = DB::table('organizations')->where('id', $internal)->value('pipeline_stage');
        $this->actingAs($this->staff('sales', ['sales']));

        Livewire::test(LeadsRegister::class)
            ->call('updateStage', $this->orgId, 'Anything at all')
            ->call('updateStage', $internal, 'Won / Lost');
        $this->assertSame('Lead Captured', DB::table('organizations')->where('id', $this->orgId)->value('pipeline_stage'));
        $this->assertSame($internalStage, DB::table('organizations')->where('id', $internal)->value('pipeline_stage'));

        Livewire::test(LeadsRegister::class)->call('updateStage', $this->orgId, 'Qualifying');
        $this->assertSame('Qualifying', DB::table('organizations')->where('id', $this->orgId)->value('pipeline_stage'));
    }

    public function test_a_lead_someone_owns_cannot_be_taken_over_and_claims_are_logged(): void
    {
        $first = $this->staff('sales', ['sales']);
        $this->actingAs($first);
        Livewire::test(LeadsRegister::class)->call('claimLead', $this->orgId);
        $this->assertSame($first->id, DB::table('organizations')->where('id', $this->orgId)->value('assigned_to'));

        // A colleague's "Assign to me" sent from an old page doesn't take it over
        $this->actingAs($this->staff('sales2', ['sales']));
        Livewire::test(LeadsRegister::class)->call('claimLead', $this->orgId);
        $this->assertSame($first->id, DB::table('organizations')->where('id', $this->orgId)->value('assigned_to'));

        $log = DB::table('system_audit_logs')->where('action', 'LEAD_CLAIMED')->get();
        $this->assertCount(1, $log);
        $this->assertSame($first->id, $log[0]->user_id);
        $this->assertSame($this->orgId, $log[0]->target_id);
        $this->assertEquals(['assigned_to' => $first->id], json_decode($log[0]->new_values, true));
    }
}
