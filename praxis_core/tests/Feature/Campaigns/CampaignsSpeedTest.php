<?php

namespace Tests\Feature\Campaigns;

use App\Livewire\Hq\Campaigns\Audiences;
use App\Livewire\Hq\Campaigns\BroadcastEditor;
use App\Livewire\Hq\Campaigns\Outreach\OutreachCampaignPage;
use App\Livewire\Hq\Campaigns\Outreach\OutreachReplies;
use App\Models\AiRun;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachReply;
use App\Models\Mail\MailMessage;
use App\Support\Campaigns\Audience;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Outreach\Drafts;
use App\Support\Campaigns\Outreach\ReplyText;
use App\Support\Campaigns\Outreach\Senders;
use App\Support\Campaigns\Outreach\Sorting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Campaigns stay quick as audiences and imports grow: one pass over a segment per screen, saved segments counted once
 * while the builder is used, AI polls that only look at a status until something changed, bulk AI work queued a few
 * jobs at a time, and a reply's dialog that reads only the one body it shows.
 */
class CampaignsSpeedTest extends TestCase
{
    use OutreachFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOutreach();
    }

    /** Queries that read the people of a segment (one per group, plus the do-not-email list) */
    private function peopleQueries(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $n = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `client_users` as `cu`') || str_contains($q['query'], 'from `marketing_subscribers` as `s`'))->count();
        DB::disableQueryLog();

        return $n;
    }

    public function test_one_pass_over_a_segment_gives_its_numbers_and_its_first_people_in_the_same_order(): void
    {
        foreach (['Ada', 'Bola', 'Chidi'] as $n => $first) {
            $org = $this->org(['status' => $n ? 'Lead' : 'Active Client', 'country' => $n === 2 ? 'Cameroon' : 'Nigeria', 'preferred_language' => $n === 2 ? null : 'EN']);
            $this->contact($org, ['first_name' => $first, 'last_name' => 'Test', 'email' => strtolower($first) . '@kilo.test']);
            $this->contact($org, ['first_name' => $first, 'last_name' => 'Test', 'email' => strtolower($first) . '.two@kilo.test']);
        }
        $this->subscriber(['email' => 'bola@kilo.test', 'first_name' => 'Bola']);
        $this->subscriber(['email' => 'sam@kilo.test', 'country' => 'Senegal']);
        $rules = ['who' => ['clients', 'leads', 'subscribers']];

        $preview = Audience::preview($rules, 4);
        $this->assertSame(Audience::summary($rules), $preview['summary']);
        $this->assertSame(Audience::recipients($rules)->take(4)->values()->all(), $preview['recipients']->values()->all());
        $this->assertSame(7, $preview['summary']['total'], 'bola@ once, as a lead’s contact');
        $this->assertSame(['client', 'client', 'lead', 'lead'], $preview['recipients']->pluck('kind')->all());

        // The broadcast editor's Audience step reads the segment once (it read it twice: numbers, then the first 25)
        $this->actingAs($this->staff('sales', ['sales']));
        $broadcast = Broadcasts::create(auth()->user(), 'announcement');
        $broadcast->forceFill(['segment_id' => $this->segment($rules)->id])->save();
        $editor = Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id]);
        $this->assertSame(4, $this->peopleQueries(fn () => $editor->set('step', 'audience')), 'clients, leads, Kaizen writers, subscribers: once each');
        $editor->assertSee('7 people today')->assertSee('chidi@kilo.test')->assertSee('sam@kilo.test');
    }

    public function test_saved_segments_are_counted_once_while_the_builder_is_used(): void
    {
        $this->contact($this->org(['status' => 'Active Client', 'industry' => 'Banking']), ['email' => 'ada@bank.test']);
        $this->contact($this->org(['industry' => 'Banking']), ['email' => 'bola@bank.test']);
        $this->contact($this->org(['industry' => 'Retail']), ['email' => 'chidi@shop.test']);
        $banking = $this->segment(['who' => ['clients', 'leads'], 'filters' => ['industry' => ['Banking']]], 'Banking (test)');
        $this->segment(['who' => ['leads']], 'Leads (test)');
        $this->actingAs($this->staff('sales', ['sales']));

        $page = Livewire::test(Audiences::class)->assertSee('Banking (test)')->assertViewHas('segments', fn ($rows) => $rows->pluck('summary.total')->all() === [2, 2]);

        // Ticking the builder's boxes counts the segment being built, not the saved ones again
        $page->call('newSegment');
        $this->assertSame(1, $this->peopleQueries(fn () => $page->set('segmentWho', ['leads'])));
        $this->assertSame(1, $this->peopleQueries(fn () => $page->set('segmentFilters.industry', ['Retail'])));
        $page->assertViewHas('segmentSummary', fn ($s) => $s['total'] === 1);

        // A segment edited is counted again at once, with the others
        DB::table('client_users')->insert(['id' => (string) Str::uuid(), 'organization_id' => $this->org(['industry' => 'Banking']), 'first_name' => 'Dayo', 'last_name' => 'Test',
            'email' => 'dayo@bank.test', 'status' => 'Active', 'is_subscribed' => 1, 'lifecycle_stage' => 'Lead', 'created_at' => now(), 'updated_at' => now()]);
        $page->call('editSegment', $banking->id)->set('segmentWho', ['leads'])->call('saveSegment')
            ->assertViewHas('segments', fn ($rows) => $rows->pluck('summary.total')->all() === [2, 3]);
        // Leaving the tab and coming back counts them all again
        $page->set('tab', 'suppressed')->set('tab', 'segments')->assertViewHas('segments', fn ($rows) => $rows->pluck('summary.total')->all() === [2, 3]);
    }

    public function test_the_broadcast_editor_checks_a_running_draft_without_redrawing_until_it_is_done(): void
    {
        FakeAi::install()->respond('broadcast_draft', [
            'subject' => 'We have opened in Douala', 'preheader' => '', 'headline' => '',
            'blocks' => [['type' => 'text', 'text' => 'Our team is now in Douala.', 'source' => '', 'label' => '', 'items' => []]],
        ]);
        config(['ai.queue.connection' => 'database']); // the job waits, as on the server
        $this->actingAs($this->staff('sales', ['sales']));
        $broadcast = Broadcasts::create(auth()->user(), 'announcement');

        $editor = Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])
            ->set('brief', 'We have opened an office in Douala; tell clients who to call.')->call('writeWithAi')
            ->assertSeeHtml('wire:poll.2s="checkAi"');
        $runId = $editor->get('aiRunId');
        $this->assertNotNull($runId);

        // Still running: one small query, nothing redrawn
        DB::flushQueryLog();
        DB::enableQueryLog();
        $editor->call('checkAi');
        $runQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '`ai_runs`'))->pluck('query')->all();
        DB::disableQueryLog();
        $this->assertArrayNotHasKey('html', $editor->effects);
        $this->assertSame(['select `status` from `ai_runs` where `ai_runs`.`id` = ? limit 1'], $runQueries);
        $this->assertSame($runId, $editor->get('aiRunId'));

        // Done: applied and drawn
        AiRun::whereKey($runId)->update(['status' => 'done', 'progress' => 100, 'finished_at' => now(), 'output' => json_encode([
            'subject' => 'We have opened in Douala', 'preheader' => '', 'headline' => '',
            'blocks' => [['type' => 'text', 'text' => 'Our team is now in Douala.', 'source' => '', 'label' => '', 'items' => []]],
        ])]);
        $editor->call('checkAi')->assertSet('aiRunId', null)->assertDontSeeHtml('wire:poll.2s="checkAi"')
            ->assertDispatched('praxis-toast', $this->toast('Draft written'));
        $this->assertArrayHasKey('html', $editor->effects);
        $this->assertSame('We have opened in Douala', $broadcast->fresh()->content['en']['subject']);
    }

    public function test_the_outreach_pages_redraw_only_when_an_email_or_a_reply_moved(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $writing = $this->prospect($campaign, 'writing', ['company' => 'Kilo Writing Ltd (test)']);
        $this->prospect($campaign, 'draft');
        $this->actingAs($gerald);

        $page = Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id, 'filter' => 'all'])->assertSeeHtml('wire:poll.4s="tick"');
        $page->call('tick');
        $this->assertArrayNotHasKey('html', $page->effects, 'nothing moved: nothing redrawn');

        // Claude finished one: the next tick draws it
        $this->travel(2)->seconds();
        OutreachEmail::whereKey($this->firstOf($writing)->id)->update(['status' => 'draft', 'subject' => 'Your new depot in Kano']);
        OutreachEmail::whereKey($this->followUpOf($writing)->id)->update(['status' => 'draft']);
        $page->call('tick')->assertSee('Your new depot in Kano')->assertDontSeeHtml('wire:poll');
        $this->assertArrayHasKey('html', $page->effects);

        // Replies: the same while Claude sorts
        $reply = OutreachReply::create(['campaign_id' => $campaign->id, 'prospect_id' => $writing->id, 'kind' => 'reply', 'message_key' => 'k-' . Str::random(8),
            'from_email' => $writing->email, 'from_name' => 'Ada Obi', 'subject' => 'Re: Your new depot in Kano', 'snippet' => 'Call me.', 'received_at' => now(),
            'ai_run_id' => (string) Str::uuid()]);
        $replies = Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->assertSeeHtml('wire:poll.4s="tick"');
        $replies->call('tick');
        $this->assertArrayNotHasKey('html', $replies->effects);
        $this->travel(2)->seconds();
        $reply->forceFill(['ai_run_id' => null, 'ai_label' => 'interested', 'ai_reason' => 'Asks for a call'])->save();
        $replies->call('tick')->assertSee('Asks for a call')->assertDontSeeHtml('wire:poll');
    }

    public function test_bulk_ai_work_is_queued_a_few_jobs_at_a_time(): void
    {
        FakeAi::install();
        config(['ai.queue.connection' => 'database']); // jobs wait for the worker, as on the server
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        foreach (range(1, Drafts::IN_FLIGHT + 3) as $n) {
            $this->prospect($campaign, 'writing');
        }
        $queued = fn (string $purpose) => AiRun::where('purpose', $purpose)->whereIn('status', ['queued', 'running'])->count();

        $this->assertSame(Drafts::IN_FLIGHT, Drafts::writeDue());
        $this->assertSame(Drafts::IN_FLIGHT, $queued(Drafts::PURPOSE));
        $this->assertSame(0, Drafts::writeDue(), 'the worker is still on them');

        // Two finish (here: fail, filed by the next pass); two more start
        AiRun::where('purpose', Drafts::PURPOSE)->limit(2)->get()->each(fn ($run) => $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.busy', 'finished_at' => now()])->save());
        $this->assertSame(2, Drafts::writeDue());
        $this->assertSame(Drafts::IN_FLIGHT, $queued(Drafts::PURPOSE));
        $this->assertSame(2, OutreachEmail::where('kind', 'first')->where('status', 'not_written')->count());

        // Reply sorting is held the same way
        $prospects = $campaign->prospects()->get();
        foreach ($prospects->take(Sorting::IN_FLIGHT + 2) as $p) {
            OutreachReply::create(['campaign_id' => $campaign->id, 'prospect_id' => $p->id, 'kind' => 'reply', 'message_key' => 'k-' . Str::random(8),
                'from_email' => $p->email, 'subject' => 'Re: hello', 'snippet' => 'Tell me more.', 'received_at' => now()]);
        }
        $this->assertSame(Sorting::IN_FLIGHT, Sorting::sortDue());
        $this->assertSame(0, Sorting::sortDue());
        $this->assertSame(Sorting::IN_FLIGHT, $queued(Sorting::PURPOSE));
    }

    public function test_a_replys_dialog_reads_only_the_body_it_shows(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $p = $this->prospect($campaign, 'sent');
        $box = Senders::mailbox($gerald->id);
        $folder = DB::table('mail_folders')->insertGetId(['account_id' => $box->id, 'path' => 'INBOX', 'name' => 'Inbox', 'role' => 'inbox', 'selectable' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        $mid = Str::random(12) . '@mail.test';
        $id = DB::table('mail_messages')->insertGetId(['account_id' => $box->id, 'folder_id' => $folder, 'uid' => 7, 'message_id' => $mid,
            'message_id_hash' => MailMessage::hashId($mid), 'from_email' => $p->email, 'from_name' => 'Ada Obi', 'subject' => 'Re: Onne', 'received_at' => now(),
            'is_outbound' => 0, 'body_state' => 'cached', 'body_text' => 'Yes, call me on Tuesday afternoon.', 'snippet' => 'Yes', 'created_at' => now(), 'updated_at' => now()]);
        $reply = OutreachReply::create(['campaign_id' => $campaign->id, 'prospect_id' => $p->id, 'kind' => 'reply', 'mail_message_id' => $id,
            'message_key' => MailMessage::hashId($mid), 'mail_account_id' => $box->id, 'from_email' => $p->email, 'subject' => 'Re: Onne', 'snippet' => 'Yes', 'received_at' => now()]);

        $copy = ReplyText::copyFor($gerald, $reply);
        $this->assertSame($id, $copy->id);
        $this->assertArrayNotHasKey('body_text', $copy->getAttributes());
        $this->assertArrayNotHasKey('body_html', $copy->getAttributes());
        $this->assertStringContainsString('Yes, call me on Tuesday afternoon.', json_encode(ReplyText::present($gerald, $copy)));

        $this->actingAs($gerald);
        Livewire::test(OutreachReplies::class, ['campaignId' => $campaign->id])->call('openReply', $reply->id)->assertSee('Yes, call me on Tuesday afternoon.');
    }
}
