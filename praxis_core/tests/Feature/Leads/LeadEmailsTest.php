<?php

namespace Tests\Feature\Leads;

use App\Livewire\Hq\Leads\EmailsTab;
use App\Livewire\Hq\Leads\LeadPage;
use App\Models\Organization;
use App\Models\User;
use App\Support\Integrations;
use App\Support\Leads\Contacts;
use App\Support\Leads\LeadEmails;
use App\Support\Leads\Leads;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Leads PR 2's Emails (docs/LEADS.md section 9, owner's decision 12): HQ Mail to and from a lead's contacts, matched by
 * address both ways, only from the mailboxes the person may read; leads:file-emails puts who wrote to whom on the
 * timeline (never the subject or text), once per message; the Emails tab and Email them.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadEmailsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private int $uid = 1;

    protected function setUp(): void
    {
        parent::setUp();
        MailSettings::set('open_to', 'everyone');
        MailAccess::flush();
    }

    private function lead(User $by): Organization
    {
        return Leads::create(['name' => 'Kilo Retail (test)', 'country' => 'Nigeria', 'industry' => 'Retail'],
            [['name' => 'Ada Test', 'email' => 'Ada@Kilo.test'], ['name' => 'Bola Test', 'phone' => '+234 800 000 0003']], $by);
    }

    /** A mailbox with Inbox, Sent, Spam and Drafts; shared ones get $members (level send) */
    private function mailbox(string $kind, string $email, ?User $owner, array $members = []): array
    {
        $id = (string) Str::uuid();
        DB::table('mail_accounts')->insert(['id' => $id, 'kind' => $kind, 'email' => $email, 'display_name' => Str::headline(Str::before($email, '@')),
            'owner_user_id' => $owner?->id, 'username' => $email, 'secret' => Crypt::encryptString('x'), 'imap_host' => 'mail.test', 'imap_port' => 993,
            'imap_encryption' => 'ssl', 'smtp_host' => 'mail.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'status' => 'paused', 'created_at' => now(), 'updated_at' => now()]);
        foreach ($members as $m) {
            DB::table('mail_account_members')->insert(['account_id' => $id, 'user_id' => $m->id, 'level' => 'send', 'created_at' => now(), 'updated_at' => now()]);
        }
        $folders = [];
        foreach (['inbox' => 'INBOX', 'sent' => 'INBOX.Sent', 'spam' => 'INBOX.Spam', 'drafts' => 'INBOX.Drafts'] as $role => $path) {
            $folders[$role] = DB::table('mail_folders')->insertGetId(['account_id' => $id, 'path' => $path, 'name' => $path, 'role' => $role, 'selectable' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        MailAccess::flush();

        return ['id' => $id, 'folders' => $folders, 'email' => $email];
    }

    private function message(array $box, string $folder, string $messageId, array $from, array $to, bool $out, string $at, array $extra = []): int
    {
        return DB::table('mail_messages')->insertGetId($extra + ['account_id' => $box['id'], 'folder_id' => $box['folders'][$folder], 'uid' => $this->uid++,
            'message_id' => $messageId, 'message_id_hash' => sha1(strtolower($messageId)), 'subject' => 'About ' . $messageId, 'from_name' => $from[0], 'from_email' => $from[1],
            'to' => json_encode(array_map(fn ($a) => ['name' => $a[0], 'email' => $a[1]], $to)), 'cc' => '[]',
            'to_text' => implode(' ', array_map(fn ($a) => $a[0] . ' ' . $a[1], $to)), 'sent_at' => $at, 'received_at' => $at, 'is_outbound' => $out,
            'snippet' => 'Snippet of ' . $messageId, 'body_state' => 'cached', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_mail_both_ways_from_the_mailboxes_the_person_may_read(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $other = $this->staff('other', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $shared = $this->mailbox('shared', 'intelligence@jbspraxis.test', $sales, [$sales]);
        $mine = $this->mailbox('personal', 'sales@jbspraxis.test', $sales);
        $theirs = $this->mailbox('personal', 'other@jbspraxis.test', $other);

        $in = $this->message($shared, 'inbox', 'in-1@kilo.test', ['Ada Test', 'ada@kilo.test'], [['Intelligence', 'intelligence@jbspraxis.test']], false, '2026-11-01 09:00:00');
        $this->message($shared, 'inbox', 'in-1@kilo.test', ['Ada Test', 'ada@kilo.test'], [['Intelligence', 'intelligence@jbspraxis.test']], false, '2026-11-01 09:00:00', ['folder_id' => $shared['folders']['sent']]);   // a copy
        $out = $this->message($mine, 'sent', 'out-1@jbspraxis.test', ['Sales Test', 'sales@jbspraxis.test'], [['Ada', 'ADA@kilo.test']], true, '2026-11-02 09:00:00', ['sent_by' => $sales->id]);
        $this->message($mine, 'spam', 'spam-1@kilo.test', ['Ada Test', 'ada@kilo.test'], [['Sales', 'sales@jbspraxis.test']], false, '2026-11-03 09:00:00');
        $this->message($mine, 'drafts', 'draft-1@jbspraxis.test', ['Sales Test', 'sales@jbspraxis.test'], [['Ada', 'ada@kilo.test']], true, '2026-11-03 10:00:00');
        $this->message($mine, 'inbox', 'bada-1@kilo.test', ['Bada', 'bada@kilo.test'], [['Sales', 'sales@jbspraxis.test']], false, '2026-11-03 11:00:00');   // contains the address, isn't it
        $private = $this->message($theirs, 'inbox', 'priv-1@kilo.test', ['Ada Test', 'ada@kilo.test'], [['Other', 'other@jbspraxis.test']], false, '2026-11-04 09:00:00');

        $mail = LeadEmails::for($sales, $lead->id);
        $this->assertSame(['About out-1@jbspraxis.test', 'About in-1@kilo.test'], $mail->pluck('subject')->all(),
            'Newest first; once for its two copies; Spam, Drafts, another address and another person’s mailbox left out');
        $this->assertSame($out, $mail[0]->id);
        $this->assertContains($mail[1]->id, [$in, $in + 1]);
        $this->assertSame(['out', 'in'], $mail->pluck('direction')->all());
        $this->assertSame('ada@kilo.test', $mail[0]->contact_email);
        $this->assertSame([$private], LeadEmails::for($other, $lead->id)->pluck('id')->all(), 'Their own mailbox only');

        // Mail not open to someone: nothing
        MailSettings::set('open_to', 'owner');
        MailAccess::flush();
        $this->assertTrue(LeadEmails::for($sales, $lead->id)->isEmpty());
    }

    public function test_the_timeline_gets_who_wrote_to_whom_once_per_message(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $shared = $this->mailbox('shared', 'intelligence@jbspraxis.test', $sales, [$sales]);
        $this->message($shared, 'inbox', 'in-1@kilo.test', ['Ada Test', 'ada@kilo.test'], [['Intelligence', 'intelligence@jbspraxis.test']], false, '2026-11-01 09:00:00');
        $this->message($shared, 'sent', 'in-1@kilo.test', ['Ada Test', 'ada@kilo.test'], [['Intelligence', 'intelligence@jbspraxis.test']], false, '2026-11-01 09:00:00');
        $out = $this->message($shared, 'sent', 'out-1@jbspraxis.test', ['Intelligence', 'intelligence@jbspraxis.test'], [['Ada', 'ada@kilo.test']], true, '2026-11-02 09:00:00', ['sent_by' => $sales->id]);
        $this->message($shared, 'inbox', 'x-1@else.test', ['Someone', 'someone@else.test'], [['Intelligence', 'intelligence@jbspraxis.test']], false, '2026-11-02 10:00:00');

        $this->artisan('leads:file-emails')->expectsOutputToContain('2 line(s)')->assertSuccessful();
        $lines = DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'email')->orderBy('occurred_at')->get();
        $this->assertSame(['Ada Test emailed Intelligence', 'Sales Test emailed Ada Test'], $lines->pluck('summary')->all());
        $this->assertSame(['client', 'staff'], $lines->pluck('actor_type')->all());
        $this->assertSame(['mail_message', (string) $out], [$lines[1]->related_type, $lines[1]->related_id]);
        $this->assertStringNotContainsString('About', $lines->implode('summary'), 'Never the subject');
        $this->assertNull($lines[0]->body);

        // Nothing new: nothing written; the cursor stays at the last message read
        $this->artisan('leads:file-emails')->expectsOutputToContain('0 line(s)')->assertSuccessful();
        $this->assertSame((string) DB::table('mail_messages')->max('id'), Integrations::get(LeadEmails::CURSOR));

        // A contact added later: opening the Emails tab files their past mail
        $this->message($shared, 'inbox', 'cee-1@kilo.test', ['Cee Test', 'cee@kilo.test'], [['Intelligence', 'intelligence@jbspraxis.test']], false, '2026-10-30 09:00:00');
        $this->artisan('leads:file-emails')->assertSuccessful();
        Contacts::add($lead, ['name' => 'Cee Test', 'email' => 'cee@kilo.test'], $sales);
        Livewire::test(EmailsTab::class, ['leadId' => $lead->id])->assertDispatched('lead-changed')->assertSee('About cee-1@kilo.test');
        $this->assertSame(3, DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'email')->count());
        Livewire::test(EmailsTab::class, ['leadId' => $lead->id])->assertNotDispatched('lead-changed');
    }

    public function test_the_emails_tab_and_email_them(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $finance = $this->staff('finance', ['finance']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $shared = $this->mailbox('shared', 'intelligence@jbspraxis.test', $sales, [$sales]);
        $this->message($shared, 'inbox', 'in-1@kilo.test', ['Ada Test', 'ada@kilo.test'], [['Intelligence', 'intelligence@jbspraxis.test']], false, '2026-11-01 09:00:00');
        $ada = Contacts::of($lead->id)->firstWhere('email', 'ada@kilo.test');
        $bola = Contacts::of($lead->id)->firstWhere('first_name', 'Bola');

        $tab = Livewire::test(EmailsTab::class, ['leadId' => $lead->id])
            ->assertSee('About in-1@kilo.test')->assertSee('From Ada Test')->assertSee('Received · Intelligence')
            ->assertSee('Email them')->assertSeeLivewire('hq.mail.composer');
        $tab->call('emailThem', $ada->id)->assertHasNoErrors();
        $this->assertStringContainsString('mail-compose', json_encode($tab->effects['xjs'] ?? []));
        $this->assertStringContainsString('ada@kilo.test', json_encode($tab->effects['xjs'] ?? []));
        $tab->call('emailThem', $bola->id)->assertNotFound();   // no email
        Livewire::test(LeadPage::class, ['organization' => $lead])->assertSee('Emails (1)');

        // Finance reads Leads but isn't on the mailbox: no mail, nothing to send from
        $this->actingAs($finance);
        Livewire::test(EmailsTab::class, ['leadId' => $lead->id])->assertDontSee('About in-1@kilo.test')->assertDontSee('Email them')
            ->assertSee('No emails yet')->call('emailThem', $ada->id)->assertForbidden();
    }
}
