<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\Inbox;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Models\User;
use App\Support\Hosts;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** The Mail page (docs/MAIL.md, PR 2): list, reader, deep links, access per level, search and older mail. */
class ReadInboxTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_a_conversation_link_opens_its_mailbox_marks_it_read_and_shows_who_opened_it(): void
    {
        [$account, $sender, $reader] = $this->shared();
        $own = $this->mailbox('rea@jbspraxis.com', 'personal', $reader);
        $uid = $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Mireille Ngo <mireille@client.cm>', subject: 'Budget review for Q4', text: 'Could we move it to Thursday?'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        // The link names the conversation; box= from an old link (their own mailbox) gives way to the conversation's
        Livewire::actingAs($reader)->withQueryParams(['box' => $own->id, 'thread' => $thread->id])->test(Inbox::class)
            ->assertSet('box', $account->id)
            ->assertSet('thread', $thread->id)
            ->assertSee('Budget review for Q4')
            ->assertSee('Could we move it to Thursday?')
            ->assertSee('Opened by')
            // The conversation's team bar mounts with it and records "viewing" itself: no event, so no second round trip
            ->assertNotDispatched('mail-thread-opened');
        $this->assertSame('viewing', DB::table('mail_presence')->where('user_id', $reader->id)->where('thread_id', $thread->id)->value('state'));

        $message = MailMessage::firstOrFail();
        $this->assertTrue($message->is_seen);
        $this->assertSame($reader->id, $message->seen_by);
        $this->assertContains('\\Seen', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags'], 'Read for everyone: the server knows');

        // Someone else opening later sees who opened it first
        Livewire::actingAs($sender)->withQueryParams(['thread' => $thread->id])->test(Inbox::class)
            ->assertSee('Opened by')->assertSee($reader->first_name);
        $this->assertSame($reader->id, $message->fresh()->seen_by);
    }

    public function test_links_to_mail_the_person_cannot_open_show_nothing_of_it(): void
    {
        [$account] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Board pack', text: 'Confidential numbers'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $outsider = $this->staff('out', ['sales']);
        Livewire::actingAs($outsider)->withQueryParams(['thread' => $thread->id, 'box' => $account->id])->test(Inbox::class)
            ->assertSet('thread', null)
            ->assertDontSee('Board pack')
            ->assertDontSee('Confidential numbers')
            ->assertSee('That conversation is no longer in a mailbox you can open.');
        $this->assertFalse(MailMessage::firstOrFail()->is_seen, 'Nothing was marked read');

        // A personal mailbox is private, even from Owner accounts
        $ada = $this->staff('ada', ['sales']);
        $personal = $this->mailbox('ada@jbspraxis.com', 'personal', $ada);
        $this->server->deliver('ada@jbspraxis.com', 'INBOX', FakeMailServer::message(to: 'ada@jbspraxis.com', subject: 'Private matter'));
        MailSync::make()->account($personal);
        $private = MailThread::where('account_id', $personal->id)->firstOrFail();
        $owner = $this->staff('own', ['owner']);
        Livewire::actingAs($owner)->withQueryParams(['thread' => $private->id])->test(Inbox::class)
            ->assertDontSee('Private matter');
        Livewire::actingAs($owner)->test(Inbox::class)->call('selectBox', $personal->id)->assertForbidden();
        Livewire::actingAs($owner)->test(Inbox::class)->call('openThread', $private->id)->assertNotFound();

        // Mail closed to everyone again: only Owner accounts use it
        DB::table('mail_settings')->where('key', 'open_to')->delete();
        \App\Support\Mail\MailAccess::flush();
        Livewire::actingAs($ada)->test(Inbox::class)->assertForbidden();
    }

    public function test_read_level_reads_but_cannot_file_and_send_level_can(): void
    {
        [$account, $sender, $reader] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Proposal questions'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $page = Livewire::actingAs($reader)->test(Inbox::class)
            ->assertSee('Proposal questions')
            ->call('openThread', $thread->id)
            ->assertSee('Mark as unread')
            ->assertDontSee('aria-label="Archive"', false)
            ->assertDontSee('Reply all');
        $page->call('act', 'archive')->assertForbidden();
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX'));

        // Marking unread is reading, so Read can do it
        Livewire::actingAs($reader)->test(Inbox::class)->call('openThread', $thread->id)->call('act', 'unread')->assertSee('Marked as unread.');
        $this->assertFalse(MailMessage::firstOrFail()->is_seen);

        Livewire::actingAs($sender)->test(Inbox::class)->call('openThread', $thread->id)
            ->assertSee('aria-label="Archive"', false)->assertSee('Reply all')
            ->call('act', 'archive')->assertSee('Archived.')->assertSet('thread', null);
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX'));
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX.Archive'));
    }

    public function test_switcher_folders_counts_badges_filters_and_pages(): void
    {
        [$account, $sender] = $this->shared();
        $own = $this->mailbox('sen@jbspraxis.com', 'personal', $sender);
        for ($i = 1; $i <= 53; $i++) {
            $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: "Request {$i}", date: now()->subMinutes(200 - $i)->toRfc2822String()), $i <= 50 ? ['\\Seen'] : []);
        }
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Signed contract', attachments: [['name' => 'contract.pdf', 'mime' => 'application/pdf', 'content' => '%PDF-1.4 x']]), ['\\Seen', '\\Flagged']);
        $this->server->deliver('sen@jbspraxis.com', 'INBOX', FakeMailServer::message(to: 'sen@jbspraxis.com', subject: 'My own mail'));
        MailSync::make()->account($account);
        MailSync::make()->account($own);
        $assigned = MailThread::where('subject', 'Request 1')->firstOrFail();
        $assigned->forceFill(['assigned_to' => $sender->id, 'status' => 'done'])->save();

        $page = Livewire::actingAs($sender)->test(Inbox::class)
            ->assertSet('box', $own->id) // their own mailbox first
            ->assertSee('My own mail')
            ->call('selectBox', $account->id)
            ->assertSee('Signed contract')
            ->assertSee('1–50 of 54')
            ->assertSee('Has attachments')
            ->assertSee('Flagged')
            ->assertDontSee('>Request 1</span>', false);
        $this->assertSame(3, $account->folder('inbox')->unread_count);

        $page->call('goToPage', 2)->assertSee('51–54 of 54')->assertSee('Request 1')->assertSee('Done')->assertSee('>You<', false);
        $page->call('setFilter', 'unread')->assertSee('Request 53')->assertDontSee('Signed contract')->assertSet('page', 1);
        $page->call('setFilter', 'mine')->assertSee('Request 1')->assertDontSee('Request 53');
        $page->call('setFilter', 'open')->assertDontSee('>Request 1</span>', false)->assertSee('Request 53');
        $page->call('setFilter', 'done')->assertSee('Request 1')->assertDontSee('Request 53');

        // Folders by role or id, and the ?folder= link
        $page->call('setFilter', 'all')->call('selectFolder', 'trash')->assertSee('No mail in Trash');
        Livewire::actingAs($sender)->withQueryParams(['box' => $account->id, 'folder' => 'trash'])->test(Inbox::class)->assertSee('No mail in Trash');
    }

    public function test_reader_holds_back_remote_pictures_and_never_shows_unsafe_html(): void
    {
        [$account, , $reader] = $this->shared();
        $html = '<p>Quarterly <b>update</b></p><img src="https://track.test/open.gif" alt=""><script>alert("x")</script><a href="javascript:alert(1)" onclick="x()">click</a>';
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'News <news@vendor.test>', subject: 'Newsletter', html: $html, text: 'Quarterly update'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();
        $message = MailMessage::firstOrFail();

        $page = Livewire::actingAs($reader)->test(Inbox::class)->call('openThread', $thread->id)
            ->assertSee('Pictures from this sender are hidden')
            ->assertSee('sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"', false);
        $doc = $this->srcdoc($page->html());
        $this->assertStringContainsString('Quarterly <b>update</b>', $doc);
        $this->assertStringNotContainsString('track.test', $doc);
        $this->assertStringNotContainsStringIgnoringCase('<script', $doc);
        $this->assertStringNotContainsString('javascript:', $doc);
        $this->assertStringNotContainsString('onclick', $doc);
        $this->assertStringContainsString("default-src 'none'", html_entity_decode($doc, ENT_QUOTES | ENT_HTML5));

        $page->call('loadImages', $message->id)->assertDontSee('Pictures from this sender are hidden');
        $this->assertStringContainsString('track.test/open.gif', $this->srcdoc($page->html()));

        // Always for this sender: remembered for this person only
        Livewire::actingAs($reader)->test(Inbox::class)->call('openThread', $thread->id)->call('trustSender', $message->id)
            ->assertSee('Pictures from news@vendor.test will always load for you.');
        $this->assertTrue(DB::table('mail_image_trust')->where('user_id', $reader->id)->where('sender', 'news@vendor.test')->exists());
        $again = Livewire::actingAs($reader)->test(Inbox::class)->call('openThread', $thread->id);
        $this->assertStringContainsString('track.test/open.gif', $this->srcdoc($again->html()));
        $other = Livewire::actingAs($this->owner())->test(Inbox::class)->call('selectBox', $account->id)->call('openThread', $thread->id);
        $this->assertStringNotContainsString('track.test', $this->srcdoc($other->html()));
    }

    /** Google Calendar ships dark-device rules that put #e8eaed on the reader's white page (1.2:1): only the light palette reaches the frame */
    public function test_a_calendar_invitation_shows_in_its_light_palette_on_the_readers_white_page(): void
    {
        [$account, , $reader] = $this->shared();
        $html = '<style>'
            . ':root{color-scheme:light dark}'
            . '.primary-text{color:#212121}.secondary-text{color:#5f6368}.btn{background:#1a73e8;color:#ffffff}'
            . '@media (prefers-color-scheme: dark){'
            . '.primary-text{color:#e8eaed !important}.secondary-text{color:#9aa0a6 !important}'
            . '.btn{background:#8ab4f8 !important}}'
            . '</style>'
            . '<div class="primary-text">MyCity ticket payments - what is done and where we are blocked</div>'
            . '<div class="secondary-text">Monday Nov 9, 2026 2pm - 3pm (West Africa Time - Lagos)</div>'
            . '<a class="btn" href="https://meet.google.com/htw-rgya-wbc">Join with Google Meet</a>';

        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(
            from: 'JBS Praxis <praxisjbs@gmail.com>',
            subject: 'Invitation: MyCity Ticket Payments @ Mon Nov 9, 2026 2pm - 3pm (WAT) (tom.asaah@jbspraxis.com)',
            html: $html,
            text: null,
        ));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $page = Livewire::actingAs($reader)->test(Inbox::class)->call('openThread', $thread->id)
            ->assertSee('Join with Google Meet');
        $doc = $this->srcdoc($page->html());

        $this->assertStringContainsString('html,body{margin:0;padding:0;background:#FFFFFF;color-scheme:light}', $doc);
        $this->assertStringContainsString('color:#0C142B', $doc);

        $this->assertStringNotContainsStringIgnoringCase('prefers-color-scheme', $doc);
        foreach (['#e8eaed', '#9aa0a6', '#8ab4f8'] as $dark) {
            $this->assertStringNotContainsStringIgnoringCase($dark, $doc, "The frame still carries {$dark}");
        }

        $this->assertStringContainsString('.primary-text{color:#212121}', $doc);
        $this->assertStringContainsString('.secondary-text{color:#5f6368}', $doc);
        $this->assertStringContainsString('.btn{background:#1a73e8;color:#ffffff}', $doc);
    }

    public function test_conversation_shows_each_message_once_with_attachments_and_cid_pictures(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', subject: 'Plans', text: 'First message', messageId: 'one@client.test', date: now()->subHours(3)->toRfc2822String()), ['\Seen']);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', subject: 'Re: Plans', html: '<p>See the plan <img src="cid:plan@x"></p>', text: null,
            messageId: 'two@client.test', inReplyTo: 'one@client.test', references: '<one@client.test>',
            attachments: [['name' => 'plan.png', 'mime' => 'image/png', 'content' => 'PNGDATA', 'inline' => true, 'cid' => 'plan@x'], ['name' => 'brief.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'content' => 'DOCX']]));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();
        $this->assertSame(1, MailThread::count());
        $second = MailMessage::where('message_id', 'two@client.test')->firstOrFail();

        $page = Livewire::actingAs($sender)->test(Inbox::class)->call('openThread', $thread->id)
            ->assertSee('2 messages')
            ->assertSee('brief.docx')
            ->assertDontSee('plan.png') // an inline picture is part of the message, not a file to download
            ->assertSee('Reply all');
        $this->assertStringContainsString('/mail/read/inline/' . $second->id . '/plan@x', $this->srcdoc($page->html()));
        $page->assertSee('Open all 2')->call('expandAll')->assertSee('First message')->assertDontSee('Open all 2');
    }

    public function test_search_in_hqs_copy_then_on_the_server_and_older_mail(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Wanjiru <wanjiru@logistics.ke>', subject: 'Timeline', text: 'Two questions on the handover'));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ola <ola@pharma.ng>', subject: 'Month-end files', text: 'Attached are the files'));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ola <ola@pharma.ng>', subject: 'Handover 2024', text: 'The old handover plan', date: now()->subMonths(15)->toRfc2822String()));
        $this->server->deliver(self::BOX, 'INBOX.Trash', FakeMailServer::message(subject: 'Handover draft binned'));
        MailSync::make()->account($account, null, true);

        $page = Livewire::actingAs($sender)->withQueryParams(['box' => $account->id])->test(Inbox::class)
            ->set('search', 'handover')
            ->assertSee('Timeline')
            ->assertDontSee('Month-end files')
            ->assertDontSee('Handover draft binned') // Trash is searched only from inside it
            ->assertDontSee('Handover 2024')         // older than HQ's copy
            ->assertSee('Search older mail on the server');

        $page->set('search', 'from:ola')->assertSee('Month-end files')->assertDontSee('Timeline');

        $page->set('search', 'handover')->call('searchServer')
            ->assertSee('On the mail server: 2 results in Inbox')
            ->assertSee('Handover 2024');
        $older = collect($page->get('live')['items'])->firstWhere('subject', 'Handover 2024');
        $page->call('openLive', $older['uid'])->assertSee('The old handover plan')->assertSee('Read from the mail server');

        Livewire::actingAs($sender)->withQueryParams(['box' => $account->id])->test(Inbox::class)
            ->assertSee('Load older mail')
            ->call('loadOlder')
            ->assertSee('Older than 12 months, from the mail server')
            ->assertSee('Handover 2024');

        Livewire::actingAs($sender)->withQueryParams(['box' => $account->id, 'q' => 'nothingmatchesthis'])->test(Inbox::class)
            ->assertSee("Nothing in HQ's copy matches", false);
    }

    public function test_events_from_the_other_parts_refresh_the_page(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Hello there', messageId: 'h1@client.test'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $page = Livewire::actingAs($sender)->test(Inbox::class)->call('openThread', $thread->id);

        // A reply sent from HQ lands in Sent and joins the conversation; mail-sent shows it open
        $this->server->deliver(self::BOX, 'INBOX.Sent', FakeMailServer::message(from: 'JBS Praxis Intelligence <' . self::BOX . '>', to: 'ada@client.test', subject: 'Re: Hello there',
            text: 'Thanks, Ada. We are on it.', inReplyTo: 'h1@client.test', references: '<h1@client.test>'), ['\\Seen']);
        MailSync::make()->account($account, null, true);
        $page->dispatch('mail-sent', accountId: $account->id, threadId: $thread->id, outboxId: 'abc')->assertSee('Thanks, Ada. We are on it.');

        $page->dispatch('mail-thread-changed', threadId: $thread->id)->assertSet('thread', $thread->id);
        $page->dispatch('mail-refresh', accountId: $account->id)->assertOk();

        MailThread::query()->delete();
        $page->dispatch('mail-thread-changed', threadId: $thread->id)->assertSet('thread', null);
    }

    public function test_the_page_renders_on_the_hq_host_with_the_mount_points(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Mounted'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $html = $this->actingAs($sender)->get('https://' . Hosts::hq() . '/mail?box=' . $account->id . '&thread=' . $thread->id)
            ->assertOk()->assertSee('Mounted')->getContent();

        $view = file_get_contents(resource_path('views/livewire/hq/mail/inbox.blade.php'));
        foreach (['<livewire:hq.mail.composer />', '<livewire:hq.mail.thread-summary :thread-id=', '<livewire:hq.mail.thread-team :thread-id=', '<livewire:hq.mail.ask-mail :account-id='] as $mount) {
            $this->assertStringContainsString($mount, $view);
        }
        $this->assertStringContainsString('mail-compose', $html);
    }

    /** @return array{0: MailAccount, 1: User, 2: User} */
    private function shared(): array
    {
        $account = $this->mailbox(self::BOX);
        $sender = $this->staff('sen', ['sales']);
        $reader = $this->staff('rea', ['engineer']);
        $this->member($account, $sender, 'send');
        $this->member($account, $reader, 'read');

        return [$account, $sender, $reader];
    }

    private function owner(): User
    {
        return $this->staff('own', ['owner']);
    }

    /** The reader frame's document (srcdoc, decoded) */
    private function srcdoc(string $html): string
    {
        preg_match_all('/srcdoc="([^"]*)"/', $html, $m);

        return html_entity_decode(implode("\n", $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
