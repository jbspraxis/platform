<?php

namespace Tests\Feature;

use App\Livewire\ClientOs\Dashboard;
use App\Mail\TeamMemberInvitation;
use App\Models\ClientUser;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

/** The owner's decisions after the live portal QA, 2 Nov 2026 (docs/QA-PORTAL.md section 5) */
class OwnerDecisionsTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private function member(string $org, int $n, array $values = []): ClientUser
    {
        return ClientUser::forceCreate($values + [
            'organization_id' => $org, 'role_id' => DB::table('roles')->where('slug', 'client-member')->value('id'),
            'first_name' => 'Member', 'last_name' => (string) $n, 'email' => "member{$n}@city.test",
            'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 0,
        ]);
    }

    public function test_d1_an_organisation_has_up_to_10_portal_accounts(): void
    {
        Mail::fake();
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'City Client', 'status' => 'Active Client', 'preferred_language' => 'EN']);
        $this->phase($this->project(['organization_id' => $org]), 1, 100);
        $admin = $this->member($org, 1, ['role_id' => Access::clientAdminRoleId(), 'is_primary_client' => 1]);
        foreach ([2, 3, 4] as $n) {
            $this->member($org, $n);
        }

        // Four accounts, as MyCity has: the admin invites a fifth (before, 3 was the most)
        $portal = Livewire::actingAs($admin, 'client')->test(Dashboard::class)->call('switchTab', 'team')
            ->set('inviteFirstName', 'Fifth')->set('inviteLastName', 'Person')->set('inviteEmail', 'fifth@city.test')
            ->call('inviteTeamMember')->assertHasNoErrors();
        Mail::assertSent(TeamMemberInvitation::class, fn ($m) => $m->hasTo('fifth@city.test'));

        foreach (range(6, 10) as $n) {
            $this->member($org, $n);
        }
        $portal->set('inviteFirstName', 'Eleventh')->set('inviteLastName', 'Person')->set('inviteEmail', 'eleventh@city.test')
            ->call('inviteTeamMember')->assertSee('the maximum of 10 Client Portal accounts');
        $this->assertDatabaseMissing('client_users', ['email' => 'eleventh@city.test']);
    }
}
