<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\Inbox;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Support\Mail\MailActions;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Livewire\Livewire;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/**
 * Opening mail without waiting on the mail server (docs/MAIL.md, owner decision 6 unchanged): all the messages a
 * conversation opens are marked read in one go (one server session, not one per message), and where PHP can send the
 * response first, the server's \Seen is written just after it, with HQ's copy going back to unread if the server refuses.
 */
class ReadOpenSpeedTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_opening_a_conversation_marks_every_unread_message_with_one_server_session(): void
    {
        [$account, , $reader] = $this->shared();
        $uids = $this->conversation(3);
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $before = $this->server->sessions;
        Livewire::actingAs($reader)->test(Inbox::class)->call('openThread', $thread->id)->assertSee('Opened by');

        $this->assertSame(1, $this->server->sessions - $before, 'One sign-in for the whole conversation');
        foreach ($uids as $uid) {
            $this->assertContains('\\Seen', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags']);
        }
        $this->assertSame(3, MailMessage::where('is_seen', true)->where('seen_by', $reader->id)->count());
        $this->assertSame(0, $thread->fresh()->unread_count);
        $this->assertSame(0, MailFolder::where('role', 'inbox')->value('unread_count'));
    }

    public function test_after_the_response_hq_shows_it_read_at_once_and_the_server_follows(): void
    {
        config(['mail_client.mark_read_after_response' => true]);
        [$account, , $reader] = $this->shared();
        $uids = $this->conversation(2);
        MailSync::make()->account($account);
        $messages = MailMessage::orderBy('id')->get();

        $before = $this->server->sessions;
        MailActions::make()->openedMany($reader, $messages);

        // HQ's copy, the counts and "Opened by" before the response is sent; the server not yet
        $this->assertSame(2, MailMessage::where('is_seen', true)->where('seen_by', $reader->id)->count());
        $this->assertSame(0, MailThread::firstOrFail()->unread_count);
        $this->assertSame(0, MailFolder::where('role', 'inbox')->value('unread_count'));
        $this->assertSame($before, $this->server->sessions, 'Nothing waits on the mail server');
        $this->assertNotContains('\\Seen', $this->server->folder(self::BOX, 'INBOX')[$uids[0]]['flags']);

        app(DeferredCallbackCollection::class)->invoke();

        $this->assertSame(1, $this->server->sessions - $before);
        foreach ($uids as $uid) {
            $this->assertContains('\\Seen', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags']);
        }
        $this->assertSame(2, MailMessage::where('is_seen', true)->count());
    }

    public function test_after_the_response_a_refusal_puts_hq_back_to_unread(): void
    {
        config(['mail_client.mark_read_after_response' => true]);
        [$account, , $reader] = $this->shared();
        $uids = $this->conversation(2);
        MailSync::make()->account($account);

        MailActions::make()->openedMany($reader, MailMessage::orderBy('id')->get());
        $this->assertSame(2, MailMessage::where('is_seen', true)->count());

        $this->server->setPassword(self::BOX, 'changed-on-the-server');
        app(DeferredCallbackCollection::class)->invoke();

        // As when the server came first: unread in HQ and on the server, so the two agree; who opened it stays
        $this->assertSame(0, MailMessage::where('is_seen', true)->count());
        $this->assertSame(2, MailMessage::where('seen_by', $reader->id)->count());
        $this->assertSame(2, MailThread::firstOrFail()->unread_count);
        $this->assertSame(2, MailFolder::where('role', 'inbox')->value('unread_count'));
        $this->assertNotContains('\\Seen', $this->server->folder(self::BOX, 'INBOX')[$uids[1]]['flags']);
    }

    public function test_the_reader_keeps_the_sanitised_message_between_renders_and_follows_its_pictures_choice(): void
    {
        [$account, $sender] = $this->shared();
        $html = '<p>Hello <img src="https://tracker.example/pixel.png"> team</p>';
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Newsletter', html: $html, text: 'Hello team'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $page = Livewire::actingAs($sender)->test(Inbox::class)->call('openThread', $thread->id);
        $blocked = $page->html();
        $this->assertStringNotContainsString('tracker.example/pixel.png', $this->frames($blocked), 'Remote pictures held back');

        // The body changes in the database: the kept view is what the reader shows on the next render
        MailMessage::query()->update(['body_html' => '<p>Changed</p>']);
        $this->assertStringContainsString('Hello', $this->frames($page->call('tick')->html()));

        // Loading the pictures is another view of the message, made afresh
        $id = MailMessage::firstOrFail()->id;
        $this->assertStringContainsString('Changed', $this->frames($page->call('loadImages', $id)->html()));
    }

    public function test_the_minute_poll_draws_nothing_until_a_mailbox_changes(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'First of the day'));
        MailSync::make()->account($account);

        $page = Livewire::actingAs($sender)->test(Inbox::class)->assertSee('First of the day');

        // A write that no counter or conversation saw (straight to the table): the poll skips the render, so it isn't drawn
        MailThread::query()->update(['subject' => 'Renamed behind its back']);
        $page->call('tick')->assertSee('First of the day');

        // New mail through sync changes the mailbox's stamp: the next poll draws the list again
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Second of the day'));
        MailSync::make()->account($account);
        $page->call('tick')->assertSee('Second of the day')->assertSee('Renamed behind its back');

        // And every fifth quiet poll draws anyway, for whatever a stamp can't see
        MailThread::query()->update(['subject' => 'Seen on the fifth']);
        foreach (range(1, 4) as $quiet) {
            $page->call('tick')->assertDontSee('Seen on the fifth');
        }
        $page->call('tick')->assertSee('Seen on the fifth');
    }

    public function test_the_team_bar_heartbeat_keeps_presence_and_draws_only_when_something_changed(): void
    {
        [$account, $sender, $reader] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Contract'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $bar = Livewire::actingAs($sender)->test(\App\Livewire\Hq\Mail\ThreadTeam::class, ['threadId' => $thread->id])->assertSee('Assigned to');
        $drawn = $bar->get('drawn');
        $this->assertNotSame('', $drawn);

        // Nothing changed: presence is kept alive, the bar isn't drawn again
        \Illuminate\Support\Facades\DB::table('mail_presence')->delete();
        $bar->call('heartbeat')->assertSet('drawn', $drawn);
        $this->assertSame('viewing', \Illuminate\Support\Facades\DB::table('mail_presence')->where('user_id', $sender->id)->value('state'));

        // A colleague opens it: the next heartbeat draws them
        \App\Support\Mail\Team\MailPresence::touch($reader, $thread);
        $bar->call('heartbeat')->assertSee($reader->first_name);
        $this->assertNotSame($drawn, $bar->get('drawn'));

        // Read level doesn't get (or pay for) the list of people to assign to
        Livewire::actingAs($reader)->test(\App\Livewire\Hq\Mail\ThreadTeam::class, ['threadId' => $thread->id])
            ->assertViewHas('assignees', fn ($a) => $a->isEmpty());
    }

    /** @return list<int> UIDs of a conversation of $n unread messages in the shared Inbox */
    private function conversation(int $n): array
    {
        $uids = [$this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Budget', messageId: 'root@client.test', date: now()->subHours(5)->toRfc2822String()))];
        for ($i = 1; $i < $n; $i++) {
            $uids[] = $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Re: Budget', messageId: "r{$i}@client.test",
                inReplyTo: 'root@client.test', references: '<root@client.test>', date: now()->subHours(5 - $i)->toRfc2822String()));
        }

        return $uids;
    }

    /** @return array{0: \App\Models\Mail\MailAccount, 1: \App\Models\User, 2: \App\Models\User} */
    private function shared(): array
    {
        $account = $this->mailbox(self::BOX);
        $sender = $this->staff('sen', ['sales']);
        $reader = $this->staff('rea', ['engineer']);
        $this->member($account, $sender, 'send');
        $this->member($account, $reader, 'read');

        return [$account, $sender, $reader];
    }

    /** The reader frames' documents (srcdoc, decoded) */
    private function frames(string $html): string
    {
        preg_match_all('/srcdoc="([^"]*)"/', $html, $m);

        return html_entity_decode(implode("\n", $m[1]), ENT_QUOTES | ENT_HTML5);
    }
}
