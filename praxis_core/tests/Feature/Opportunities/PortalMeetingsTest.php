<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\Opportunities\MeetingsTab;
use App\Livewire\Portal\MeetingsGateway;
use App\Mail\MeetingInvitationMail;
use App\Models\Meeting;
use App\Support\Google\MeetingRequest;
use App\Support\Opportunities\FreeTimes;
use App\Support\Opportunities\PortalLinks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * The client's meetings page (docs/OPPORTUNITIES.md decisions 4 and 12): signed, expiring link; upcoming meetings
 * and minutes sent, never internal meetings or transcripts; a follow-up booked at a free time (Google event, invites)
 * or times proposed for the lead to confirm in HQ.
 */
class PortalMeetingsTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private FakeGoogle $google;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 5 November 2026, 13:00 in Lagos
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
        $this->google = FakeGoogle::install();
    }

    public function test_the_link_is_signed_and_expires(): void
    {
        [$o, , $contact] = $this->opportunity();
        $other = $this->opportunityFor($this->organisation());
        $url = PortalLinks::meetings($o, $contact);
        $this->assertStringStartsWith('http://portal.', $url);
        $this->assertStringContainsString("/meetings/{$o->id}?c={$contact}&expires=", $url);

        $this->get($url)->assertOk()->assertSee('Your meetings with JBS Praxis')->assertSee('Payroll and HR');
        $this->get(strtok($url, '?'))->assertForbidden();
        $this->get(str_replace($o->id, $other->id, $url))->assertForbidden();
        $this->get(str_replace("c={$contact}", 'c=' . $this->clientContact($o->organization_id), $url))->assertForbidden();

        $this->travel(61)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_the_client_books_a_free_time_and_hq_creates_the_meeting_with_the_invitations(): void
    {
        [$o, $lead, $contact] = $this->opportunity();
        // The company calendar is busy 10:00 to 11:00 WAT tomorrow; the lead's own calendar isn't shared
        $this->google->busy('primary', [['2026-11-06 09:00:00', '2026-11-06 10:00:00']])->busy($lead->email, null);

        $free = FreeTimes::for($o);
        $friday = array_map(fn ($s) => $s->setTimezone('Africa/Lagos')->format('H:i'), $free['2026-11-06']);
        $this->assertSame(['09:00', '11:00', '11:30', '12:00'], array_slice($friday, 0, 4));   // 09:30 and 10:00 clash
        $this->assertSame('16:00', end($friday));
        $this->assertArrayNotHasKey('2026-11-07', $free);   // Saturday
        $this->assertArrayHasKey('2026-11-09', $free);

        $page = Livewire::withQueryParams(['c' => $contact])->test(MeetingsGateway::class, ['opportunity' => $o->id])
            ->assertSet('name', 'Ada Client')->assertSet('email', 'ada@kribi.test')
            ->call('choose', 'pick')->assertSee('Free times (60 minutes)')
            ->call('book')->assertHasErrors(['chosen'])
            ->call('pick', '2026-11-06T08:00:00+00:00')   // 09:00 WAT
            ->set('note', 'The year-end close')
            ->call('book')->assertHasNoErrors()
            ->assertSee('Booked.');

        $m = Meeting::with('attendees')->sole();
        $this->assertSame(['client', 'scheduled', null], [$m->kind, $m->status, $m->created_by]);
        $this->assertSame('2026-11-06 08:00:00', $m->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('Follow-up: Payroll and HR', $m->title);
        $this->assertSame('The year-end close', $m->agenda);
        $this->assertEqualsCanonicalizing(['staff', 'client'], $m->attendees->pluck('role')->all());
        $this->google->assertMeetingCreated(fn (MeetingRequest $r) => collect($r->attendees)->pluck('email')->contains('ada@kribi.test'));
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo('ada@kribi.test') && $mail->variant === 'invite');
        $this->assertSame('Follow-up booked by the client', DB::table('notifications')->where('user_id', $lead->id)->value('title'));

        // A time that has just gone is refused
        $this->google->busy('primary', [['2026-11-06 08:00:00', '2026-11-06 17:00:00']]);
        $page->call('choose', 'pick')->call('pick', '2026-11-06T13:00:00+00:00')->call('book')->assertHasErrors(['chosen']);
    }

    public function test_the_client_proposes_times_and_the_lead_schedules_one_in_hq(): void
    {
        [$o, $lead] = $this->opportunity();
        // Bola, from a partner firm, was a guest at an earlier meeting of this work: HQ knows the address
        $earlier = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $o->organization_id, 'kind' => 'client', 'title' => 'Earlier',
            'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHour(), 'status' => 'cancelled', 'transcript_status' => 'manual', 'created_by' => $lead->id]);
        $earlier->attendees()->create(['email' => 'bola@partner.test', 'name' => 'Bola Guest', 'role' => 'guest']);

        Livewire::test(MeetingsGateway::class, ['opportunity' => $o->id])
            ->call('choose', 'propose')
            ->call('propose')->assertHasErrors(['name', 'email'])
            // An address HQ doesn't know for this work can't book: a forwarded link can't invite anyone
            ->set('name', 'Someone')->set('email', 'someone@elsewhere.test')
            ->call('propose')->assertHasErrors(['email'])->assertSee('Use the email address our invitation was sent to.')
            ->set('name', 'Bola Guest')->set('email', 'bola@partner.test')
            ->call('propose')->assertHasErrors(['proposed'])
            ->set('proposed.0.date', '2026-11-05')->set('proposed.0.time', '13:30')
            ->set('proposed.1.date', '2026-11-10')->set('proposed.1.time', '15:00')
            ->call('propose')->assertHasErrors(['proposed.0'])->assertSee('at least two hours from now')
            ->set('proposed.0.date', '')->set('proposed.0.time', '')
            ->call('propose')->assertHasNoErrors()->assertSee('We’ll confirm one of your times by email.')
            ->assertSee('Waiting for us to confirm a time');

        $m = Meeting::with('attendees')->where('status', '!=', 'cancelled')->sole();
        $this->assertSame(['requested', null], [$m->status, $m->google_event_id]);
        $this->assertStringContainsString('Tuesday 10 November 2026, 15:00 WAT', $m->agenda);
        $this->assertSame(['bola@partner.test' => 'guest', mb_strtolower($lead->email) => 'staff'], $m->attendees->pluck('role', 'email')->sortKeys()->all());
        $this->google->assertNothingCreated();
        Mail::assertNothingSent();
        $this->assertSame('The client proposed meeting times', DB::table('notifications')->where('user_id', $lead->id)->value('title'));

        // The request stays on top in HQ even once its first proposed time has gone by
        $this->actingAs($lead);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00', 'Africa/Lagos'));
        $this->assertContains($m->id, Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->viewData('upcoming')->pluck('id')->all());
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));

        // The lead schedules it at the client's time: now it is in Google, with the invitations
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('openEdit', $m->id)->assertSet('date', '2026-11-10')->assertSet('time', '15:00')->assertSee('Schedule the requested meeting')
            ->call('save')->assertHasNoErrors()->assertSee('Meeting scheduled');
        $this->assertSame(['scheduled', 'fake_event_1'], [$m->fresh()->status, $m->fresh()->google_event_id]);
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo('bola@partner.test') && $mail->variant === 'invite');
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MEETING_CONFIRMED', 'target_id' => $o->id]);
    }

    public function test_only_client_meetings_and_the_minutes_sent_appear(): void
    {
        [$o] = $this->opportunity();
        $meeting = fn (array $v) => Meeting::create($v + ['opportunity_id' => $o->id, 'organization_id' => $o->organization_id, 'kind' => 'client',
            'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour(), 'status' => 'held', 'transcript_status' => 'ready']);
        $meeting(['title' => 'Kick-off'])->transcripts()->create(['source' => 'paste', 'text' => 'SECRET TRANSCRIPT', 'collected_at' => now(),
            'notes' => ['summary' => 'SECRET NOTES'], 'minutes' => "We agreed the scope.\n\nDecisions\n- Lagos first", 'minutes_sent_at' => now()]);
        $meeting(['title' => 'Workshop'])->transcripts()->create(['source' => 'paste', 'text' => 'x', 'collected_at' => now(), 'minutes' => 'UNSENT MINUTES']);
        $meeting(['title' => 'Team debrief', 'kind' => 'internal']);
        $meeting(['title' => 'Cancelled call', 'status' => 'cancelled', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $meeting(['title' => 'Next steps call', 'status' => 'scheduled', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(), 'meet_link' => 'https://meet.google.com/abc-defg-hij']);

        Livewire::test(MeetingsGateway::class, ['opportunity' => $o->id])
            ->assertSee('Next steps call')->assertSee('https://meet.google.com/abc-defg-hij')
            ->assertSee('Kick-off')->assertSee('We agreed the scope.')->assertSee('Lagos first')
            ->assertDontSee('SECRET')->assertDontSee('UNSENT MINUTES')->assertDontSee('Team debrief')->assertDontSee('Cancelled call');
    }

    public function test_without_google_only_proposing_times_is_offered_and_limits_apply(): void
    {
        [$o] = $this->opportunity();
        $this->google->disconnected();
        $page = Livewire::test(MeetingsGateway::class, ['opportunity' => $o->id])
            ->call('choose', 'pick')->assertSet('mode', 'propose')->assertSee('Free times can’t be shown right now');

        // Three a day from the portal, then the client is asked to reply instead
        foreach (range(1, 3) as $i) {
            Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $o->organization_id, 'kind' => 'client', 'title' => "Request {$i}",
                'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'status' => 'requested', 'transcript_status' => 'waiting']);
        }
        $page->set('name', 'Ada')->set('email', 'ada@kribi.test')->set('proposed.0.date', '2026-11-10')->set('proposed.0.time', '10:00')
            ->call('propose')->assertSee('Several meetings were booked from this page today');
        $this->assertSame(3, Meeting::count());

        $o->forceFill(['stage' => 'lost'])->save();
        Livewire::test(MeetingsGateway::class, ['opportunity' => $o->id])->set('mode', 'propose')
            ->set('name', 'Ada')->set('email', 'ada@kribi.test')->set('proposed.0.date', '2026-11-10')->set('proposed.0.time', '10:00')
            ->call('propose')->assertSee('This piece of work is closed');
    }

    public function test_only_the_three_proposed_times_the_page_offers_are_read(): void
    {
        [$o] = $this->opportunity();
        // A crafted request adds a fourth (and fifth) row: they are ignored
        $page = Livewire::test(MeetingsGateway::class, ['opportunity' => $o->id])->call('choose', 'propose')
            ->set('name', 'Ada')->set('email', 'ada@kribi.test');
        foreach (['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-16'] as $i => $day) {
            $page->set("proposed.{$i}", ['date' => $day, 'time' => '10:00']);
        }
        $page->set('proposed.5', 'not a row')->call('propose')->assertHasNoErrors();

        $agenda = Meeting::sole()->agenda;
        $this->assertStringContainsString('Thursday 12 November 2026, 10:00 WAT', $agenda);
        $this->assertStringNotContainsString('13 November', $agenda);
        $this->assertStringNotContainsString('16 November', $agenda);
    }

    public function test_french(): void
    {
        [$o] = $this->opportunity(['language' => 'fr', 'title' => 'Paie et RH']);
        $this->get(PortalLinks::meetings($o))->assertOk()
            ->assertSee('Vos réunions avec JBS Praxis')->assertSee('Réserver une réunion de suivi')->assertSee('Proposer des créneaux');
    }

    public function test_a_french_clients_request_reaches_the_lead_in_hqs_language(): void
    {
        [$o, $lead] = $this->opportunity(['language' => 'fr']);

        Livewire::test(MeetingsGateway::class, ['opportunity' => $o->id])
            ->call('choose', 'propose')->set('name', 'Ada Client')->set('email', 'ada@kribi.test')
            ->set('proposed.0.date', '2026-11-10')->set('proposed.0.time', '15:00')
            ->call('propose')->assertHasNoErrors();

        // The page spoke French to the client; HQ's bell is in HQ's language (docs/QA-PORTAL.md)
        $this->assertSame('The client proposed meeting times', DB::table('notifications')->where('user_id', $lead->id)->value('title'));
    }

    private function opportunity(array $values = []): array
    {
        $lead = $this->staff('lead', ['sales']);
        $org = $this->organisation(['name' => 'Kribi Foods']);
        $contact = $this->clientContact($org, ['first_name' => 'Ada', 'last_name' => 'Client', 'email' => 'ada@kribi.test']);
        $o = $this->opportunityFor($org, $values + ['title' => 'Payroll and HR', 'lead_user_id' => $lead->id]);

        return [$o, $lead, $contact];
    }
}
