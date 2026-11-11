<?php

namespace Tests\Feature;

use App\Livewire\Portal\ContractVerification;
use App\Livewire\Portal\MeetingsGateway;
use App\Livewire\Portal\ProjectTracker;
use App\Livewire\Portal\ProposalReview;
use App\Livewire\Portal\SignoffGateway;
use App\Mail\UserInvitation;
use App\Models\Meeting;
use App\Support\Opportunities\Proposals;
use App\Support\Portal\SignerOnboarding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Opportunities\OpportunityFixtures;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

/**
 * The client's token pages do less on each request (performance pass, 11 Nov 2026): the tracker's PIN screen reads
 * only the project's name and the chat shows the latest 100 messages; the sign-off page keeps its signature images
 * out of the page state; the document check answers with the page; the meetings page reads only the minutes; a
 * proposal is read once per request; the pages' styles are sent once, in <head>; and the setup email after a
 * signing goes once the signing is saved.
 */
class PortalPagesSpeedTest extends TestCase
{
    use OpportunityFixtures, ProjectFixtures, UsesThrowawayDatabase {
        OpportunityFixtures::organisation insteadof ProjectFixtures;
    }

    /** A tracker with one milestone and task, unlocked with PIN 1234 */
    private function tracker(array $values = []): array
    {
        $staff = (string) Str::uuid();
        $this->insertRow('tracker_users', ['id' => $staff, 'name' => 'Musa Delivery', 'email' => Str::random(8) . '@praxis.test', 'password' => 'x']);
        $tracker = (string) Str::uuid();
        $this->insertRow('trackers', $values + ['id' => $tracker, 'unique_token' => Str::random(40), 'pin_code' => '1234',
            'project_name' => 'Fleet portal rollout', 'client_name' => 'Kilo Logistics', 'created_by' => $staff]);
        $milestone = (string) Str::uuid();
        $this->insertRow('tracker_milestones', ['id' => $milestone, 'tracker_id' => $tracker, 'title' => 'Discovery']);
        $task = (string) Str::uuid();
        $this->insertRow('tracker_tasks', ['id' => $task, 'milestone_id' => $milestone, 'title' => 'Map the depots', 'assigned_to' => $staff]);

        return ['id' => $tracker, 'token' => DB::table('trackers')->where('id', $tracker)->value('unique_token'), 'task' => $task, 'staff' => $staff];
    }

    private function messages(string $tracker, int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['id' => (string) Str::uuid(), 'tracker_id' => $tracker, 'sender_type' => 'client', 'body' => sprintf('Message number %03d', $i),
                'created_at' => now()->subMinutes($count - $i), 'updated_at' => now()];
        }
        DB::table('tracker_messages')->insert($rows);
    }

    public function test_the_tracker_pin_screen_reads_only_the_project_name(): void
    {
        $t = $this->tracker();
        $this->messages($t['id'], 3);

        DB::enableQueryLog();
        Livewire::test(ProjectTracker::class, ['token' => $t['token']])
            ->assertSee('Fleet portal rollout')->assertDontSee('Map the depots')->assertDontSee('Message number');
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();

        $this->assertStringNotContainsString('tracker_milestones', $sql);
        $this->assertStringNotContainsString('tracker_messages', $sql);
    }

    public function test_the_right_pin_unlocks_at_once_and_a_wrong_one_does_not(): void
    {
        $t = $this->tracker();

        Livewire::test(ProjectTracker::class, ['token' => $t['token']])
            ->call('verifyPin', '9999')->assertSet('pinError', true)->assertSet('isAuthorized', false)
            ->call('verifyPin', '1234')->assertSet('pinError', false)->assertSet('isAuthorized', true)
            ->assertSee('Map the depots');
    }

    public function test_the_tracker_chat_shows_the_latest_100_messages_and_earlier_ones_on_request(): void
    {
        $t = $this->tracker();
        $this->messages($t['id'], 105);
        session()->put('tracker_auth_' . $t['id'], true);

        $page = Livewire::test(ProjectTracker::class, ['token' => $t['token']])
            ->assertSee('Message number 105')->assertSee('Message number 006')
            ->assertDontSee('Message number 005')->assertSee(__('gateway.tracker.show_earlier'));
        $this->assertLessThan(strpos($page->html(), 'Message number 105'), strpos($page->html(), 'Message number 006'), 'Oldest first');

        $page->call('showEarlierMessages')
            ->assertSee('Message number 001')->assertSee('Message number 105')->assertDontSee(__('gateway.tracker.show_earlier'));
        $this->assertLessThan(strpos($page->html(), 'Message number 002'), strpos($page->html(), 'Message number 001'));
    }

    public function test_a_reply_can_only_be_about_a_task_of_this_tracker(): void
    {
        $t = $this->tracker();
        $other = $this->tracker(['project_name' => 'Another project']);
        session()->put('tracker_auth_' . $t['id'], true);

        Livewire::test(ProjectTracker::class, ['token' => $t['token']])
            ->call('setReplyContext', $other['task'])->assertSet('replyingToTask', null)->assertNotDispatched('focus-chat-input')
            ->call('setReplyContext', $t['task'])->assertSet('replyingToTask', $t['task'])->assertDispatched('focus-chat-input')
            ->assertSee(__('gateway.tracker.replying_about'));
    }

    /** A handover sign-off of a project of a client organisation */
    private function signoff(array $values = []): array
    {
        $org = $this->organisation(['name' => 'Kilo Logistics']);
        $project = $this->project(['organization_id' => $org, 'title' => 'Fleet portal']);
        $id = (string) Str::uuid();
        $token = Str::random(48);
        $this->insertRow('project_signoffs', $values + ['id' => $id, 'project_id' => $project, 'token' => $token, 'signatory_email' => 'ngozi@kilo.test',
            'executive_summary' => 'The portal is live in all depots.', 'roi_metrics_achieved' => json_encode([['metric' => 'Close time', 'value' => '5 days']]),
            'status' => 'Awaiting Signature']);

        return ['id' => $id, 'token' => $token];
    }

    public function test_the_signature_images_stay_out_of_the_sign_off_page_state(): void
    {
        $client = 'data:image/png;base64,' . str_repeat('A', 4000);
        $ours = 'data:image/png;base64,' . str_repeat('B', 4000);
        $s = $this->signoff(['status' => 'Signed', 'signed_at' => now(), 'signatory_name' => 'Ngozi Eze', 'client_signature_b64' => $client,
            'client_signature_hash' => str_repeat('f', 64), 'jbs_signature_b64' => $ours, 'auth_otp' => '123456']);

        $page = Livewire::test(SignoffGateway::class, ['token' => $s['token']])->assertSet('viewState', 'completed');
        $state = json_encode($page->snapshot);
        $this->assertStringNotContainsString(str_repeat('A', 4000), $state);
        $this->assertStringNotContainsString(str_repeat('B', 4000), $state);
        $this->assertStringNotContainsString('123456', $state, 'Never the code');
        // The signed record's PDF page still carries the client's signature
        $this->assertStringContainsString($client, $page->html());
        $this->assertStringNotContainsString($ours, $page->html());
    }

    public function test_after_signing_the_drawn_signature_no_longer_travels_with_the_page(): void
    {
        Mail::fake();
        $s = $this->signoff();
        session()->put('signoff_otp_verified_' . $s['id'], now()->addMinutes(30)->timestamp);
        $drawn = 'data:image/png;base64,' . str_repeat('C', 3000);

        $page = Livewire::test(SignoffGateway::class, ['token' => $s['token']])
            ->set('signerName', 'Ngozi Eze')->set('signerTitle', 'Finance director')->set('signerCompany', 'Kilo Logistics')
            ->set('signatureBase64', $drawn)
            ->call('bindContract')
            ->assertHasNoErrors()->assertSet('viewState', 'completed')->assertSet('signatureBase64', '');

        $this->assertSame($drawn, DB::table('project_signoffs')->where('id', $s['id'])->value('client_signature_b64'));
        $this->assertStringNotContainsString(str_repeat('C', 3000), json_encode($page->snapshot));
        $this->assertStringContainsString($drawn, $page->html());
    }

    public function test_the_document_check_answers_with_the_page_itself(): void
    {
        $hash = str_repeat('a', 64);
        $this->signoff(['status' => 'Signed', 'signed_at' => now(), 'signatory_name' => 'Ngozi Eze', 'client_signature_hash' => $hash]);

        Livewire::test(ContractVerification::class, ['hash' => $hash])
            ->assertSet('verificationState', 'valid')->assertSee(__('gateway.verify.valid_title'))->assertSee('Fleet portal');
        Livewire::test(ContractVerification::class, ['hash' => str_repeat('b', 64)])
            ->assertSet('verificationState', 'invalid')->assertSee(__('gateway.verify.invalid_title'));

        // The full page: the result is in the first response, and the page's styles are in <head>
        $html = $this->get(route('portal.verify.contract', ['hash' => $hash]))->assertOk()->assertSee(__('gateway.verify.valid_title'))->getContent();
        $style = strpos($html, '.gateway-main { max-width: 720px;');
        $this->assertNotFalse($style);
        $this->assertLessThan(strpos($html, '</head>'), $style, 'The styles are sent once, in the head');
        $this->assertSame(1, substr_count($html, '.gateway-main { max-width: 720px;'));
    }

    public function test_a_tracker_round_trip_does_not_carry_the_pages_styles(): void
    {
        $t = $this->tracker();
        session()->put('tracker_auth_' . $t['id'], true);

        $page = Livewire::test(ProjectTracker::class, ['token' => $t['token']]);
        $this->assertStringNotContainsString('.app-wrapper {', $page->html());
        $page->call('setReplyContext', $t['task']);
        $this->assertStringNotContainsString('.app-wrapper {', $page->html());
        $this->assertStringNotContainsString('.chat-scroller {', $page->html());

        $html = $this->get(route('portal.tracker', ['token' => $t['token']]))->assertOk()->getContent();
        $this->assertNotFalse(strpos($html, '.app-wrapper {'));
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, '.app-wrapper {'));
    }

    public function test_the_meetings_page_reads_only_the_minutes_of_a_transcript(): void
    {
        $org = $this->organisation();
        $o = $this->opportunityFor($org, ['title' => 'Payroll and HR']);
        $meeting = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => 'Kick-off',
            'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour(), 'status' => 'held', 'transcript_status' => 'ready']);
        $meeting->transcripts()->create(['source' => 'paste', 'text' => str_repeat('SECRET TRANSCRIPT ', 50), 'collected_at' => now(),
            'notes' => ['summary' => 'SECRET NOTES'], 'minutes' => 'We agreed the scope.', 'minutes_sent_at' => now()]);

        DB::enableQueryLog();
        Livewire::test(MeetingsGateway::class, ['opportunity' => $o->id])->assertSee('We agreed the scope.')->assertDontSee('SECRET');
        $transcripts = array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($q) => str_contains($q, 'from `meeting_transcripts`')));
        DB::disableQueryLog();

        $this->assertCount(1, $transcripts);
        $this->assertStringStartsWith('select `id`, `meeting_id`, `minutes`, `minutes_sent_at` from `meeting_transcripts`', $transcripts[0]);
    }

    public function test_a_proposal_is_read_once_per_request(): void
    {
        Mail::fake();
        $lead = $this->staff('lead', ['sales']);
        $org = $this->organisation(['name' => 'Kilo Pharma']);
        $contact = $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@kilo.test']);
        $o = $this->opportunityFor($org, ['title' => 'Month-end close', 'lead_user_id' => $lead->id, 'stage' => 'proposal']);
        $this->actingAs($lead);
        $p = Proposals::start($o, 'USD', $lead);
        Proposals::save($p, ['title' => 'A faster close', 'sections' => [['key' => 'approach', 'title' => 'We start with the ledger', 'body' => 'Map the close']],
            'lines' => [['code' => '#1', 'description' => 'Diagnostic', 'quantity' => '1', 'unit_price' => 1000]]], $lead);
        Proposals::approve($p, $lead);
        Proposals::send($p, ['ngozi@kilo.test'], $lead);
        auth()->guard('web')->logout();

        $page = Livewire::withQueryParams(['c' => $contact])->test(ProposalReview::class, ['proposal' => $p->id]);
        DB::enableQueryLog();
        $page->call('openComment', 'approach')->assertSet('commenting', 'approach');
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertCount(1, array_filter($queries, fn ($q) => str_starts_with($q, 'select * from `proposals` where `proposals`.`id` = ?')), 'The action and the render share one read');
        $this->assertCount(1, array_filter($queries, fn ($q) => str_contains($q, 'from `client_users` where `id` = ?')), "The link's contact is read once");
    }

    public function test_the_setup_email_after_signing_goes_once_the_signing_is_saved(): void
    {
        Mail::fake();
        $org = $this->organisation(['name' => 'Kilo Pharma']);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'email' => 'ngozi@kilo.test']);
        $organisation = DB::table('organizations')->where('id', $org)->first();

        // A signing that is rolled back sends no setup link
        try {
            DB::transaction(function () use ($organisation) {
                SignerOnboarding::welcome($organisation, 'ngozi@kilo.test', 'Fleet portal', now());
                Mail::assertNothingSent();
                throw new \RuntimeException('The signing failed');
            });
        } catch (\RuntimeException) {
        }
        Mail::assertNothingSent();
        $this->assertNull(DB::table('client_users')->where('email', 'ngozi@kilo.test')->value('invitation_token'));

        // A saved one sends it once, after the commit
        DB::transaction(function () use ($organisation) {
            SignerOnboarding::welcome($organisation, 'ngozi@kilo.test', 'Fleet portal', now());
            Mail::assertNothingSent();
        });
        Mail::assertSent(UserInvitation::class, 1);
        $this->assertNotNull(DB::table('client_users')->where('email', 'ngozi@kilo.test')->value('invitation_token'));
    }
}
