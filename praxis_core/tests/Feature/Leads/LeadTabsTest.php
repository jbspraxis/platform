<?php

namespace Tests\Feature\Leads;

use App\Livewire\Hq\Leads\LeadPage;
use App\Livewire\Hq\Leads\MeetingsTab;
use App\Livewire\Hq\Leads\OpportunitiesTab;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Leads\LeadMeetings;
use App\Support\Leads\Leads;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * Leads PR 2's tabs on the lead's page (docs/LEADS.md section 9): Meetings (schedule, log, change, cancel, the
 * transcript, typed notes, the AI's next step, Open an opportunity with the AI's title), Opportunities in full, and the
 * tab counts. HQ is English. Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadTabsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const NOTES = ['summary' => 'They count stock on paper.', 'decisions' => [], 'needs' => [], 'risks' => [], 'open_questions' => [],
        'next_steps' => [['action' => 'Send a pilot outline', 'owner' => 'JBS Praxis', 'due' => 'Friday']]];

    private FakeGoogle $google;

    private FakeAi $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
        $this->google = FakeGoogle::install();
        $this->ai = FakeAi::install()->respond('meeting_notes', self::NOTES)->respond('opportunity_title', ['title' => 'Stock and till reconciliation']);
    }

    private function lead(User $by): Organization
    {
        return Leads::create(['name' => 'Kilo Retail (test)', 'country' => 'Nigeria', 'industry' => 'Retail', 'phone' => '+234 800 000 0001', 'address' => '1 Example Way, Lagos'],
            [['name' => 'Ada Test', 'email' => 'ada@kilo.test'], ['name' => 'Bola Test', 'phone' => '+234 800 000 0003']], $by);
    }

    private function tab(Organization $lead, array $query = [])
    {
        return Livewire::withQueryParams($query)->test(MeetingsTab::class, ['leadId' => $lead->id]);
    }

    public function test_logging_a_meeting_from_the_tab(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $ada = DB::table('client_users')->where('email', 'ada@kilo.test')->value('id');

        $tab = $this->tab($lead)->assertSee('No meetings yet')->assertSee('Log a meeting')
            ->call('openLog')->assertSet('form', 'log')->assertSet('staff', [$sales->id])->assertSet('contacts', [$ada])
            ->assertSee('no email yet: add one on the Overview to record them');   // Bola has a phone only

        // In the future, or in person with no address: said so
        $tab->set('date', '2026-11-06')->set('time', '10:00')->call('save')->assertHasErrors('date')
            ->set('date', '2026-11-04')->set('where', 'in_person')->set('location', '')->set('staff', [])->call('save')->assertHasErrors(['location', 'staff'])
            ->assertSee('Give the address where you met, or choose a call or video.');

        $tab->set('location', 'Their office')->set('staff', [$sales->id])->set('typedNotes', 'They count stock on paper.')
            ->set('transcriptText', 'Ada Test: We count stock on paper.')->call('save')
            ->assertHasNoErrors()->assertSet('form', '')->assertDispatched('lead-changed')->assertSee('Meeting logged with its transcript');
        $m = Meeting::where('organization_id', $lead->id)->sole();
        $tab->assertSet('open', $m->id)->assertSee('Their office')->assertSee('They count stock on paper.')
            ->assertSeeHtml(e(route('hq.crm.lead-meeting-notes', ['organization' => $lead->id, 'meeting' => $m->id, 'what' => 'notes', 'transcript' => $m->transcripts->sole()->id])))
            ->assertDontSee('Minutes for the client');

        // The AI's first next step becomes the lead's
        $tab->call('useNextStep', $m->transcripts->sole()->id)->assertSee('Next step set: “Send a pilot outline”');
        $this->assertSame('Send a pilot outline', $lead->fresh()->next_step);

        // Typed notes edited later
        $tab->call('editNotes', $m->id)->assertSet('notesText', 'They count stock on paper.')->set('notesText', 'Pilot in two stores.')->call('saveNotes')->assertSee('Notes saved.');
        $this->assertSame('Pilot in two stores.', $m->fresh()->typed_notes);

        Livewire::test(LeadPage::class, ['organization' => $lead])->assertSee('Meetings (1)');
    }

    public function test_scheduling_changing_and_cancelling_from_the_tab(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);

        $this->google->disconnected();
        $this->tab($lead)->assertSee('Google isn’t connected')->assertSeeHtml('wire:click="openSchedule" disabled');
        $this->google = FakeGoogle::install();

        $tab = $this->tab($lead)->call('openSchedule')->assertSet('title', 'Introduction: Kilo Retail (test)')->assertSet('location', '1 Example Way, Lagos')
            ->set('date', '2026-11-04')->call('save')->assertHasErrors('date')
            ->set('date', '2026-11-09')->set('time', '10:00')->set('contacts', [])->call('save')->assertHasErrors('contacts')
            ->set('guests', 'femi@foxtrot.test')->call('save')->assertHasNoErrors()->assertSee('Meeting scheduled. Google has sent the invitations.');
        $m = Meeting::with('attendees')->where('organization_id', $lead->id)->sole();
        $this->assertSame([null, 'scheduled', null], [$m->opportunity_id, $m->status, $m->location]);
        $this->assertSame(['femi@foxtrot.test'], $m->attendees->where('role', 'guest')->pluck('email')->values()->all());

        // Changed: Google is told; a meeting on Meet stays on Meet
        $tab->call('openEdit', $m->id)->assertSet('form', 'edit')->assertDontSee('In person</label>', false)->set('time', '11:00')->call('save')->assertHasNoErrors()
            ->assertSee('Meeting changed.');
        $this->assertSame('2026-11-09 10:00:00', $m->fresh()->starts_at->format('Y-m-d H:i:s'));
        $this->assertCount(1, $this->google->updated);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $lead->id, 'type' => 'meeting', 'meeting_id' => $m->id, 'summary' => 'Meeting moved to Mon 9 Nov, 11:00 (WAT): Introduction: Kilo Retail (test)']);

        // Cancelled through its own dialog
        $tab->call('confirmCancel', $m->id)->assertSee('Cancel “Introduction: Kilo Retail (test)”?')->call('cancelMeeting')->assertSee('Meeting cancelled.');
        $this->assertSame('cancelled', $m->fresh()->status);
    }

    public function test_a_booking_waits_for_free_audits_and_view_only_people_read(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $delivery = $this->staff('delivery', ['delivery']);   // Leads view only
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $starts = CarbonImmutable::parse('2026-11-09 10:30', 'Africa/Lagos');
        $appt = $this->insertRow('appointments', ['id' => (string) \Illuminate\Support\Str::uuid(), 'organization_id' => $lead->id, 'title' => 'Free audit: Finance',
            'status' => 'Requested', 'start_time' => '2026-11-09 10:30:00', 'end_time' => '2026-11-09 11:30:00', 'created_at' => now()])['id'];
        $booking = LeadMeetings::fromBooking($lead->id, $appt, null, 'Free audit: Finance', $starts, $starts->addHour());

        $this->tab($lead, ['meeting' => $booking->id])->assertSee('Waiting for approval')->assertSee('Approve it or decline it in')
            ->assertDontSee('Cancel the meeting')->call('openEdit', $booking->id)->assertSee('A free audit request is approved in Free audits.')
            ->call('confirmCancel', $booking->id)->assertSet('cancellingId', '');

        $this->actingAs($delivery);
        $this->tab($lead, ['meeting' => $booking->id])->assertSee('Free audit: Finance')->assertDontSee('Log a meeting')
            ->call('openLog')->assertForbidden();
    }

    public function test_opening_an_opportunity_from_the_tab_with_the_ai_title(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $starts = CarbonImmutable::parse('2026-11-04 15:00', 'Africa/Lagos');
        $m = LeadMeetings::logPast($lead, ['title' => 'Introduction: Kilo Retail', 'starts_at' => $starts, 'ends_at' => $starts->addHour(), 'staff' => [$sales->id],
            'contacts' => [], 'guests' => [], 'transcript' => 'Ada Test: Our stock never matches the tills.'], $sales);

        $tab = $this->tab($lead, ['meeting' => $m->id])->assertSee('Open an opportunity from it')
            ->call('openOpportunity', $m->id)->assertSet('oppTitle', 'Kilo Retail')->assertSet('oppLead', $sales->id)->assertSet('oppLanguage', 'en')
            ->call('suggestTitle')->assertSet('oppTitle', 'Stock and till reconciliation')->assertSee('Suggested by the AI from the meeting’s transcript')
            ->call('suggestTitle');   // asked once per dialog
        $this->assertCount(1, $this->ai->requestsFor('opportunity_title'));

        $tab->set('oppTitle', '')->call('createOpportunity')->assertHasErrors('oppTitle')
            ->set('oppTitle', 'Stock and till pilot')->call('createOpportunity');
        $o = Opportunity::where('organization_id', $lead->id)->sole();
        $tab->assertRedirect(route('hq.crm.opportunity', ['opportunity' => $o->id, 'tab' => 'meetings', 'meeting' => $m->id]));
        $this->assertSame($o->id, $m->fresh()->opportunity_id);

        // On the lead it is listed with its opportunity, read only
        $this->tab($lead)->assertSee('In opportunities')->assertSee('Stock and till pilot')->assertDontSee('Open an opportunity from it');
        $this->tab($lead)->call('openOpportunity', $m->id)->assertNotFound();
    }

    public function test_the_opportunities_tab_in_full(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $open = Opportunity::create(['organization_id' => $lead->id, 'title' => 'Fleet tracking (test)', 'stage' => 'proposal_sent', 'lead_user_id' => $sales->id,
            'language' => 'en', 'source' => 'website_booking', 'created_by' => $sales->id]);
        Opportunity::create(['organization_id' => $lead->id, 'title' => 'Payroll (test)', 'stage' => 'lost', 'lead_user_id' => $sales->id, 'language' => 'en',
            'source' => 'manual', 'lost_reason' => 'Chose another firm', 'closed_at' => now()->subDay(), 'created_by' => $sales->id]);
        $this->insertRow('proposals', ['id' => (string) \Illuminate\Support\Str::uuid(), 'opportunity_id' => $open->id, 'organization_id' => $lead->id, 'proposal_ref' => 'PRX-P-TEST-1',
            'status' => Proposal::SENT, 'total_value' => 2500000, 'currency' => 'NGN', 'version' => 1, 'signing_mode' => 'approve', 'created_at' => now(), 'updated_at' => now()]);
        Meeting::create(['opportunity_id' => $open->id, 'organization_id' => $lead->id, 'kind' => 'client', 'title' => 'Follow-up', 'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(), 'status' => 'scheduled', 'transcript_status' => 'waiting']);

        Livewire::test(OpportunitiesTab::class, ['leadId' => $lead->id])
            ->assertSeeHtml('<strong>1</strong> open')->assertSeeHtml('<strong>1</strong> lost')
            ->assertSeeInOrder(['Open proposals', 'Fleet tracking (test)', 'From a website booking', '1 meeting', 'Next: Sat 7 Nov', 'PRX-P-TEST-1', 'Payroll (test)', 'Chose another firm'])
            ->assertSeeHtml(route('hq.crm.opportunity', ['opportunity' => $open->id]));
        Livewire::test(LeadPage::class, ['organization' => $lead])->assertSee('Opportunities (2)')->assertSee('Meetings (1)');
    }
}
