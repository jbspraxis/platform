<?php

namespace Tests\Feature\Mail;

use App\Events\Mail\MailReceived;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** Copying a mailbox into HQ (docs/MAIL.md, "Sync"), against the in-memory server. */
class MailSyncTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    public function test_first_copy_reads_folders_messages_bodies_attachments_and_threads(): void
    {
        $account = $this->mailbox(self::BOX);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(
            from: '"Okafor, Ada" <ada@client.test>', subject: 'Proposal for Q4', text: "Hello team,\nPlease see attached.",
            html: '<p>Hello team,</p><p>Please see attached.</p>', messageId: 'p1@client.test', date: now()->subDays(3)->toRfc2822String(),
            attachments: [['name' => 'brief.pdf', 'mime' => 'application/pdf', 'content' => '%PDF-1.4 brief']],
        ), ['\\Seen']);
        $this->server->deliver(self::BOX, 'INBOX.Sent', FakeMailServer::message(
            from: 'JBS Praxis Intelligence <intelligence@jbspraxis.com>', to: 'ada@client.test', subject: 'Re: Proposal for Q4',
            text: 'Thanks Ada, we will reply by Friday.', messageId: 'r1@jbspraxis.com', inReplyTo: 'p1@client.test',
            references: '<p1@client.test>', date: now()->subDays(2)->toRfc2822String(),
        ), ['\\Seen']);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(
            from: 'Ada <ada@client.test>', subject: 'RE: Proposal for Q4', text: "Great, thanks.\n\nOn Tue, Ada wrote:\n> old",
            messageId: 'p2@client.test', inReplyTo: 'r1@jbspraxis.com', date: now()->subDay()->toRfc2822String(),
        ));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Unrelated', messageId: 'u1@x.test'));
        // Older than 12 months: stays on the server only
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Ancient', date: now()->subMonths(13)->toRfc2822String()));

        $result = MailSync::make()->account($account);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(4, $result['added']);
        $this->assertSame(0, $result['received'], 'The first copy never counts as new mail');

        $roles = MailFolder::where('account_id', $account->id)->pluck('role', 'path')->all();
        $this->assertEqualsCanonicalizing(['INBOX' => 'inbox', 'INBOX.Sent' => 'sent', 'INBOX.Drafts' => 'drafts', 'INBOX.Junk' => 'spam', 'INBOX.Trash' => 'trash'], $roles);

        $proposal = MailMessage::where('message_id', 'p1@client.test')->firstOrFail();
        $this->assertSame('Okafor, Ada', $proposal->from_name);
        $this->assertSame('cached', $proposal->body_state);
        $this->assertStringContainsString('Please see attached.', $proposal->body_text);
        $this->assertStringContainsString('<p>Please see attached.</p>', $proposal->body_html);
        $this->assertTrue($proposal->is_seen);
        $this->assertTrue($proposal->has_attachments);
        $this->assertSame(['brief.pdf'], $proposal->attachments->pluck('filename')->all());

        // Three messages across Inbox and Sent are one conversation
        $thread = MailThread::findOrFail($proposal->thread_id);
        $this->assertSame(3, $thread->message_count);
        $this->assertSame(1, $thread->unread_count);
        $this->assertSame('Great, thanks.', $thread->snippet);
        $this->assertSame('Proposal for Q4', $thread->subject);
        $this->assertTrue($thread->has_attachments);
        $this->assertSame(['ada@client.test', 'intelligence@jbspraxis.com'], array_column($thread->participants, 'email'));
        $this->assertTrue(MailMessage::where('message_id', 'r1@jbspraxis.com')->value('is_outbound'));

        $this->assertSame(2, MailThread::where('account_id', $account->id)->count());
        $this->assertFalse(MailMessage::where('subject', 'Ancient')->exists());
        $inbox = MailFolder::where('account_id', $account->id)->where('role', 'inbox')->first();
        $this->assertNotNull($inbox->initial_synced_at);
        $this->assertSame(3, $inbox->total_count);
        $this->assertSame(2, $inbox->unread_count);
        $this->assertSame(4, $inbox->server_total);
    }

    public function test_later_runs_add_new_mail_raise_mail_received_and_follow_flags_and_deletions(): void
    {
        $account = $this->mailbox(self::BOX);
        $keep = $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'First', messageId: 'a@x.test'));
        $gone = $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Second', messageId: 'b@x.test'));
        MailSync::make()->account($account);

        Event::fake([MailReceived::class]);
        $this->server->setFlags(self::BOX, 'INBOX', $keep, ['\\Seen', '\\Flagged']);
        $this->server->remove(self::BOX, 'INBOX', $gone);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Mireille <m@client.test>', subject: 'Third', messageId: 'c@x.test'));
        // Mail the mailbox sent itself never counts as received
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: self::BOX, subject: 'Note to self', messageId: 'd@x.test'));

        $result = MailSync::make()->account($account);

        $this->assertSame(2, $result['added']);
        $this->assertSame(1, $result['removed']);
        $this->assertSame(1, $result['updated']);
        $first = MailMessage::where('message_id', 'a@x.test')->first();
        $this->assertTrue($first->is_seen);
        $this->assertTrue($first->is_flagged);
        $this->assertFalse(MailMessage::where('message_id', 'b@x.test')->exists());
        $this->assertFalse(MailThread::where('subject', 'Second')->exists(), 'An emptied thread nobody worked on goes');

        Event::assertDispatched(MailReceived::class, function (MailReceived $e) use ($account) {
            $third = MailMessage::where('message_id', 'c@x.test')->first();

            return $e->accountId === $account->id && $e->messageIds === [$third->id] && $e->threadIds === [$third->thread_id];
        });
    }

    public function test_a_renumbered_folder_is_read_again(): void
    {
        $account = $this->mailbox(self::BOX);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'One', messageId: 'one@x.test'));
        MailSync::make()->account($account);
        $before = MailMessage::where('message_id', 'one@x.test')->value('id');

        $this->server->renumber(self::BOX, 'INBOX');
        MailSync::make()->account($account);

        $this->assertSame(1, MailMessage::where('message_id', 'one@x.test')->count());
        $this->assertNotSame($before, MailMessage::where('message_id', 'one@x.test')->value('id'));
    }

    public function test_threads_join_by_subject_and_merge_when_a_link_turns_up(): void
    {
        $account = $this->mailbox(self::BOX);
        // A reply without headers linking it, same subject and same person: joins
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'ada@client.test', subject: 'Budget 2027', messageId: 'b1@x.test', date: now()->subDays(5)->toRfc2822String()));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'ada@client.test', subject: 'Re: Budget 2027', messageId: 'b2@x.test', date: now()->subDays(4)->toRfc2822String()));
        // Same subject, a stranger, not a reply: its own thread
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'other@else.test', subject: 'Budget 2027', messageId: 'b3@x.test', date: now()->subDays(3)->toRfc2822String()));
        MailSync::make()->account($account);

        $t1 = MailMessage::where('message_id', 'b1@x.test')->value('thread_id');
        $this->assertSame($t1, MailMessage::where('message_id', 'b2@x.test')->value('thread_id'));
        $t3 = MailMessage::where('message_id', 'b3@x.test')->value('thread_id');
        $this->assertNotSame($t1, $t3);

        // Teamwork on the second thread survives the merge
        MailThread::where('id', $t3)->update(['assigned_to' => '00000000-0000-4000-8000-000000000001']);
        DB::table('mail_thread_notes')->insert(['thread_id' => $t3, 'user_id' => '00000000-0000-4000-8000-000000000001', 'body' => 'Call them', 'created_at' => now(), 'updated_at' => now()]);

        // A message that references both joins them into the older thread
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'ada@client.test', subject: 'Re: Budget 2027', messageId: 'b4@x.test', references: '<b1@x.test> <b3@x.test>', inReplyTo: 'b3@x.test'));
        MailSync::make()->account($account);

        $this->assertSame(1, MailMessage::whereIn('message_id', ['b1@x.test', 'b2@x.test', 'b3@x.test', 'b4@x.test'])->distinct()->count('thread_id'));
        $merged = MailThread::find(min($t1, $t3));
        $this->assertSame(4, $merged->message_count);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $merged->assigned_to);
        $this->assertSame(1, DB::table('mail_thread_notes')->where('thread_id', $merged->id)->count());
        $this->assertNull(MailThread::find(max($t1, $t3)));
    }

    public function test_a_refused_password_stops_sync_and_tells_the_managers_once(): void
    {
        $owner = $this->staff('own', ['owner']);
        $account = $this->mailbox(self::BOX);
        $this->member($account, $owner, 'manage');
        $this->server->setPassword(self::BOX, 'changed-in-cpanel');

        $result = MailSync::make()->account($account);
        $this->assertSame('error', $result['status']);
        $this->assertSame('needs_reconnect', $account->fresh()->status);
        $this->assertSame(1, DB::table('notifications')->where('user_id', $owner->id)->where('title', 'Mail needs a password')->count());

        $this->assertSame('skipped', MailSync::make()->account($account->fresh())['status']);
        $this->assertSame(1, DB::table('notifications')->where('user_id', $owner->id)->count());
    }

    public function test_the_ai_summary_is_kept_until_new_mail(): void
    {
        $account = $this->mailbox(self::BOX);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Plan', messageId: 's1@x.test', date: now()->subHour()->toRfc2822String()));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();
        $thread->update(['ai_summary' => ['gist' => 'A plan'], 'ai_summary_at' => now()]);

        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Re: Plan', messageId: 's2@x.test', inReplyTo: 's1@x.test', date: now()->addMinute()->toRfc2822String()));
        MailSync::make()->account($account);

        $this->assertNull($thread->fresh()->ai_summary);
    }
}
