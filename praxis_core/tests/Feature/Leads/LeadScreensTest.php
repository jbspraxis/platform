<?php

namespace Tests\Feature\Leads;

use App\Livewire\Hq\Intelligence\LeadsRegister;
use App\Livewire\Hq\Leads\AddLead;
use App\Livewire\Hq\Leads\LeadPage;
use App\Livewire\Hq\Leads\LeadSettings;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Support\Hosts;
use App\Support\Leads\Contacts;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\Leads;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Leads PR 1's screens (docs/LEADS.md section 7): Add a lead, the lead's page (Overview, Activity, and the tabs PR 2
 * fills), the list opening each lead, and Lead settings. HQ is English.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadScreensTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function lead(array $values = [], array $contacts = [], $by = null): Organization
    {
        return Leads::create($values + ['name' => 'Kilo Logistics (test)', 'country' => 'Nigeria', 'industry' => 'Logistics', 'phone' => '+234 800 000 0001'], $contacts, $by);
    }

    private function page(Organization $lead, array $query = [])
    {
        return Livewire::withQueryParams($query)->test(LeadPage::class, ['organization' => $lead]);
    }

    public function test_the_routes_and_who_opens_them(): void
    {
        $hq = 'http://' . Hosts::hq();
        $sales = $this->staff('sales', ['sales']);
        $lead = $this->lead([], [], $sales);
        $engineer = $this->staff('engineer', ['engineer']);
        $delivery = $this->staff('delivery', ['delivery']);

        $this->actingAs($sales)->get($hq . '/intelligence/leads')->assertOk()->assertSee('Kilo Logistics (test)')
            ->assertSee(route('hq.crm.lead', ['organization' => $lead->id]), false)->assertSee('Add lead');
        $this->actingAs($sales)->get($hq . '/intelligence/leads/new')->assertOk()->assertSee('Add a lead');
        $this->actingAs($sales)->get($hq . '/intelligence/leads/' . $lead->id)->assertOk()->assertSee('All leads')->assertSee('Log activity');
        // Leads access (crm.view) sees every lead; adding needs crm.create; no Leads access, no page
        $this->actingAs($delivery)->get($hq . '/intelligence/leads/' . $lead->id)->assertOk()->assertDontSee('Edit details')->assertDontSee('Log it');
        $this->actingAs($delivery)->get($hq . '/intelligence/leads/new')->assertForbidden();
        $this->actingAs($engineer)->get($hq . '/intelligence/leads/' . $lead->id)->assertForbidden();
        $this->actingAs($sales)->get($hq . '/intelligence/leads/' . \Illuminate\Support\Str::uuid())->assertNotFound();
    }

    public function test_adding_a_lead(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);

        // Nothing typed: the three required fields, and a way to reach them
        Livewire::test(AddLead::class)->set('lead.next_step', '')->call('save')
            ->assertHasErrors(['lead.name', 'lead.industry'])->assertHasNoErrors('lead.country');
        Livewire::test(AddLead::class)->set('lead.name', 'Lima Freight (test)')->set('lead.industry', 'Logistics')->call('save')
            ->assertHasErrors('lead.reach')->assertSee('Add at least one phone number or email address');

        $screen = Livewire::test(AddLead::class)
            ->assertSet('lead.next_step', 'Make first contact')
            ->set('lead.name', 'Lima Freight (test)')->set('lead.country', 'Cameroon')->set('lead.industry', 'Logistics')
            ->assertSet('lead.preferred_language', 'FR')
            ->set('contacts.0.name', 'Ada Test')->set('contacts.0.email', 'ada@lima.test')->set('contacts.0.deal_role', 'decision_maker')
            ->call('addContact')->set('contacts.1.name', 'Bola Test')->set('contacts.1.phone', '+237 600 000 001')
            ->call('makeMain', 1)
            ->call('addContact')
            ->set('lead.source', 'event')->set('lead.source_detail', 'Trade fair (test)')
            ->call('save')->assertHasNoErrors();
        $org = Organization::where('name', 'Lima Freight (test)')->sole();
        $screen->assertRedirect(route('hq.crm.lead', ['organization' => $org->id]));
        $this->assertSame([$sales->id, 'event', 'Trade fair (test)', 'FR', 'Make first contact'], [$org->assigned_to, $org->source, $org->source_detail, $org->preferred_language, $org->next_step]);
        $contacts = Contacts::of($org->id);
        $this->assertSame(['Bola', 'Ada'], $contacts->pluck('first_name')->all(), 'Bola is the main contact; the empty third block is dropped');

        // "Another country" is typed
        Livewire::test(AddLead::class)->set('lead.name', 'Mike Mining (test)')->set('lead.industry', 'Logistics')->set('lead.email', 'info@mike.test')
            ->set('lead.country', AddLead::OTHER_COUNTRY)->call('save')->assertHasErrors('otherCountry')
            ->set('otherCountry', 'Mali')->call('save')->assertHasNoErrors();
        $this->assertSame('Mali', Organization::where('name', 'Mike Mining (test)')->value('country'));
    }

    public function test_duplicates_show_while_typing_and_saving_anyway_is_allowed(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $kilo = $this->lead(['name' => 'Kilo Logistics'], [['name' => 'Ada Test', 'email' => 'ada@kilo.test']], $sales);

        Livewire::test(AddLead::class)->set('lead.name', 'Kilo Logistics Ltd')
            ->assertSee('This may already be in HQ')->assertSee('Same name')->assertSeeHtml(route('hq.crm.lead', ['organization' => $kilo->id]))->assertSee('Open it')
            ->set('lead.name', 'Brand New (test)')->assertDontSee('This may already be in HQ')
            ->set('contacts.0.email', 'ada@kilo.test')->assertSee('Same email')
            ->set('contacts.0.email', '')->set('lead.name', 'Kilo Logistics Ltd')->set('lead.industry', 'Logistics')->set('lead.email', 'other@kilo2.test')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(2, Organization::where('name', 'like', 'Kilo Logistics%')->count());

        // A contact whose email is someone else's is refused under the field, naming the lead
        Livewire::test(AddLead::class)->set('lead.name', 'November Co (test)')->set('lead.industry', 'Logistics')
            ->set('contacts.0.name', 'Ada Copy')->set('contacts.0.email', 'ada@kilo.test')->call('save')
            ->assertHasErrors('contacts.0.email')->assertSee('already a contact of Kilo Logistics.');
    }

    public function test_the_cost_of_poor_quality_is_optional_in_more_details(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        $system = DB::table('mdm_copq_architectures')->value('tech_key');
        $bottleneck = DB::table('mdm_copq_bottlenecks')->value('bottleneck_name');
        $industry = DB::table('mdm_copq_industries')->value('industry_name');

        // More details starts closed; one figure filled means all are needed, and the section opens to show why
        $screen = Livewire::test(AddLead::class)->assertSet('moreOpen', false)->assertDontSee('Annual turnover')
            ->set('lead.name', 'Oscar Retail (test)')->set('lead.industry', $industry)->set('lead.phone', '+234 800 000 0009')
            ->set('copq.turnover', '1000000')->call('save')->assertHasErrors(['copq.acv', 'copq.tool'])->assertSet('moreOpen', true)->assertSee('Annual turnover');
        $screen->set('copq.tool', $system)->set('copq.bottleneck', $bottleneck)->set('copq.currency', 'USD')
            ->set('copq.acv', '20000')->set('copq.headcount', '80')->set('copq.mgmt_nodes', '8')->set('copq.manual_hrs', '6')
            ->set('copq.hourly_cost', '30')->set('copq.error_rate', '4')->call('save')->assertHasNoErrors();
        $org = Organization::where('name', 'Oscar Retail (test)')->sole();
        $assessment = DB::table('copq_assessments')->where('organization_id', $org->id)->sole();
        $this->assertSame([$system, 'USD', 80], [$assessment->primary_tool, $assessment->copq_currency, (int) $assessment->headcount]);
        $this->assertGreaterThan(0, (float) $assessment->calculated_leakage);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $org->id, 'type' => 'copq_calculated']);

        // Without figures, no estimate; the lead page offers Calculate
        $plain = $this->lead(['name' => 'Papa Plain (test)', 'industry' => $industry]);
        $this->page($plain)->assertSee('Not estimated yet')->call('startCalculate')->call('saveCopq')->assertHasErrors(['copq.turnover'])
            ->set('copq.tool', $system)->set('copq.bottleneck', $bottleneck)->set('copq.turnover', '500000')->set('copq.acv', '10000')
            ->set('copq.headcount', '20')->set('copq.mgmt_nodes', '2')->set('copq.manual_hrs', '5')->set('copq.hourly_cost', '20')->set('copq.error_rate', '3')
            ->call('saveCopq')->assertHasNoErrors()->assertSee('Estimated, a year');
        $this->assertSame(1, DB::table('copq_assessments')->where('organization_id', $plain->id)->count());
    }

    public function test_the_overview_status_client_next_step_and_activity(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead([], [['name' => 'Ada Test', 'email' => 'ada@kilo.test', 'job_title' => 'CFO', 'deal_role' => 'finance']], $sales);

        $screen = $this->page($lead)->assertSee('Ada Test')->assertSee('Main contact')->assertSee('Finance')->assertSee('New')
            // Lost needs a reason; "other" a note
            ->set('status', 'lost')->assertSee('Why it was lost')->call('saveStatus')->assertHasErrors('lostReason')
            ->set('lostReason', 'other')->call('saveStatus')->assertHasErrors('lostNote')
            ->set('lostNote', 'Merged with another group.')->call('saveStatus')->assertHasNoErrors()->assertSee('Merged with another group.');
        $this->assertSame('lost', $lead->fresh()->lead_status);

        // Mark as client asks first
        $screen->call('askMarkClient')->assertSee('It becomes an Active Client from today')->call('cancelMarkClient');
        $this->assertSame('lost', $lead->fresh()->lead_status);
        $screen->set('status', 'client')->call('saveStatus')->assertSet('confirmingClient', true)->call('markClient')
            ->assertSee('Client since')->assertSee('Marked by hand');
        $this->assertSame(['client', 'Active Client'], [$lead->fresh()->lead_status, $lead->fresh()->status]);

        // Next step
        $screen->call('editNextStep')->set('nextStep', 'Send the contract')->set('nextStepDue', '')->call('saveNextStep')->assertHasErrors('nextStepDue')
            ->set('nextStepDue', '2026-12-01')->call('saveNextStep')->assertHasNoErrors()->assertSee('Send the contract');
        $this->assertSame('2026-12-01', $lead->fresh()->next_step_due->format('Y-m-d'));

        // Log activity: a call with the contact, then it is on the timeline and the Activity tab
        $ada = Contacts::of($lead->id)->first();
        $screen->set('activityType', 'whatsapp')->call('logActivity')->assertHasErrors('activityText')
            ->set('activityText', "Agreed to meet on Friday.\nThey want the deck first.")->set('activityContact', $ada->id)->call('logActivity')->assertHasNoErrors()
            ->assertSee('WhatsApp with Ada Test: Agreed to meet on Friday.');
        $row = DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'whatsapp')->sole();
        $this->assertSame([$sales->id, $ada->id], [$row->actor_id, $row->client_user_id]);

        $this->page($lead, ['tab' => 'activity'])->assertSee('They want the deck first.')->assertSee('Became a client')
            ->set('activityFilter', 'whatsapp')->assertSee('Agreed to meet on Friday.')->assertDontSee('Marked as a client by hand.');
    }

    public function test_details_owner_source_and_contacts_are_edited_on_the_page(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead([], [], $sales);

        $this->page($lead)->call('editDetails')->assertSet('details.source', 'manual')
            ->set('details.phone', '')->call('saveDetails')->assertHasErrors('details.phone')
            ->set('details.phone', '+234 800 000 0001')->set('details.source', 'referral')->set('details.source_detail', 'A client (test)')
            ->set('details.city', 'Lagos')->call('saveDetails')->assertHasNoErrors()->assertSee('Referral')->assertSee('Lagos, Nigeria');
        $this->assertSame(['referral', 'A client (test)', 'Lagos'], [$lead->fresh()->source, $lead->fresh()->source_detail, $lead->fresh()->city]);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $lead->id, 'type' => 'source_changed']);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $lead->id, 'type' => 'details_changed', 'summary' => 'Details changed: city.']);

        $screen = $this->page($lead)->call('newContact')->call('saveContact')->assertHasErrors('contact.name')
            ->set('contact.name', 'Bola Test')->set('contact.email', 'not-an-email')->call('saveContact')->assertHasErrors('contact.email')
            ->set('contact.email', 'bola@kilo.test')->set('contact.deal_role', 'technical')->call('saveContact')->assertHasNoErrors()->assertSee('Bola Test');
        $bola = Contacts::of($lead->id)->sole();
        $this->assertTrue((bool) $bola->is_main_contact);
        $screen->call('editContact', $bola->id)->set('contact.job_title', 'CTO')->call('saveContact')->assertSee('CTO')
            ->call('removeContact', $bola->id)->assertDontSee('bola@kilo.test');
        $this->assertSoftDeleted('client_users', ['id' => $bola->id]);
    }

    public function test_someone_with_view_only_changes_nothing(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $lead = $this->lead([], [['name' => 'Ada Test', 'email' => 'ada@kilo.test']], $sales);
        $this->actingAs($this->staff('auditor', ['auditor']));

        $screen = $this->page($lead)->assertSee('Kilo Logistics (test)')->assertDontSee('Edit details')->assertDontSee('Mark as client')->assertDontSee('Add contact');
        foreach (['editDetails', 'saveDetails', 'saveStatus', 'markClient', 'editNextStep', 'newContact', 'startCalculate', 'logActivity', 'claim', 'openDiffViewer'] as $action) {
            $this->page($lead)->call($action)->assertForbidden();
        }
        $this->page($lead)->call('removeContact', Contacts::of($lead->id)->first()->id)->assertForbidden();
        $this->assertSame('new', $lead->fresh()->lead_status);
        $this->assertSame(1, Contacts::of($lead->id)->count());
    }

    public function test_the_tabs_for_meetings_emails_and_opportunities(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead([], [], $sales);
        Opportunity::create(['organization_id' => $lead->id, 'title' => 'Fleet tracking (test)', 'stage' => 'proposal_sent', 'lead_user_id' => $sales->id,
            'language' => 'en', 'source' => 'manual', 'created_by' => $sales->id]);

        $this->page($lead, ['tab' => 'meetings'])->assertSee('Meetings with this lead are coming');
        $this->page($lead, ['tab' => 'emails'])->assertSee('Emails with this lead are coming');
        $this->page($lead, ['tab' => 'opportunities'])->assertSee('Opportunities (1)')->assertSee('Fleet tracking (test)')
            ->assertSee('led by Sales Test')->assertSeeHtml(e(route('hq.crm.opportunities', ['new' => 1, 'organization' => $lead->id])));
        $this->page($lead, ['tab' => 'nonsense'])->assertSet('tab', 'overview');
    }

    public function test_the_list_and_lead_settings(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $sales = $this->staff('gerald', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead(['next_step' => 'Call back', 'next_step_due' => '2026-01-05'], [['name' => 'Ada Test', 'email' => 'ada@kilo.test']], $sales);

        Livewire::test(LeadsRegister::class)->assertSee('Kilo Logistics (test)')->assertSee('Ada Test')->assertSee('Call back')->assertSee('overdue')
            ->assertSee('New')->assertDontSee('Lead settings');
        $csv = Livewire::test(LeadsRegister::class)->call('exportCsv');
        $this->assertNotNull($csv);

        // Lead settings: the owner (or a partner) chooses who website leads go to
        $this->actingAs($owner);
        Livewire::test(LeadsRegister::class)->assertSee('Lead settings');
        Livewire::test(LeadSettings::class)->call('show')->assertSee('Website leads go to')->set('search', 'Gerald')->assertSee('Gerald Test')
            ->call('choose', $sales->id)->assertRedirect(route('hq.crm.leads'));
        $this->assertSame($sales->id, LeadOwner::defaultOwnerId());
        Livewire::test(LeadSettings::class)->call('show')->assertSee('Gerald Test')->call('clear');
        $this->assertNull(LeadOwner::defaultOwnerId());
        $this->actingAs($sales);
        Livewire::test(LeadSettings::class)->assertForbidden();
    }
}
