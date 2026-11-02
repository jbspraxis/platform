<?php

namespace Tests\Feature\Opportunities;

use App\Events\ProposalSent;
use App\Livewire\Hq\Intelligence\ProposalBuilder;
use App\Livewire\Hq\Opportunities\OpportunityPage;
use App\Livewire\Hq\Opportunities\ProposalsTab;
use App\Mail\ProposalMail;
use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\ProposalComment;
use App\Models\User;
use App\Support\Ai\AiRequest;
use App\Support\Opportunities\ProposalPdf;
use App\Support\Opportunities\Proposals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * The opportunity's Proposals tab (docs/OPPORTUNITIES.md section 4, row 4; owner decisions 7 to 9): the AI drafts a
 * version from the opportunity with line items priced from the financial dictionary; it is edited as people go;
 * only the owner or the lead approves it; sending claims it once and attaches the PDF; failed addresses are kept
 * for a retry; versions say what changed; legacy proposals keep the old builder. FakeAi answers: no request reaches
 * Anthropic.
 */
class ProposalsTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private const DRAFT = [
        'title' => 'A faster month-end close',
        'sections' => [
            ['key' => 'executive_summary', 'title' => 'A close in five days is within reach', 'body' => "Your close takes 15 days.\n\nWe recommend three phases."],
            ['key' => 'approach', 'title' => 'We start with the ledger', 'body' => "Phase one\n- Map the close\n- Agree the controls"],
            ['key' => 'scope', 'title' => 'Scope', 'body' => ''],                                   // no text: dropped
        ],
        'suggested_line_items' => [
            ['code' => '#1292P', 'quantity' => 2, 'reason' => 'The close needs a diagnostic first.'],
            ['code' => '#NOPE', 'quantity' => 1, 'reason' => 'Not in the dictionary: dropped.'],
            ['code' => '#1292p', 'quantity' => 5, 'reason' => 'The same code again: dropped.'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
    }

    /** An opportunity led by $lead with a contact and a client meeting whose transcript and notes are filed */
    private function opportunity(User $lead, array $values = []): Opportunity
    {
        $org = $this->organisation(['name' => 'Kilo Pharma', 'country' => 'Nigeria']);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@kilo.test']);
        $o = $this->opportunityFor($org, $values + ['title' => 'Month-end close', 'lead_user_id' => $lead->id, 'stage' => 'questionnaire']);
        $meeting = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => 'Discovery',
            'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 10:00:00', 'status' => 'held', 'transcript_status' => 'ready']);
        $meeting->attendees()->create(['email' => 'cfo@kilo.test', 'name' => 'Bola Ade', 'role' => 'guest']);
        MeetingTranscript::create(['meeting_id' => $meeting->id, 'source' => 'meet_api', 'text' => 'Ngozi (10:01): our month-end close takes 15 days.',
            'notes' => ['summary' => 'The close takes 15 days.', 'decisions' => [], 'needs' => ['A faster close'], 'risks' => [], 'open_questions' => [], 'next_steps' => []],
            'collected_at' => '2026-10-05 10:30:00']);

        return $o;
    }

    /** A version ready to send: started, written, approved */
    private function approved(Opportunity $o, User $by): Proposal
    {
        $p = Proposals::start($o, 'USD', $by);
        Proposals::save($p, ['title' => 'A faster close', 'sections' => [['key' => 'executive_summary', 'title' => 'Summary', 'body' => 'We recommend it.']],
            'lines' => [['code' => '#1292P', 'description' => 'Diagnostic & Audit', 'quantity' => '1 Phase', 'unit_price' => 1000]]], $by);
        $this->assertNull(Proposals::approve($p, $by));

        return $p->fresh();
    }

    public function test_the_lead_drafts_a_proposal_from_the_opportunity_with_prices_from_the_dictionary(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $ai = FakeAi::install()->respond('proposal', self::DRAFT, 30000, 4000, 0, 12000);

        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])
            ->assertSee('Write the proposal')->assertSee('The questionnaire isn’t complete')
            ->set('currency', 'EUR')->set('instructions', 'Keep it to two phases.')
            ->call('start', true)
            ->assertDispatched('opportunity-stage-changed')->assertDispatched('proposals-changed')
            ->assertSet('title', 'A faster month-end close')->assertSee('Suggested by the AI: The close needs a diagnostic first.');

        $p = Proposal::where('opportunity_id', $o->id)->sole();
        $this->assertSame(['approve', 1, Proposal::DRAFT, 'EUR'], [$p->signing_mode, $p->version, $p->status, $p->currency]);
        $this->assertMatchesRegularExpression('/^PRP-20261105-\d{3}$/', $p->proposal_ref);
        $this->assertNull($p->token_hash, 'No legacy signing link');
        $this->assertSame(['executive_summary', 'approach'], array_column($p->sections, 'key'));
        // One line from the dictionary: 2 × 1,000 USD at 0.8909 EUR per dollar; unknown and repeated codes dropped
        $lines = Proposals::lines($p);
        $this->assertSame([['#1292P', '2 Phase', '890.90', '1781.80']], $lines->map(fn ($l) => [$l->code, $l->quantity, $l->unit_price, $l->total_price])->all());
        $this->assertSame(1781.80, (float) $p->subtotal);
        $this->assertSame(1781.80, (float) $p->total_value, 'No VAT: no JBS Praxis company charges VAT yet (VatSwitchTest has the rest)');
        $this->assertFalse($p->vat_applied);
        $this->assertSame('proposal', $o->fresh()->stage);
        $this->assertNull($p->ai_run_id, 'Applied once, then unlinked');

        // The AI read the full transcript (once, here), the questionnaire's place and the dictionary without prices
        $ai->assertRan('proposal', function (AiRequest $r) {
            $system = implode("\n", $r->system);

            return str_contains($system, 'our month-end close takes 15 days') && str_contains($r->prompt, '#1292P | Diagnostic & Audit')
                && ! str_contains($r->prompt, '1000.00') && str_contains($r->prompt, 'Keep it to two phases.');
        });
        $this->assertSame(['PROPOSAL_STARTED', 'PROPOSAL_DRAFT_REQUESTED', 'OPPORTUNITY_STAGE_CHANGED'],
            DB::table('system_audit_logs')->where('target_id', $o->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_a_budget_in_the_leads_instructions_gives_the_ai_the_prices_to_fit_it(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $ai = FakeAi::install()->respond('proposal', self::DRAFT);

        // Owner, 2 Nov 2026 (docs/QA-PORTAL.md D7): a budget caps the draft; the AI sees the prices only then
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])
            ->set('currency', 'EUR')->set('instructions', 'Phase one only, around EUR 2,000 in total.')
            ->call('start', true)->assertHasNoErrors();

        $ai->assertRan('proposal', function (AiRequest $r) {
            return str_contains($r->prompt, '#1292P | Diagnostic & Audit') && str_contains($r->prompt, 'unit price: EUR 890.90')
                && str_contains($r->prompt, 'the lead gave a budget, so the line items must fit within it')
                && str_contains($r->prompt, 'choose the items and quantities so their total stays within that budget');
        });
        $this->assertTrue(Proposals::budgetGiven('Budget de 12 millions de nairas.'));
        $this->assertFalse(Proposals::budgetGiven('Deliver in 3 months, in 2 milestones.'));
    }

    public function test_a_background_draft_is_filed_when_its_job_ends_and_rings_the_lead(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        FakeAi::install()->respond('proposal', self::DRAFT)->refuse('proposal_changes');
        config(['ai.queue.connection' => 'database']); // the job waits for the worker, as on the server
        $bells = fn () => DB::table('notifications')->where('user_id', $lead->id)->orderBy('created_at')->get(['title', 'message', 'link_url']);

        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('start', true)
            ->assertSee('The AI is drafting version 1')->assertSeeHtml('wire:poll.3s="check"');
        $p = Proposal::sole();
        $this->assertSame(Proposals::BLANK_SECTIONS, array_column($p->sections, 'key'));

        // The worker runs it: the draft goes in at once and the lead gets a bell, page open or not
        \App\Support\Ai\Ai::run(AiRun::where('status', 'queued')->sole());
        $this->assertSame(['executive_summary', 'approach'], array_column($p->fresh()->sections, 'key'));
        $this->assertSame([['Proposal drafted: Month-end close', 'Version 1 is ready for you to review and edit.',
            route('hq.crm.opportunity', ['opportunity' => $o->id, 'tab' => 'proposals'])]], $bells()->map(fn ($b) => array_values((array) $b))->all());
        $tab->call('check')->assertSet('title', 'A faster month-end close')->assertDontSeeHtml('wire:poll');

        // A draft that fails rings the lead with why
        FakeAi::install()->refuse('proposal');
        config(['ai.queue.connection' => 'database']);
        $tab->call('openRedraft')->call('draft');
        \App\Support\Ai\Ai::run(AiRun::where('status', 'queued')->sole());
        $failed = $bells()->firstWhere('title', 'Proposal not drafted: Month-end close');
        $this->assertNotNull($failed);
        $this->assertStringStartsWith('The AI couldn’t draft the proposal. ', $failed->message);

        // "What changed" rings no one
        $p->forceFill(['version' => 2, 'previous_version_id' => $p->id])->save(); // stands in for a later version
        FakeAi::install()->refuse('proposal_changes');
        config(['ai.queue.connection' => 'database']);
        Proposals::summariseChanges($p->fresh(), $lead);
        \App\Support\Ai\Ai::run(AiRun::where('status', 'queued')->sole());
        $this->assertCount(2, $bells());
    }

    public function test_a_draft_that_comes_back_empty_fails_and_can_be_tried_again(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        FakeAi::install()->respond('proposal', ['title' => 'x', 'sections' => [['key' => 'a', 'title' => 'No text', 'body' => ' ']], 'suggested_line_items' => []]);

        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('start', true)
            ->assertSee('The AI couldn’t draft the proposal');
        $p = Proposal::where('opportunity_id', $o->id)->sole();
        $this->assertSame('failed', AiRun::find($p->ai_run_id)->status);
        // The blank sections stay, so the lead can write it by hand, or ask again
        $this->assertSame(Proposals::BLANK_SECTIONS, array_column($p->sections, 'key'));
        $tab->call('dismissFailure', 'ai_run_id')->assertDontSee('The AI couldn’t draft the proposal');
        $this->assertNull($p->fresh()->ai_run_id);
    }

    public function test_drafting_with_ai_needs_the_right_to_read_transcripts_and_every_action_its_permission(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        FakeAi::install()->respond('proposal', self::DRAFT);
        DB::table('role_permissions')->where('role_id', DB::table('roles')->where('audience', 'staff')->where('slug', 'sales')->value('id'))
            ->whereIn('permission_id', DB::table('permissions')->where('module', 'opportunities')->where('action', 'transcripts')->pluck('id'))->delete();

        // A colleague who manages opportunities but may not read transcripts: a blank proposal only
        $this->actingAs($this->staff('colleague', ['sales']));
        $runs = AiRun::count();
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])
            ->assertSee('Only someone who can read the meetings’ transcripts')->assertDontSee('Draft with AI')
            ->call('start', true)->assertForbidden();
        $this->assertSame($runs, AiRun::count());
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('start', false)->assertSee('Edit the draft');
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('openRedraft')->call('draft')->assertForbidden();
        // …and doesn't approve: only the owner or the lead
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertDontSee('Approve this version')->call('approve')->assertForbidden();

        // Someone who can only read opportunities changes nothing
        $this->actingAs($this->staff('auditor', ['auditor']));
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertDontSee('Edit the draft')->assertSee('You can read this draft.')
            ->call('addSection')->assertForbidden();
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('send')->assertForbidden();
    }

    public function test_the_opportunitys_team_works_on_the_proposal_and_nobody_else_opens_it(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $p = Proposals::start($o, 'USD', $lead);
        // An engineer has no opportunity permission: on the team they work on it, off it they see nothing
        $member = $this->staff('engineer', ['engineer']);
        $outsider = $this->staff('designer', ['engineer']);
        DB::table('opportunity_members')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'opportunity_id' => $o->id, 'user_id' => $member->id, 'created_at' => now()]);

        $this->actingAs($outsider);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertForbidden();
        $this->get(route('hq.crm.proposal-pdf', ['opportunity' => $o->id, 'proposal' => $p->id]))->assertForbidden();

        $this->actingAs($member);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSee('Edit the draft')
            ->set('sections.0.body', 'We recommend it.')
            ->assertDontSee('Approve this version')->call('approve')->assertForbidden();
        $this->assertSame('We recommend it.', $p->fresh()->sections[0]['body']);
        $this->get(route('hq.crm.proposal-pdf', ['opportunity' => $o->id, 'proposal' => $p->id]))->assertOk();
        // The lead approves; the member sends
        $this->assertNull(Proposals::approve($p->fresh(), $lead));
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('send')->assertSee('Sent.');

        // Taken off the team: the next request on a tab left open (an action or the poll) is refused
        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertOk();
        DB::table('opportunity_members')->where('user_id', $member->id)->delete();
        $tab->call('check')->assertForbidden();
    }

    public function test_the_draft_is_saved_as_people_go_and_the_totals_follow(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $p = Proposals::start($o, 'USD', $lead);

        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])
            ->set('title', 'Close in five days')
            ->set('sections.0.body', "We recommend it.\n\n- One\n- Two")
            ->set('addCode', '#1292P')->call('addDictionaryLine')
            ->set('lines.0.quantity', '3 phases')
            ->call('addCustomLine')->set('lines.1.description', 'Travel')->set('lines.1.unit_price', '250');
        $p->refresh();
        $this->assertSame('Close in five days', $p->title);
        $this->assertSame("We recommend it.\n\n- One\n- Two", $p->sections[0]['body']);
        $this->assertSame(3250.0, (float) $p->subtotal);
        $this->assertSame(3250.0, (float) $p->total_value, 'No VAT while the company doesn’t charge it');
        $tab->assertSee("USD 3,250.00")->assertDontSee('VAT (');

        // A new currency prices the dictionary's items in it; the lead's own items keep their price
        $tab->set('currency', 'EUR');
        $this->assertSame(['890.90', '250.00'], Proposals::lines($p)->pluck('unit_price')->all());

        // Sections: add, move, remove; at most MAX_SECTIONS
        $tab->call('addSection')->set('sections.5.title', 'Risks')->call('moveSection', 5, -1)->call('removeSection', 1);
        $this->assertSame(['executive_summary', 'scope', 'team', 'section_6', 'timeline'], array_column($p->fresh()->sections, 'key'));
        $this->assertSame('Risks', $p->fresh()->sections[3]['title']);

        // A version that was approved meanwhile isn't changed by a page left open
        $this->assertNull(Proposals::approve($p->fresh(), $lead));
        $tab->set('title', 'Too late')->assertSee('Your last change wasn’t saved')->assertDontSee('Edit the draft');
        $this->assertSame('Close in five days', $p->fresh()->title);
    }

    public function test_only_the_owner_or_the_lead_approves_and_only_a_written_version(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $p = Proposals::start($o, 'USD', $lead);
        $this->actingAs($lead);

        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('approve')->assertSee('Give it a title and write at least one section first.');
        $this->assertNull($p->fresh()->approved_at);

        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->set('sections.0.body', 'We recommend it.')
            ->assertSee('The owner or the opportunity’s lead (Lead Test) approves it')->call('approve')->assertForbidden();

        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('approve')
            ->assertSee('Approved. Choose who it goes to, then send it.')->assertSee('Approved by Owner Test')
            ->assertSet('sendTo', ['ngozi@kilo.test'])->assertDontSee('Edit the draft');
        $this->assertSame($owner->id, $p->fresh()->approved_by);

        // Back to draft, by the lead, and approved again
        $this->actingAs($lead);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('reopen')->assertSee('Back to draft: you can change it again.')->assertSee('Edit the draft')
            ->call('approve')->assertHasNoErrors();
        $this->assertSame($lead->id, $p->fresh()->approved_by);
        $this->assertSame(['PROPOSAL_STARTED', 'PROPOSAL_APPROVED', 'PROPOSAL_REOPENED', 'PROPOSAL_APPROVED'],
            DB::table('system_audit_logs')->where('target_id', $o->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_sending_claims_the_version_once_attaches_the_pdf_and_only_reaches_people_hq_knows(): void
    {
        Event::fake([ProposalSent::class]);
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $p = $this->approved($o, $lead);

        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])
            ->assertSee('Ngozi Eze')->assertSee('cfo@kilo.test')
            ->set('sendTo', ['stranger@elsewhere.test'])->call('send')->assertHasErrors(['sendTo.0'])
            ->set('sendTo', [])->call('send')->assertHasErrors(['sendTo'])
            ->set('sendTo', ['ngozi@kilo.test', 'cfo@kilo.test'])->call('send')
            ->assertSee('Sent. The lead is told')->assertDispatched('opportunity-stage-changed');

        $p->refresh();
        $this->assertSame(Proposal::SENT, $p->status);
        $this->assertSame(['ngozi@kilo.test', 'cfo@kilo.test'], array_column($p->recipients, 'email'));
        $this->assertSame('proposal_sent', $o->fresh()->stage);
        Mail::assertSent(ProposalMail::class, 2);
        $mail = Mail::sent(ProposalMail::class, fn (ProposalMail $m) => $m->hasTo('ngozi@kilo.test'))->sole();
        $this->assertStringStartsWith('%PDF-', (string) $mail->pdf);
        $this->assertNotNull($mail->clientUserId);
        $this->assertSame('en', $mail->locale);
        Event::assertDispatched(ProposalSent::class, fn ($e) => $e->proposalId === $p->id);

        // A second send (a double click, or a colleague at the same moment) sends nothing
        try {
            Proposals::send($p, ['ngozi@kilo.test'], $lead);
            $this->fail('The proposal was sent twice');
        } catch (LogicException) {
        }
        Mail::assertSent(ProposalMail::class, 2);
    }

    public function test_an_address_that_failed_is_kept_and_sent_again(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $p = $this->approved($o, $lead);
        // A real (array) mailer where the CFO's mailbox refuses mail until it is fixed
        Mail::swap(new MailManager($this->app));
        $failing = ['cfo@kilo.test'];
        $delivered = [];
        Event::listen(MessageSending::class, function (MessageSending $e) use (&$failing, &$delivered) {
            $to = $e->message->getTo()[0]->getAddress();
            if (in_array($to, $failing, true)) {
                throw new RuntimeException('Mailbox unavailable');
            }
            $delivered[] = [$to, $e->message->getAttachments()[0]->getContentType(), $e->message->getAttachments()[0]->getFilename()];
        });

        // Nobody reached: nothing counts as sent, and it can be sent again as it is
        $failing = ['ngozi@kilo.test', 'cfo@kilo.test'];
        $this->assertSame(['ngozi@kilo.test', 'cfo@kilo.test'], Proposals::send($p, ['ngozi@kilo.test', 'cfo@kilo.test'], $lead));
        $this->assertSame([Proposal::DRAFT, null], [$p->fresh()->status, $p->fresh()->sent_at]);

        $failing = ['cfo@kilo.test'];
        $this->assertSame(['cfo@kilo.test'], Proposals::send($p, ['ngozi@kilo.test', 'cfo@kilo.test'], $lead));
        $this->assertSame(['cfo@kilo.test'], $p->fresh()->recipients_unsent);
        $this->assertSame([['ngozi@kilo.test', 'application/pdf', ProposalPdf::filename($p)]], $delivered);

        $failing = [];
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSee('Not reached: cfo@kilo.test.')
            ->call('resend')->assertSee('Sent.')->assertDontSee('Not reached');
        $this->assertSame(['ngozi@kilo.test', 'cfo@kilo.test'], array_column($delivered, 0));
        $this->assertNull($p->fresh()->recipients_unsent);
    }

    public function test_the_next_version_starts_from_the_one_the_client_has_and_says_what_changed(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $v1 = $this->approved($o, $lead);
        Proposals::send($v1, ['ngozi@kilo.test'], $lead);
        $person = ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'];
        Proposals::comment($v1->fresh(), 'executive_summary', $person, 'Can it start in January?');
        Proposals::requestChanges($v1->fresh(), $person, 'Please add training.');
        $this->assertSame('proposal', $o->fresh()->stage);
        $ai = FakeAi::install()->respond('proposal_changes', ['summary' => 'We moved the start and added training.', 'changes' => [
            ['section_key' => 'executive_summary', 'change' => 'Starts in January'], ['section_key' => 'line_items', 'change' => 'Two training days added'],
        ]]);

        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])
            ->assertSee('The client asked for changes.')->assertSee('Please add training.')->assertSee('Can it start in January?')
            ->call('newVersion')->assertSee('Version 2 started from the previous one.')
            ->assertSee('What changed since version 1')->assertSee('Can it start in January?');
        $v2 = Proposals::versions($o)->first();
        $this->assertSame([2, $v1->id, 'A faster close', 1], [$v2->version, $v2->previous_version_id, $v2->title, Proposals::lines($v2)->count()]);

        // Approving needs "what changed"; the AI drafts it (Haiku), the lead can edit it
        $tab->call('approve')->assertSee('Say what changed since the previous version first')
            ->call('summariseChanges')
            ->assertSet('changesSummary', "We moved the start and added training.\n\n- Summary: Starts in January\n- Investment: Two training days added");
        $ai->assertRan('proposal_changes', fn (AiRequest $r) => str_contains($r->prompt, 'THE VERSION THE CLIENT HAS (version 1)') && $r->model === 'claude-haiku-5-5');
        $tab->call('approve')->assertHasNoErrors()->call('send');

        $this->assertSame([Proposal::SUPERSEDED, Proposal::SENT], [$v1->fresh()->status, $v2->fresh()->status]);
        Mail::assertSent(ProposalMail::class, fn (ProposalMail $m) => $m->proposal->version === 2
            && str_contains($m->renderParts()['html'], 'Two training days added') && $m->renderParts()['subject'] === 'Our proposal, version 2: A faster close');
        // The version switcher shows both
        $tab->call('show', $v1->id)->assertSee('Replaced by a later version');
    }

    public function test_a_draft_version_can_be_discarded_and_the_page_counts_versions(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $v1 = $this->approved($o, $lead);
        Proposals::send($v1, ['ngozi@kilo.test'], $lead);
        $v2 = Proposals::newVersion($o, $lead);
        $this->assertNull(Proposals::newVersion($o, $lead), 'One draft at a time');

        Livewire::test(OpportunityPage::class, ['opportunity' => $o])->assertSee('Proposals (2)');
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('discard')->assertSee('The draft of version 2 was discarded.')
            ->assertDispatched('proposals-changed');
        $this->assertSoftDeleted($v2);
        $this->assertSame([1], Proposals::versions($o)->pluck('version')->all());

        // An approved draft: not by someone who only edits; the lead (or the owner) can
        $v3 = Proposals::newVersion($o, $lead);
        Proposals::save($v3, ['title' => 'A faster close', 'sections' => $v3->sections, 'lines' => [], 'changes_summary' => 'Shorter.'], $lead);
        $this->assertNull(Proposals::approve($v3, $lead));
        $this->assertFalse(Proposals::discard($v3->fresh(), $lead, mayApprove: false));
        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('discard')->assertSee('This draft can’t be discarded');
        $this->assertNotSoftDeleted($v3);
        $this->actingAs($lead);
        // (numbered 2 again: the draft discarded above never reached the client)
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('discard')->assertSee('The draft of version 2 was discarded.');
        $this->assertSoftDeleted($v3);
        // A version that was sent is never discarded
        $this->assertFalse(Proposals::discard($v1->fresh(), $lead, mayApprove: true));
    }

    public function test_once_the_client_approves_a_version_no_other_is_approved_sent_or_started(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $person = ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'];
        $v1 = $this->approved($o, $lead);
        Proposals::send($v1, ['ngozi@kilo.test'], $lead);
        // Version 2 is written and approved while the client still has version 1
        $v2 = Proposals::newVersion($o, $lead);
        Proposals::save($v2, ['title' => 'A faster close', 'sections' => $v2->sections, 'lines' => [], 'changes_summary' => 'Training added.'], $lead);
        $this->assertNull(Proposals::approve($v2, $lead));

        // The client approves version 1: version 2 is set aside and can't go out
        $this->assertTrue(Proposals::approveForClient($v1->fresh(), $person, 'Finance director'));
        $this->assertSame([Proposal::CLIENT_APPROVED, Proposal::SUPERSEDED], [$v1->fresh()->status, $v2->fresh()->status]);
        try {
            Proposals::send($v2->fresh(), ['ngozi@kilo.test'], $lead);
            $this->fail('A version was sent after the client approved another');
        } catch (LogicException) {
        }
        $this->assertSame('client_approved', Proposals::approve($v2->fresh(), $lead));
        $this->assertNull(Proposals::newVersion($o, $lead));
        Mail::assertSent(ProposalMail::class, 1);
        // Nothing moves the stage back from Proposal approved
        $this->assertFalse(Proposals::requestChanges($v1->fresh(), $person, 'More?'));
        $this->assertSame('proposal_approved', $o->fresh()->stage);

        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('show', $v2->id)
            ->assertSee('Set aside')->assertSee('The client approved version 1, so this version was set aside.')->assertDontSee('Start version 3');
    }

    public function test_a_stale_page_can_not_approve_a_version_the_client_no_longer_has(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $person = ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'];
        $v1 = $this->approved($o, $lead);
        Proposals::send($v1, ['ngozi@kilo.test'], $lead);
        $v2 = Proposals::newVersion($o, $lead);
        Proposals::save($v2, ['title' => 'A faster close', 'sections' => $v2->sections, 'lines' => [], 'changes_summary' => 'Training added.'], $lead);
        Proposals::approve($v2, $lead);
        Proposals::send($v2->fresh(), ['ngozi@kilo.test'], $lead);

        // Version 1's page, still open: replaced, so neither approval nor changes
        $this->assertFalse(Proposals::approveForClient($v1->fresh(), $person, 'Finance director'));
        $this->assertFalse(Proposals::requestChanges($v1->fresh(), $person, 'Hm'));
        $this->assertTrue(Proposals::approveForClient($v2->fresh(), $person, 'Finance director'));
        $this->assertSame(1, Proposal::where('opportunity_id', $o->id)->where('status', Proposal::CLIENT_APPROVED)->count());
    }

    public function test_the_unsent_list_is_right_at_every_moment_of_a_send(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $p = $this->approved($o, $lead);
        // What a request cut short at each email would leave to retry
        Mail::swap(new MailManager($this->app));
        $seen = [];
        Event::listen(MessageSending::class, function (MessageSending $e) use ($p, &$seen) {
            $seen[$e->message->getTo()[0]->getAddress()] = Proposal::find($p->id)->recipients_unsent;
        });

        $this->assertSame([], Proposals::send($p, ['ngozi@kilo.test', 'cfo@kilo.test'], $lead));
        $this->assertSame(['ngozi@kilo.test' => ['ngozi@kilo.test', 'cfo@kilo.test'], 'cfo@kilo.test' => ['cfo@kilo.test']], $seen);
        $this->assertNull($p->fresh()->recipients_unsent);
    }

    public function test_the_link_is_sent_again_to_people_hq_knows_once_at_a_time(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $p = $this->approved($o, $lead);
        Proposals::send($p, ['ngozi@kilo.test'], $lead);

        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSee('Send the link again')->assertSet('linkTo', ['ngozi@kilo.test'])
            ->set('linkTo', ['stranger@elsewhere.test'])->call('sendLinkAgain')->assertHasErrors(['linkTo.0'])
            ->set('linkTo', ['ngozi@kilo.test', 'cfo@kilo.test'])->call('sendLinkAgain')->assertSee('The link was sent again.');
        Mail::assertSent(ProposalMail::class, 3);
        $this->assertSame(['ngozi@kilo.test', 'cfo@kilo.test'], array_column($p->fresh()->recipients, 'email'));
        $this->assertNull($p->fresh()->recipients_unsent);

        // While another send of this version runs, nothing goes
        $busy = \Illuminate\Support\Facades\Cache::lock('proposal-mail:' . $p->id, 600);
        $this->assertTrue($busy->get());
        try {
            Proposals::sendAgain($p->fresh(), ['ngozi@kilo.test'], $lead);
            $this->fail('Sent while another send of the version was running');
        } catch (LogicException) {
        }
        $busy->release();
        Mail::assertSent(ProposalMail::class, 3);

        // Not once its validity is over
        $this->travel(Proposals::VALIDITY_DAYS + 1)->days();
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertDontSee('Send the link again')
            ->set('linkTo', ['ngozi@kilo.test'])->call('sendLinkAgain')->assertHasErrors(['linkTo']);
        Mail::assertSent(ProposalMail::class, 3);
    }

    public function test_legacy_proposals_keep_the_old_builder_and_a_v2_proposal_never_opens_there(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $o = $this->opportunity($owner);
        $legacy = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('proposals', ['id' => $legacy, 'organization_id' => $o->organization_id, 'opportunity_id' => $o->id, 'proposal_ref' => 'SOW-20261001-111',
            'title' => 'Old proposal', 'status' => 'Pending Auth', 'signing_mode' => 'legacy', 'token_hash' => 'TKN-LEGACY', 'payment_schedule_type' => '40_30_30', 'created_at' => now()]);
        $p = Proposals::start($o, 'USD', $owner);

        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSee('Earlier proposals')->assertSee('SOW-20261001-111');
        Livewire::test(ProposalBuilder::class, ['id' => $p->id])
            ->assertRedirect(route('hq.crm.opportunity', ['opportunity' => $o->id, 'tab' => 'proposals', 'version' => $p->id]));
        Livewire::test(ProposalBuilder::class)->call('loadExistingProposal', $p->id)->assertRedirect();
        // The legacy one opens as before
        Livewire::test(ProposalBuilder::class, ['id' => $legacy])->assertSet('proposalId', $legacy)->assertNoRedirect();
    }

    public function test_the_pdf_opens_in_hq_for_people_who_see_opportunities_and_never_as_a_draft_in_the_portal(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead, ['language' => 'fr']);
        $this->actingAs($lead);
        $p = $this->approved($o, $lead);

        $pdf = $this->get(route('hq.crm.proposal-pdf', ['opportunity' => $o->id, 'proposal' => $p->id]));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get(route('hq.crm.proposal-pdf', ['opportunity' => \Illuminate\Support\Str::uuid(), 'proposal' => $p->id]))->assertNotFound();
        $this->actingAs($this->staff('engineer', ['engineer']));
        $this->get(route('hq.crm.proposal-pdf', ['opportunity' => $o->id, 'proposal' => $p->id]))->assertForbidden();

        // Portal: signed, and only once sent
        $signed = \App\Support\Opportunities\PortalLinks::proposalPdf($p);
        $this->get(preg_replace('~^https?://[^/]+~', '', $signed))->assertNotFound();
        $this->get(route('portal.proposals.pdf', ['proposal' => $p->id]))->assertForbidden();
        Proposals::send($p, ['ngozi@kilo.test'], $lead);
        $this->get(preg_replace('~^https?://[^/]+~', '', \App\Support\Opportunities\PortalLinks::proposalPdf($p)))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="' . ProposalPdf::filename($p) . '"');
    }

    public function test_the_email_and_the_pdf_speak_the_opportunitys_language(): void
    {
        foreach (ProposalMail::samples() as $name => $mail) {
            foreach (['en', 'fr'] as $locale) {
                $parts = (clone $mail)->locale($locale)->renderParts();
                $this->assertNotEmpty($parts['subject'], "{$name} {$locale}");
                $this->assertStringContainsString('/proposals/', $parts['html']);
            }
        }
        $fr = ProposalMail::samples()['version']->locale('fr')->renderParts();
        $this->assertSame("Notre proposition, version 2\u{00A0}: A faster month-end close for the Lagos entities", $fr['subject']);
        $this->assertStringContainsString('Ce qui a changé depuis la version 1', $fr['html']);
        $this->assertStringContainsString("26\u{202F}875,00\u{00A0}USD", $fr['html']);
    }

    public function test_english_and_french_have_the_same_strings(): void
    {
        foreach (['proposals', 'emails/proposal'] as $file) {
            $en = array_keys(Arr::dot(require lang_path("en/{$file}.php")));
            $fr = array_keys(Arr::dot(require lang_path("fr/{$file}.php")));
            sort($en);
            sort($fr);
            $this->assertSame($en, $fr, $file);
        }
    }

    public function test_comments_are_resolved_in_hq(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunity($lead);
        $this->actingAs($lead);
        $p = $this->approved($o, $lead);
        Proposals::send($p, ['ngozi@kilo.test'], $lead);
        $c = Proposals::comment($p->fresh(), 'executive_summary', ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'Shorter, please.');
        $other = ProposalComment::create(['proposal_id' => Proposals::start($this->opportunityFor($this->organisation(), ['lead_user_id' => $lead->id]), 'USD', $lead)->id, 'section_key' => 'general',
            'author_type' => 'client', 'author_name' => 'X', 'body' => 'Another opportunity']);

        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSee('Shorter, please.')->call('resolve', $c->id)->assertSee('Dealt with')
            ->call('resolve', $c->id, false)->assertDontSee('Dealt with');
        $this->assertNull($c->fresh()->resolved_at);
        // Only comments of this opportunity's proposals
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('resolve', $other->id);
    }
}
