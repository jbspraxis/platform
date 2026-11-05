<?php

namespace Tests\Feature\Campaigns;

use App\Livewire\Hq\Campaigns\Outreach\OutreachCampaignPage;
use App\Livewire\Hq\Campaigns\Outreach\OutreachList;
use App\Livewire\Hq\Campaigns\Outreach\OutreachReplies;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Campaigns\OutreachReply;
use App\Models\ClientUser;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Organization;
use App\Support\Ai\AiRequest;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Outreach\Inbound;
use App\Support\Campaigns\Outreach\Limits;
use App\Support\Campaigns\Outreach\Notices;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Outreach\Sender;
use App\Support\Campaigns\Outreach\Senders;
use App\Support\Integrations;
use App\Support\Leads\LeadEmails;
use App\Support\Leads\Leads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Outreach replies (C3, docs/CAMPAIGNS.md section 12; the owner's decisions of 3 Nov 2026): replies found in HQ Mail and
 * sorted by Claude, confirmed or changed by a person in one click; Unsubscribe suppressing; bounce notices and spam
 * reports filed strictly; Make a lead (new, and an existing company getting a contact); Not now with its bell; the
 * numbers; the screens and who may use them. Nothing leaves the machine: FakeMailServer, FakeAi, .test addresses.
 */
class OutreachRepliesTest extends TestCase
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

    /** The prospect's first email went from this mailbox (as Sender records it), its follow-up scheduled */
    private function sent(OutreachProspect $p, MailAccount $box, ?Carbon $at = null): OutreachEmail
    {
        $at ??= now()->subHour();
        $first = $this->firstOf($p);
        $first->forceFill(['status' => 'sent', 'sent_at' => $at, 'tried_at' => $at, 'message_id' => Str::random(16) . '@jbspraxis.test',
            'sender_id' => $box->owner_user_id, 'mail_account_id' => $box->id])->save();
        $this->followUpOf($p)->forceFill(['status' => 'scheduled', 'send_after' => $at->copy()->addDays(5)])->save();
        $p->forceFill(['status' => 'emailed'])->save();

        return $first->fresh();
    }

    /** A message in HQ Mail's copy of the mailbox (as mail:sync stores it); returns its id */
    private function mail(MailAccount $box, string $from, array $values = [], ?string $role = 'inbox'): int
    {
        $folder = DB::table('mail_folders')->where('account_id', $box->id)->where('role', $role)->value('id')
            ?? DB::table('mail_folders')->insertGetId(['account_id' => $box->id, 'path' => $role === 'inbox' ? 'INBOX' : 'INBOX.' . ucfirst($role), 'name' => ucfirst($role),
                'role' => $role, 'selectable' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $mid = $values['message_id'] ?? Str::random(12) . '@mail.test';

        return DB::table('mail_messages')->insertGetId($values + [
            'account_id' => $box->id, 'folder_id' => $folder, 'uid' => random_int(1, 999999), 'message_id' => $mid, 'message_id_hash' => MailMessage::hashId($mid),
            'from_email' => $from, 'from_name' => 'Ada Obi', 'subject' => 'Re: Your second warehouse in Onne', 'received_at' => now(), 'sent_at' => now(),
            'is_outbound' => 0, 'body_state' => 'cached', 'body_text' => 'Thanks.', 'snippet' => 'Thanks.', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** outreach:replies a minute later (mail stored in the last seconds waits for the next run) */
    private function collect(): void
    {
        Carbon::setTestNow(now()->addMinute());
        $this->artisan('outreach:replies')->assertSuccessful();
    }

    private function replyOf(OutreachProspect $p): OutreachReply
    {
        return OutreachReply::where('prospect_id', $p->id)->latest('id')->firstOrFail();
    }

    public function test_a_reply_is_found_sorted_by_claude_and_confirmed_or_changed_in_one_click(): void
    {
        $ai = FakeAi::install()->respond('outreach_reply', ['label' => 'interested', 'reason' => 'Asks for a call next Tuesday afternoon', 'come_back_on' => '']);
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $box = Senders::mailbox($gerald->id);
        $this->at('2026-11-04 09:00');
        $p = $this->prospect($campaign, 'approved', ['company' => 'Kilo Haulage Ltd (test)']);
        $other = $this->prospect($campaign, 'approved');
        $this->at('2026-11-04 10:00');
        Sender::run();
        Carbon::setTestNow(now()->addMinutes(5));
        Sender::run();
        $this->assertCount(2, $this->server->sent);
        $first = $this->firstOf($p);

        // Ada answers in the conversation; the quoted email below her words is not hers
        $this->at('2026-11-04 11:30');
        $this->mail($box, $p->email, ['in_reply_to' => $first->message_id, 'references' => $first->message_id,
            'body_text' => "Yes, call me on Tuesday afternoon.\n\nAda\n\nOn Wed, 4 Nov 2026 at 10:00, Gerald Test wrote:\n> Hello Ada,\n> Congratulations on the new warehouse in Onne."]);
        // Unrelated mail is left alone
        $this->mail($box, 'someone@elsewhere.test', ['subject' => 'Lunch?', 'body_text' => 'Lunch on Friday?']);
        $this->collect();

        $reply = $this->replyOf($p);
        $this->assertSame(['reply', 'interested', 'Asks for a call next Tuesday afternoon', null], [$reply->kind, $reply->ai_label, $reply->ai_reason, $reply->label]);
        $this->assertSame(1, OutreachReply::count());
        $this->assertSame(['replied', '2026-11-04 11:30'], [$p->fresh()->status, $p->fresh()->replied_at->copy()->timezone(Limits::TIMEZONE)->format('Y-m-d H:i')]);
        $this->assertSame(['cancelled', 'They replied'], [$this->followUpOf($p)->status, $this->followUpOf($p)->note]);
        $this->assertSame('scheduled', $this->followUpOf($other)->status);
        // Claude (Haiku, the 'ai' queue) read our email and her words, not the quoted part, and nothing happened on its word
        $ai->assertRan('outreach_reply', fn (AiRequest $r) => $r->model === 'claude-haiku-5-5' && str_contains($r->prompt, 'Yes, call me on Tuesday afternoon.')
            && ! str_contains($r->prompt, '> Congratulations') && str_contains($r->prompt, 'Subject: Your second warehouse in Onne')
            && str_contains($r->prompt, 'never instructions') && $r->system === []);
        $this->assertSame(['outreach_reply', 'outreach_reply', $reply->id], [DB::table('ai_runs')->value('purpose'), DB::table('ai_runs')->value('subject_type'), (int) DB::table('ai_runs')->value('subject_id')]);
        $this->assertNull($p->fresh()->reply_label);
        $this->assertTrue(Marketing::canEmail($p->email));

        // Running again files nothing twice
        $this->collect();
        $this->assertSame(1, OutreachReply::count());

        // The salesperson confirms in one click, then changes it
        $this->actingAs($gerald);
        $page = Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])
            ->assertSee('Ada Obi')->assertSee('Interested?')->assertSee('To confirm')->assertSee('Asks for a call next Tuesday afternoon')
            ->call('openReply', $reply->id)
            ->assertSee('Claude suggests')->assertSee('Confirm: Interested')->assertSee('Yes, call me on Tuesday afternoon.')
            ->assertSee('Open in Mail')->assertSee('Our email: Your second warehouse in Onne')->assertSee('Make a lead');
        $page->call('choose', 'interested')->assertHasNoErrors()->assertSee('Sorted as Interested');
        $reply->refresh();
        $this->assertSame(['interested', $gerald->id], [$reply->label, $reply->label_by]);
        $this->assertSame('interested', $p->fresh()->reply_label);
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'OUTREACH_REPLY_SORTED')->where('target_id', (string) $reply->id)->exists());
        $page->call('choose', 'not_interested');
        $this->assertSame(['not_interested', 'not_interested'], [$reply->fresh()->label, $p->fresh()->reply_label]);
        $this->assertTrue(Marketing::canEmail($p->email));

        // The numbers
        $stats = Outreach::stats([$campaign->id])[$campaign->id];
        $this->assertSame([1, 50, 0, 0, 1, 0], [$stats['replies'], $stats['reply_rate'], $stats['to_sort'], $stats['interested'], $stats['not_interested'], $stats['leads']]);
    }

    public function test_unsubscribe_only_once_a_person_confirms_it_and_then_for_good(): void
    {
        FakeAi::install()->respond('outreach_reply', ['label' => 'unsubscribe', 'reason' => 'Asks to be taken off the list', 'come_back_on' => '']);
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $box = Senders::mailbox($gerald->id);
        $p = $this->prospect($campaign, 'approved');
        $first = $this->sent($p, $box);
        // From her personal address, in the same conversation
        $this->mail($box, 'ada.personal@mail.test', ['in_reply_to' => $first->message_id, 'body_text' => 'Please remove me from your list.']);
        $this->collect();

        $reply = $this->replyOf($p);
        $this->assertSame(['unsubscribe', null], [$reply->ai_label, $reply->label]);
        $this->assertTrue(Marketing::canEmail($p->email)); // Claude's word alone changes nothing
        $this->assertSame('replied', $p->fresh()->status);

        $this->actingAs($gerald);
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $reply->id)->call('choose', 'unsubscribe')->assertHasNoErrors();
        foreach ([$p->email, 'ada.personal@mail.test'] as $address) {
            $entry = Marketing::entry($address);
            $this->assertSame(['unsubscribed', 'outreach'], [$entry->reason, $entry->source]);
        }
        $this->assertSame('unsubscribed', $p->fresh()->status);
        $this->assertNotNull($p->fresh()->unsubscribed_at);
        // Changing the label later leaves the address on the list: only the person can undo an unsubscribe
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $reply->id)
            ->call('choose', 'interested')->assertSee('only they can undo that');
        $this->assertFalse(Marketing::canEmail($p->email));
        $this->assertFalse(Marketing::lift($p->email));
    }

    public function test_not_now_keeps_a_reminder_and_the_bell_rings_once_on_that_working_day(): void
    {
        FakeAi::install()->respond('outreach_reply', ['label' => 'not_now', 'reason' => 'Budget opens in January', 'come_back_on' => '2027-01-09']);
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $box = Senders::mailbox($gerald->id);
        $this->at('2026-11-04 10:00');
        $p = $this->prospect($campaign, 'approved');
        $first = $this->sent($p, $box);
        $q = $this->prospect($campaign, 'approved', ['first_name' => 'Bola']);
        $this->sent($q, $box);
        $this->mail($box, $p->email, ['in_reply_to' => $first->message_id, 'body_text' => 'Not this year. Try me after 8 January.']);
        $this->collect();
        $reply = $this->replyOf($p);
        $this->assertSame(['not_now', '2027-01-09'], [$reply->ai_label, $reply->ai_remind_on->format('Y-m-d')]);

        $this->actingAs($gerald);
        $page = Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $reply->id)
            ->assertSet('remindOn', '2027-01-09')->assertSee('the day they named');
        // A day in the past is refused
        $page->set('remindOn', '2026-11-01')->call('choose', 'not_now')->assertHasErrors('remindOn');
        $this->assertNull($reply->fresh()->label);
        $page->set('remindOn', '2027-01-09')->call('choose', 'not_now')->assertHasNoErrors();
        $this->assertSame(['not_now', '2027-01-09'], [$p->fresh()->reply_label, $p->fresh()->remind_on->format('Y-m-d')]);

        // Never sent again by itself: the sender sends nothing more to her
        $sentBefore = count($this->server->sent);
        foreach (['2026-11-05 10:00', '2026-11-10 10:00', '2027-01-11 10:00'] as $when) {
            $this->at($when);
            Sender::run();
        }
        $this->assertCount($sentBefore + 1, $this->server->sent); // only Bola's follow-up
        $this->assertSame($q->email, end($this->server->sent)['email']->getTo()[0]->getAddress());
        $this->assertSame('cancelled', $this->followUpOf($p)->status);

        // 9 Jan 2027 is a Saturday: the bell comes on Monday 11 Jan at 07:30, once
        $this->at('2027-01-08 07:30');
        $this->artisan('outreach:remind')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->where('title', 'Time to get back to Ada')->count());
        $this->at('2027-01-11 07:30');
        $this->artisan('outreach:remind', ['--dry-run' => true])->expectsOutputToContain('[dry run] Gerald')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->where('title', 'Time to get back to Ada')->count());
        $this->artisan('outreach:remind')->assertSuccessful();
        $this->artisan('outreach:remind')->assertSuccessful();
        $bells = DB::table('notifications')->where('title', 'Time to get back to Ada')->get();
        $this->assertCount(1, $bells);
        $this->assertSame($gerald->id, $bells[0]->user_id);
        $this->assertStringContainsString('Budget opens in January', $bells[0]->message);
        $this->assertStringContainsString('show=replies', $bells[0]->link_url);
        $this->assertStringContainsString('reply=' . $reply->id, $bells[0]->link_url);
        $this->assertNotNull($p->fresh()->reminded_at);

        // A new day rings again, once
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $reply->id)->assertSee('The bell rang')
            ->set('remindOn', '2027-03-01')->call('saveReminder')->assertHasNoErrors();
        $this->assertNull($p->fresh()->reminded_at);
        $this->at('2027-03-01 07:30');
        $this->artisan('outreach:remind');
        $this->assertSame(2, DB::table('notifications')->where('title', 'Time to get back to Ada')->count());

        // Without a day from Claude, three months; the schedule as leads:morning-bell's
        FakeAi::install()->respond('outreach_reply', ['label' => 'not_now', 'reason' => 'Busy this quarter', 'come_back_on' => '']);
        $this->at('2026-11-04 12:00');
        $this->mail($box, $q->email, ['body_text' => 'Busy this quarter, sorry.']);
        $this->collect();
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $this->replyOf($q)->id)->assertSet('remindOn', '2027-02-04');
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'outreach:remind'));
        $this->assertSame(['30 7 * * 1-5', 'Africa/Lagos'], [$event->expression, $event->timezone]);
        $collect = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'outreach:replies'));
        $this->assertTrue($collect->expression === '* * * * *' && $collect->withoutOverlapping);
    }

    public function test_bounce_notices_bounce_the_prospect_only_when_they_name_the_email_or_its_recipient(): void
    {
        $ai = FakeAi::install()->respond('outreach_reply', ['label' => 'not_interested', 'reason' => 'Not their area', 'come_back_on' => '']);
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $box = Senders::mailbox($gerald->id);
        $elsewhere = $this->personalMailbox($this->salesperson('bola', false));
        $this->at('2026-11-04 10:00');
        [$a, $b, $c, $d, $e] = array_map(fn ($i) => $this->prospect($campaign, 'approved'), range(1, 5));
        $firstA = $this->sent($a, $box, now()->subMinutes(50));
        foreach ([$b, $c, $d, $e] as $i => $p) {
            $this->sent($p, $box, now()->subMinutes(40 - $i));
        }

        // Gmail's notice names the email in In-Reply-To: C2's sweep takes it for a reply first
        $this->mail($box, 'mailer-daemon@googlemail.com', ['from_name' => 'Mail Delivery Subsystem', 'subject' => 'Delivery Status Notification (Failure)',
            'in_reply_to' => $firstA->message_id, 'references' => $firstA->message_id,
            'body_text' => "Address not found\n\nYour message wasn't delivered to {$a->email} because the address couldn't be found.\n\nThe response from the remote server was:\n550 5.1.1 The email account that you tried to reach does not exist."]);
        Sender::run();
        $this->assertSame('replied', $a->fresh()->status);
        // Postfix's notice names the recipient only, in the mailbox that sent to them
        $this->mail($box, 'MAILER-DAEMON@mail.kilo.test', ['from_name' => 'Mail Delivery System', 'subject' => 'Undelivered Mail Returned to Sender', 'has_attachments' => 1,
            'body_text' => "This is the mail system at host mail.kilo.test.\n\n<{$b->email}>: host mx.kilo.test[192.0.2.1] said: 550 5.1.1 <{$b->email}>: Recipient address rejected: User unknown"]);
        // Not strict enough: another mailbox, a delay, two people in one notice
        $this->mail($elsewhere, 'MAILER-DAEMON@mail.kilo.test', ['subject' => 'Undelivered Mail Returned to Sender', 'body_text' => "<{$c->email}>: 550 5.1.1 User unknown"]);
        $this->mail($box, 'postmaster@outlook.test', ['subject' => 'Delivery delayed: Your second warehouse in Onne', 'body_text' => "Action: delayed\nStatus: 4.4.1\nFinal-Recipient: rfc822; {$c->email}"]);
        $this->mail($box, 'MAILER-DAEMON@mail.kilo.test', ['subject' => 'Undelivered Mail Returned to Sender', 'body_text' => "<{$c->email}>: 550 5.1.1 User unknown\n<{$d->email}>: 550 5.1.1 User unknown"]);
        // A person writing about delays is a reply, not a notice
        $this->mail($box, $e->email, ['subject' => 'Re: delayed invoices', 'body_text' => 'Our invoices are delayed, but this is not for us.']);
        DB::table('mail_attachments')->insert(['message_id' => DB::table('mail_messages')->where('subject', 'Undelivered Mail Returned to Sender')->where('account_id', $box->id)->min('id'),
            'section' => '2', 'filename' => 'details.txt', 'mime' => 'message/delivery-status', 'size' => 300, 'is_inline' => 0]);
        $this->collect();

        foreach ([$a, $b] as $p) {
            $p->refresh();
            $this->assertSame(['bounced', null, 'bounced'], [$p->status, $p->replied_at, $p->reply_label]);
            $this->assertNotNull($p->bounced_at);
            $this->assertSame(['bounced', 'outreach'], [Marketing::entry($p->email)->reason, Marketing::entry($p->email)->source]);
            $this->assertSame('bounced', $this->firstOf($p)->status);
            $this->assertStringContainsString('550 5.1.1', $this->firstOf($p)->note);
            $this->assertSame('cancelled', $this->followUpOf($p)->status);
            $notice = $this->replyOf($p);
            $this->assertSame(['bounce', 'bounced', null], [$notice->kind, $notice->label, $notice->label_by]);
        }
        foreach ([$c, $d] as $p) {
            $this->assertSame('emailed', $p->fresh()->status);
            $this->assertTrue(Marketing::canEmail($p->email));
        }
        $this->assertSame(['reply', 'not_interested'], [$this->replyOf($e)->kind, $this->replyOf($e)->ai_label]);
        $this->assertCount(1, $ai->requestsFor('outreach_reply')); // notices are never sent to Claude
        // 2 of today's 5 bounced: the sender pauses, as for refusals while sending
        $this->assertTrue(Senders::isPaused($gerald->id));
        $this->assertSame('bounces', Senders::state($gerald->id)->paused_reason);
        $stats = Outreach::stats([$campaign->id])[$campaign->id];
        $this->assertSame([2, 1, 3], [$stats['bounced'], $stats['replies'], $stats['sent']]);

        // The notice reader's rules
        $this->assertSame('bounce', Notices::kind('mailer-daemon@x.test', 'Mail delivery failed: returning message to sender', 'SMTP error: 550 No such user'));
        $this->assertSame('delay', Notices::kind('postmaster@x.test', 'Delivery Status Notification (Delay)', 'Status: 4.4.7'));
        $this->assertNull(Notices::kind('ada@kilo.test', 'Undeliverable: re your email', '550 5.1.1'));
        $this->assertSame('complaint', Notices::kind('fbl@provider.test', 'Abuse report', "Feedback-Type: abuse\nUser-Agent: x"));
        $this->assertTrue(Notices::names('to <ada.x@kilo.test>: 550', 'ada.x@kilo.test'));
        $this->assertFalse(Notices::names('to bada.x@kilo.test.ng', 'ada.x@kilo.test'));
    }

    public function test_a_spam_complaint_report_or_a_persons_spam_choice_pauses_the_sender(): void
    {
        FakeAi::install()->respond('outreach_reply', ['label' => 'unsubscribe', 'reason' => 'Calls it spam', 'come_back_on' => '']);
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $box = Senders::mailbox($gerald->id);
        $p = $this->prospect($campaign, 'approved');
        $first = $this->sent($p, $box);
        $id = $this->mail($box, 'fbl@provider.test', ['from_name' => 'Feedback Loop', 'subject' => 'Complaint about a message', 'has_attachments' => 1,
            'body_text' => "This is an email abuse report.\n\nFeedback-Type: abuse\nOriginal-Rcpt-To: {$p->email}\nMessage-ID: <{$first->message_id}>"]);
        DB::table('mail_attachments')->insert(['message_id' => $id, 'section' => '2', 'filename' => 'report.txt', 'mime' => 'message/feedback-report', 
            'size' => 200, 'is_inline' => 0]);
        $this->collect();

        $this->assertSame(['complaint', 'complained'], [$this->replyOf($p)->kind, $this->replyOf($p)->label]);
        $this->assertSame('complained', Marketing::entry($p->email)->reason);
        $this->assertNotNull($p->fresh()->complained_at);
        $this->assertTrue(Senders::isPaused($gerald->id));
        $this->assertSame('complaint', Senders::state($gerald->id)->paused_reason);

        // A person reads "this is spam" in a reply: their choice (Claude's labels never include it), with a step to confirm
        $kemi = $this->salesperson('kemi');
        $campaign2 = $this->outreachCampaign($kemi);
        $q = $this->prospect($campaign2, 'approved');
        $firstQ = $this->sent($q, Senders::mailbox($kemi->id));
        $this->mail(Senders::mailbox($kemi->id), $q->email, ['in_reply_to' => $firstQ->message_id, 'body_text' => 'This is spam. I am reporting you.']);
        $this->collect();
        $this->actingAs($kemi);
        $page = Livewire::test(OutreachReplies::class, ['campaignId' => $campaign2->id])->call('openReply', $this->replyOf($q)->id)
            ->call('askSpam')->assertSee('Record a spam complaint from');
        $this->assertFalse(Senders::isPaused($kemi->id));
        $page->call('markSpam')->assertSee('Recorded as a spam complaint');
        $this->assertSame(['complained', 'complained'], [$this->replyOf($q)->label, Marketing::entry($q->email)->reason]);
        $this->assertTrue(Senders::isPaused($kemi->id));
        $this->assertTrue(DB::table('notifications')->where('user_id', $kemi->id)->where('title', 'like', 'Outreach paused%')->exists());
    }

    public function test_make_a_lead_new_existing_company_and_existing_contact(): void
    {
        FakeAi::install()->respond('outreach_reply', ['label' => 'interested', 'reason' => 'Wants the free audit', 'come_back_on' => '']);
        $gerald = $this->salesperson();
        $owner = $this->staff('olu', ['owner']);
        $campaign = $this->outreachCampaign($gerald, ['name' => 'Lagos logistics, November (test)']);
        $box = Senders::mailbox($gerald->id);
        $a = $this->prospect($campaign, 'approved', ['company' => 'Kilo Haulage Ltd (test)', 'website' => 'https://kilo-haulage.test', 'website_read_at' => now(),
            'findings' => 'Opened a second warehouse in Onne in September.', 'job_title' => 'Operations director']);
        $this->firstOf($a)->forceFill(['insight' => 'From What we found: the Onne warehouse.'])->save();
        $firstA = $this->sent($a, $box);
        // Delta is already a lead of Kemi's; Echo's buyer is already a contact somewhere
        $kemi = $this->salesperson('kemi', false);
        $delta = Leads::create(['name' => 'Delta Oil Services (test)', 'country' => 'Nigeria', 'industry' => 'Energy & Oil/Gas', 'phone' => '+234 800 000 0002'], [], $kemi);
        $b = $this->prospect($campaign, 'approved', ['company' => 'DELTA OIL SERVICES LTD (test)', 'first_name' => 'Chidi', 'last_name' => 'Okafor']);
        $firstB = $this->sent($b, $box);
        $echo = Leads::create(['name' => 'Echo Retail (test)', 'country' => 'Nigeria', 'industry' => 'Retail', 'phone' => '+234 800 000 0003'],
            [['name' => 'Kofi Mensah', 'email' => 'kofi@echo-retail.test']], $kemi);
        $c = $this->prospect($campaign, 'approved', ['company' => 'Echo Stores (test)', 'first_name' => 'Kofi', 'email' => 'kofi@echo-retail.test']);
        $firstC = $this->sent($c, $box);
        foreach ([[$a, $firstA], [$b, $firstB], [$c, $firstC]] as [$p, $f]) {
            $this->mail($box, $p->email, ['in_reply_to' => $f->message_id, 'body_text' => 'Yes, the free audit sounds good.']);
        }
        $this->collect();

        // The owner makes Ada a lead: owned by the campaign's sender, with the research as its first note
        $this->actingAs($owner);
        $page = Livewire::test(OutreachReplies::class)->call('openReply', $this->replyOf($a)->id)
            ->assertSee('A new lead, Kilo Haulage Ltd (test), owned by Gerald Test')->call('makeLead')->assertHasNoErrors()
            ->assertDispatched('praxis-toast', $this->toast('Lead made: Kilo Haulage Ltd (test), owned by Gerald Test.'))->assertSee('Open the lead');
        $org = Organization::findOrFail($a->fresh()->organization_id);
        // Making the lead is a person's decision: the unsorted reply is now Interested
        $this->assertSame(['interested', $owner->id, 'interested'], [$this->replyOf($a)->label, $this->replyOf($a)->label_by, $a->fresh()->reply_label]);
        $this->assertSame(['Lead', 'new', 'outreach', 'Outreach: Lagos logistics, November (test)', $gerald->id, 'Nigeria', 'Logistics & Supply Chain'],
            [$org->status, $org->lead_status, $org->source, $org->source_detail, $org->assigned_to, $org->country, $org->industry]);
        $contact = ClientUser::where('organization_id', $org->id)->sole();
        $this->assertSame([$a->email, 'outreach', 'Ada Obi', 'Operations director', true], [$contact->email, $contact->source, $contact->full_name, $contact->job_title, (bool) $contact->is_main_contact]);
        $note = DB::table('lead_timeline')->where('organization_id', $org->id)->where('type', 'note')->sole();
        $this->assertStringContainsString('From outreach: Lagos logistics, November (test)', $note->summary);
        foreach (['What we found: Opened a second warehouse in Onne in September.', 'Website: https://kilo-haulage.test', 'Claude’s insight: From What we found: the Onne warehouse.'] as $line) {
            $this->assertStringContainsString($line, $note->body);
        }
        $this->assertSame($owner->id, $a->fresh()->lead_made_by);
        $this->assertTrue(DB::table('notifications')->where('user_id', $gerald->id)->where('title', 'A lead from outreach: Kilo Haulage Ltd (test)')->exists());
        // The prospect row stays, and the thread shows on the lead through LeadEmails
        $this->assertSame(2, OutreachEmail::where('prospect_id', $a->id)->count());
        $this->assertTrue(LeadEmails::for($gerald, $org->id)->pluck('from_email')->contains($a->email));
        LeadEmails::fileNew();
        $this->assertTrue(DB::table('lead_timeline')->where('organization_id', $org->id)->where('type', 'email')->exists());
        // Twice makes nothing new
        $page->call('makeLead');
        $this->assertSame(1, Organization::where('name', 'Kilo Haulage Ltd (test)')->count());

        // Gerald makes Chidi a lead: Delta exists, so he becomes its contact, and Delta keeps its owner
        $this->actingAs($gerald);
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $this->replyOf($b)->id)
            ->assertSee('is already a lead')->call('makeLead')
            ->assertDispatched('praxis-toast', $this->toast('Delta Oil Services (test) is already a lead owned by Kemi Test, so Chidi Okafor was added there as a contact.'));
        $this->assertSame($delta->id, $b->fresh()->organization_id);
        $this->assertSame($kemi->id, $delta->fresh()->assigned_to);
        $this->assertSame('outreach', ClientUser::where('organization_id', $delta->id)->where('email', $b->email)->sole()->source);
        $this->assertTrue(DB::table('lead_timeline')->where('organization_id', $delta->id)->where('type', 'contact_added')->exists());
        $this->assertTrue(DB::table('notifications')->where('user_id', $kemi->id)->where('title', 'A contact from outreach: Delta Oil Services (test)')->exists());
        // Kofi is already Echo's contact: linked, nothing new
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $this->replyOf($c)->id)->call('makeLead')
            ->assertDispatched('praxis-toast', $this->toast('Kofi Obi is already a contact of Echo Retail (test)'));
        $this->assertSame([$echo->id, 1], [$c->fresh()->organization_id, ClientUser::where('email', 'kofi@echo-retail.test')->count()]);
        $this->assertSame(3, Outreach::stats([$campaign->id])[$campaign->id]['leads']);
    }

    public function test_claude_unavailable_leaves_the_reply_for_a_person_and_ask_again_works(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $box = Senders::mailbox($gerald->id);
        $p = $this->prospect($campaign, 'approved');
        $first = $this->sent($p, $box);
        $ai = FakeAi::install()->unconfigured();
        $this->mail($box, $p->email, ['in_reply_to' => $first->message_id, 'body_text' => 'Tell me more.']);
        // The same message in a second folder is one reply
        $this->mail($box, $p->email, ['message_id' => DB::table('mail_messages')->value('message_id'), 'in_reply_to' => $first->message_id], 'archive');
        $this->collect();
        $reply = $this->replyOf($p);
        $this->assertSame(1, OutreachReply::count());
        $this->assertSame(['ai.errors.not_configured', null], [$reply->ai_error, $reply->ai_label]);
        $this->collect(); // not asked again by itself
        $ai->assertNothingRan();

        $this->actingAs($gerald);
        $page = Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->assertSee('To sort')->call('openReply', $reply->id)
            ->assertSee('Claude couldn’t sort this reply');
        FakeAi::install()->respond('outreach_reply', ['label' => 'interested', 'reason' => 'Asks for details', 'come_back_on' => '2027-01-01']);
        $page->call('askAgain')->assertSee('Claude suggests')->assertSee('Asks for details');
        $this->assertSame(['interested', null], [$reply->fresh()->ai_label, $reply->fresh()->ai_remind_on]); // a day only for not now
        $page->call('choose', 'interested');
        $this->assertSame('interested', $reply->fresh()->label);
    }

    public function test_the_collector_reads_each_message_once_from_the_first_outreach_email(): void
    {
        FakeAi::install()->respond('outreach_reply', ['label' => 'interested', 'reason' => 'Yes', 'come_back_on' => '']);
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $box = Senders::mailbox($gerald->id);
        $p = $this->prospect($campaign, 'approved');
        // Mail from her before the email went is not a reply
        $this->mail($box, $p->email, ['received_at' => now()->subDays(3), 'subject' => 'Old thread']);
        $first = $this->sent($p, $box);
        $this->mail($box, $p->email, ['received_at' => now(), 'subject' => 'Re: yours']);
        $this->collect();
        $this->assertSame(1, OutreachReply::count());
        $this->assertSame('Re: yours', $this->replyOf($p)->subject);
        $this->assertSame((string) DB::table('mail_messages')->max('id'), Integrations::get(Inbound::CURSOR));

        // Mail stored a moment ago waits for the next run
        $id = $this->mail($box, $p->email, ['in_reply_to' => $first->message_id, 'subject' => 'Re: one more thing']);
        $this->artisan('outreach:replies');
        $this->assertSame(1, OutreachReply::count());
        $this->collect();
        $this->assertSame(2, OutreachReply::count());
        $this->assertSame((string) $id, Integrations::get(Inbound::CURSOR));
    }

    public function test_the_screens_numbers_and_who_may_see_replies(): void
    {
        FakeAi::install()->respond('outreach_reply', ['label' => 'interested', 'reason' => 'Asks for a call', 'come_back_on' => '']);
        $gerald = $this->salesperson();
        $owner = $this->staff('olu', ['owner']);
        $auditor = $this->staff('audrey', ['auditor']);
        $kemi = $this->salesperson('kemi');
        $campaign = $this->outreachCampaign($gerald, ['name' => 'Gerald’s campaign (test)']);
        $kemiCampaign = $this->outreachCampaign($kemi, ['name' => 'Kemi’s campaign (test)']);
        $box = Senders::mailbox($gerald->id);
        $p = $this->prospect($campaign, 'approved');
        $first = $this->sent($p, $box);
        $this->sent($this->prospect($campaign, 'approved'), $box);
        $this->mail($box, $p->email, ['in_reply_to' => $first->message_id, 'body_text' => 'Call me, please.']);
        $this->collect();
        $reply = $this->replyOf($p);

        // The Outreach page: Replies with the count to sort, and the numbers per campaign
        $this->actingAs($gerald);
        Livewire::test(OutreachList::class)->assertSee('Replies')->assertSee('1 to sort')->assertSee('50%');
        $this->get($this->hqUrl('/campaigns/outreach/replies'))->assertOk()->assertSee('Gerald’s campaign (test)')->assertSee('Ada Obi');
        // The campaign page: its Replies tab and the replies numbers
        Livewire::withQueryParams(['show' => 'replies'])->test(OutreachCampaignPage::class, ['campaign' => $campaign->id])
            ->assertSet('filter', 'replies')->assertSeeLivewire(OutreachReplies::class)->assertSee('50% reply rate')->assertSee('Leads made');
        // A bell's link opens the reply
        Livewire::withQueryParams(['reply' => $reply->id])->test(OutreachReplies::class, ['campaignId' => $campaign->id])->assertSet('openId', $reply->id)
            ->assertSee('Call me, please.');

        // Another salesperson: not Gerald's replies
        $this->actingAs($kemi);
        Livewire::test(OutreachReplies::class)->assertDontSee('Ada Obi')->call('openReply', $reply->id)->assertSet('openId', null);
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->assertForbidden();
        Livewire::withQueryParams(['show' => 'replies'])->test(OutreachCampaignPage::class, ['campaign' => $campaign->id])->assertSet('filter', 'review')
            ->assertDontSeeLivewire(OutreachReplies::class);
        // Outreach view only (Auditor): the numbers, never the replies
        $this->actingAs($auditor);
        $this->get($this->hqUrl('/campaigns/outreach/replies'))->assertForbidden();
        Livewire::test(OutreachList::class)->assertSee('50%')->assertDontSee(route('hq.campaigns.outreach.replies'))
            ->assertDontSee(route('hq.campaigns.outreach.campaign', ['campaign' => $campaign->id, 'show' => 'replies']));
        // The owner (outreach.approve) sorts every campaign, but Gerald's own mailbox stays private: label and reason, not the text
        $this->actingAs($owner);
        Livewire::test(OutreachReplies::class)->assertSee('Ada Obi')->call('openReply', $reply->id)->assertSee('Asks for a call')
            ->assertDontSee('Call me, please.')->assertSee('only Gerald can read it')->assertDontSee('Open in Mail')
            ->call('choose', 'interested')->assertHasNoErrors();
        $this->assertSame([$owner->id, 'interested'], [$reply->fresh()->label_by, $reply->fresh()->label]);
        $this->assertNotNull($kemiCampaign->id);
    }
}
