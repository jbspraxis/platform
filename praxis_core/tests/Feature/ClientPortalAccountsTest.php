<?php

namespace Tests\Feature;

use App\Livewire\Hq\System\RoleManagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Accounts › Client Portal accounts: one row per client, its people in a dialog (owner, 11 Nov 2026) */
class ClientPortalAccountsTest extends TestCase
{
    use UsesThrowawayDatabase;

    public function test_the_list_shows_one_row_per_client_and_its_people_open_in_a_dialog(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $member = DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id');
        $beta = (string) Str::uuid();
        $alpha = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $beta, 'name' => 'Beta Client Ltd', 'status' => 'Active Client']);
        $this->insertRow('organizations', ['id' => $alpha, 'name' => 'Alpha Client Ltd', 'status' => 'Active Client']);
        $account = fn (string $org, string $first, string $email, array $more) => $this->insertRow('client_users', $more + [
            'id' => (string) Str::uuid(), 'organization_id' => $org, 'first_name' => $first, 'last_name' => 'Client', 'email' => $email,
            'role_id' => $member, 'is_primary_client' => 0, 'status' => 'Active', 'lifecycle_stage' => 'Active',
        ]);
        $account($beta, 'Bode', 'bode@beta.test', ['is_primary_client' => 1, 'role_id' => \App\Support\Access::clientAdminRoleId(), 'last_login_at' => '2026-11-10 08:30:00']);
        $account($beta, 'Ngozi', 'ngozi@beta.test', ['lifecycle_stage' => 'Onboarding']);
        $account($alpha, 'Ama', 'ama@alpha.test', ['status' => 'Suspended']);

        $screen = Livewire::test(RoleManagement::class)->call('setTab', 'CLIENTS')
            ->assertSeeInOrder(['Alpha Client Ltd', 'Beta Client Ltd'])
            ->assertSee('2 clients, 3 accounts')
            ->assertSee('1 suspended')->assertSee('1 invited')
            ->assertSee('bode@beta.test')               // its Client Admin
            ->assertSee('10 Nov 2026, 09:30')           // last sign-in, Lagos time
            ->assertSee('No Client Admin yet')
            ->assertDontSee('ngozi@beta.test');         // people only in the dialog

        $screen->call('openClient', $beta)->assertSet('openClientId', $beta)
            ->assertSee('ngozi@beta.test')->assertSee('Resend invitation')->assertSee('2 Client Portal accounts')
            ->assertDontSee('ama@alpha.test');

        // Only a client on the list opens; Close and changing tab close it
        $screen->call('openClient', (string) Str::uuid())->assertSet('openClientId', null);
        $screen->call('openClient', $alpha)->assertSee('Restore access')->call('closeClient')->assertSet('openClientId', null);
        $screen->call('openClient', $alpha)->call('setTab', 'INTERNAL')->assertSet('openClientId', null);
    }

    public function test_a_client_account_can_be_removed_but_not_the_primary_contact_or_a_staff_account(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $member = DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id');
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'Gamma Client Ltd', 'status' => 'Active Client']);
        $primary = (string) Str::uuid();
        $leaving = (string) Str::uuid();
        $this->insertRow('client_users', ['id' => $primary, 'organization_id' => $org, 'first_name' => 'Bode', 'last_name' => 'Client', 'email' => 'bode@gamma.test',
            'is_primary_client' => 1, 'role_id' => \App\Support\Access::clientAdminRoleId(), 'status' => 'Active', 'lifecycle_stage' => 'Active']);
        $this->insertRow('client_users', ['id' => $leaving, 'organization_id' => $org, 'first_name' => 'Cynthia', 'last_name' => 'Client', 'email' => 'cynthia@gamma.test',
            'is_primary_client' => 0, 'role_id' => $member, 'status' => 'Active', 'lifecycle_stage' => 'Onboarding', 'invitation_token' => 'tok-123']);
        $this->insertRow('passkeys', ['owner_id' => $leaving, 'guard' => 'client', 'credential_id' => 'cred-1', 'public_key' => 'pk', 'algorithm' => -7, 'name' => 'Phone', 'created_at' => now(), 'updated_at' => now()]);

        $screen = Livewire::test(RoleManagement::class)->call('setTab', 'CLIENTS')->call('openClient', $org)
            ->assertSee('Remove account')
            ->call('removeClientAccount', $leaving)->assertSet('showSuccessModal', true)
            ->assertDontSee('cynthia@gamma.test');

        $row = DB::table('client_users')->where('id', $leaving)->first();
        $this->assertNotNull($row->deleted_at);
        $this->assertSame('Suspended', $row->status);
        $this->assertNull($row->invitation_token);
        $this->assertSame(0, DB::table('passkeys')->where('owner_id', $leaving)->count());
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'CLIENT_ACCOUNT_REMOVED', 'target_id' => $leaving, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $org, 'type' => 'contact_removed']);

        // The primary contact and staff accounts stay
        $screen->call('removeClientAccount', $primary)->call('removeClientAccount', $owner->id);
        $this->assertNull(DB::table('client_users')->where('id', $primary)->value('deleted_at'));
        $this->assertSame('Active', DB::table('users')->where('id', $owner->id)->value('status'));
    }
}
