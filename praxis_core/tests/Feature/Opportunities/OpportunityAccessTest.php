<?php

namespace Tests\Feature\Opportunities;

use App\Support\Access;
use App\Support\Hosts;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\DB;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Who may do what with opportunities and integrations (docs/OPPORTUNITIES.md 8.1), per starter role, and the
 * migration that grants the new permissions to roles that already exist on live.
 */
class OpportunityAccessTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    public function test_each_role_opens_what_it_should(): void
    {
        $opportunity = $this->opportunityFor($this->organisation());

        $expect = [
            // role => [board, opportunity page, integrations]
            'owner' => [200, 200, 200],
            'partner' => [200, 200, 200],
            'sales' => [200, 200, 403],
            'delivery' => [200, 200, 403],
            'finance' => [200, 200, 403],
            'auditor' => [200, 200, 403],
            'engineer' => [403, 403, 403],
            'hr' => [403, 403, 403],
        ];
        foreach ($expect as $role => [$board, $page, $integrations]) {
            $user = $this->staff($role, [$role]);
            $this->actingAs($user)->get($this->hq('/opportunities'))->assertStatus($board);
            $this->actingAs($user)->get($this->hq('/opportunities/' . $opportunity->id))->assertStatus($page);
            $this->actingAs($user)->get($this->hq('/system/integrations'))->assertStatus($integrations);
        }
    }

    public function test_manage_approve_and_transcripts(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $finance = $this->staff('finance', ['finance']);
        $delivery = $this->staff('delivery', ['delivery']);
        $owner = $this->staff('owner', ['owner']);
        $opportunity = $this->opportunityFor($this->organisation(), ['lead_user_id' => $delivery->id]);

        $this->assertTrue(OpportunityAccess::canCreate($sales));
        $this->assertTrue(OpportunityAccess::canManage($sales, $opportunity));
        $this->assertFalse(OpportunityAccess::canApprove($sales, $opportunity), 'Sales approves only as the lead');
        $this->assertTrue(OpportunityAccess::canReadTranscripts($sales, $opportunity));

        $this->assertTrue(OpportunityAccess::canView($finance));
        $this->assertFalse(OpportunityAccess::canCreate($finance));
        $this->assertFalse(OpportunityAccess::canManage($finance, $opportunity));
        $this->assertFalse(OpportunityAccess::canReadTranscripts($finance, $opportunity));

        // The lead approves their own opportunity's documents, whatever their role
        $this->assertTrue(OpportunityAccess::canApprove($delivery, $opportunity));
        $this->assertTrue(OpportunityAccess::canReadTranscripts($delivery, $opportunity));
        $this->assertTrue(OpportunityAccess::canApprove($owner, $opportunity));

        // A suspended lead is no longer the lead
        DB::table('users')->where('id', $delivery->id)->update(['status' => 'Suspended']);
        Access::flush();
        $this->assertFalse(OpportunityAccess::canApprove($delivery->fresh(), $opportunity));
    }

    public function test_the_grant_migration_gives_existing_roles_their_permissions(): void
    {
        // Live today: roles exist, the new permissions don't
        DB::table('permissions')->whereIn('module', ['opportunities', 'integrations'])->delete();
        Access::flush();

        (require database_path('migrations/2026_10_31_800002_grant_opportunity_permissions.php'))->up();
        (require database_path('migrations/2026_10_31_800002_grant_opportunity_permissions.php'))->up(); // twice: nothing doubles
        Access::flush();

        $granted = fn (string $role) => DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.slug', $role)->whereIn('permissions.module', ['opportunities', 'integrations'])
            ->orderBy('permissions.slug')->pluck('permissions.slug')->all();

        $this->assertSame(['opportunities.create', 'opportunities.manage', 'opportunities.transcripts', 'opportunities.view'], $granted('sales'));
        $this->assertSame(['opportunities.create', 'opportunities.manage', 'opportunities.transcripts', 'opportunities.view'], $granted('partner'));
        $this->assertSame(['opportunities.transcripts', 'opportunities.view'], $granted('delivery'));
        $this->assertSame(['opportunities.view'], $granted('finance'));
        $this->assertSame(['opportunities.view'], $granted('auditor'));
        $this->assertSame([], $granted('engineer'));
        $this->assertSame([], $granted('hr'));

        // Approving and integrations stay with the owner, who has every permission
        $owner = $this->staff('owner', ['owner']);
        $this->assertTrue(Access::allows($owner, 'opportunities.approve'));
        $this->assertTrue(Access::allows($owner, 'integrations.manage'));
        $this->assertFalse(Access::allows($this->staff('partner', ['partner']), 'integrations.manage'));
    }
}
