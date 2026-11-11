<?php

namespace Tests\Feature\Leads;

use App\Livewire\Hq\Intelligence\LeadsRegister;
use App\Livewire\Hq\Leads\AddLead;
use App\Livewire\Hq\Leads\EmailsTab;
use App\Livewire\Hq\Leads\FollowUps;
use App\Livewire\Hq\Leads\LeadPage;
use App\Livewire\Hq\Leads\MeetingsTab;
use App\Livewire\Hq\Leads\OpportunitiesTab;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Support\Leads\Contacts;
use App\Support\Leads\LeadNumbers;
use App\Support\Leads\Leads;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailSettings;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The Leads screens do less on each request (performance pass, 11 Nov 2026): the lead page counts its emails once and
 * keeps the count until new mail, a contact or the person's mailboxes change it; opening the Emails tab again reads
 * no old mail, and filing reads what is on the timeline once per batch; the list counts its strip in a few queries,
 * whatever the number of owners, and not again while someone types a search; My follow-ups lists the first 50 later
 * and unplanned leads; Add a lead looks for duplicates only when the name, an email or a phone changed; the Meetings
 * tab reads a transcript's text only for the open meeting; the lead's tabs ask for the opportunity team once.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadsSpeedTest extends TestCase
{
    use UsesThrowawayDatabase;

    private int $uid = 1;

    /** A lead added by $by; Ada Test is its contact unless $contacts says otherwise */
    private function lead(User $by, array $values = [], ?array $contacts = null): Organization
    {
        return Leads::create($values + ['name' => 'Kilo Retail (test)', 'country' => 'Nigeria', 'industry' => 'Retail', 'phone' => '+234 800 000 ' . random_int(1000, 9999)],
            $contacts ?? [['name' => 'Ada Test', 'email' => 'ada@kilo.test']], $by);
    }

    /** A shared mailbox with an Inbox, that $members may read */
    private function mailbox(array $members): array
    {
        $id = (string) Str::uuid();
        DB::table('mail_accounts')->insert(['id' => $id, 'kind' => 'shared', 'email' => 'intelligence@jbspraxis.test', 'display_name' => 'Intelligence',
            'owner_user_id' => null, 'username' => 'x', 'secret' => Crypt::encryptString('x'), 'imap_host' => 'mail.test', 'imap_port' => 993,
            'imap_encryption' => 'ssl', 'smtp_host' => 'mail.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'status' => 'paused', 'created_at' => now(), 'updated_at' => now()]);
        foreach ($members as $m) {
            DB::table('mail_account_members')->insert(['account_id' => $id, 'user_id' => $m->id, 'level' => 'send', 'created_at' => now(), 'updated_at' => now()]);
        }
        $inbox = DB::table('mail_folders')->insertGetId(['account_id' => $id, 'path' => 'INBOX', 'name' => 'INBOX', 'role' => 'inbox', 'selectable' => 1, 'created_at' => now(), 'updated_at' => now()]);
        MailAccess::flush();

        return ['id' => $id, 'inbox' => $inbox];
    }

    private function message(array $box, string $from, string $to): int
    {
        $n = $this->uid++;

        return DB::table('mail_messages')->insertGetId(['account_id' => $box['id'], 'folder_id' => $box['inbox'], 'uid' => $n, 'message_id' => "m{$n}@test",
            'message_id_hash' => sha1("m{$n}@test"), 'subject' => "About {$n}", 'from_name' => 'Someone', 'from_email' => $from,
            'to' => json_encode([['name' => 'Someone', 'email' => $to]]), 'cc' => '[]', 'to_text' => "Someone {$to}", 'sent_at' => now()->subMinutes(100 - $n),
            'received_at' => now()->subMinutes(100 - $n), 'is_outbound' => false, 'snippet' => 'Hello', 'body_state' => 'cached', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** The SQL a call ran */
    private function queries(callable $fn): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $sql = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $sql;
    }

    /** How many of these queries read the mail itself (matching To and Cc reads every message) */
    private function mailScans(array $sql): int
    {
        return count(array_filter($sql, fn ($q) => str_contains($q, 'to_text')));
    }

    public function test_the_lead_page_counts_its_emails_once_and_follows_new_mail(): void
    {
        MailSettings::set('open_to', 'everyone');
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $box = $this->mailbox([$sales]);
        $this->message($box, 'ada@kilo.test', 'intelligence@jbspraxis.test');
        $this->message($box, 'someone@else.test', 'intelligence@jbspraxis.test');

        $page = Livewire::test(LeadPage::class, ['organization' => $lead])->assertSee('Emails (1)');
        // Clicks on the page don't read the mail again
        $this->assertSame(0, $this->mailScans($this->queries(fn () => $page->call('editDetails')->call('cancelDetails')->call('show', 'activity'))));

        // New mail shows at once (the newest message is part of the count's key)
        $this->message($box, 'intelligence@jbspraxis.test', 'ADA@kilo.test');
        $page->call('show', 'overview')->assertSee('Emails (2)');

        // A contact added: their mail counts too
        $this->message($box, 'cee@kilo.test', 'intelligence@jbspraxis.test');
        Contacts::add($lead, ['name' => 'Cee Test', 'email' => 'cee@kilo.test'], $sales);
        $page->call('show', 'overview')->assertSee('Emails (3)');

        // Mail closed to them: nothing counted, and nothing read
        MailSettings::set('open_to', 'owner');
        MailAccess::flush();
        $sql = $this->queries(fn () => $page->call('show', 'overview')->assertSee('Emails')->assertDontSee('Emails (3)'));
        $this->assertSame(0, $this->mailScans($sql));
    }

    public function test_opening_the_emails_tab_again_reads_no_old_mail(): void
    {
        MailSettings::set('open_to', 'everyone');
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $box = $this->mailbox([$sales]);
        for ($i = 0; $i < 12; $i++) {
            $this->message($box, 'ada@kilo.test', 'intelligence@jbspraxis.test');
        }
        $this->artisan('leads:file-emails')->assertSuccessful();
        $this->assertSame(12, DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'email')->count());

        // The first opening checks the lead's past mail once; what is on the timeline is asked in one query, not per message
        $sql = $this->queries(fn () => Livewire::test(EmailsTab::class, ['leadId' => $lead->id])->assertNotDispatched('lead-changed')->assertSee('About 1'));
        $this->assertSame(2, $this->mailScans($sql), 'Filing the past mail, then the list');
        $this->assertLessThanOrEqual(1, count(array_filter($sql, fn ($q) => str_contains($q, 'dedupe_key'))));

        // Opened again with the same contacts and no new mail filed: the list only
        $tab = null;
        $sql = $this->queries(function () use ($lead, &$tab) {
            $tab = Livewire::test(EmailsTab::class, ['leadId' => $lead->id]);
        });
        $this->assertSame(1, $this->mailScans($sql));

        // Email them opens the composer without reading the list again
        $ada = DB::table('client_users')->where('email', 'ada@kilo.test')->value('id');
        $sql = $this->queries(fn () => $tab->call('emailThem', $ada));
        $this->assertSame(0, $this->mailScans($sql));
        $this->assertStringContainsString('mail-compose', json_encode($tab->effects['xjs'] ?? []));

        // A contact added since: their past mail is filed when the tab opens, each line once
        $this->message($box, 'cee@kilo.test', 'intelligence@jbspraxis.test');
        $this->message($box, 'cee@kilo.test', 'intelligence@jbspraxis.test');
        $this->artisan('leads:file-emails')->assertSuccessful();
        Contacts::add($lead, ['name' => 'Cee Test', 'email' => 'cee@kilo.test'], $sales);
        Livewire::test(EmailsTab::class, ['leadId' => $lead->id])->assertDispatched('lead-changed');
        Livewire::test(EmailsTab::class, ['leadId' => $lead->id])->assertNotDispatched('lead-changed');
        $this->assertSame(14, DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'email')->count());
        $this->assertSame(2, DB::table('lead_timeline')->where('organization_id', $lead->id)->where('summary', 'Cee Test emailed Intelligence')->count());
    }

    public function test_the_list_counts_its_strip_in_a_few_queries_and_not_while_searching(): void
    {
        $gerald = $this->staff('gerald', ['sales']);
        $this->actingAs($gerald);
        $owners = [$gerald, $this->staff('ifeoma', ['sales'])];
        foreach ($owners as $k => $u) {
            $this->lead($u, ['name' => "Lead {$k} (test)"], []);
        }
        $screen = Livewire::test(LeadsRegister::class);
        $two = count($this->queries(fn () => $screen->toggle('byOwner')));

        // More owners: the per-owner view costs the same
        foreach (['tunde', 'ngozi', 'emeka'] as $k => $slug) {
            $this->lead($this->staff($slug, ['sales']), ['name' => "Lead more {$k} (test)"], []);
        }
        $screen = Livewire::test(LeadsRegister::class);
        $five = count($this->queries(fn () => $screen->toggle('byOwner')));
        $this->assertSame($two, $five, 'The per-owner view is counted in the same queries for 2 owners or 5');
        $this->assertCount(5, $screen->viewData('perOwner'));

        // Typing a search reads the list, not the strip
        $sql = $this->queries(fn () => $screen->set('search', 'Lead 0'));
        $this->assertSame([], array_values(array_filter($sql, fn ($q) => str_contains($q, '`opportunities`') || str_contains($q, 'distinct'))));
        $this->assertSame(['Lead 0 (test)'], collect($screen->viewData('leads'))->pluck('name')->all());

        // Taking a lead counts the strip again
        $nobody = $this->lead($gerald, ['name' => 'Nobody’s (test)'], []);
        DB::table('organizations')->where('id', $nobody->id)->update(['assigned_to' => null]);
        $screen = Livewire::test(LeadsRegister::class)->assertSee('Numbers for your leads');
        $before = $screen->viewData('numbers')['new']['period'];
        $screen->call('claimLead', $nobody->id);
        $this->assertSame($before + 1, $screen->viewData('numbers')['new']['period']);
    }

    public function test_the_strip_keeps_its_figures(): void
    {
        $gerald = $this->staff('gerald', ['sales']);
        $other = $this->staff('ifeoma', ['sales']);
        $this->actingAs($gerald);
        $lagos = Carbon::now('Africa/Lagos');
        $a = $this->lead($gerald, ['name' => 'Alpha (test)', 'next_step' => 'Call', 'next_step_due' => $lagos->copy()->subDay()->format('Y-m-d')], []);
        $this->lead($gerald, ['name' => 'Bravo (test)'], []);
        $this->lead($other, ['name' => 'Charlie (test)', 'next_step' => 'Visit', 'next_step_due' => $lagos->copy()->addDay()->format('Y-m-d')], []);
        $d = $this->lead($gerald, ['name' => 'Delta (test)'], []);
        DB::table('organizations')->where('id', $d->id)->update(['assigned_to' => null, 'lead_status' => 'client', 'client_via' => 'manual']);
        $opp = Opportunity::create(['organization_id' => $a->id, 'title' => 'Fleet (test)', 'stage' => 'discovery', 'lead_user_id' => $gerald->id, 'language' => 'en', 'source' => 'manual', 'created_by' => $gerald->id]);
        $this->insertRow('proposals', ['id' => (string) Str::uuid(), 'organization_id' => $a->id, 'opportunity_id' => $opp->id, 'proposal_ref' => 'PRP-S-1', 'version' => 1,
            'status' => 'Sent', 'signing_mode' => 'approve', 'subtotal' => 1000, 'total_value' => 1075, 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()]);

        $rows = LeadNumbers::byOwner('all', $gerald);
        $this->assertSame(['Gerald Test', 'Ifeoma Test', 'No owner'], $rows->pluck('name')->all());
        foreach ($rows as $row) {
            $s = LeadNumbers::summary($row->scope, 'all', $gerald);
            $this->assertSame([$s['new']['period'], $s['overdue'], $s['noStep'], $s['conversion'], $s['value']],
                [$row->new, $row->overdue, $row->noStep, $row->conversion, $row->value], "The per-owner row of {$row->name} matches its strip");
        }
        $this->assertSame([2, 2, 1, 1, 0], [$rows[0]->new, $rows[0]->open, $rows[0]->overdue, $rows[0]->noStep, $rows[2]->open]);
        $this->assertSame(['totals' => ['USD' => 1000.0], 'open' => 1, 'priced' => 1], $rows[0]->value);
        $this->assertSame(['clients' => 1, 'leads' => 1, 'rate' => 100.0], $rows[2]->conversion);
    }

    public function test_follow_ups_list_the_first_fifty_later_and_unplanned_leads(): void
    {
        $gerald = $this->staff('gerald', ['sales']);
        $this->actingAs($gerald);
        $lagos = Carbon::now('Africa/Lagos');
        $this->lead($gerald, ['name' => 'Late (test)', 'next_step' => 'Call', 'next_step_due' => $lagos->copy()->subDays(2)->format('Y-m-d')], []);
        for ($i = 0; $i < 55; $i++) {
            $this->insertRow('organizations', ['id' => (string) Str::uuid(), 'name' => sprintf('Unplanned %02d (test)', $i), 'status' => 'Lead', 'lead_status' => 'new',
                'assigned_to' => $gerald->id, 'created_at' => now()->subMinutes(100 - $i), 'updated_at' => now()]);
        }

        $screen = Livewire::test(FollowUps::class);
        $this->assertCount(50, $screen->viewData('groups')['none']);
        $this->assertSame(['overdue' => 1, 'today' => 0, 'week' => 0, 'later' => 0, 'none' => 55], $screen->viewData('counts'));
        $screen->assertSee('No next step (55)')->assertSee('Show 5 more of 55')->assertSee('Unplanned 49 (test)')->assertDontSee('Unplanned 50 (test)')
            ->call('showMore', 'none')->assertSee('Unplanned 54 (test)')->assertDontSee('Show 5 more of 55')->call('showMore', 'nothing');
        $this->assertCount(55, $screen->viewData('groups')['none']);
        $this->assertSame(['Late (test)'], $screen->viewData('groups')['overdue']->pluck('name')->all());
    }

    public function test_add_a_lead_looks_for_duplicates_only_when_the_name_an_email_or_a_phone_changes(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $kilo = $this->lead($sales, ['name' => 'Kilo Logistics Ltd'], [['name' => 'Ada Test', 'email' => 'Ada@Kilo.test']]);

        $form = Livewire::test(AddLead::class)->call('choose', 'blank')->set('lead.name', 'KILO logistics')->assertSee('This may already be in HQ');
        $sql = $this->queries(fn () => $form->set('lead.city', 'Lagos')->set('lead.address', '1 Example Way'));
        $this->assertSame([], array_values(array_filter($sql, fn ($q) => str_contains($q, '`organizations`'))));
        $form->assertSee('This may already be in HQ');

        // The email, in another case: found by the contact's address
        $form->set('lead.name', 'Brand New (test)')->assertDontSee('This may already be in HQ')->set('contacts.0.email', 'ada@KILO.test')
            ->assertSee('This may already be in HQ')->assertSeeHtml(route('hq.crm.lead', ['organization' => $kilo->id]));
    }

    public function test_the_meetings_tab_reads_a_transcript_only_for_the_open_meeting(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $finance = $this->staff('finance', ['finance']);   // Leads access, no transcripts
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $meetings = [];
        foreach (['First', 'Second'] as $i => $title) {
            $meetings[$i] = Meeting::create(['organization_id' => $lead->id, 'kind' => 'client', 'title' => "{$title} talk", 'starts_at' => now()->subDays(5 - $i),
                'ends_at' => now()->subDays(5 - $i)->addHour(), 'status' => 'held', 'transcript_status' => 'ready']);
            MeetingTranscript::create(['meeting_id' => $meetings[$i]->id, 'source' => 'paste', 'text' => "Ada Test: the {$title} transcript.",
                'entries' => [['speaker' => 'Ada Test', 'text' => "the {$title} transcript."]]]);
        }

        $tab = Livewire::test(MeetingsTab::class, ['leadId' => $lead->id]);
        $loaded = fn ($tab) => collect($tab->viewData('past'))->flatMap->transcripts->filter(fn ($t) => array_key_exists('text', $t->getAttributes()))->pluck('meeting_id')->all();
        $this->assertSame([], $loaded($tab), 'Closed meetings: no transcript text read');
        $this->assertFalse(collect($tab->viewData('past'))->flatMap->transcripts->contains(fn ($t) => array_key_exists('entries', $t->getAttributes())));

        $tab->call('toggle', $meetings[0]->id)->assertSee('the First transcript.')->assertDontSee('the Second transcript.');
        $this->assertSame([$meetings[0]->id], $loaded($tab));

        // Without the right to read transcripts, not even the open one's
        $this->actingAs($finance);
        $tab = Livewire::withQueryParams(['meeting' => $meetings[0]->id])->test(MeetingsTab::class, ['leadId' => $lead->id])
            ->assertSee('First talk')->assertDontSee('the First transcript.');
        $this->assertSame([], $loaded($tab));
    }

    public function test_the_meetings_poll_draws_the_tab_only_when_a_run_moved_on(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $m = Meeting::create(['organization_id' => $lead->id, 'kind' => 'client', 'title' => 'Talk', 'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHour(), 'status' => 'held', 'transcript_status' => 'ready']);
        $t = MeetingTranscript::create(['meeting_id' => $m->id, 'source' => 'paste', 'text' => 'Ada Test: hello.']);
        $run = (string) Str::uuid();
        $this->insertRow('ai_runs', ['id' => $run, 'purpose' => 'meeting_notes', 'provider' => 'claude', 'model' => 'test', 'subject_type' => 'meeting_transcript',
            'subject_id' => $t->id, 'status' => 'running', 'progress' => 20, 'created_at' => now(), 'updated_at' => now()]);

        $tab = Livewire::withQueryParams(['meeting' => $m->id])->test(MeetingsTab::class, ['leadId' => $lead->id])->assertSeeHtml('wire:poll.4s="check"');
        $tab->call('check');
        $this->assertArrayNotHasKey('html', $tab->effects, 'Nothing moved: nothing drawn');

        DB::table('ai_runs')->where('id', $run)->update(['progress' => 60]);
        $tab->call('check');
        $this->assertArrayHasKey('html', $tab->effects);

        DB::table('ai_runs')->where('id', $run)->update(['status' => 'done', 'progress' => 100]);
        DB::table('meeting_transcripts')->where('id', $t->id)->update(['notes' => json_encode(['summary' => 'They count stock on paper.'])]);
        $tab->call('check')->assertSee('They count stock on paper.')->assertDontSeeHtml('wire:poll.4s');
    }

    public function test_the_lead_tabs_ask_for_the_opportunity_team_once(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        // Leads access but not opportunities.view: they open only the opportunities they are on
        $slug = 'leads-only-' . Str::lower(Str::random(4));
        $this->insertRow('roles', ['slug' => $slug, 'name' => 'Test ' . $slug, 'audience' => 'staff', 'is_protected' => 0]);
        DB::table('role_permissions')->insert(['role_id' => DB::table('roles')->where('slug', $slug)->value('id'), 'permission_id' => DB::table('permissions')->where('slug', 'crm.view')->value('id')]);
        $member = $this->staff('member', [$slug]);
        $opps = [];
        for ($i = 0; $i < 6; $i++) {
            $opps[$i] = Opportunity::create(['organization_id' => $lead->id, 'title' => "Opportunity {$i} (test)", 'stage' => 'discovery', 'language' => 'en', 'source' => 'manual',
                'lead_user_id' => $i === 5 ? $member->id : $sales->id, 'created_by' => $sales->id]);
            Meeting::create(['opportunity_id' => $opps[$i]->id, 'organization_id' => $lead->id, 'kind' => 'client', 'title' => "Meeting {$i}", 'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(2)->addHour(), 'status' => 'scheduled', 'transcript_status' => 'waiting']);
            if ($i % 2) {
                DB::table('opportunity_members')->insert(['id' => (string) Str::uuid(), 'opportunity_id' => $opps[$i]->id, 'user_id' => $member->id, 'created_at' => now()]);
            }
        }
        $this->actingAs($member);
        $expected = collect($opps)->mapWithKeys(fn ($o) => [$o->id => OpportunityAccess::canView($member, $o)])->all();
        $this->assertSame([false, true, false, true, false, true], array_values($expected));

        $tab = null;
        $sql = $this->queries(function () use ($lead, &$tab) {
            $tab = Livewire::test(OpportunitiesTab::class, ['leadId' => $lead->id]);
        });
        $this->assertSame(1, count(array_filter($sql, fn ($q) => str_contains($q, 'opportunity_members'))));
        $this->assertEquals($expected, $tab->viewData('visible')->all());

        $sql = $this->queries(fn () => Livewire::test(MeetingsTab::class, ['leadId' => $lead->id])
            ->assertSeeHtml(e(route('hq.crm.opportunity', ['opportunity' => $opps[1]->id, 'tab' => 'meetings', 'meeting' => Meeting::where('opportunity_id', $opps[1]->id)->value('id')])))
            ->assertDontSeeHtml(e(route('hq.crm.opportunity', ['opportunity' => $opps[0]->id, 'tab' => 'meetings', 'meeting' => Meeting::where('opportunity_id', $opps[0]->id)->value('id')]))));
        $this->assertSame(1, count(array_filter($sql, fn ($q) => str_contains($q, 'opportunity_members'))));
    }
}
