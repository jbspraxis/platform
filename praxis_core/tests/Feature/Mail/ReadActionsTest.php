<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\Inbox;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Support\Mail\Sync\MailSync;
use Livewire\Livewire;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** The reader's toolbar and keyboard actions, through MailActions: the server first, then HQ (docs/MAIL.md, PR 2). */
class ReadActionsTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_archive_with_undo_spam_and_back_and_move_to_a_folder(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->addFolder(self::BOX, 'INBOX.Clients', []);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'File me'));
        MailSync::make()->account($account, null, true);
        $thread = MailThread::firstOrFail();

        $page = Livewire::actingAs($sender)->test(Inbox::class)->call('openThread', $thread->id)
            ->call('act', 'archive')
            ->assertSee('Archived.')->assertSee('Undo')
            ->assertSet('thread', null)
            ->assertDispatched('mail-thread-changed', threadId: $thread->id);
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX.Archive'));
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX'));

        $page->call('undoMove')->assertSee('Moved back to Inbox.');
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX'));
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX.Archive'));

        $page->call('openThread', $thread->id)->call('act', 'spam')->assertSee('Moved to Spam.');
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX.Junk'));
        $page->call('selectFolder', 'spam')->assertSee('File me')->call('openThread', $thread->id)->assertSee('Not spam')
            ->call('act', 'inbox')->assertSee('Moved to Inbox.');
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX'));

        $clients = MailFolder::where('path', 'INBOX.Clients')->firstOrFail();
        $page->call('selectFolder', 'inbox')->call('openThread', $thread->id)->call('act', 'move:' . $clients->id)->assertSee('Moved.');
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX.Clients'));
        $this->assertSame($clients->id, MailMessage::firstOrFail()->folder_id);
    }

    public function test_flag_unread_trash_and_delete_for_good_after_confirming(): void
    {
        [$account, $sender, $reader] = $this->shared();
        $uid = $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Bin me'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();

        $page = Livewire::actingAs($sender)->test(Inbox::class)->call('openThread', $thread->id)
            ->call('act', 'flag')->assertSee('Flagged.');
        $this->assertContains('\\Flagged', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags']);
        $page->call('act', 'unflag')->assertSee('Flag removed.');
        $this->assertNotContains('\\Flagged', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags']);

        $page->call('act', 'unread')->assertSet('thread', null);
        $this->assertNotContains('\\Seen', $this->server->folder(self::BOX, 'INBOX')[$uid]['flags']);

        // Delete for good is refused outside Trash and Spam
        $page->call('openThread', $thread->id)->call('act', 'delete')->assertSee('Move it to Trash first.');
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX'));

        $page->call('act', 'trash')->assertSee('Moved to Trash.');
        $page->call('selectFolder', 'trash')->call('openThread', $thread->id)
            ->call('askConfirm', 'delete')->assertSee('Delete this conversation for good?')->assertSee('Keep it')
            ->call('act', 'delete')->assertSee('Deleted for good.')->assertSet('confirm', null);
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX.Trash'));
        $this->assertSame(0, MailMessage::count());

        // Read level never gets there
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Again'));
        MailSync::make()->account($account);
        Livewire::actingAs($reader)->test(Inbox::class)->call('openThread', MailThread::firstOrFail()->id)->call('act', 'trash')->assertForbidden();
        Livewire::actingAs($reader)->test(Inbox::class)->call('selectFolder', 'trash')->assertDontSee('Empty Trash');
    }

    public function test_empty_trash_clears_the_server_folder_after_a_named_confirmation(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX.Trash', FakeMailServer::message(subject: 'Old one'));
        $this->server->deliver(self::BOX, 'INBOX.Trash', FakeMailServer::message(subject: 'Very old one', date: now()->subMonths(20)->toRfc2822String()));
        MailSync::make()->account($account, null, true);

        Livewire::actingAs($sender)->withQueryParams(['box' => $account->id, 'folder' => 'trash'])->test(Inbox::class)
            ->assertSee('Old one')
            ->call('askConfirm', 'empty')
            ->assertSee('Empty Trash?')
            ->assertSee('including mail older than 12 months')
            ->call('emptyFolder')
            ->assertSee('Trash is empty.')
            ->assertSee('No mail in Trash');
        $this->assertCount(0, $this->server->folder(self::BOX, 'INBOX.Trash'));
    }

    public function test_a_server_refusal_is_told_calmly(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Stay put'));
        MailSync::make()->account($account);
        $thread = MailThread::firstOrFail();
        $page = Livewire::actingAs($sender)->test(Inbox::class)->call('openThread', $thread->id);

        $this->server->setPassword(self::BOX, 'changed-on-the-server');
        $page->call('act', 'archive')->assertSee("The mail server didn&#039;t accept that. Try again in a minute.", false)->assertSet('thread', $thread->id);
        $page->call('refreshMailbox')->assertSee('Try again in a minute.');
        $this->assertSame('INBOX', MailMessage::firstOrFail()->folder->path);
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
}
