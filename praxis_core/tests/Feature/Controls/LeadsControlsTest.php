<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Intelligence\LeadsRegister;
use App\Livewire\Hq\Leads\LeadPage;
use App\Models\Organization;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Leads: merging two assessments (now on the lead's page) writes only the whitelisted columns (the browser can't
 * name others) and is in the audit log; JBS Praxis itself never opens as a lead; a lead someone owns can't be taken
 * over by another "Assign to me", and only its owner or a partner hands it on.
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

    private function page()
    {
        return Livewire::test(LeadPage::class, ['organization' => Organization::find($this->orgId)]);
    }

    public function test_the_merge_columns_and_records_cannot_be_changed_from_the_browser(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        $screen = $this->page()->call('openDiffViewer')->assertSet('showDiffModal', true);

        foreach (['diffFields', 'diffOld', 'diffNew', 'leadId', 'canEdit'] as $property) {
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

        $this->page()
            ->call('openDiffViewer')
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
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $this->orgId, 'type' => 'records_merged', 'actor_id' => $sales->id]);
    }

    public function test_jbs_praxis_itself_is_not_a_lead(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(LeadPage::class, ['organization' => Organization::find(CoreSeeder::INTERNAL_ORG_ID)])->assertNotFound();
        Livewire::test(LeadsRegister::class)->call('claimLead', CoreSeeder::INTERNAL_ORG_ID);
        $this->assertNull(DB::table('organizations')->where('id', CoreSeeder::INTERNAL_ORG_ID)->value('assigned_to'));
    }

    public function test_a_lead_can_be_assigned_to_anyone_by_its_owner_or_a_partner(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $sales2 = $this->staff('sales2', ['sales']);
        $delivery = $this->staff('delivery', ['delivery']);
        $partner = $this->staff('partner', ['partner']);
        $owner = fn () => DB::table('organizations')->where('id', $this->orgId)->value('assigned_to');

        // Nobody owns it: anyone with crm.edit hands it to anyone, not only to themselves
        $this->actingAs($sales);
        $this->page()->assertSee('Who follows this lead up')->call('assignLead', $sales2->id)->assertSee('Sales2 Test');
        $this->assertSame($sales2->id, $owner());
        $log = DB::table('system_audit_logs')->where('action', 'LEAD_ASSIGNED')->first();
        $this->assertSame($sales->id, $log->user_id);
        $this->assertEquals(['assigned_to' => $sales2->id], json_decode($log->new_values, true));
        $this->assertDatabaseHas('notifications', ['user_id' => $sales2->id, 'title' => 'A lead was assigned to you',
            'link_url' => route('hq.crm.lead', ['organization' => $this->orgId], false)]);

        // A colleague who neither owns it nor approves can't take it back or move it
        $this->page()->assertDontSee('Keep ')->assertSee('Only Sales2 or a partner can hand this lead to someone else')
            ->call('assignLead', $sales->id)->assertForbidden();
        $this->assertSame($sales2->id, $owner());

        // Its owner can hand it on (to someone without crm.edit too); a partner can reassign it without the owner's say
        $this->actingAs($sales2);
        $this->page()->call('startReassign')->assertSet('ownerPicking', true)
            ->assertSee('Keep Sales2')->call('assignLead', $delivery->id)->assertSet('ownerPicking', false)->assertSee('Delivery Test');
        $this->assertSame($delivery->id, $owner());
        $this->actingAs($delivery);
        $this->page()->call('startReassign')->assertForbidden();
        $this->actingAs($partner);
        $this->page()->call('startReassign')->call('assignLead', $sales->id);
        $this->assertSame($sales->id, $owner());
        $this->assertSame(3, DB::table('system_audit_logs')->where('action', 'LEAD_ASSIGNED')->count());
        $this->assertSame(3, DB::table('lead_timeline')->where('organization_id', $this->orgId)->where('type', 'owner_changed')->count());
    }

    public function test_a_lead_someone_owns_cannot_be_taken_over_and_claims_are_logged(): void
    {
        $first = $this->staff('sales', ['sales']);
        $this->actingAs($first);
        Livewire::test(LeadsRegister::class)->call('claimLead', $this->orgId);
        $this->assertSame($first->id, DB::table('organizations')->where('id', $this->orgId)->value('assigned_to'));

        // A colleague's "Assign to me" sent from an old page doesn't take it over, from the list or the lead's page
        $this->actingAs($this->staff('sales2', ['sales']));
        Livewire::test(LeadsRegister::class)->call('claimLead', $this->orgId);
        $this->page()->call('claim')->assertSee('Someone took this lead a moment ago.');
        $this->assertSame($first->id, DB::table('organizations')->where('id', $this->orgId)->value('assigned_to'));

        $log = DB::table('system_audit_logs')->where('action', 'LEAD_CLAIMED')->get();
        $this->assertCount(1, $log);
        $this->assertSame($first->id, $log[0]->user_id);
        $this->assertSame($this->orgId, $log[0]->target_id);
        $this->assertEquals(['assigned_to' => $first->id], json_decode($log[0]->new_values, true));
    }
}
