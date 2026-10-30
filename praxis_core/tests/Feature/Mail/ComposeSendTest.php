<?php

namespace Tests\Feature\Mail;

use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Support\Mail\Compose\Outbox;
use App\Support\Mail\Compose\OutboxSender;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** Writing and sending (PR 3, docs/MAIL.md): drafts, the outbox, mail:send-due, replies, forwards, failures. */
class ComposeSendTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
        Storage::fake('local');
    }

    public function test_a_reply_goes_from_the_mailbox_with_threading_signature_and_quote_and_is_credited(): void
    {
        [$account, $sender] = $this->shared();
        $uid = $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(
            from: 'Ada Client <ada@client.test>', subject: 'Proposal', text: "Can you send the proposal?\nThanks", messageId: 'p1@client.test',
        ));
        MailSync::make()->account($account);
        $original = MailMessage::firstOrFail();

        $outbox = Outbox::make()->start($sender, 'reply', null, $original);
        $this->assertSame([['name' => 'Ada Client', 'email' => 'ada@client.test']], $outbox->payload['to']);
        $this->assertSame('Re: Proposal', $outbox->payload['subject']);
        $this->assertSame('slim', $outbox->payload['signature'], 'Replies carry the short signature');
        $this->assertSame($original->thread_id, $outbox->thread_id);

        Outbox::make()->save($sender, $outbox, ['html' => '<p onclick="x()">Here it is.<script>alert(1)</script></p>']);
        $this->assertSame('<p>Here it is.</p>', $outbox->fresh()->payload['html'], 'The editor HTML is cleaned');

        Outbox::make()->queue($sender, $outbox);
        $this->assertSame(0, OutboxSender::make()->due()['sent'], 'Nothing goes during the undo window');

        $this->travel(11)->seconds();
        $this->artisan('mail:send-due')->assertSuccessful();

        $this->assertCount(1, $this->server->sent);
        $email = $this->server->sent[0]['email'];
        $raw = $this->server->sent[0]['raw'];
        $this->assertSame('"JBS Praxis Intelligence" <intelligence@jbspraxis.com>', $email->getFrom()[0]->toString());
        $this->assertStringContainsString('In-Reply-To: <p1@client.test>', $raw);
        $this->assertStringContainsString('References: <p1@client.test>', $raw);
        $this->assertStringContainsString('Sen Test', $email->getHtmlBody(), 'The person shows in their signature');
        $this->assertStringContainsString(self::BOX, $email->getTextBody(), 'The signature carries the shared address');
        $this->assertStringContainsString('> Can you send the proposal?', $email->getTextBody(), 'The original is quoted');
        $this->assertStringContainsString('wrote:', $email->getHtmlBody());

        $outbox->refresh();
        $this->assertSame('sent', $outbox->status);
        $this->assertSame(MailMessage::hashId($outbox->message_id), $outbox->message_id_hash);
        $this->assertContains('\\Answered', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags']);
        $this->assertTrue($original->fresh()->is_answered);

        // Sent was read straight after: the copy is in HQ, in the conversation, credited to the sender
        $copy = MailMessage::where('message_id_hash', $outbox->message_id_hash)->first();
        $this->assertNotNull($copy, 'The Sent copy is in HQ without waiting for the next sync');
        $this->assertSame($sender->id, $copy->sent_by);
        $this->assertSame($original->thread_id, $copy->thread_id);
        $this->assertTrue(DB::table('mail_thread_activity')->where('thread_id', $original->thread_id)->where('type', 'sent')->exists());
    }

    public function test_undo_brings_it_back_as_a_draft_and_nothing_is_sent(): void
    {
        [$account, $sender] = $this->shared();
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Hello');
        Outbox::make()->queue($sender, $outbox);

        $this->assertTrue(Outbox::make()->undo($sender, $outbox));
        $this->travel(1)->minutes();
        OutboxSender::make()->due();

        $this->assertSame('draft', $outbox->fresh()->status);
        $this->assertCount(0, $this->server->sent);
    }

    public function test_scheduled_mail_waits_for_its_time(): void
    {
        [$account, $sender] = $this->shared();
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Monday news');
        Outbox::make()->queue($sender, $outbox, now()->addHour());

        $this->travel(30)->minutes();
        OutboxSender::make()->due();
        $this->assertCount(0, $this->server->sent);

        $this->travel(31)->minutes();
        OutboxSender::make()->due();
        $this->assertCount(1, $this->server->sent);
        $this->assertSame('sent', $outbox->fresh()->status);

        $this->expectException(ValidationException::class);
        Outbox::make()->queue($sender, $this->draftTo($sender, $account, 'ada@client.test', 'Past'), now()->subMinute());
    }

    public function test_a_temporary_failure_is_tried_again_and_a_permanent_one_tells_the_sender(): void
    {
        [$account, $sender] = $this->shared();
        MailSync::make()->account($account);
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Try again');
        Outbox::make()->queue($sender, $outbox);
        $this->travel(11)->seconds();

        $this->server->smtpFailure = 'temporary';
        OutboxSender::make()->due();
        $outbox->refresh();
        $this->assertSame('queued', $outbox->status);
        $this->assertSame(1, (int) $outbox->attempts);
        $this->assertNotNull($outbox->last_error);
        $this->assertTrue($outbox->send_at->gt(now()->addSeconds(50)), 'The next try waits a minute');

        $this->travel(61)->seconds();
        OutboxSender::make()->due();
        $this->assertSame('sent', $outbox->fresh()->status);
        $this->assertCount(1, $this->server->sent);

        $refused = $this->draftTo($sender, $account, 'nobody@client.test', 'Refused');
        Outbox::make()->queue($sender, $refused);
        $this->travel(11)->seconds();
        $this->server->smtpFailure = 'rejected';
        OutboxSender::make()->due();
        $this->assertSame('failed', $refused->fresh()->status);
        $notice = DB::table('notifications')->where('user_id', $sender->id)->where('title', 'Mail not sent')->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Refused', $notice->message);
        $this->assertSame('/mail/outbox', $notice->link_url);
    }

    public function test_temporary_failures_stop_after_the_last_retry(): void
    {
        [$account, $sender] = $this->shared();
        config(['mail_compose.retry_minutes' => [1]]);
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Busy');
        Outbox::make()->queue($sender, $outbox);
        foreach ([11, 61] as $seconds) {
            $this->travel($seconds)->seconds();
            $this->server->smtpFailure = 'temporary';
            OutboxSender::make()->due();
        }
        $this->assertSame('failed', $outbox->fresh()->status);
        $this->assertSame(2, (int) $outbox->fresh()->attempts);
    }

    public function test_a_message_stuck_while_sending_is_never_sent_twice(): void
    {
        [$account, $sender] = $this->shared();
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Stuck');
        $outbox->forceFill(['status' => 'sending'])->save();
        MailOutbox::where('id', $outbox->id)->update(['updated_at' => now()->subMinutes(20)]);

        $this->assertSame(1, OutboxSender::make()->due()['stuck']);
        $this->assertSame('failed', $outbox->fresh()->status);
        $this->assertCount(0, $this->server->sent);
    }

    public function test_read_only_people_cannot_write_and_nobody_touches_another_persons_draft(): void
    {
        [$account, $sender, $reader] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Hello'));
        MailSync::make()->account($account);

        try {
            Outbox::make()->start($reader, 'reply', null, MailMessage::firstOrFail());
            $this->fail('Read access must not write');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $draft = $this->draftTo($sender, $account, 'ada@client.test', 'Mine');
        $other = $this->staff('oth', ['sales']);
        $this->member($account, $other, 'send');
        $this->expectException(HttpException::class);
        Outbox::make()->save($other, $draft, ['subject' => 'Taken']);
    }

    public function test_reply_all_keeps_everyone_but_the_mailbox_itself(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(
            from: 'Ada <ada@client.test>', to: 'intelligence@jbspraxis.com, Bob <bob@client.test>', cc: 'carol@client.test, ada@client.test', subject: 'Team',
        ));
        MailSync::make()->account($account);

        $outbox = Outbox::make()->start($sender, 'reply_all', null, MailMessage::firstOrFail());

        $this->assertSame(['ada@client.test'], array_column($outbox->payload['to'], 'email'));
        $this->assertSame(['bob@client.test', 'carol@client.test'], array_column($outbox->payload['cc'], 'email'));
    }

    public function test_forwarding_carries_the_originals_attachments_and_uploads_travel_too(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Brief', text: 'See attached',
            attachments: [['name' => 'brief.pdf', 'mime' => 'application/pdf', 'content' => '%PDF-original']]));
        MailSync::make()->account($account);

        $outbox = Outbox::make()->start($sender, 'forward', null, MailMessage::firstOrFail());
        $this->assertSame('Fwd: Brief', $outbox->payload['subject']);
        $this->assertSame('full', $outbox->payload['signature']);
        $this->assertSame(['brief.pdf'], array_column($outbox->payload['attachments'], 'name'));

        Outbox::make()->save($sender, $outbox, ['to' => ['Partner <partner@firm.test>']]);
        $upload = Outbox::make()->addUpload($sender, $outbox->fresh(), UploadedFile::fake()->createWithContent('notes.txt', 'My notes'));
        Storage::disk('local')->assertExists($upload['path']);

        Outbox::make()->queue($sender, $outbox->fresh());
        $this->travel(11)->seconds();
        OutboxSender::make()->due();

        $email = $this->server->sent[0]['email'];
        $files = collect($email->getAttachments())->mapWithKeys(fn ($a) => [$a->getFilename() => $a->getBody()]);
        $this->assertSame('%PDF-original', $files['brief.pdf']);
        $this->assertSame('My notes', $files['notes.txt']);
        $this->assertStringContainsString('Forwarded message', $email->getTextBody());
        Storage::disk('local')->assertMissing($upload['path']);
    }

    public function test_attachments_over_the_limit_are_refused(): void
    {
        [$account, $sender] = $this->shared();
        config(['mail_compose.max_attachments_mb' => 1]);
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Big');
        Outbox::make()->addUpload($sender, $outbox, UploadedFile::fake()->create('a.pdf', 700));

        $this->expectException(ValidationException::class);
        Outbox::make()->addUpload($sender, $outbox->fresh(), UploadedFile::fake()->create('b.pdf', 700));
    }

    public function test_drafts_are_kept_in_the_servers_drafts_and_removed_when_sent(): void
    {
        [$account, $sender] = $this->shared();
        MailSync::make()->account($account);
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Draft one', 'bcc@client.test');

        $this->assertTrue(Outbox::make()->saveServerDraft($sender, $outbox));
        Outbox::make()->save($sender, $outbox->fresh(), ['subject' => 'Draft two']);
        Outbox::make()->saveServerDraft($sender, $outbox->fresh());

        $drafts = $this->server->folder(self::BOX, 'INBOX.Drafts');
        $this->assertCount(1, $drafts, 'The new copy replaces the old one');
        $copy = array_values($drafts)[0];
        $this->assertContains('\\Draft', $copy['flags']);
        $this->assertStringContainsString('Subject: Draft two', $copy['raw']);
        $this->assertStringContainsString('<' . $outbox->message_id . '>', $copy['raw'], 'Same Message-ID as the message that will go');

        // Opening the server's copy (as PR 2 does from Drafts) finds HQ's own draft
        MailSync::make()->account($account, null, true);
        $serverDraft = MailMessage::whereHas('folder', fn ($q) => $q->where('role', 'drafts'))->firstOrFail();
        $this->assertSame($outbox->id, Outbox::make()->fromServerDraft($sender, $serverDraft)->id);

        Outbox::make()->queue($sender, $outbox->fresh());
        $this->travel(11)->seconds();
        OutboxSender::make()->due();
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX.Drafts'));
        $this->assertFalse(MailMessage::whereHas('folder', fn ($q) => $q->where('role', 'drafts'))->exists());
    }

    public function test_a_draft_written_in_another_mail_app_opens_as_a_new_draft(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX.Drafts', FakeMailServer::message(
            from: self::BOX, to: 'Ada <ada@client.test>', subject: 'Half done', text: 'Started in Roundcube',
        ), ['\\Draft', '\\Seen']);
        MailSync::make()->account($account, null, true);
        $draft = MailMessage::firstOrFail();

        $outbox = Outbox::make()->fromServerDraft($sender, $draft);

        $this->assertSame('Half done', $outbox->payload['subject']);
        $this->assertSame(['ada@client.test'], array_column($outbox->payload['to'], 'email'));
        $this->assertStringContainsString('Started in Roundcube', $outbox->payload['html']);
        $this->assertSame('none', $outbox->payload['signature']);
        $this->assertSame((int) $draft->uid, $outbox->payload['server_draft']['uid']);
    }

    public function test_discarding_removes_the_draft_everywhere(): void
    {
        [$account, $sender] = $this->shared();
        MailSync::make()->account($account);
        $outbox = $this->draftTo($sender, $account, 'ada@client.test', 'Bin me');
        Outbox::make()->saveServerDraft($sender, $outbox);

        Outbox::make()->discard($sender, $outbox->fresh());

        $this->assertNull(MailOutbox::find($outbox->id));
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX.Drafts'));
    }

    // ------------------------------------------------------------------

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

    private function draftTo($user, $account, string $to, string $subject, ?string $bcc = null): MailOutbox
    {
        $outbox = Outbox::make()->start($user, 'new', $account);
        Outbox::make()->save($user, $outbox, ['to' => [$to], 'bcc' => $bcc ? [$bcc] : [], 'subject' => $subject, 'html' => '<p>Hello there.</p>']);

        return $outbox->fresh();
    }
}
