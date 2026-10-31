<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\Opportunities\MeetingsTab;
use App\Livewire\Hq\Opportunities\OpportunityPage;
use App\Mail\MeetingInvitationMail;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Support\Google\MeetingRequest;
use App\Support\Opportunities\Meetings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * The opportunity's Meetings tab (docs/OPPORTUNITIES.md section 4, row 2): client and internal meetings created,
 * changed and cancelled through Google, invitations (Google + branded email for clients only), validation,
 * permissions, French.
 */
class MeetingsScreenTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 5 November 2026, 09:00 in Lagos
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00:00', 'UTC'));
        Mail::fake();
    }

    public function test_the_lead_schedules_a_client_meeting_in_google_with_our_email_in_the_opportunitys_language(): void
    {
        $google = FakeGoogle::install();
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $org = $this->organisation(['name' => 'Société Kribi', 'preferred_language' => 'FR']);
        $chantal = $this->clientContact($org, ['first_name' => 'Chantal', 'email' => 'chantal@kribi.test']);
        $this->clientContact($org, ['first_name' => 'Paul', 'email' => 'paul@kribi.test', 'is_primary_client' => 0]);
        $o = $this->opportunityFor($org, ['title' => 'Paie et RH', 'language' => 'fr', 'lead_user_id' => $sales->id]);

        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->assertSee('Schedule a meeting')
            ->call('openSchedule', 'client')
            ->assertSet('title', "Découverte\u{00A0}: Paie et RH")      // the client sees it: the opportunity's language
            ->assertSet('date', '2026-11-06')
            ->assertSet('staff', [$sales->id])
            ->assertSet('contacts', [$chantal])
            ->set('time', '14:30')
            ->set('duration', 90)
            ->set('guests', "Advisor <advisor@partner.test>, ADA@kribi.test")
            ->set('agenda', 'Payroll today')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Meeting scheduled')
            ->assertDispatched('meetings-changed');

        $m = Meeting::with('attendees')->sole();
        $this->assertSame(['client', 'scheduled', 'waiting'], [$m->kind, $m->status, $m->transcript_status]);
        $this->assertSame('2026-11-06 13:30:00', $m->starts_at->format('Y-m-d H:i:s'));   // 14:30 WAT is 13:30 UTC
        $this->assertSame('2026-11-06 15:00:00', $m->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-06 15:20:00', $m->transcript_next_try_at->format('Y-m-d H:i:s'));
        $this->assertSame(['fake_event_1', 'https://meet.google.com/abc-aaaa-xyz', 'abc-aaaa-xyz'], [$m->google_event_id, $m->meet_link, $m->meet_code]);
        $this->assertEqualsCanonicalizing(['staff', 'client', 'guest', 'guest'], $m->attendees->pluck('role')->all());
        $this->assertEqualsCanonicalizing([mb_strtolower($sales->email), 'chantal@kribi.test', 'advisor@partner.test', 'ada@kribi.test'], $m->attendees->pluck('email')->all());

        $google->assertMeetingCreated(fn (MeetingRequest $r) => $r->title === "Découverte\u{00A0}: Paie et RH" && count($r->attendees) === 4
            && str_contains($r->description, 'Payroll today') && str_contains($r->description, 'JBS Praxis enregistre une transcription'));

        // The branded email to the client and the guests, in French; never to staff
        Mail::assertSent(MeetingInvitationMail::class, 3);
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo('chantal@kribi.test') && $mail->locale === 'fr' && $mail->variant === 'invite');
        Mail::assertNotSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo($sales->email));
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MEETING_SCHEDULED', 'target_table' => 'opportunities', 'target_id' => $o->id, 'user_id' => $sales->id]);
    }

    public function test_an_internal_meeting_gets_googles_invite_only_and_starts_from_the_last_client_meetings_notes(): void
    {
        $google = FakeGoogle::install();
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $org = $this->organisation();
        $contact = $this->clientContact($org);
        $o = $this->opportunityFor($org, ['lead_user_id' => $owner->id]);
        $held = $this->heldMeeting($o, ['title' => 'Discovery']);
        $held->transcripts()->create(['source' => 'paste', 'text' => 'Ada: we close the books by hand', 'collected_at' => now(),
            'notes' => ['summary' => 'Manual closing.', 'decisions' => ['Start with Lagos'], 'needs' => [], 'risks' => [], 'open_questions' => ['Who owns the chart of accounts?'],
                'next_steps' => [['action' => 'Send trial balances', 'owner' => 'Client', 'due' => '']]]]);

        $screen = Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('openSchedule', 'internal')
            ->assertSet('title', 'Internal: Finance system');
        $this->assertStringContainsString('Start with Lagos', $screen->get('agenda'));
        $this->assertStringContainsString('Who owns the chart of accounts?', $screen->get('agenda'));
        $this->assertStringContainsString('Send trial balances', $screen->get('agenda'));

        $screen->set('contacts', [$contact])->set('time', '11:00')->call('save')->assertHasNoErrors();

        $m = Meeting::where('kind', 'internal')->with('attendees')->sole();
        $this->assertSame(['staff'], $m->attendees->pluck('role')->all());   // client contacts never join an internal meeting
        $this->assertCount(1, $google->created);
        $this->assertStringNotContainsString('transcript', (string) $google->created[0]['request']->description);
        Mail::assertNothingSent();
    }

    public function test_the_form_checks_times_and_people_and_google_failures_save_nothing(): void
    {
        $google = FakeGoogle::install();
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $o = $this->opportunityFor($this->organisation(), ['lead_user_id' => $sales->id]);

        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('openSchedule', 'client')
            ->set('title', '')->set('date', '2026-11-04')->set('time', '10:00')->set('guests', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['title', 'date', 'guests'])
            ->assertSee('Give the meeting a title.')
            ->assertSee('Choose a time that is still ahead.')
            ->assertSee('“not-an-email” isn’t a complete email address.')
            ->set('title', 'Discovery')->set('date', '2026-11-06')->set('guests', '')->set('staff', [])
            ->call('save')
            ->assertHasErrors(['staff', 'contacts'])
            ->assertSee('Invite at least one person from the client')
            ->set('staff', [$sales->id])->set('guests', 'ada@client.test');
        $google->assertNothingCreated();

        $google->fail('revoked', 'createMeeting');
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('openSchedule', 'client')->set('guests', 'ada@client.test')
            ->call('save')
            ->assertHasErrors(['google'])
            ->assertSee(__('integrations.google.errors.revoked'));
        $this->assertSame(0, Meeting::count());
        Mail::assertNothingSent();
    }

    public function test_moving_a_meeting_tells_existing_guests_and_invites_new_ones_then_cancelling_tells_everyone(): void
    {
        $google = FakeGoogle::install();
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $o = $this->opportunityFor($this->organisation(), ['lead_user_id' => $sales->id]);
        $m = Meetings::schedule($o, ['kind' => 'client', 'title' => 'Discovery', 'starts_at' => CarbonImmutable::parse('2026-11-06 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-11-06 10:00', 'UTC'), 'staff' => [$sales->id], 'guests' => [['email' => 'ada@client.test', 'name' => 'Ada Obi']]])->meeting;
        Mail::fake();

        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('openEdit', $m->id)
            ->assertSet('date', '2026-11-06')->assertSet('time', '10:00')->assertSet('guests', 'ada@client.test')
            ->set('time', '15:00')->set('guests', "ada@client.test\nben@client.test")
            ->call('save')->assertHasNoErrors()->assertSee('Meeting changed');

        $this->assertSame('fake_event_1', $google->updated[0]['eventId']);
        $this->assertSame('2026-11-06 14:00:00', $m->fresh()->starts_at->format('Y-m-d H:i:s'));
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo('ada@client.test') && $mail->variant === 'moved');
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo('ben@client.test') && $mail->variant === 'invite');
        Mail::assertSent(MeetingInvitationMail::class, 2);

        Mail::fake();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('confirmCancel', $m->id)->assertSee('Cancel this meeting?')
            ->call('cancelMeeting')->assertSee('Meeting cancelled');
        $this->assertSame(['fake_event_1'], $google->cancelled);
        $this->assertSame('cancelled', $m->fresh()->status);
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->variant === 'cancelled', 2);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MEETING_CANCELLED', 'target_id' => $o->id]);

        // Past and cancelled meetings can't change
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->call('openEdit', $m->id)->assertSet('formOpen', false)
            ->assertSee('can’t be changed');
    }

    public function test_who_may_do_what(): void
    {
        FakeGoogle::install();
        $finance = $this->staff('finance', ['finance']);
        $delivery = $this->staff('delivery', ['delivery']);
        $lead = $this->staff('lead', ['finance']);
        $o = $this->opportunityFor($this->organisation(), ['lead_user_id' => $lead->id]);
        $m = $this->heldMeeting($o);
        $m->transcripts()->create(['source' => 'paste', 'text' => 'Ada: confidential detail', 'collected_at' => now()]);

        // Finance may look, not act, and doesn't read full transcripts
        $this->actingAs($finance);
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->assertDontSee('Schedule a meeting')
            ->call('toggle', $m->id)->assertDontSee('confidential detail')->assertSee('Only the lead and people who may read transcripts')
            ->call('openSchedule', 'client')->assertForbidden();

        // Delivery reads transcripts; the opportunity's lead does too, without the permission
        $this->actingAs($delivery);
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->call('toggle', $m->id)->assertSee('confidential detail');
        $this->actingAs($lead);
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->call('toggle', $m->id)->assertSee('confidential detail');

        // Someone without opportunities.view can't open the tab at all
        $this->actingAs($this->staff('engineer', ['engineer']));
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->assertForbidden();
    }

    public function test_google_not_connected_says_so(): void
    {
        FakeGoogle::install()->disconnected();
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $o = $this->opportunityFor($this->organisation());

        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->assertSee('Google isn’t connected')->assertSee('Connect Google in Integrations');
    }

    public function test_the_opportunity_page_shows_the_tab_and_french(): void
    {
        FakeGoogle::install();
        $this->actingAs($this->staff('owner', ['owner']));
        $o = $this->opportunityFor($this->organisation(), ['title' => 'Paie et RH']);
        $this->heldMeeting($o, ['title' => 'Audit offert']);

        Livewire::test(OpportunityPage::class, ['opportunity' => $o])->call('show', 'meetings')
            ->assertSeeLivewire(MeetingsTab::class)->assertSee('Meetings (1)');

        App::setLocale('fr');
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->assertSee('Planifier une réunion')->assertSee('Audit offert')->assertSee('Passées et annulées')
            ->call('openSchedule', 'client')->assertSee('Heures d’Afrique de l’Ouest');

        // Every key in one language is in the other
        foreach (['meetings', 'emails/client_meeting', 'emails/minutes', 'opportunities'] as $file) {
            $en = array_keys(Arr::dot(require lang_path("en/{$file}.php")));
            $fr = array_keys(Arr::dot(require lang_path("fr/{$file}.php")));
            sort($en);
            sort($fr);
            $this->assertSame($en, $fr, "lang/en/{$file}.php and lang/fr/{$file}.php have different keys");
        }
    }

    public function test_the_invitation_email_in_both_languages(): void
    {
        foreach (MeetingInvitationMail::samples() as $name => $mail) {
            foreach (['en', 'fr'] as $locale) {
                $parts = (clone $mail)->locale($locale)->renderParts();
                $this->assertNotSame('', $parts['subject'], "{$name} {$locale}");
                $this->assertStringNotContainsString('emails.', $parts['html'], "{$name} {$locale}: a missing string");
                if ($name !== 'cancelled') {
                    $this->assertStringContainsString('https://meet.google.com/abc-defg-hij', $parts['html']);
                    $this->assertStringContainsString($locale === 'fr' ? 'transcription' : 'transcript', $parts['text']);
                }
                $this->assertStringContainsString('/meetings/0b5c8d1e-2f3a-4b5c-8d6e-7f8091a2b3c4?', $parts['html'], 'the booking link');
            }
        }
        $fr = MeetingInvitationMail::samples()['invite']->locale('fr')->renderParts();
        $this->assertSame("Invitation\u{00A0}: Discovery: finance system le jeudi 5 novembre 2026 à 10\u{202F}h\u{202F}30 (WAT)", $fr['subject']);
    }

    private function heldMeeting($o, array $values = []): Meeting
    {
        return Meeting::create($values + [
            'opportunity_id' => $o->id, 'organization_id' => $o->organization_id, 'kind' => 'client', 'title' => 'Discovery',
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour(), 'time_zone' => 'Africa/Lagos',
            'status' => 'held', 'transcript_status' => 'ready',
        ]);
    }
}
