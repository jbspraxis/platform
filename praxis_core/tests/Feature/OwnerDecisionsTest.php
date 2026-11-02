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

    public function test_d6_a_company_being_registered_is_added_without_its_numbers_and_bills_nigerian_clients(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        DB::table('mdm_legal_entities')->where('region_code', 'NG')->delete(); // live had only the Cameroon company

        Livewire::test(\App\Livewire\Hq\Mdm\LegalEntityRegistry::class)
            ->set('region_code', 'NG')->set('legal_name', 'JBS Praxis LLC')->set('registered_address', '21B Bode Ajakaiye Street, Lekki, Lagos')
            ->set('legal_representative_name', 'Tom-Blake Forghab')->set('legal_representative_title', 'Managing Partner')
            ->call('saveEntity')->assertHasNoErrors()->assertSee('Being registered');

        $entity = DB::table('mdm_legal_entities')->where('region_code', 'NG')->sole();
        $this->assertSame([null, null, null, 0], [$entity->registration_number, $entity->tax_identification_number, $entity->employer_social_security_code, (int) $entity->charges_vat]);
        // It is Nigeria's company now: it issues, without VAT ("off", not "no company")
        $this->assertSame($entity->id, \App\Support\Finance\Vat::issuer(null)?->id);
        $vat = \App\Support\Finance\Vat::for('Nigeria');
        $this->assertSame(['off', 'JBS Praxis LLC'], [$vat['why'], $vat['company']]);
    }

    public function test_d9_client_review_closes_after_2_working_days_with_one_reminder_the_day_before(): void
    {
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'City Client', 'status' => 'Active Client', 'preferred_language' => 'EN']);
        $project = $this->project(['organization_id' => $org]);
        $task = $this->task($this->phase($project, 1, 100), ['title' => 'Map view']);
        $this->member($org, 1, ['role_id' => Access::clientAdminRoleId(), 'is_primary_client' => 1]);

        // Friday 6 November, 15:00 in Lagos: the window skips the weekend and closes Tuesday at 15:00
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-06 15:00', 'Africa/Lagos'));
        \App\Support\Projects\Tasks::move($project, DB::table('tasks')->where('id', $task)->first(), 'QA_Review');
        $this->assertSame('2026-11-10 14:00:00', (string) DB::table('tasks')->where('id', $task)->value('qa_sla_expires_at'));

        // Monday afternoon: one reminder, once
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-09 16:00', 'Africa/Lagos'));
        $this->artisan('ops:qa-auto-approve')->assertSuccessful();
        $this->artisan('ops:qa-auto-approve')->assertSuccessful();
        $this->assertSame(1, DB::table('client_notifications')->where('kind', 'task_review_reminder')->count());
        $this->assertSame('QA_Review', DB::table('tasks')->where('id', $task)->value('status'));

        // Tuesday after 15:00: approved automatically
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-10 15:01', 'Africa/Lagos'));
        $this->artisan('ops:qa-auto-approve')->assertSuccessful();
        $this->assertSame(['Done', 1], [DB::table('tasks')->where('id', $task)->value('status'), (int) DB::table('tasks')->where('id', $task)->value('qa_auto_approved')]);
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
