<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\ThreadTeam;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Models\Mail\MailThreadNote;
use App\Models\User;
use App\Support\Mail\Sync\MailSync;
use App\Support\Mail\Team\MailPresence;
use App\Support\Mail\Team\MailTeam;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** Teamwork on shared mailboxes (docs/MAIL.md, owner decision 7): assign, Open / Done, notes, presence, activity. */
class TeamWorkTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    private MailAccount $box;

    private MailThread $thread;

    private User $owner;

    private User $manager;

    private User $sender;

    private User $reader;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();

        $this->owner = $this->staff('own', ['owner']);
        $this->manager = $this->staff('mgr', ['sales']);
        $this->sender = $this->staff('snd', ['sales']);
        $this->reader = $this->staff('rdr', ['sales']);
        $this->outsider = $this->staff('out', ['sales']);

        $this->box = $this->mailbox(self::BOX);
        $this->member($this->box, $this->manager, 'manage');
        $this->member($this->box, $this->sender, 'send');
        $this->member($this->box, $this->reader, 'read');

        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', subject: 'Proposal for Q4', text: 'Can you send the brief?', messageId: 'p1@client.test'));
        MailSync::make()->account($this->box);
        $this->thread = MailThread::firstOrFail();
    }

    public function test_send_level_assigns_and_the_assignee_gets_a_bell_notification(): void
    {
        Livewire::actingAs($this->sender)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->assertSee('Assigned to')
            ->call('assign', $this->reader->id)
            ->assertSet('error', 'That person can’t reply from this mailbox.')
            ->call('assign', $this->manager->id)
            ->assertSet('error', null)
            ->assertDispatched('mail-thread-changed', threadId: $this->thread->id);

        $this->thread->refresh();
        $this->assertSame($this->manager->id, $this->thread->assigned_to);
        $this->assertSame($this->sender->id, $this->thread->assigned_by);

        $bell = DB::table('notifications')->where('user_id', $this->manager->id)->get();
        $this->assertCount(1, $bell);
        $this->assertSame('Conversation assigned to you', $bell[0]->title);
        $this->assertStringContainsString('Proposal for Q4', $bell[0]->message);
        $this->assertSame('/mail?box=' . $this->box->id . '&thread=' . $this->thread->id, $bell[0]->link_url);
        $this->assertSame(1, DB::table('notifications')->count(), 'Nobody else is told');

        // Taking it yourself tells nobody
        Livewire::actingAs($this->owner)->test(ThreadTeam::class, ['threadId' => $this->thread->id])->call('assignToMe');
        $this->assertSame($this->owner->id, $this->thread->refresh()->assigned_to);
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(['assigned', 'assigned'], DB::table('mail_thread_activity')->orderBy('id')->pluck('type')->all());
    }

    public function test_read_level_sees_the_bar_and_adds_notes_but_cannot_assign_or_mark_done(): void
    {
        $page = Livewire::actingAs($this->reader)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->assertSee('Nobody')
            ->assertDontSee('Mark done')
            ->assertDontSee('Assign to me');

        // A forbidden call ends that page's test component, so each gets its own
        $page->call('assignToMe')->assertForbidden();
        $page = Livewire::actingAs($this->reader)->test(ThreadTeam::class, ['threadId' => $this->thread->id]);
        $page->call('markDone')->assertForbidden();
        $page = Livewire::actingAs($this->reader)->test(ThreadTeam::class, ['threadId' => $this->thread->id]);
        $this->assertNull($this->thread->refresh()->assigned_to);
        $this->assertSame('open', $this->thread->status);

        $page->set('note', '')->call('addNote')->assertHasErrors('note');
        $page->set('note', 'Called them: the PO comes on Monday.')->call('addNote')
            ->assertHasNoErrors()->assertSet('note', '')->assertSet('panel', 'notes')
            ->assertSee('Called them: the PO comes on Monday.');
        $note = MailThreadNote::firstOrFail();
        $this->assertSame($this->reader->id, $note->user_id);

        // Someone else's note: only its writer or a manager deletes it
        Livewire::actingAs($this->sender)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->call('deleteNote', $note->id)->assertForbidden();
        Livewire::actingAs($this->manager)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->call('deleteNote', $note->id)->assertOk();
        $this->assertSoftDeleted($note);
    }

    public function test_open_and_done_with_activity_timeline(): void
    {
        $page = Livewire::actingAs($this->sender)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->call('markDone')
            ->assertSee('Done')
            ->assertSee('Open again');
        $this->thread->refresh();
        $this->assertSame('done', $this->thread->status);
        $this->assertSame($this->sender->id, $this->thread->done_by);
        $this->assertNotNull($this->thread->done_at);

        $page->call('reopen')->assertSee('Mark done');
        $this->thread->refresh();
        $this->assertSame('open', $this->thread->status);
        $this->assertNull($this->thread->done_by);

        $page->call('togglePanel', 'activity')
            ->assertSee('You marked this done')
            ->assertSee('You opened this again');
        Livewire::actingAs($this->reader)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->call('togglePanel', 'activity')
            ->assertSee($this->sender->name . ' marked this done');
    }

    public function test_nothing_for_outsiders_or_personal_mailboxes(): void
    {
        Livewire::actingAs($this->outsider)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->assertDontSee('Assigned to')
            ->call('markDone')->assertForbidden();
        Livewire::actingAs($this->outsider)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->set('note', 'sneaky')->call('addNote')->assertForbidden();
        $this->assertSame(0, MailThreadNote::count());
        $this->assertSame(0, DB::table('mail_presence')->where('user_id', $this->outsider->id)->count());

        // Personal mailbox: no teamwork, even for its owner
        $mine = $this->mailbox('rdr@jbspraxis.com', 'personal', $this->reader);
        $this->server->deliver('rdr@jbspraxis.com', 'INBOX', FakeMailServer::message(subject: 'Mine', messageId: 'mine@x.test'));
        MailSync::make()->account($mine);
        $own = MailThread::where('account_id', $mine->id)->firstOrFail();
        Livewire::actingAs($this->reader)->test(ThreadTeam::class, ['threadId' => $own->id])
            ->assertDontSee('Assigned to')
            ->call('markDone')->assertForbidden();

        $this->expectException(HttpException::class);
        MailTeam::setStatus($this->reader, $own, 'done');
    }

    public function test_presence_shows_who_is_viewing_and_replying_and_expires(): void
    {
        Livewire::actingAs($this->sender)->test(ThreadTeam::class, ['threadId' => $this->thread->id]);
        $this->assertSame('viewing', DB::table('mail_presence')->where('user_id', $this->sender->id)->value('state'));

        // The composer (PR 3) says the sender started replying
        Livewire::actingAs($this->sender)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->dispatch('mail-composing', threadId: $this->thread->id, state: 'replying');
        $this->assertSame('replying', DB::table('mail_presence')->where('user_id', $this->sender->id)->value('state'));

        // A poll while replying keeps "replying"
        MailPresence::touch($this->sender, $this->thread);
        $this->assertSame('replying', DB::table('mail_presence')->where('user_id', $this->sender->id)->value('state'));

        Livewire::actingAs($this->manager)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->assertSee($this->sender->name . ' is writing a reply. Check with them before you send one.')
            ->assertDontSee($this->manager->name . ' is');

        Livewire::actingAs($this->sender)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->dispatch('mail-composing', threadId: $this->thread->id, state: 'stopped');
        Livewire::actingAs($this->manager)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->assertSee($this->sender->name . ' is also looking at this.');

        // Another conversation's event changes nothing here
        Livewire::actingAs($this->reader)->test(ThreadTeam::class, ['threadId' => $this->thread->id])
            ->dispatch('mail-composing', threadId: $this->thread->id + 999, state: 'replying');
        $this->assertSame('viewing', DB::table('mail_presence')->where('user_id', $this->reader->id)->value('state'));

        $this->travel(61)->seconds();
        $this->assertSame([], MailPresence::others($this->manager, $this->thread));
        $this->artisan('mail:prune')->assertSuccessful();
        $this->assertSame(0, DB::table('mail_presence')->count());
    }

    public function test_mail_thread_opened_marks_viewing(): void
    {
        DB::table('mail_presence')->delete();
        $page = Livewire::actingAs($this->reader)->test(ThreadTeam::class, ['threadId' => $this->thread->id]);
        DB::table('mail_presence')->delete();
        $page->dispatch('mail-thread-opened', accountId: $this->box->id, threadId: $this->thread->id);
        $this->assertSame('viewing', DB::table('mail_presence')->where('user_id', $this->reader->id)->value('state'));
        $this->assertTrue(MailMessage::where('thread_id', $this->thread->id)->exists());
    }
}
