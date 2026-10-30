<?php

namespace Tests\Feature\Mail;

use App\Events\Mail\MailReceived;
use App\Events\Mail\MailThreadsMerged;
use App\Listeners\Mail\OnMailReceived;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Support\Mail\Sync\MailSync;
use App\Support\Pwa\PushCategories;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/**
 * New-mail alerts (owner decision 9: everything to everyone who can read the mailbox), reopening Done
 * conversations on a client reply, and following merged conversations. Driven through real sync runs, so
 * Laravel's event discovery of app/Listeners/Mail is tested too.
 */
class TeamNotificationsTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_every_new_message_goes_to_everyone_who_can_read_the_mailbox_and_nobody_else(): void
    {
        $owner = $this->staff('own', ['owner']);
        $reader = $this->staff('rdr', ['sales']);
        $sender = $this->staff('snd', ['sales']);
        $outsider = $this->staff('out', ['sales']);
        $leaver = $this->staff('old', ['sales']);
        $box = $this->mailbox(self::BOX);
        $this->member($box, $reader, 'read');
        $this->member($box, $sender, 'send');
        $this->member($box, $leaver, 'read');
        $leaver->forceFill(['status' => 'Suspended'])->save();

        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Old news', messageId: 'old@x.test'));
        MailSync::make()->account($box);
        $this->assertSame(0, DB::table('notifications')->count(), 'The first copy alerts nobody');

        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: '"Okafor, Ada" <ada@client.test>', subject: 'Proposal for Q4', text: 'Hello', messageId: 'p1@client.test'));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'mireille@client.test', subject: '', text: "Bonjour l'équipe, voici le devis signé pour le projet de Lagos, merci", messageId: 'p2@client.test'));
        MailSync::make()->account($box);

        $bell = DB::table('notifications')->orderBy('created_at')->get();
        $this->assertCount(6, $bell, 'Two messages × three readers (owner, reader, sender)');
        $this->assertEqualsCanonicalizing([$owner->id, $reader->id, $sender->id], $bell->pluck('user_id')->unique()->values()->all());
        $this->assertFalse($bell->contains('user_id', $outsider->id));
        $this->assertFalse($bell->contains('user_id', $leaver->id));

        $ada = $bell->firstWhere('title', 'New mail: Okafor, Ada');
        $this->assertNotNull($ada);
        $this->assertSame('Proposal for Q4 · intelligence@jbspraxis.com', $ada->message);
        $this->assertSame('Message', $ada->type);
        $thread = MailMessage::where('message_id', 'p1@client.test')->value('thread_id');
        $this->assertSame('/mail?box=' . $box->id . '&thread=' . $thread, $ada->link_url);
        $this->assertSame('mail', PushCategories::forNotification($ada->link_url, $ada->type), 'Mail push can be muted on its own');

        $noSubject = $bell->firstWhere('title', 'New mail: mireille@client.test');
        $this->assertStringStartsWith("Bonjour l'équipe, voici le devis", $noSubject->message);
    }

    public function test_personal_mailbox_alerts_only_its_person_and_not_mail_they_wrote_in_hq(): void
    {
        $this->staff('own', ['owner']);
        $me = $this->staff('me', ['sales']);
        $colleague = $this->staff('col', ['sales']);
        $mine = $this->mailbox('me@jbspraxis.com', 'personal', $me);
        $box = $this->mailbox(self::BOX);
        $this->member($box, $colleague, 'send');
        MailSync::make()->account($mine);
        MailSync::make()->account($box);

        // My colleague wrote to me from the shared mailbox in HQ (PR 3's outbox row), and a client wrote to the shared mailbox
        DB::table('mail_outbox')->insert([
            'id' => (string) Str::uuid(), 'account_id' => $box->id, 'user_id' => $colleague->id, 'kind' => 'new', 'status' => 'sent',
            'payload' => '{}', 'message_id' => 'hq1@jbspraxis.com', 'message_id_hash' => MailMessage::hashId('hq1@jbspraxis.com'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->server->deliver('me@jbspraxis.com', 'INBOX', FakeMailServer::message(from: 'JBS Praxis Intelligence <intelligence@jbspraxis.com>', to: 'me@jbspraxis.com', subject: 'Handover', messageId: 'hq1@jbspraxis.com'));
        $this->server->deliver('me@jbspraxis.com', 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', to: 'me@jbspraxis.com', subject: 'Private', messageId: 'pv@client.test'));
        MailSync::make()->account($mine);

        $this->assertSame(['me' => 2], ['me' => DB::table('notifications')->where('user_id', $me->id)->count()]);
        $this->assertSame(0, DB::table('notifications')->where('user_id', $colleague->id)->count(), 'Nobody else sees a personal mailbox, nor its alerts');
        $this->assertSame('Private', DB::table('notifications')->where('user_id', $me->id)->where('title', 'New mail: Ada')->value('message'));
    }

    public function test_a_client_reply_opens_a_done_conversation_again(): void
    {
        $sender = $this->staff('snd', ['sales']);
        $box = $this->mailbox(self::BOX);
        $this->member($box, $sender, 'send');
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'ada@client.test', subject: 'Invoice', messageId: 'i1@client.test'));
        MailSync::make()->account($box);
        $thread = MailThread::firstOrFail();
        \App\Support\Mail\Team\MailTeam::setStatus($sender, $thread, 'done');

        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'ada@client.test', subject: 'Re: Invoice', text: 'One more question', messageId: 'i2@client.test', inReplyTo: 'i1@client.test', references: '<i1@client.test>'));
        MailSync::make()->account($box);

        $thread->refresh();
        $this->assertSame('open', $thread->status);
        $this->assertNull($thread->done_by);
        $this->assertNull($thread->done_at);
        $reopened = DB::table('mail_thread_activity')->where('thread_id', $thread->id)->where('type', 'reopened')->first();
        $this->assertNotNull($reopened);
        $this->assertNull($reopened->user_id);
        $this->assertSame('reply', json_decode($reopened->data, true)['reason']);
    }

    public function test_listener_failures_never_break_sync(): void
    {
        $box = $this->mailbox(self::BOX);
        Log::spy();
        // Anything going wrong inside the listener (here: reading the mailbox) is logged, never thrown
        MailAccount::retrieved(fn () => throw new \RuntimeException('database went away'));

        (new OnMailReceived())->handle(new MailReceived($box->id, [1], [1]));

        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx) => $m === 'Mail: new-mail alerts failed' && $ctx['account'] === $box->id)->once();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_merged_conversations_keep_a_line_in_the_timeline_and_lose_their_summary(): void
    {
        $box = $this->mailbox(self::BOX);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Workshop', messageId: 'w1@x.test'));
        MailSync::make()->account($box);
        $into = MailThread::firstOrFail();
        $into->forceFill(['ai_summary' => ['gist' => 'old'], 'ai_summary_at' => now()])->save();

        event(new MailThreadsMerged([$into->id + 100], $into->id));

        $into->refresh();
        $this->assertNull($into->ai_summary);
        $this->assertSame('merged', DB::table('mail_thread_activity')->where('thread_id', $into->id)->value('type'));
    }
}
