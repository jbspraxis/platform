<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\Mailboxes;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAccountMember;
use App\Models\Mail\MailMessage;
use App\Support\Hosts;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailCredentials;
use App\Support\Mail\MailSettings;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** HQ → Mail → Mailboxes (docs/MAIL.md, "Mailboxes"). */
class MailboxesScreenTest extends TestCase
{
    use MailTestKit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    public function test_connect_checks_with_the_server_and_never_keeps_the_password_in_the_page(): void
    {
        $owner = $this->staff('own', ['owner']);
        $owner->forceFill(['email' => 'tom@jbspraxis.com'])->save();
        $this->server->addMailbox('tom@jbspraxis.com', 'right-pass');
        $this->server->deliver('tom@jbspraxis.com', 'INBOX', FakeMailServer::message(to: 'tom@jbspraxis.com', subject: 'Welcome'));

        $page = Livewire::actingAs($owner)->test(Mailboxes::class)
            ->assertSet('connectEmail', 'tom@jbspraxis.com')
            ->assertSet('connectServer.imap_host', 'mail.jbspraxis.com')
            ->set('connectPassword', 'wrong-pass')
            ->call('connect')
            ->assertHasErrors('connectPassword')
            ->assertSet('connectPassword', '');
        $this->assertSame(0, MailAccount::count());

        $page->set('connectPassword', 'right-pass')->call('connect')
            ->assertHasNoErrors()
            ->assertSet('connectPassword', '')
            ->assertSee('Connected tom@jbspraxis.com');

        $account = MailAccount::firstOrFail();
        $this->assertSame('personal', $account->kind);
        $this->assertSame($owner->id, $account->owner_user_id);
        $this->assertNotSame('right-pass', $account->secret);
        $this->assertSame('right-pass', MailCredentials::password($account));
        $this->assertSame(1, MailMessage::count(), 'The first copy starts at once');
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'MAIL_MAILBOX_CONNECTED')->count());
    }

    public function test_add_a_shared_mailbox_and_manage_its_people(): void
    {
        $partner = $this->staff('par', ['partner']);
        $ada = $this->staff('ada', ['sales']);
        $this->openMailToEveryone();
        $this->server->addMailbox('intelligence@jbspraxis.com', 'team-pass');

        Livewire::actingAs($this->staff('sal', ['sales']))->test(Mailboxes::class)->call('openModal', 'add-shared')->assertForbidden();

        $page = Livewire::actingAs($partner)->test(Mailboxes::class)
            ->call('openModal', 'add-shared')
            ->set('sharedEmail', 'intelligence@jbspraxis.com')
            ->assertSet('sharedName', 'JBS Praxis Intelligence')
            ->set('sharedPassword', 'team-pass')
            ->call('addShared')
            ->assertHasNoErrors()
            ->assertSet('modal', 'people');

        $account = MailAccount::where('email', 'intelligence@jbspraxis.com')->firstOrFail();
        $this->assertSame('shared', $account->kind);
        $this->assertSame('manage', MailAccountMember::where('account_id', $account->id)->where('user_id', $partner->id)->value('level'));

        $page->set('inviteUser', $ada->id)->set('inviteLevel', 'send')->call('invite')->assertHasNoErrors();
        $member = MailAccountMember::where('account_id', $account->id)->where('user_id', $ada->id)->firstOrFail();
        $this->assertSame('send', $member->level);
        $this->assertSame(1, DB::table('notifications')->where('user_id', $ada->id)->where('title', 'You have a shared mailbox')->count());

        // The last manager can't step down
        $mine = MailAccountMember::where('account_id', $account->id)->where('user_id', $partner->id)->first();
        $page->set("memberLevels.{$mine->id}", 'read')->assertHasErrors('members');
        $this->assertSame('manage', $mine->fresh()->level);

        $page->set("memberLevels.{$member->id}", 'manage');
        $this->assertSame('manage', $member->fresh()->level);
        $page->set("memberLevels.{$mine->id}", 'read')->assertHasNoErrors();
        $this->assertSame('read', $mine->fresh()->level);

        // Now only a reader: no more managing
        MailAccess::flush();
        Livewire::actingAs($partner)->test(Mailboxes::class)->call('openModal', 'people', $account->id)->assertForbidden();

        Livewire::actingAs($ada)->test(Mailboxes::class)
            ->call('openModal', 'people', $account->id)
            ->call('removeMember', $mine->id)->assertHasNoErrors();
        $this->assertFalse(MailAccountMember::where('id', $mine->id)->exists());
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'MAIL_MEMBER_REMOVED')->count());
    }

    public function test_a_shared_address_cannot_be_connected_twice(): void
    {
        $partner = $this->staff('par', ['partner']);
        $this->openMailToEveryone();
        $this->mailbox('intelligence@jbspraxis.com');

        Livewire::actingAs($partner)->test(Mailboxes::class)
            ->set('connectEmail', 'intelligence@jbspraxis.com')
            ->set('connectPassword', 'secret-pass')
            ->call('connect')
            ->assertHasErrors('connectEmail');
    }

    public function test_new_password_after_a_refusal_restarts_the_mailbox(): void
    {
        $owner = $this->staff('own', ['owner']);
        $account = $this->mailbox('intelligence@jbspraxis.com');
        $account->update(['status' => 'needs_reconnect']);
        $this->server->setPassword('intelligence@jbspraxis.com', 'new-pass');

        Livewire::actingAs($owner)->test(Mailboxes::class)
            ->assertSee('Needs password')
            ->call('openModal', 'password', $account->id)
            ->set('newPassword', 'new-pass')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('newPassword', '');

        $this->assertSame('active', $account->fresh()->status);
        $this->assertSame('new-pass', MailCredentials::password($account->fresh()));
    }

    public function test_remove_deletes_hqs_copy_only(): void
    {
        $owner = $this->staff('own', ['owner']);
        $account = $this->mailbox('intelligence@jbspraxis.com');
        $this->server->deliver('intelligence@jbspraxis.com', 'INBOX', FakeMailServer::message(subject: 'Keep on server'));
        \App\Support\Mail\Sync\MailSync::make()->account($account);

        Livewire::actingAs($owner)->test(Mailboxes::class)
            ->call('openModal', 'remove', $account->id)
            ->assertSee('Remove intelligence@jbspraxis.com from HQ?')
            ->call('removeMailbox');

        $this->assertSame(0, MailAccount::count());
        $this->assertSame(0, MailMessage::count());
        $this->assertCount(1, $this->server->folder('intelligence@jbspraxis.com', 'INBOX'));
    }

    public function test_only_owners_open_mail_to_everyone(): void
    {
        $owner = $this->staff('own', ['owner']);
        $this->openMailToEveryone();
        Livewire::actingAs($this->staff('par', ['partner']))->test(Mailboxes::class)->call('setOpenTo', 'owner')->assertForbidden();
        MailSettings::set('open_to', 'owner');
        MailAccess::flush();

        Livewire::actingAs($owner)->test(Mailboxes::class)
            ->assertSee('Open Mail to everyone')
            ->call('openModal', 'open-mail')
            ->call('setOpenTo', 'everyone');

        MailSettings::flush();
        $this->assertSame('everyone', MailSettings::get('open_to'));
        // The owner's own line (the 12 Nov 2026 migration wrote one of its own, with no person)
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'MAIL_OPENED_TO_EVERYONE')->where('user_id', $owner->id)->count());
    }

    public function test_pages_render_on_the_hq_host(): void
    {
        $owner = $this->staff('own', ['owner']);
        $this->mailbox('own@jbspraxis.com', 'personal', $owner);
        $base = 'https://' . Hosts::hq();

        $this->actingAs($owner)->get($base . '/mail')->assertOk()->assertSee('own@jbspraxis.com');
        $this->actingAs($owner)->get($base . '/mail/mailboxes')->assertOk()->assertSee('Your mailbox');
        $this->actingAs($owner)->get($base . '/mail/signature')->assertOk();
        $this->actingAs($owner)->get($base . '/dashboard/workspace')->assertOk()->assertSee('/mail', false);
    }
}
