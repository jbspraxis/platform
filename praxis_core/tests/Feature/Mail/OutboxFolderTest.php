<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\ComposeOutbox;
use App\Livewire\Hq\Mail\Inbox;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAccountMember;
use App\Models\Mail\MailOutbox;
use App\Models\User;
use App\Support\Mail\Compose\Outbox;
use App\Support\Mail\MailAccess;
use App\Support\Mail\Sync\MailSync;
use Livewire\Livewire;
use Tests\TestCase;

/** The Outbox entry in the Mail page's folder list: below Drafts, a link to Scheduled and unsent, with its count. */
class OutboxFolderTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_outbox_sits_below_drafts_with_the_count_of_this_persons_waiting_mail_only(): void
    {
        [$account, $sender, $other] = $this->shared();
        // Counted: two unfinished drafts, one scheduled, one that didn't go
        $this->row($sender, $account, 'draft', ['touched' => true, 'subject' => 'Proposal one']);
        $this->row($sender, $account, 'draft', ['touched' => true, 'subject' => 'Proposal two']);
        $this->row($sender, $account, 'queued', ['touched' => true, 'subject' => 'Monday 8am']);
        $this->row($sender, $account, 'failed', ['touched' => true, 'subject' => 'Refused']);
        // Not counted: an untouched or empty draft, mail that already went, and someone else's rows
        $this->row($sender, $account, 'draft', ['touched' => false, 'subject' => 'Never typed in']);
        $this->row($sender, $account, 'draft', ['touched' => true, 'html' => '<p></p>']);
        $this->row($sender, $account, 'sent', ['touched' => true, 'subject' => 'Already delivered']);
        $this->row($other, $account, 'draft', ['touched' => true, 'subject' => 'Not yours']);
        $this->row($other, $account, 'queued', ['touched' => true, 'subject' => 'Not yours either']);
        MailSync::make()->account($account);

        $html = Livewire::actingAs($sender)->test(Inbox::class)->assertSee('Drafts')->html();

        // The folder list: Outbox is the item right after Drafts
        $names = $this->navNames($html);
        $drafts = array_search('Drafts', $names, true);
        $this->assertNotFalse($drafts);
        $this->assertSame('Outbox', $names[$drafts + 1] ?? null);

        // A link to the existing page, navigated like HQ's other links, with the count (this person's four)
        preg_match('~<li wire:key="folder-outbox">\s*<a href="([^"]+)"([^>]*)>(.*?)</a>~s', $html, $link);
        $this->assertSame(route('hq.mail.compose.outbox'), html_entity_decode($link[1] ?? ''));
        $this->assertStringContainsString('wire:navigate', $link[2]);
        $this->assertStringContainsString('aria-label="4 waiting"', $link[3]);
        $this->assertStringContainsString('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', $link[3]); // the clock

        // It is the same number as the page it opens
        Livewire::actingAs($sender)->test(ComposeOutbox::class)
            ->assertSee('Proposal one')->assertSee('Proposal two')->assertSee('Monday 8am')->assertSee('Refused')
            ->assertDontSee('Never typed in')->assertDontSee('Already delivered')->assertDontSee('Not yours');
        $this->assertSame(4, Outbox::make()->waiting($sender)->count());

        // The other person sees their own two
        $this->assertStringContainsString('aria-label="2 waiting"', $this->outboxLink(Livewire::actingAs($other)->test(Inbox::class)->html()));
    }

    public function test_the_phone_folder_picker_has_an_outbox_option_below_drafts_that_opens_the_page(): void
    {
        [$account, $sender] = $this->shared();
        $this->row($sender, $account, 'draft', ['touched' => true, 'subject' => 'Proposal one']);
        $this->row($sender, $account, 'queued', ['touched' => true, 'subject' => 'Monday 8am']);
        MailSync::make()->account($account);

        $c = Livewire::actingAs($sender)->test(Inbox::class);

        preg_match('~<select id="mr-folder-pick".*?</select>~s', $c->html(), $select);
        preg_match_all('~<option value="([^"]*)"[^>]*>([^<]*)</option>~', $select[0], $options, PREG_SET_ORDER);
        $values = array_column($options, 1);
        $drafts = array_search('drafts', $values, true);
        $this->assertNotFalse($drafts);
        $this->assertSame('outbox', $values[$drafts + 1] ?? null);
        $this->assertSame('Outbox (2)', trim($options[$drafts + 1][2]));

        $c->call('selectFolder', 'outbox')->assertRedirect(route('hq.mail.compose.outbox'));
    }

    public function test_nothing_waiting_shows_the_entry_without_a_count(): void
    {
        [$account, $sender] = $this->shared();
        MailSync::make()->account($account);

        $html = Livewire::actingAs($sender)->test(Inbox::class)->html();

        $this->assertContains('Outbox', $this->navNames($html));
        $this->assertStringNotContainsString('waiting"', $this->outboxLink($html));
        $this->assertStringNotContainsString('mr-nav-count', $this->outboxLink($html));
        preg_match('~<option value="outbox"[^>]*>([^<]*)</option>~', $html, $option);
        $this->assertSame('Outbox', trim($option[1]));
    }

    public function test_mail_from_a_mailbox_the_person_can_no_longer_send_from_is_not_counted(): void
    {
        [$account, $sender] = $this->shared();
        $this->row($sender, $account, 'draft', ['touched' => true, 'subject' => 'Proposal one']);
        MailSync::make()->account($account);
        // Same person, now read-only on that mailbox: the page hides the row, so the count does too
        MailAccountMember::where('account_id', $account->id)->where('user_id', $sender->id)->update(['level' => 'read']);
        MailAccess::flush();

        $this->assertSame(0, Outbox::make()->waiting($sender)->count());
        $this->assertStringNotContainsString('waiting"', $this->outboxLink(Livewire::actingAs($sender)->test(Inbox::class)->html()));
    }

    /** @return list<string> the labels in the desktop folder list, in order */
    private function navNames(string $html): array
    {
        preg_match('~<nav class="mr-nav".*?</nav>~s', $html, $nav);
        preg_match_all('~<span class="mr-nav-name">(.*?)</span>~s', $nav[0] ?? '', $names);

        return array_map(fn ($n) => trim(strip_tags($n)), $names[1]);
    }

    private function outboxLink(string $html): string
    {
        preg_match('~<li wire:key="folder-outbox">.*?</li>~s', $html, $li);

        return $li[0] ?? '';
    }

    private function row(User $user, MailAccount $account, string $status, array $payload): MailOutbox
    {
        $payload += ['html' => '<p>Hello</p>'];

        return MailOutbox::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'status' => $status, 'kind' => 'new', 'payload' => $payload,
            'send_at' => $status === 'queued' ? now()->addDay() : null,
        ]);
    }

    /** @return array{0: MailAccount, 1: User, 2: User} */
    private function shared(): array
    {
        $account = $this->mailbox(self::BOX);
        $sender = $this->staff('sen', ['sales']);
        $other = $this->staff('oth', ['sales']);
        $this->member($account, $sender, 'send');
        $this->member($account, $other, 'send');

        return [$account, $sender, $other];
    }
}
