<?php

namespace Tests\Feature\Campaigns;

use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Mail\MailAccount;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Outreach\Limits;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Outreach\Sender;
use App\Support\Campaigns\Outreach\Senders;
use App\Support\Mail\MailSendException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Email;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/**
 * The outreach sender (docs/CAMPAIGNS.md section 10; owner's decisions 2 and 4, 3 Nov 2026): one email per sender at a
 * time from their own mailbox, within the window, at least 4 minutes apart, 15 a day in the first 2 weeks then 30
 * (follow-ups count); the last-moment do-not-email check; bounces, the automatic pause on bounces and spam complaints;
 * never two sends for one email; the unsubscribe line, headers and links; the follow-up 3 working days later and its
 * cancelling by a reply. Nothing leaves the machine: the mail server is FakeMailServer.
 */
class OutreachSendingTest extends TestCase
{
    use OutreachFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOutreach();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Lagos time (WAT, UTC+1) */
    private function at(string $wat): void
    {
        Carbon::setTestNow(Carbon::parse($wat, Limits::TIMEZONE)->utc());
    }

    public function test_one_email_per_sender_from_their_mailbox_within_the_window_and_at_least_4_minutes_apart(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $a = $this->prospect($campaign, 'approved');
        $b = $this->prospect($campaign, 'approved');
        $this->prospect($campaign, 'draft'); // not approved: never sent
        $mailbox = Senders::mailbox($gerald->id);

        // Tuesday 3 Nov 2026: before 08:30, at 17:30 and on Saturday nothing goes
        foreach (['2026-11-03 08:29', '2026-11-03 17:30', '2026-11-07 10:00'] as $when) {
            $this->at($when);
            Sender::run();
        }
        $this->assertCount(0, $this->server->sent);

        $this->at('2026-11-03 08:30');
        $this->artisan('outreach:send')->assertSuccessful();
        $this->assertCount(1, $this->server->sent);
        $sent = $this->server->sent[0];
        $this->assertSame($mailbox->email, $sent['account']);
        /** @var Email $email */
        $email = $sent['email'];
        $this->assertSame($a->email, $email->getTo()[0]->getAddress());
        $this->assertSame('Ada Obi', $email->getTo()[0]->getName());
        $this->assertSame('Your second warehouse in Onne', $email->getSubject());
        $first = $this->firstOf($a);
        $this->assertSame('sent', $first->status);
        $this->assertNotNull($first->message_id);
        $this->assertSame($gerald->id, $first->sender_id);
        $this->assertSame('emailed', $a->fresh()->status);
        // The unsubscribe line and headers, with this email's own token, on the portal host
        $url = route('email.unsubscribe.outreach', ['token' => $first->token]);
        $this->assertSame('<' . $url . '>', $email->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
        $this->assertStringContainsString('Congratulations on the new warehouse in Onne.', $email->getHtmlBody());
        $this->assertStringContainsString('href="' . e($url) . '"', $email->getHtmlBody());
        $this->assertStringContainsString('unsubscribe here', $email->getHtmlBody());
        $this->assertStringContainsString($gerald->name, $email->getHtmlBody()); // the HQ Mail signature
        $this->assertStringContainsString('unsubscribe here: ' . $url, $email->getTextBody());
        $this->assertStringNotContainsString('{{', $email->getHtmlBody());

        // 3 minutes later: too soon; 4 minutes after the first: the next
        $this->at('2026-11-03 08:33');
        Sender::run();
        $this->assertCount(1, $this->server->sent);
        $this->at('2026-11-03 08:34');
        Sender::run();
        $this->assertCount(2, $this->server->sent);
        $this->assertSame($b->email, $this->server->sent[1]['email']->getTo()[0]->getAddress());

        // The schedule runs both commands every minute, never twice at once
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command ?? '', 'outreach:'));
        $this->assertCount(2, $events);
        $this->assertTrue($events->every(fn ($e) => $e->expression === '* * * * *' && $e->withoutOverlapping));
    }

    public function test_15_a_day_in_the_first_2_weeks_then_30_and_follow_ups_count(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        for ($i = 0; $i < 40; $i++) {
            $this->prospect($campaign, 'approved');
        }
        // A follow-up already due counts toward the same limit (and goes first)
        $old = $this->prospect($campaign, 'sent');
        OutreachEmail::whereKey($this->firstOf($old)->id)->update(['sent_at' => Carbon::parse('2026-10-28 10:00', Limits::TIMEZONE)->utc(), 'message_id' => 'old@jbspraxis.test']);
        OutreachEmail::whereKey($this->followUpOf($old)->id)->update(['status' => 'scheduled', 'send_after' => Carbon::parse('2026-11-02 10:00', Limits::TIMEZONE)->utc()]);

        $this->at('2026-11-03 09:00');
        for ($i = 0; $i < 20; $i++) {
            Sender::run();
            Carbon::setTestNow(now()->addMinutes(4));
        }
        $this->assertCount(15, $this->server->sent);
        $this->assertSame('Re: Your second warehouse in Onne', $this->server->sent[0]['email']->getSubject());
        $this->assertSame('sent', $this->followUpOf($old)->status);
        $this->assertSame(15, Senders::triedToday($gerald->id));
        $this->assertSame(15, Senders::limitToday($gerald->id));

        // The next day starts again at 15; from the 15th day after their first email, 30
        $this->at('2026-11-04 09:00');
        Sender::run();
        $this->assertCount(16, $this->server->sent);
        DB::table('outreach_senders')->where('user_id', $gerald->id)->update(['first_sent_at' => Carbon::parse('2026-10-20 09:00', Limits::TIMEZONE)->utc()]);
        $this->assertSame(30, Senders::limitToday($gerald->id));

        // The owner's limits apply from the next run
        Limits::set(['daily_limit' => 2], $this->staff('owner', ['owner']));
        Carbon::setTestNow(now()->addMinutes(5));
        Sender::run();
        $this->assertCount(17, $this->server->sent);
        Carbon::setTestNow(now()->addMinutes(5));
        Sender::run();
        $this->assertCount(17, $this->server->sent, 'Two today already: the new limit is reached');
    }

    public function test_the_do_not_email_list_is_asked_again_at_the_last_moment(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $gone = $this->prospect($campaign, 'approved');
        $next = $this->prospect($campaign, 'approved');
        Marketing::suppress($gone->email, 'unsubscribed', ['source' => 'broadcast']);

        $this->at('2026-11-03 10:00');
        Sender::run();
        $this->assertCount(0, $this->server->sent);
        $this->assertSame(['skipped', 'On the do-not-email list'], [$this->firstOf($gone)->status, $this->firstOf($gone)->note]);
        $this->assertSame('cancelled', $this->followUpOf($gone)->status);

        // A skipped email isn't an attempt: the next prospect goes at once
        Sender::run();
        $this->assertCount(1, $this->server->sent);
        $this->assertSame($next->email, $this->server->sent[0]['email']->getTo()[0]->getAddress());
    }

    public function test_a_bounce_goes_on_the_list_and_too_many_pause_the_sender_with_a_bell(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $ok = $this->prospect($campaign, 'approved');
        $bad = $this->prospect($campaign, 'approved');
        $later = $this->prospect($campaign, 'approved');
        $this->refuse([$bad->email => new MailSendException('The outgoing mail server refused the message: 550 5.1.1 <' . $bad->email . '>: Recipient address rejected: User unknown', 'rejected', true, 550)]);

        $this->at('2026-11-03 10:00');
        Sender::run();
        Carbon::setTestNow(now()->addMinutes(4));
        Sender::run();

        $this->assertSame('sent', $this->firstOf($ok)->status);
        $this->assertSame('bounced', $this->firstOf($bad)->status);
        $this->assertSame('cancelled', $this->followUpOf($bad)->status);
        $this->assertSame('bounced', $bad->fresh()->status);
        $entry = Marketing::entry($bad->email);
        $this->assertSame(['bounced', 'outreach'], [$entry->reason, $entry->source]);
        // 1 of 2 bounced (50%, over 5%): paused, and both the sender and the owner get a bell
        $this->assertTrue(Senders::isPaused($gerald->id));
        $this->assertSame('bounces', Senders::state($gerald->id)->paused_reason);
        foreach ([$gerald, $owner] as $who) {
            $this->assertTrue(DB::table('notifications')->where('user_id', $who->id)->where('title', 'Outreach paused for Gerald')->exists());
        }

        Carbon::setTestNow(now()->addMinutes(10));
        Sender::run();
        $this->assertSame('approved', $this->firstOf($later)->status, 'Nothing goes while paused');

        $this->assertTrue(Senders::resume($gerald->id, $owner));
        Sender::run();
        $this->assertSame('sent', $this->firstOf($later)->status);
        $this->assertSame(1, DB::table('notifications')->where('user_id', $gerald->id)->where('title', 'like', 'Outreach paused%')->count(), 'One bell per pause');
    }

    public function test_a_spam_complaint_pauses_the_sender_and_stops_the_follow_up(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $p = $this->prospect($campaign, 'approved');
        $this->at('2026-11-03 10:00');
        Sender::run();
        $this->assertSame('scheduled', $this->followUpOf($p)->status);

        // C3 (or the owner, from a mail provider's report) records the complaint
        Marketing::suppress($p->email, 'complained', ['source' => 'outreach']);
        Carbon::setTestNow(now()->addMinutes(5));
        Sender::run();

        $this->assertTrue(Senders::isPaused($gerald->id));
        $this->assertSame('complaint', Senders::state($gerald->id)->paused_reason);
        $this->assertSame(['cancelled', 'Marked as spam'], [$this->followUpOf($p)->status, $this->followUpOf($p)->note]);
        $this->assertNotNull($p->fresh()->complained_at);
        $this->assertTrue(DB::table('notifications')->where('user_id', $gerald->id)->where('message', 'like', '%marked an outreach email as spam%')->exists());
    }

    public function test_the_follow_up_goes_3_working_days_later_unless_they_reply(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $silent = $this->prospect($campaign, 'approved');
        $replied = $this->prospect($campaign, 'approved');
        $wrote = $this->prospect($campaign, 'approved');
        Limits::set(['gap_minutes' => 1], $this->staff('owner', ['owner']));

        // Thursday 5 Nov: the three first emails
        $this->at('2026-11-05 10:00');
        foreach (range(1, 3) as $i) {
            Sender::run();
            Carbon::setTestNow(now()->addMinute());
        }
        $this->assertCount(3, $this->server->sent);
        // Friday, Monday, Tuesday: the follow-ups wait until Tuesday 10 Nov at the same time
        $follow = $this->followUpOf($silent);
        $this->assertSame('scheduled', $follow->status);
        $this->assertSame('2026-11-10 10:00', $follow->send_after->copy()->timezone(Limits::TIMEZONE)->format('Y-m-d H:i'));

        // One replies in the same conversation, another writes from their address (HQ Mail's copy, mail:sync)
        $mailbox = Senders::mailbox($gerald->id);
        $this->receivedMail($mailbox, 'someone.else@kilo.test', ['in_reply_to' => $this->firstOf($replied)->message_id, 'received_at' => now()]);
        $this->receivedMail($mailbox, strtoupper($wrote->email), ['subject' => 'Your email', 'received_at' => now()]);
        // Mail from the prospect before the first email went doesn't count
        $this->receivedMail($mailbox, $silent->email, ['received_at' => Carbon::parse('2026-11-01 10:00', Limits::TIMEZONE)->utc()]);

        $this->at('2026-11-09 10:00');
        Sender::run();
        $this->assertCount(3, $this->server->sent, 'Monday: nothing due yet');
        foreach ([$replied, $wrote] as $p) {
            $this->assertSame(['cancelled', 'They replied'], [$this->followUpOf($p)->status, $this->followUpOf($p)->note]);
            $this->assertSame('replied', $p->fresh()->status);
        }
        $this->assertSame('scheduled', $this->followUpOf($silent)->status);

        $this->at('2026-11-10 10:00');
        Sender::run();
        $this->assertCount(4, $this->server->sent);
        $email = $this->server->sent[3]['email'];
        $first = $this->firstOf($silent);
        $this->assertSame('Re: Your second warehouse in Onne', $email->getSubject());
        $this->assertSame('<' . $first->message_id . '>', $email->getHeaders()->get('In-Reply-To')->getBodyAsString());
        $this->assertStringContainsString($first->message_id, $email->getHeaders()->get('References')->getBodyAsString());
        $this->assertStringContainsString('One more thought on Onne.', $email->getHtmlBody());
        $this->assertStringContainsString('wrote:', $email->getHtmlBody()); // the first email, quoted
        $this->assertStringContainsString(route('email.unsubscribe.outreach', ['token' => $this->followUpOf($silent)->token]), $email->getTextBody());
        $this->assertSame('sent', $this->followUpOf($silent)->status);
        Carbon::setTestNow(now()->addHour());
        Sender::run();
        $this->assertCount(4, $this->server->sent, 'Exactly one follow-up');
    }

    public function test_a_reply_that_arrives_after_the_sweep_still_stops_the_follow_up_at_the_last_moment(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $p = $this->prospect($campaign, 'approved');
        $this->at('2026-11-05 10:00');
        Sender::run();
        // A reply whose References (not In-Reply-To) name the first email
        $this->receivedMail(Senders::mailbox($gerald->id), 'ada.obi@elsewhere.test', ['references' => 'other@x.test ' . $this->firstOf($p)->message_id]);
        $this->at('2026-11-10 10:00');
        Sender::run();
        $this->assertCount(1, $this->server->sent);
        $this->assertSame('cancelled', $this->followUpOf($p)->status);
    }

    public function test_an_email_is_never_sent_twice_and_one_interrupted_mid_send_fails(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $taken = $this->prospect($campaign, 'approved');
        $stuck = $this->prospect($campaign, 'approved');
        $this->at('2026-11-03 10:00');
        // Another run took this one a moment ago, and one died 20 minutes ago mid-send
        OutreachEmail::whereKey($this->firstOf($taken)->id)->update(['status' => 'sending', 'sender_id' => $gerald->id, 'tried_at' => now()->subMinutes(5), 'updated_at' => now()]);
        OutreachEmail::whereKey($this->firstOf($stuck)->id)->update(['status' => 'sending', 'sender_id' => $gerald->id, 'tried_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)]);

        Sender::run();
        $this->assertCount(0, $this->server->sent);
        $this->assertSame('sending', $this->firstOf($taken)->status);
        $this->assertSame(['failed', Sender::INTERRUPTED_MESSAGE], [$this->firstOf($stuck)->status, $this->firstOf($stuck)->note]);
        $this->assertSame('cancelled', $this->followUpOf($stuck)->status);
    }

    public function test_no_mailbox_a_refused_password_or_a_temporary_refusal(): void
    {
        // Not connected: nothing goes, and the screens say how to connect
        $nobox = $this->salesperson('nina', mailbox: false);
        $waiting = $this->prospect($this->outreachCampaign($nobox), 'approved');
        $this->at('2026-11-03 10:00');
        Sender::run();
        $this->assertSame('approved', $this->firstOf($waiting)->status);
        $this->assertStringContainsString('open Mail › Mailboxes', (string) Senders::mailboxProblem($nobox));

        // A refused password: put back, and the sender pauses until it is reconnected
        $gerald = $this->salesperson();
        $p = $this->prospect($this->outreachCampaign($gerald), 'approved');
        $this->server->smtpFailure = 'auth';
        Sender::run();
        $this->assertSame('approved', $this->firstOf($p)->status);
        $this->assertSame('mailbox', Senders::state($gerald->id)->paused_reason);

        // "Try later" (4xx): back in the queue, at most 3 tries
        $tess = $this->salesperson('tess');
        $q = $this->prospect($this->outreachCampaign($tess), 'approved');
        foreach (range(1, 3) as $try) {
            $this->server->smtpFailure = 'temporary';
            Carbon::setTestNow(now()->addMinutes(5));
            Sender::run();
        }
        $this->assertSame(['failed', 3], [$this->firstOf($q)->status, $this->firstOf($q)->attempts]);
        $this->assertSame('cancelled', $this->followUpOf($q)->status);
        $this->assertCount(0, $this->server->sent);
        $this->assertFalse(Senders::isPaused($tess->id));
    }

    public function test_the_unsubscribe_link_and_the_inbox_button_stop_all_marketing(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $fr = $this->prospect($campaign, 'approved', ['language' => 'fr', 'email' => 'brice.ngono@lima.test', 'first_name' => 'Brice', 'country' => 'Cameroon']);
        $en = $this->prospect($campaign, 'approved');
        $page = $this->portalUrl('/email/unsubscribe/o/' . $this->firstOf($fr)->token);

        // Opening the page never unsubscribes; it speaks the prospect's language
        $this->get($page)->assertOk()->assertSee('Ne plus recevoir d’e-mails de JBS Praxis')->assertSee('b••••••@lima.test');
        $this->assertTrue(Marketing::canEmail($fr->email));
        $this->get($page . '?lang=en')->assertSee('Stop emails from JBS Praxis?');
        $this->post($page)->assertOk()->assertSee('Vous êtes désabonné');
        $entry = Marketing::entry($fr->email);
        $this->assertSame(['unsubscribed', 'outreach'], [$entry->reason, $entry->source]);
        $this->assertSame('unsubscribed', $fr->fresh()->status);
        $this->assertSame(['skipped', 'Unsubscribed'], [$this->firstOf($fr)->status, $this->firstOf($fr)->note]);
        $this->assertSame('cancelled', $this->followUpOf($fr)->status);
        $this->get($page)->assertSee('Vous êtes désabonné');

        // The inbox's own one-click button (RFC 8058), on the follow-up's link
        $this->call('POST', $this->portalUrl('/email/unsubscribe/o/' . $this->followUpOf($en)->token), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()->assertSee('Unsubscribed');
        $this->assertFalse(Marketing::canEmail($en->email));
        $this->assertSame('skipped', $this->firstOf($en)->status);

        $this->get($this->portalUrl('/email/unsubscribe/o/' . str_repeat('x', 40)))->assertNotFound()->assertSee('We couldn’t use this link');
        $this->call('POST', $this->portalUrl('/email/unsubscribe/o/' . str_repeat('x', 40)), ['List-Unsubscribe' => 'One-Click'])->assertNotFound();

        $this->at('2026-11-03 10:00');
        Sender::run();
        $this->assertCount(0, $this->server->sent);
    }

    public function test_prospects_never_reach_a_broadcast_audience(): void
    {
        $gerald = $this->salesperson();
        $p = $this->prospect($this->outreachCampaign($gerald), 'draft');
        $everyone = \App\Support\Campaigns\Audience::recipients(['who' => ['clients', 'leads', 'subscribers'], 'filters' => []]);
        $this->assertNotContains($p->email, collect($everyone)->pluck('email')->all());
        $this->assertFalse(DB::table('client_users')->where('email', $p->email)->exists());
        $this->assertFalse(DB::table('marketing_subscribers')->where('email', $p->email)->exists());
    }

    /** The fake server refuses these addresses with these errors */
    private function refuse(array $errors): void
    {
        $server = new class extends FakeMailServer
        {
            public array $refusals = [];

            public function send(MailAccount $account, Email $email): string
            {
                $to = $email->getTo()[0]->getAddress();
                if (isset($this->refusals[$to])) {
                    throw $this->refusals[$to];
                }

                return parent::send($account, $email);
            }
        };
        $server->mailboxes = $this->server->mailboxes;
        $server->refusals = $errors;
        app()->instance(\App\Support\Mail\MailServer::class, $server);
        $this->server = $server;
    }
}
