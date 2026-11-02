<?php

namespace Tests\Feature;

use App\Livewire\Hq\System\RoleManagement;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Fixes from the live QA on the HQ side (docs/QA-PORTAL.md section 4) */
class HqQaFixesTest extends TestCase
{
    use UsesThrowawayDatabase;

    public function test_a_half_filled_account_survives_a_look_at_the_lists_and_the_member_role_is_listed_once(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $org = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'QA Test Client (delete)', 'status' => 'Lead']);

        $screen = Livewire::test(RoleManagement::class)
            ->set('first_name', 'Adaeze')->set('email', 'adaeze@client.test')->set('organization_id', $org)
            ->call('setTab', 'CLIENTS')->call('setTab', 'CREATE')
            ->assertSet('first_name', 'Adaeze')->assertSet('email', 'adaeze@client.test');

        $member = DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id');
        $this->assertNotNull($member);
        $screen->assertDontSeeHtml('<option value="' . $member . '">');

        // An edit doesn't follow you out of the form
        $screen->set('editMode', true)->call('setTab', 'CLIENTS')->assertSet('first_name', '');
    }

    public function test_an_organisation_added_from_accounts_records_who_added_it(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);

        Livewire::test(RoleManagement::class)
            ->set('organization_id', 'NEW')->set('new_org_name', 'QA Org From Accounts')->set('new_org_industry', 'Logistics & Supply Chain')
            ->set('first_name', 'Chidi')->set('last_name', 'Client')->set('email', 'chidi@client.test')
            ->call('saveUser')->assertHasNoErrors();

        $org = DB::table('organizations')->where('name', 'QA Org From Accounts')->sole();
        $this->assertSame($owner->id, $org->created_by);
        $this->assertSame($owner->id, $org->assigned_to);
    }
}
