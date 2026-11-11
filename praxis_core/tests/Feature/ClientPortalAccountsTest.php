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
}
