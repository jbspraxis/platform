<?php

namespace Tests\Feature\Clients;

use App\Livewire\Hq\Clients\ClientList;
use App\Livewire\Hq\Clients\ClientPage;
use App\Mail\UserInvitation;
use App\Models\ClientUser;
use App\Models\Organization;
use App\Support\Clients\Clients;
use App\Support\Finance\InvoicePdf;
use App\Support\Hosts;
use App\Support\HqNav;
use App\Support\Leads\Leads;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ › Clients (docs/CLIENTS.md, owner's answers 13 Nov 2026): its own menu section, the list of active and past
 * clients, the invoice details on each client's page (Sales and Finance change them) and Client Portal invitations.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class ClientsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function client(string $name, string $status = 'Active Client', array $values = [], array $contacts = []): Organization
    {
        $org = Leads::create($values + ['name' => $name, 'country' => 'Nigeria', 'industry' => 'Logistics', 'phone' => '+234 800 000 0001'], $contacts, null);
        DB::table('organizations')->where('id', $org->id)->update(['status' => $status, 'lead_status' => $status === 'Lead' ? 'new' : 'client', 'client_since' => now()]);

        return $org->fresh();
    }

    private function project(Organization $org, string $status = 'Active'): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('projects', [
            'id' => $id, 'organization_id' => $org->id, 'title' => "Project {$status} (test)", 'status' => $status, 'currency' => 'NGN',
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);

        return $id;
    }

    private function invoice(string $projectId, float $total, string $status = 'Pending', string $currency = 'NGN'): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('invoices', [
            'id' => $id, 'project_id' => $projectId, 'invoice_number' => 'INV-TEST-' . strtoupper(Str::random(6)),
            'amount_due' => $total, 'vat_amount' => 0, 'total_amount' => $total, 'currency' => $currency,
            'status' => $status, 'due_date' => '2026-11-20', 'phase_id' => null, 'remittance_account_id' => null,
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);

        return $id;
    }

    private function page(Organization $org)
    {
        return Livewire::test(ClientPage::class, ['organization' => $org]);
    }

    public function test_english_and_french_have_the_same_keys(): void
    {
        $en = array_keys(Arr::dot(require lang_path('en/clients.php')));
        $fr = array_keys(Arr::dot(require lang_path('fr/clients.php')));
        sort($en);
        sort($fr);
        $this->assertSame($en, $fr);
    }

    public function test_the_menu_and_who_opens_the_pages(): void
    {
        $hq = 'http://' . Hosts::hq();
        $mycity = $this->client('MyCity App (test)');
        $past = $this->client('Old Friend Ltd (test)', 'Past Client');
        $lead = $this->client('Just A Lead (test)', 'Lead');

        // Its own section, right after Leads and proposals (owner, 13 Nov 2026)
        $keys = array_column(HqNav::sections($this->staff('owner', ['owner'])), 'key');
        $this->assertSame(array_search('leads', $keys, true) + 1, array_search('clients', $keys, true));
        foreach (['sales', 'finance', 'delivery', 'auditor'] as $role) {
            $this->assertContains('clients', array_column(HqNav::sections($this->staff($role, [$role])), 'key'), "{$role} opens Clients");
        }
        $engineer = $this->staff('engineer', ['engineer']);
        $this->assertNotContains('clients', array_column(HqNav::sections($engineer), 'key'));

        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales)->get($hq . '/clients')->assertOk()->assertSee('MyCity App (test)')->assertSee(route('hq.client', ['organization' => $mycity->id]), false)
            ->assertDontSee('Old Friend Ltd (test)')->assertDontSee('Just A Lead (test)');
        $this->actingAs($sales)->get($hq . '/clients/' . $mycity->id)->assertOk()->assertSee('Invoice details')->assertSee('Billed to')
            ->assertSee(route('hq.crm.lead', ['organization' => $mycity->id]), false);
        // A past client opens too (no lead page for it); a lead is not a client
        $this->actingAs($sales)->get($hq . '/clients/' . $past->id)->assertOk()->assertSee('Past client')->assertDontSee('Open lead page');
        $this->actingAs($sales)->get($hq . '/clients/' . $lead->id)->assertNotFound();
        $this->actingAs($engineer)->get($hq . '/clients')->assertForbidden();
        $this->actingAs($engineer)->get($hq . '/clients/' . $mycity->id)->assertForbidden();
        // The lead page links to the client page
        $this->actingAs($sales)->get($hq . '/intelligence/leads/' . $mycity->id)->assertOk()->assertSee(route('hq.client', ['organization' => $mycity->id]), false);
    }

    public function test_the_list_shows_contacts_projects_unpaid_and_missing_details(): void
    {
        $mycity = $this->client('MyCity App (test)', 'Active Client', [
            'email' => 'admin@mycity.test', 'address' => 'MTN Plaza, Ikoyi, Lagos',
        ], [['name' => 'Bode Test', 'email' => 'bode@mycity.test', 'main' => true], ['name' => 'Cynthia Test', 'email' => 'cynthia@mycity.test']]);
        DB::table('organizations')->where('id', $mycity->id)->update(['registration_number' => 'RC 7617757', 'tax_identification_number' => '2522545765472']);
        $tales = $this->client('Tales Consulting (test)');
        $past = $this->client('Old Friend Ltd (test)', 'Past Client');
        $running = $this->project($mycity);
        $this->project($mycity, 'Completed');
        $partPaid = $this->invoice($running, 450000);
        $this->insertRow('transactions', ['id' => (string) Str::uuid(), 'invoice_id' => $partPaid, 'status' => 'Verified', 'amount_claimed' => 300000]);
        $this->insertRow('transactions', ['id' => (string) Str::uuid(), 'invoice_id' => $partPaid, 'status' => 'Pending Verification', 'amount_claimed' => 150000]);
        $this->invoice($running, 1000, 'Overdue', 'USD');
        $this->invoice($running, 99999, 'Paid');
        $this->invoice($running, 88888, 'Cancelled');

        $this->assertSame([$mycity->id => ['NGN' => 150000.0, 'USD' => 1000.0]], Clients::unpaid([$mycity->id, $tales->id]));
        $this->assertSame([], Clients::missing(Clients::find($mycity->id)));
        $this->assertSame(['address', 'registration_number', 'tax_number', 'email'], Clients::missing(Clients::find($tales->id)));

        // Finance sees what is owed; the list is active clients first, past ones under their filter
        $this->actingAs($this->staff('finance', ['finance']));
        Livewire::test(ClientList::class)
            ->assertSeeInOrder(['MyCity App (test)', 'Bode Test', '2 contacts', '1 running · 2 in all', '₦150,000', '$1,000', 'Complete'])
            ->assertSeeInOrder(['Tales Consulting (test)', 'No contacts', 'No projects yet', 'Nothing owed', 'Missing: address, company number, tax number, email'])
            ->assertDontSee('Old Friend Ltd (test)')
            ->call('showOnly', 'past')->assertSee('Old Friend Ltd (test)')->assertDontSee('MyCity App (test)')
            ->call('showOnly', 'all')->assertSeeInOrder(['MyCity App (test)', 'Tales Consulting (test)', 'Old Friend Ltd (test)'])
            ->set('search', 'tales')->assertSee('Tales Consulting (test)')->assertDontSee('MyCity App (test)')
            ->set('search', 'nobody')->assertSee('No clients match')->call('clearSearch')->assertSee('MyCity App (test)');

        // Sales sees no amounts: no Unpaid column at all
        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(ClientList::class)->assertSee('MyCity App (test)')->assertDontSee('Unpaid')->assertDontSee('₦150,000');
        $this->page($mycity)->assertDontSee('Unpaid invoices');
        $this->actingAs($this->staff('finance', ['finance']));
        $this->page($mycity)->assertSee('Unpaid invoices')->assertSee('₦150,000.00 left')->assertSee('Overdue');
        $this->assertNotNull($past);
    }

    public function test_sales_and_finance_change_the_invoice_details_and_the_invoice_prints_them(): void
    {
        $tales = $this->client('Tales Consulting (test)');
        $project = $this->project($tales);
        $invoiceId = $this->invoice($project, 900000);

        // Sales (crm.edit): saved through Leads::updateDetails, with its timeline line
        $this->actingAs($this->staff('sales', ['sales']));
        $this->page($tales)->assertSee('Missing on invoices: address, company number, tax number, email')
            ->call('editDetails')->assertSet('askIndustry', false)
            ->set('details.address', "51 George Tiene Crescent\nLagos")->set('details.registration_number', 'RC 7319824')
            ->set('details.tax_number', '12345678-0001')->set('details.email', 'Hello@Tales.test')->set('details.preferred_language', 'FR')
            ->call('saveDetails')->assertHasNoErrors()->assertSet('editing', false)->assertDontSee('Missing on invoices')->assertSee('RC 7319824');
        $fresh = $tales->fresh();
        $this->assertSame(['RC 7319824', '12345678-0001', 'hello@tales.test', 'FR'], [$fresh->registration_number, $fresh->tax_identification_number, $fresh->email, $fresh->preferred_language]);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $tales->id, 'type' => 'details_changed']);

        // The invoice's "Billed to" reads them (company number and email always; the address when it was issued without one)
        [$html] = InvoicePdf::html(DB::table('invoices')->where('id', $invoiceId)->first(), 'en');
        $this->assertStringContainsString('RC 7319824', $html);
        $this->assertStringContainsString('hello@tales.test', $html);

        // The rules are the lead page's
        $this->page($tales)->call('editDetails')->set('details.email', 'not-an-email')->set('details.name', '')->call('saveDetails')
            ->assertHasErrors(['details.email', 'details.name']);

        // Finance (finance.edit, no crm.edit) changes them too
        $this->actingAs($this->staff('finance', ['finance']));
        $this->page($tales)->call('editDetails')->set('details.phone', '+234 814 454 4735')->call('saveDetails')->assertHasNoErrors();
        $this->assertSame('+234 814 454 4735', $tales->fresh()->phone);

        // Leads access alone (Delivery) reads, never writes
        $this->actingAs($this->staff('delivery', ['delivery']));
        $this->page($tales)->assertSee('RC 7319824')->assertDontSee('Edit invoice details')->call('editDetails')->assertForbidden();
    }

    public function test_a_past_client_without_an_industry_or_source_can_be_saved(): void
    {
        $past = $this->client('Old Friend Ltd (test)', 'Past Client');
        DB::table('organizations')->where('id', $past->id)->update(['industry' => null, 'source' => null]);

        $this->actingAs($this->staff('owner', ['owner']));
        $screen = $this->page($past)->call('editDetails')->assertSet('askIndustry', true)->assertSet('details.source', 'manual')
            ->assertSee('Saving needs the industry')
            ->set('details.tax_number', 'TIN-1')->call('saveDetails')->assertHasErrors('details.industry');
        $industry = DB::table('mdm_copq_industries')->value('industry_name');
        $screen->set('details.industry', $industry)->call('saveDetails')->assertHasNoErrors();
        $this->assertSame(['TIN-1', $industry, 'Past Client'], [$past->fresh()->tax_identification_number, $past->fresh()->industry, $past->fresh()->status]);
    }

    public function test_inviting_contacts_to_the_client_portal(): void
    {
        Mail::fake();
        $mycity = $this->client('MyCity App (test)', 'Active Client', ['preferred_language' => 'FR'], [
            ['name' => 'Bode Test', 'email' => 'bode@mycity.test', 'main' => true],
            ['name' => 'Cynthia Test', 'email' => 'cynthia@mycity.test'],
            ['name' => 'Phone Only', 'phone' => '+234 800 000 0002'],
        ]);
        $contact = fn (string $first) => ClientUser::where('organization_id', $mycity->id)->where('first_name', $first)->sole();
        $financeRole = (int) DB::table('roles')->where('audience', 'client')->where('slug', 'client-finance')->value('id');

        // Sales may change details but not create accounts (access.create, as on Accounts)
        $this->actingAs($this->staff('sales', ['sales']));
        $this->page($mycity)->assertSee('No portal account')->assertDontSee('Invite to the Client Portal')
            ->call('startInvite', $contact('Bode')->id)->assertForbidden();

        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        // The first one invited is the primary contact (Client Admin), the email in the client's language
        $screen = $this->page($mycity)->assertSee('Invite to the Client Portal')->assertSee('No email address')
            ->call('startInvite', $contact('Bode')->id)->assertSee('They become the primary contact')->assertSee('in French')
            ->call('invite')->assertHasNoErrors()->assertSet('inviting', null)->assertSee('Invited');
        $bode = $contact('Bode');
        $this->assertSame(['Onboarding', 'Active', 1, 'client-admin'], [$bode->lifecycle_stage, $bode->status, (int) $bode->is_primary_client,
            DB::table('roles')->where('id', $bode->role_id)->value('slug')]);
        $this->assertNull($bode->password);
        $this->assertSame(60, strlen((string) $bode->invitation_token));
        Mail::assertSent(UserInvitation::class, fn ($mail) => $mail->hasTo('bode@mycity.test') && $mail->locale === 'fr' && $mail->token === $bode->invitation_token);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'CLIENT_ACCOUNT_INVITED', 'target_id' => $bode->id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $mycity->id, 'type' => 'portal_invited', 'client_user_id' => $bode->id]);
        // The setup link opens on the Client Portal
        $this->get('http://' . Hosts::portal() . '/setup/' . $bode->invitation_token)->assertOk();

        // The next one gets the role chosen
        $screen->call('startInvite', $contact('Cynthia')->id)->assertSee('Role')->set('inviteRole', (string) $financeRole)->call('invite')->assertHasNoErrors();
        $cynthia = $contact('Cynthia');
        $this->assertSame([0, $financeRole], [(int) $cynthia->is_primary_client, (int) $cynthia->role_id]);

        // Twice is refused; someone without an email can't be invited
        $screen->call('startInvite', $cynthia->id)->call('invite')->assertHasErrors('invite');
        $screen->call('startInvite', $contact('Phone')->id)->call('invite')->assertHasErrors('invite');
        $this->assertNull($contact('Phone')->invitation_token);

        // Sending again makes a new link
        $old = $cynthia->invitation_token;
        $screen->call('resend', $cynthia->id);
        $this->assertNotSame($old, $contact('Cynthia')->invitation_token);
        Mail::assertSent(UserInvitation::class, 3);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'CLIENT_INVITATION_RESENT', 'target_id' => $cynthia->id]);

        // Once they have chosen a password: Has access, nothing to send
        DB::table('client_users')->where('id', $bode->id)->update(['password' => bcrypt('secret-password-1'), 'invitation_token' => null, 'lifecycle_stage' => 'Active']);
        $this->page($mycity)->assertSee('Has access');
        $this->page($mycity)->call('resend', $bode->id);
        Mail::assertSent(UserInvitation::class, 3);
    }

    public function test_adding_a_person_and_inviting_them_at_once(): void
    {
        Mail::fake();
        $tales = $this->client('Tales Consulting (test)');
        $this->client('Other Co (test)', 'Active Client', [], [['name' => 'Taken Test', 'email' => 'taken@other.test']]);

        $this->actingAs($this->staff('owner', ['owner']));
        $screen = $this->page($tales)->call('newPerson')->assertSet('person.invite', true)
            ->set('person.name', 'Ada Tales')->call('addPerson')->assertHasErrors('person.email')
            ->set('person.email', 'taken@other.test')->call('addPerson')->assertHasErrors('person.email')->assertSee('already a contact of Other Co (test)')
            ->set('person.email', 'ada@tales.test')->set('person.job_title', 'CFO')->call('addPerson')->assertHasNoErrors()->assertSet('adding', false);
        $ada = ClientUser::where('email', 'ada@tales.test')->sole();
        $this->assertSame([$tales->id, 'Onboarding', 1], [$ada->organization_id, $ada->lifecycle_stage, (int) $ada->is_primary_client]);
        Mail::assertSent(UserInvitation::class, fn ($mail) => $mail->hasTo('ada@tales.test'));

        // Without the invitation, just a contact; Sales never gets the invitation even if the box is sent ticked
        $this->actingAs($this->staff('sales', ['sales']));
        $this->page($tales)->call('newPerson')->assertSet('person.invite', false)
            ->set('person.name', 'Bola Tales')->set('person.invite', true)->call('addPerson')->assertHasNoErrors();
        $bola = ClientUser::where('organization_id', $tales->id)->where('first_name', 'Bola')->sole();
        $this->assertNull($bola->invitation_token);
        $this->assertSame('Lead', $bola->lifecycle_stage);
        Mail::assertSent(UserInvitation::class, 1);
        $screen->assertOk();
    }
}
