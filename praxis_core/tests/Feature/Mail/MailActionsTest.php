<?php

namespace Tests\Feature\Mail;

use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Support\Mail\MailActions;
use App\Support\Mail\MailFetch;
use App\Support\Mail\MailTransport;
use App\Support\Mail\Sync\MailSync;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mime\Email;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** Changing mail (server first, then HQ), reading what HQ doesn't keep, and sending (docs/MAIL.md). */
class MailActionsTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_opening_marks_read_for_everyone_and_remembers_who(): void
    {
        [$account, $sender, $reader] = $this->sharedWithPeople();
        $uid = $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Hello'));
        MailSync::make()->account($account);
        $message = MailMessage::firstOrFail();

        MailActions::make()->opened($reader, $message);

        $message->refresh();
        $this->assertTrue($message->is_seen);
        $this->assertSame($reader->id, $message->seen_by);
        $this->assertContains('\\Seen', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags']);
        $this->assertSame(0, MailThread::first()->unread_count);

        MailActions::make()->opened($sender, $message);
        $this->assertSame($reader->id, $message->fresh()->seen_by, 'The first opener is kept');
    }

    public function test_archive_creates_the_folder_moves_and_keeps_the_thread(): void
    {
        [$account, $sender, $reader] = $this->sharedWithPeople();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'File me', messageId: 'f@x.test'));
        MailSync::make()->account($account);
        $message = MailMessage::firstOrFail();
        $threadId = $message->thread_id;

        try {
            MailActions::make()->moveToRole($reader, [$message], 'archive');
            $this->fail('Read access must not file mail');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        MailActions::make()->moveToRole($sender, [$message], 'archive');

        $archive = MailFolder::where('account_id', $account->id)->where('role', 'archive')->firstOrFail();
        $this->assertSame('INBOX.Archive', $archive->path);
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX'));
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX.Archive'));
        $moved = MailMessage::where('message_id', 'f@x.test')->firstOrFail();
        $this->assertSame($archive->id, $moved->folder_id);
        $this->assertSame($threadId, $moved->thread_id);

        // The next sync agrees with what HQ already did
        $result = MailSync::make()->account($account, null, true);
        $this->assertSame(0, $result['added'] + $result['removed']);
    }

    public function test_delete_for_good_only_from_trash(): void
    {
        [$account, $sender] = $this->sharedWithPeople();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Bin me'));
        MailSync::make()->account($account);
        $message = MailMessage::firstOrFail();

        try {
            MailActions::make()->deleteForever($sender, [$message]);
            $this->fail('Only Trash and Spam can be emptied for good');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        MailActions::make()->moveToRole($sender, [$message], 'trash');
        MailActions::make()->deleteForever($sender, MailMessage::all());

        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX.Trash'));
        $this->assertSame(0, MailMessage::count());
    }

    public function test_bodies_attachments_and_older_mail_from_the_server(): void
    {
        [$account, , $reader] = $this->sharedWithPeople();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'With file', text: 'See file',
            attachments: [['name' => 'notes.txt', 'mime' => 'text/plain', 'content' => "line one\nline two"]]));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Old one', text: 'From long ago', date: now()->subMonths(14)->toRfc2822String()));
        MailSync::make()->account($account);
        $message = MailMessage::firstOrFail();

        $this->assertSame('See file', MailFetch::make()->body($reader, $message)['text']);
        $file = MailFetch::make()->attachment($reader, $message->attachments->first());
        $this->assertSame(['filename' => 'notes.txt', 'mime' => 'text/plain', 'content' => "line one\nline two"], $file);

        $inbox = $account->folder('inbox');
        $older = MailFetch::make()->older($reader, $inbox);
        $this->assertSame(['Old one'], array_column($older['items'], 'subject'));
        $this->assertNull($older['next']);
        $live = MailFetch::make()->message($reader, $inbox, $older['items'][0]['uid']);
        $this->assertSame('From long ago', $live['text']);

        $outsider = $this->staff('out', ['hr']);
        $this->expectException(HttpException::class);
        MailFetch::make()->body($outsider, $message);
    }

    public function test_sending_uses_the_mailbox_as_from_and_files_a_copy_in_sent(): void
    {
        [$account, $sender, $reader] = $this->sharedWithPeople();
        MailSync::make()->account($account); // learns the folders

        $result = MailTransport::make()->send($sender, $account, (new Email())->to('ada@client.test')->bcc('boss@jbspraxis.com')->subject('Re: Proposal')->text('Thanks, Ade'));

        $this->assertCount(1, $this->server->sent);
        $sent = $this->server->sent[0]['email'];
        $this->assertSame('"JBS Praxis Intelligence" <intelligence@jbspraxis.com>', $sent->getFrom()[0]->toString());
        $this->assertStringEndsWith('@jbspraxis.com', $result['message_id']);
        $this->assertNotNull($result['sent_uid']);
        $copy = $this->server->folder(self::BOX, 'INBOX.Sent')[$result['sent_uid']];
        $this->assertContains('\\Seen', $copy['flags']);
        $this->assertStringNotContainsString('boss@jbspraxis.com', $copy['raw'], 'Bcc never appears in the message');

        $this->expectException(HttpException::class);
        MailTransport::make()->send($reader, $account, (new Email())->to('x@y.test')->subject('No')->text('No'));
    }

    /** @return array{0: \App\Models\Mail\MailAccount, 1: \App\Models\User, 2: \App\Models\User} */
    private function sharedWithPeople(): array
    {
        $account = $this->mailbox(self::BOX);
        $sender = $this->staff('sen', ['sales']);
        $reader = $this->staff('rea', ['engineer']);
        $this->member($account, $sender, 'send');
        $this->member($account, $reader, 'read');

        return [$account, $sender, $reader];
    }
}
