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

    public function test_d5_the_client_approver_role_reaches_a_database_that_already_has_its_roles(): void
    {
        // Live has its permissions and the three older client roles; the migration adds the fourth, once
        $old = DB::table('roles')->where('audience', 'client')->where('slug', 'client-approver')->value('id');
        DB::table('role_permissions')->where('role_id', $old)->delete();
        DB::table('roles')->where('id', $old)->delete();
        $migration = require database_path('migrations/2026_11_02_400000_add_client_approver_role.php');
        $migration->up();
        $migration->up();

        $role = DB::table('roles')->where('audience', 'client')->where('slug', 'client-approver')->sole();
        $granted = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_id', $role->id)->pluck('permissions.slug')->all();
        $this->assertSame('Client Approver', $role->name);
        $this->assertEqualsCanonicalizing(Access::expandGrant(config('access.starter_roles.client.client-approver.grant'), 'client'), $granted);
        $this->assertNotContains('portal.team.create', $granted);
        $this->assertNotContains('portal.settings.edit', $granted);
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

    public function test_d8_a_client_sees_only_the_accounts_in_the_invoices_currency_held_by_the_company_that_bills_it(): void
    {
        $company = fn (string $region, string $name) => DB::table('mdm_legal_entities')->where('region_code', $region)->value('id')
            ?? tap((string) Str::uuid(), fn ($id) => $this->insertRow('mdm_legal_entities', ['id' => $id, 'region_code' => $region, 'legal_name' => $name, 'is_active' => 1]));
        $ng = $company('NG', 'JBS Praxis LLC');
        $cm = $company('CM', 'JBS Praxis Cameroun SARL');
        DB::table('mdm_legal_entities')->whereIn('id', [$ng, $cm])->update(['is_active' => 1]);
        DB::table('mdm_remittance_accounts')->delete();
        $account = fn (string $ref, ?string $currency, ?string $entity, int $active = 1) => DB::table('mdm_remittance_accounts')->insertGetId([
            'type' => 'Bank', 'provider_name' => 'Bank ' . $ref, 'account_name' => 'JBS Praxis', 'account_reference' => $ref,
            'currency' => $currency, 'legal_entity_id' => $entity, 'is_active' => $active]);
        $naira = $account('NGN-NG-0001', 'NGN', $ng);
        $account('USD-NG-0002', 'USD', $ng);
        $account('NGN-CM-0003', 'NGN', $cm);
        $account('NGN-NG-OFF-0004', 'NGN', $ng, 0);
        $account('LEGACY-0005', null, null);
        $cfa = $account('XAF-CM-0006', 'XAF', $cm);

        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'City Client', 'status' => 'Active Client', 'preferred_language' => 'EN', 'country' => 'Nigeria']);
        $project = $this->project(['organization_id' => $org]); // NGN, no opportunity: Nigeria's company bills it
        $admin = $this->member($org, 1, ['role_id' => Access::clientAdminRoleId(), 'is_primary_client' => 1]);

        // No invoice yet: the project's currency and billing company
        $portal = fn () => Livewire::actingAs($admin, 'client')->test(Dashboard::class)->call('switchTab', 'financials');
        $portal()->assertSee('NGN-NG-0001')->assertSee('Bank transfer · NGN')
            ->assertDontSee('USD-NG-0002')->assertDontSee('NGN-CM-0003')->assertDontSee('NGN-NG-OFF-0004')->assertDontSee('LEGACY-0005')->assertDontSee('XAF-CM-0006');
        // Unpaid invoices: the accounts for each one's currency and company
        $this->invoice($project, null, 'Pending');
        $xaf = $this->invoice($project, null, 'Overdue');
        DB::table('invoices')->where('id', $xaf)->update(['currency' => 'XAF', 'billing_entity_id' => $cm]);
        $portal()->assertSee('NGN-NG-0001')->assertSee('XAF-CM-0006')->assertDontSee('USD-NG-0002')->assertDontSee('NGN-CM-0003');
        $this->assertSame([$naira], \App\Support\Finance\ReceivingAccounts::for('ngn', null)->pluck('id')->all(), 'The deposit invoice takes this one');

        // HQ › Create invoice offers only the matching account, chooses it when it is the only one, and refuses another
        $this->actingAs($this->staff('finance', ['finance']));
        Livewire::test(\App\Livewire\Hq\Finance\InvoiceLedger::class)->set('showCreateModal', true)->set('new_project_id', $project)
            ->assertSet('new_remittance_id', (string) $naira)->assertSee('Bank NGN-NG-0001')->assertDontSee('Bank USD-NG-0002')
            ->set('new_remittance_id', (string) $cfa)->set('new_amount_due', 1000)->set('new_description', 'M1')
            ->call('draftSubsequentInvoice')->assertHasErrors(['new_remittance_id']);

        // Master data › Remittance accounts asks for both, and says when an account is shown to no client
        $this->actingAs($this->staff('owner', ['owner']));
        Livewire::test(\App\Livewire\Hq\Mdm\RemittanceAccounts::class)->assertSee('Not shown to clients: set its currency and company')
            ->set('provider_name', 'UBA')->set('account_name', 'JBS Praxis LLC')->set('account_reference', '1023456789')
            ->call('saveAccount')->assertHasErrors(['currency', 'legal_entity_id'])
            ->set('currency', 'NGN')->set('legal_entity_id', $ng)->call('saveAccount')->assertHasNoErrors();
        $this->assertDatabaseHas('mdm_remittance_accounts', ['account_reference' => '1023456789', 'currency' => 'NGN', 'legal_entity_id' => $ng]);
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
