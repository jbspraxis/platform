<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\ComposeOutbox;
use App\Livewire\Hq\Mail\Composer;
use App\Livewire\Hq\Mail\Signature;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Support\Mail\Compose\Outbox;
use App\Support\Mail\Compose\Signatures;
use App\Support\Mail\MailSettings;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** The composer, Write with AI, the Signature page and Scheduled and unsent (PR 3, Livewire). */
class ComposeScreenTest extends TestCase
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

    public function test_new_mail_send_undo_window_then_delivered(): void
    {
        [$account, $sender] = $this->shared();
        MailSync::make()->account($account);

        $c = Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'new', accountId: $account->id)
            ->assertSet('open', true)
            ->assertSet('accountId', $account->id)
            ->assertSee('JBS Praxis Intelligence');

        $c->call('setRecipients', 'to', [['name' => 'Ada', 'email' => 'ADA@client.test'], ['email' => 'not an address']])
            ->assertSet('to', [['name' => 'Ada', 'email' => 'ada@client.test']])
            ->set('subject', 'Kick-off')
            ->call('autosave', '<p>Hello Ada</p>');
        $id = $c->get('outboxId');
        $this->assertSame('<p>Hello Ada</p>', MailOutbox::find($id)->payload['html']);

        $c->call('send', '<p>Hello Ada, see you Tuesday.</p>')
            ->assertSet('open', false)
            ->assertSet('toast.type', 'undo')
            ->assertDispatched('mail-sent');
        $this->assertSame('queued', MailOutbox::find($id)->status);
        $this->assertCount(0, $this->server->sent);

        $this->travel(9)->seconds();
        $c->call('deliver', $id)->assertSet('toast.type', 'sent');
        $this->assertCount(1, $this->server->sent);
        $this->assertStringContainsString('see you Tuesday', $this->server->sent[0]['email']->getHtmlBody());
    }

    public function test_undo_reopens_the_message(): void
    {
        [$account, $sender] = $this->shared();
        $c = Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'new', accountId: $account->id)
            ->call('setRecipients', 'to', [['email' => 'ada@client.test']]);
        $id = $c->get('outboxId');

        $c->call('send', '<p>Oops</p>')->call('undo', $id)
            ->assertSet('open', true)
            ->assertSet('outboxId', $id)
            ->assertDispatched('mail-editor-load');
        $this->assertSame('draft', MailOutbox::find($id)->status);
    }

    public function test_send_without_recipients_says_so(): void
    {
        [$account, $sender] = $this->shared();
        Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'new', accountId: $account->id)
            ->call('send', '<p>Hi</p>')
            ->assertSet('open', true)
            ->assertSet('error', 'Add at least one recipient.');
    }

    public function test_reply_raises_composing_and_closing_keeps_the_draft(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Question'));
        MailSync::make()->account($account);
        $message = MailMessage::firstOrFail();

        $c = Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'reply', accountId: $account->id, messageId: $message->id)
            ->assertSet('kind', 'reply')
            ->assertSet('subject', 'Re: Question')
            ->assertDispatched('mail-composing', threadId: $message->thread_id, state: 'replying');
        $id = $c->get('outboxId');

        $c->call('close', '<p>Half an answer</p>')
            ->assertSet('open', false)
            ->assertDispatched('mail-composing', threadId: $message->thread_id, state: 'stopped');
        $this->assertSame('draft', MailOutbox::find($id)->status);
        $this->assertCount(1, $this->server->folder(self::BOX, 'INBOX.Drafts'), 'Closing writes the server copy');

        // Replying again carries on with the same draft
        $again = Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'reply', accountId: $account->id, messageId: $message->id);
        $this->assertSame($id, $again->get('outboxId'));
    }

    public function test_an_untouched_composer_leaves_nothing_behind(): void
    {
        [$account, $sender] = $this->shared();
        Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'new', accountId: $account->id)
            ->call('close', '')
            ->assertSet('toast', null);
        $this->assertSame(0, MailOutbox::count());
    }

    public function test_an_answer_closed_without_text_is_dropped_and_abandoned_drafts_are_cleared(): void
    {
        [$account, $sender] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Question'));
        MailSync::make()->account($account);
        $message = MailMessage::firstOrFail();

        Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'forward', accountId: $account->id, messageId: $message->id)
            ->call('close', '<p><br></p>');
        $this->assertSame(0, MailOutbox::count());

        // Opened, then the page was left: gone a day later, a written draft stays
        Livewire::actingAs($sender)->test(Composer::class)->dispatch('mail-compose', mode: 'reply', accountId: $account->id, messageId: $message->id);
        $kept = Outbox::make()->start($sender, 'new', $account);
        Outbox::make()->save($sender, $kept, ['subject' => 'Keep me']);
        $this->travel(2)->days();
        $this->artisan('mail:send-due')->assertSuccessful();
        $this->assertSame([$kept->id], MailOutbox::pluck('id')->all());
    }

    public function test_schedule_quick_choice_and_cancel(): void
    {
        [$account, $sender] = $this->shared();
        $c = Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'new', accountId: $account->id)
            ->call('setRecipients', 'to', [['email' => 'ada@client.test']]);
        $id = $c->get('outboxId');

        $c->call('schedule', '<p>Morning</p>', 'tomorrow')->assertSet('toast.type', 'scheduled');
        $row = MailOutbox::find($id);
        $this->assertSame('queued', $row->status);
        $this->assertTrue($row->payload['scheduled']);
        $this->assertSame('08:00', $row->send_at->timezone('Africa/Lagos')->format('H:i'));

        Livewire::actingAs($sender)->test(ComposeOutbox::class)
            ->assertSee('Waiting to go')
            ->call('cancelSchedule', $id)
            ->assertSee('Drafts');
        $this->assertSame('draft', MailOutbox::find($id)->status);
    }

    public function test_a_read_only_member_cannot_open_a_reply(): void
    {
        [$account, , $reader] = $this->shared();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Hello'));
        MailSync::make()->account($account);

        Livewire::actingAs($reader)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'reply', accountId: $account->id, messageId: MailMessage::firstOrFail()->id)
            ->assertSet('open', false);
        $this->assertSame(0, MailOutbox::count());
    }

    public function test_write_with_ai_asks_gemini_only_with_mail_the_person_can_read_and_never_sends(): void
    {
        [$account, $sender] = $this->shared();
        config(['services.gemini.api_key' => 'test-key', 'services.deepseek.api_key' => 'never-used']);
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', subject: 'Workshop', text: "Can we meet next week?\n\nOn Mon, Bob wrote:\n> old quoted text", messageId: 'w1@client.test'));
        $this->server->deliver(self::BOX, 'INBOX.Junk', FakeMailServer::message(from: 'Spam <s@spam.test>', subject: 'Re: Workshop', text: 'SPAM-SECRET', inReplyTo: 'w1@client.test'));
        MailSync::make()->account($account, null, true);
        $message = MailMessage::where('message_id', 'w1@client.test')->firstOrFail();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => "Hello Ada,\n\nTuesday at 10:00 works.\n\nKind regards,\nSen"]]]]]])]);

        $c = Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'reply', accountId: $account->id, messageId: $message->id)
            ->set('aiOpen', true)
            ->set('aiPrompt', 'Say Tuesday 10:00 works')
            ->call('writeWithAi', '')
            ->assertSet('aiDraft', "Hello Ada,\n\nTuesday at 10:00 works.\n\nKind regards,\nSen");

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) {
            $body = json_encode($r->data());

            return str_contains($r->url(), 'generativelanguage.googleapis.com') && str_contains($r->url(), 'flash')
                && str_contains($body, 'Can we meet next week') && ! str_contains($body, 'SPAM-SECRET') && ! str_contains($body, 'old quoted text');
        });
        $this->assertCount(0, $this->server->sent, 'The AI never sends');

        $c->call('useAiDraft')->assertSet('aiDraft', null)->assertDispatched('mail-editor-load');
        $this->assertStringContainsString('<p>Tuesday at 10:00 works.</p>', MailOutbox::find($c->get('outboxId'))->payload['html']);
    }

    public function test_write_with_ai_without_a_key_says_so_calmly(): void
    {
        [$account, $sender] = $this->shared();
        config(['services.gemini.api_key' => null]);
        Http::fake();
        Livewire::actingAs($sender)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'new', accountId: $account->id)
            ->set('aiPrompt', 'Invite them to the workshop')
            ->call('writeWithAi', '')
            ->assertHasErrors('aiPrompt');
        Http::assertNothingSent();
    }

    public function test_signature_page_numbers_and_company_settings(): void
    {
        [$account, $sender] = $this->shared();
        DB::table('users')->where('id', $sender->id)->update(['job_title' => 'Account Manager', 'phone' => '+234 801 000 0000']);
        $sender->refresh();

        Livewire::actingAs($sender)->test(Signature::class)
            ->assertSee('Account Manager')
            // Job title only: a role is access, not a department (owner, 30 Oct 2026)
            ->assertDontSee('Sales &amp; Marketing', false)
            ->assertSee('+234 801 000 0000')
            ->set('mobile', 'call me')
            ->call('saveNumbers')
            ->assertHasErrors('mobile')
            ->set('mobile', '+234 802 111 2222')
            ->call('saveNumbers')
            ->assertHasNoErrors()
            ->assertSee('+234 802 111 2222');
        $this->assertSame('+234 802 111 2222', Signatures::numbers($sender)['mobile']);

        // Company details: Owner and Partner only
        Livewire::actingAs($sender)->test(Signature::class)->assertDontSee('Company details')->call('saveCompany')->assertForbidden();
        $owner = $this->staff('own', ['owner']);
        Livewire::actingAs($owner)->test(Signature::class)
            ->assertSee('Company details')
            ->set('company.name', 'JBS Praxis')
            ->set('company.website', 'not a site')
            ->call('saveCompany')
            ->assertHasErrors('company.website')
            ->set('company.website', 'https://jbspraxis.com')
            ->set('company.address', 'Lagos, Douala and Nairobi')
            ->call('saveCompany')
            ->assertHasNoErrors();
        MailSettings::flush();
        $this->assertSame('Lagos, Douala and Nairobi', MailSettings::get('signature.company')['address']);
        $this->assertStringContainsString('Lagos, Douala and Nairobi', Signatures::html($sender, $account, 'full'));
    }

    public function test_the_full_signature_on_a_shared_mailbox_shows_the_person_and_the_shared_address(): void
    {
        [$account, $sender] = $this->shared();
        $own = $this->mailbox('sen@jbspraxis.com', 'personal', $sender);

        $shared = Signatures::html($sender, $account, 'full');
        $this->assertStringContainsString('Sen Test', $shared);
        $this->assertStringContainsString('mailto:' . self::BOX, $shared);
        $this->assertStringNotContainsString($sender->email, $shared);
        $this->assertStringContainsString('#0C8B9B', $shared, 'The Praxis rule');
        $this->assertStringContainsString('mailto:sen@jbspraxis.com', Signatures::html($sender, $own, 'full'));
        $this->assertStringNotContainsString('<img', Signatures::html($sender, $account, 'slim'), 'The short one has no logo');
        $this->assertSame('', Signatures::html($sender, $account, 'none'));
    }

    public function test_outbox_page_shows_only_your_own_and_sends_a_failed_one_again(): void
    {
        [$account, $sender] = $this->shared();
        $mine = Outbox::make()->start($sender, 'new', $account);
        Outbox::make()->save($sender, $mine, ['to' => ['ada@client.test'], 'subject' => 'Mine to resend']);
        $mine->refresh()->forceFill(['status' => 'failed', 'last_error' => 'The outgoing mail server refused the message.'])->save();
        $other = $this->staff('oth', ['sales']);
        $this->member($account, $other, 'send');
        $theirs = Outbox::make()->start($other, 'new', $account);
        Outbox::make()->save($other, $theirs, ['subject' => 'Not yours']);

        Livewire::actingAs($sender)->test(ComposeOutbox::class)
            ->assertSee('Mine to resend')
            ->assertSee('refused the message')
            ->assertDontSee('Not yours')
            ->call('sendNow', $mine->id)
            ->assertSee('Sent.');
        $this->assertSame('sent', $mine->fresh()->status);

        Livewire::actingAs($sender)->test(ComposeOutbox::class)->call('discard', $theirs->id)->assertForbidden();
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
