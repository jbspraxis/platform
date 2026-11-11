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

    public function test_a_first_proposal_is_priced_in_the_clients_currency_where_we_bill_it(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        foreach (['Nigeria' => 'NGN', 'Cameroon' => 'XAF', 'United Kingdom' => 'USD'] as $country => $currency) {
            $org = (string) \Illuminate\Support\Str::uuid();
            $this->insertRow('organizations', ['id' => $org, 'name' => "Client in {$country}", 'status' => 'Lead', 'country' => $country]);
            $o = \App\Models\Opportunity::create(['organization_id' => $org, 'title' => 'Work', 'stage' => 'discovery', 'language' => 'en', 'source' => 'manual', 'lead_user_id' => $lead->id]);

            Livewire::test(\App\Livewire\Hq\Opportunities\ProposalsTab::class, ['opportunityId' => $o->id])->assertSet('currency', $currency);
        }
    }

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

    public function test_the_primary_contact_can_move_to_another_account_of_the_organisation(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $org = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'QA Test Client (delete)', 'status' => 'Lead']);
        $member = DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id');
        $first = (string) \Illuminate\Support\Str::uuid();
        $second = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('client_users', ['id' => $first, 'organization_id' => $org, 'first_name' => 'Chidi', 'last_name' => 'Client', 'email' => 'chidi@client.test',
            'is_primary_client' => 1, 'role_id' => \App\Support\Access::clientAdminRoleId(), 'status' => 'Active', 'lifecycle_stage' => 'Onboarding']);
        $this->insertRow('client_users', ['id' => $second, 'organization_id' => $org, 'first_name' => 'Adaeze', 'last_name' => 'Client', 'email' => 'adaeze@client.test',
            'is_primary_client' => 0, 'role_id' => $member, 'status' => 'Active', 'lifecycle_stage' => 'Onboarding']);

        // The list shows the client; its people (and their actions) are in its dialog
        Livewire::test(RoleManagement::class)->call('setTab', 'CLIENTS')->assertDontSee('Make primary contact')
            ->call('openClient', $org)->assertSee('Make primary contact')
            ->call('makePrimary', $second)->assertSet('showSuccessModal', true)->assertSet('openClientId', $org);

        $this->assertEquals([$first => 0, $second => 1], DB::table('client_users')->where('organization_id', $org)->pluck('is_primary_client', 'id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(\App\Support\Access::clientAdminRoleId(), (int) DB::table('client_users')->where('id', $second)->value('role_id'));
        $this->assertSame(\App\Support\Access::clientAdminRoleId(), (int) DB::table('client_users')->where('id', $first)->value('role_id'));
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'CLIENT_PRIMARY_CHANGED', 'target_id' => $second]);
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
