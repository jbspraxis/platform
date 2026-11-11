<?php

namespace Tests\Feature\Mail;

use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Support\Mail\Compose\Outbox;
use App\Support\Mail\MailVersion;
use App\Support\Mail\Sync\MailSync;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/**
 * Two cases the speed pass had to get right: the deferred \Seen reaches the copy HQ shows even when the message moved
 * in between, and a folder whose server count moved is recounted although sync itself changed nothing.
 */
class MailSpeedEdgeCasesTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_open_then_archive_before_the_deferred_seen_runs(): void
    {
        config(['mail_client.mark_read_after_response' => true]);
        $account = $this->mailbox(self::BOX);
        $sender = $this->staff('sen', ['sales']);
        $this->member($account, $sender, 'send');
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Invoice'));
        MailSync::make()->account($account->fresh(), null, true);
        MailSync::make()->account($account->fresh(), null, true);

        // Request 1: open (HQ read now, server \Seen deferred)
        \App\Support\Mail\MailActions::make()->openedMany($sender, MailMessage::all());
        $this->assertTrue((bool) MailMessage::value('is_seen'));
        // Request 2 (user hits Archive before request 1's deferred work reached the server)
        $deferred = app(\Illuminate\Support\Defer\DeferredCallbackCollection::class);
        \App\Support\Mail\MailActions::make()->moveToRole($sender, MailMessage::all(), 'archive');
        // now request 1's deferred work runs
        $deferred->invoke();

        $archive = collect($this->server->mailboxes[self::BOX]['folders'])->filter(fn ($f, $p) => str_contains($p, 'Archive'))->first();
        $this->assertContains('\\Seen', array_values($archive['messages'])[0]['flags'], 'the archived copy is the one marked read');
        MailSync::make()->account($account->fresh(), null, true);
        $this->assertTrue((bool) MailMessage::value('is_seen'));
    }

    public function test_drafts_count_after_a_draft_is_removed_by_hq(): void
    {
        $account = $this->mailbox(self::BOX);
        $sender = $this->staff('sen', ['sales']);
        $this->member($account, $sender, 'send');

        $uid = $this->server->deliver(self::BOX, 'INBOX.Drafts', FakeMailServer::message(subject: 'Draft one'), ['\\Draft', '\\Seen']);
        MailSync::make()->account($account->fresh(), null, true);
        MailSync::make()->account($account->fresh(), null, true);
        $drafts = MailFolder::where('role', 'drafts')->firstOrFail();
        $this->assertSame(1, (int) $drafts->total_count);
        $stamp = MailVersion::of([$account->id]);

        // HQ discards / sends the draft: removes the server copy and its own row
        $outbox = Outbox::make()->start($sender, 'new', $account);
        $p = $outbox->payload;
        $p['server_draft'] = ['path' => 'INBOX.Drafts', 'uid' => $uid];
        $outbox->forceFill(['payload' => $p])->save();
        Outbox::make()->removeServerDraft($outbox->fresh());
        $this->assertSame(0, MailMessage::count());

        // A few sync runs later
        $this->travel(6)->minutes();
        MailSync::make()->account($account->fresh(), null, true);
        $this->travel(6)->minutes();
        MailSync::make()->account($account->fresh(), null, true);
        $this->assertSame(0, (int) MailFolder::where('role', 'drafts')->value('total_count'));
    }
}
