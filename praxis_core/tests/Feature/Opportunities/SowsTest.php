<?php

namespace Tests\Feature\Opportunities;

use App\Events\ProjectCreatedFromOpportunity;
use App\Events\SowSigned;
use App\Livewire\Hq\Opportunities\OpportunityPage;
use App\Livewire\Hq\Opportunities\ProjectDraftTab;
use App\Livewire\Hq\Opportunities\SowTab;
use App\Livewire\Portal\SowReview;
use App\Mail\SigningCode;
use App\Mail\SowMail;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\ProjectDraft;
use App\Models\Proposal;
use App\Models\StatementOfWork;
use App\Models\User;
use App\Support\Ai\AiRequest;
use App\Support\Opportunities\ProjectDrafts;
use App\Support\Opportunities\Proposals;
use App\Support\Opportunities\SowPdf;
use App\Support\Opportunities\Sows;
use App\Support\Signing\SigningCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * The SOW and the project (docs/OPPORTUNITIES.md section 4, row 5, with the owner's change of 1 Nov 2026: milestones,
 * no sprints). The AI drafts the SOW from the client-approved proposal; the owner or the lead approves it by signing for
 * JBS Praxis; it is sent with the PDF; an active contact signs it in the portal with the one-time code, which raises the
 * deposit invoice on the project made for it, makes the opportunity Won and queues the project draft; the lead edits
 * the draft and Create project writes the milestones and tasks through PR 7's classes. FakeAi answers: no request
 * reaches Anthropic.
 */
class SowsTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const DRAFT = [
        'title' => 'A faster month-end close',
        'sections' => [
            ['key' => 'purpose', 'title' => 'Parties and purpose', 'body' => 'JBS Praxis and Kilo Pharma agree this statement of work.'],
            ['key' => 'scope', 'title' => 'Scope', 'body' => "In scope\n- The ledger\n- The close"],
            ['key' => 'timeline', 'title' => 'Timeline', 'body' => ''],                       // no text: dropped
        ],
        'milestones' => [
            ['key' => 'diagnostic', 'title' => 'Diagnostic', 'deliverables' => "- A map of the close\n- Accepted by the CFO", 'weeks' => 2, 'weight' => 40],
            ['key' => 'build', 'title' => 'Build and handover', 'deliverables' => '- The new close, run once with the team', 'weeks' => 3, 'weight' => 60],
        ],
    ];

    private const PLAN = [
        'milestones' => [
            ['milestone_number' => 1, 'goal' => 'An agreed map of the close', 'tasks' => [
                ['title' => 'Interview the finance team', 'description' => '- Five interviews', 'role_key' => 'analyst', 'estimate_hours' => 12],
                ['title' => 'Map the month-end close', 'description' => '- Map signed off', 'role_key' => 'analyst', 'estimate_hours' => 16],
            ]],
            ['milestone_number' => 2, 'goal' => 'The new close, run once', 'tasks' => [
                ['title' => 'Configure the ledger', 'description' => '- Tested', 'role_key' => 'consultant', 'estimate_hours' => 30],
            ]],
            ['milestone_number' => 9, 'goal' => 'Not a milestone', 'tasks' => [['title' => 'Dropped', 'description' => '', 'role_key' => 'x', 'estimate_hours' => 1]]],
        ],
        'roles' => [
            ['key' => 'analyst', 'title' => 'Business analyst', 'responsibilities' => 'Interviews and maps.'],
            ['key' => 'consultant', 'title' => 'Finance consultant', 'responsibilities' => 'The ledger.'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC')); // a Thursday
        Mail::fake();
    }

    /** An opportunity led by $lead whose client approved version 1 of the proposal (USD 1,000 + 7.5% VAT, 40/30/30) */
    private function approvedOpportunity(User $lead): Opportunity
    {
        $org = $this->organisation(['name' => 'Kilo Pharma', 'country' => 'Nigeria']);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@kilo.test']);
        $o = $this->opportunityFor($org, ['title' => 'Month-end close', 'lead_user_id' => $lead->id, 'stage' => 'proposal']);
        $meeting = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => 'Discovery',
            'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 10:00:00', 'status' => 'held', 'transcript_status' => 'ready']);
        $meeting->attendees()->create(['email' => 'cfo@kilo.test', 'name' => 'Bola Ade', 'role' => 'guest']);
        MeetingTranscript::create(['meeting_id' => $meeting->id, 'source' => 'meet_api', 'text' => 'Ngozi (10:01): our close takes 15 days, secret detail.',
            'notes' => ['summary' => 'The close takes 15 days.', 'decisions' => [], 'needs' => ['A faster close'], 'risks' => [], 'open_questions' => [], 'next_steps' => []],
            'collected_at' => '2026-10-05 10:30:00']);

        $p = Proposals::start($o, 'USD', $lead);
        Proposals::save($p, ['title' => 'A faster close', 'sections' => [['key' => 'executive_summary', 'title' => 'Summary', 'body' => 'We recommend it.']],
            'lines' => [['code' => '#1292P', 'description' => 'Diagnostic & Audit', 'quantity' => '1 Phase', 'unit_price' => 1000]]], $lead);
        $this->assertNull(Proposals::approve($p, $lead));
        Proposals::send($p->fresh(), ['ngozi@kilo.test'], $lead);
        $this->assertTrue(Proposals::approveForClient($p->fresh(), ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'CFO'));

        return $o->fresh();
    }

    /** Version 1 written by hand, two milestones (40% and 60%), approved and signed for JBS Praxis by $by */
    private function approvedSow(Opportunity $o, User $by): StatementOfWork
    {
        $sow = Sows::start($o, $by);
        Sows::save($sow, ['title' => 'A faster close', 'sections' => [['key' => 'purpose', 'title' => 'Purpose', 'body' => 'The work.']],
            'milestones' => [['key' => 'diagnostic', 'title' => 'Diagnostic', 'description' => 'A map', 'weeks' => 2, 'weight' => 40],
                ['key' => 'build', 'title' => 'Build', 'description' => 'The close', 'weeks' => 3, 'weight' => 60]]], $by);
        $this->assertNull(Sows::approve($sow, $by, 'Lead Test', self::PNG, '203.0.113.5'));

        return $sow->fresh();
    }

    /** Sent to Ngozi, then signed by her in the portal with the code */
    private function signedSow(Opportunity $o, User $lead): StatementOfWork
    {
        $sow = $this->approvedSow($o, $lead);
        $this->assertSame([], Sows::send($sow, ['ngozi@kilo.test'], $lead));
        $page = Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')
            ->set('name', 'Ngozi Eze')->set('email', 'ngozi@kilo.test')->set('jobTitle', 'CFO')->set('signature', self::PNG)
            ->call('requestCode')->assertHasNoErrors()->assertSet('step', 'code');
        $page->set('code', (string) StatementOfWork::find($sow->id)->auth_otp)->call('verify')->assertHasNoErrors()->assertSet('done', 'signed');

        return $sow->fresh();
    }

    public function test_the_lead_drafts_the_sow_from_the_approved_proposal_with_the_notes_not_the_transcripts(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $ai = FakeAi::install()->respond('sow', self::DRAFT);

        Livewire::test(SowTab::class, ['opportunityId' => $o->id])
            ->assertSee('Write the statement of work')->assertSee('which Ngozi Eze approved')
            ->set('instructions', 'Two milestones only.')->call('start', true)
            ->assertDispatched('sow-changed')->assertSet('title', 'A faster month-end close')
            ->assertSee('Weights: 100% of 100%')->assertSee("Amounts: USD\u{00A0}1,075.00 of USD\u{00A0}1,075.00");

        $sow = StatementOfWork::where('opportunity_id', $o->id)->sole();
        $this->assertSame([1, 'draft', 'Client Approved'], [$sow->version, $sow->status, $sow->proposal->status]);
        $this->assertMatchesRegularExpression('/^SOW-20261105-\d{3}$/', $sow->reference);
        $this->assertSame(['purpose', 'scope'], array_column($sow->sections, 'key'));
        $this->assertSame([['diagnostic', 'Diagnostic', 2, 40.0], ['build', 'Build and handover', 3, 60.0]],
            array_map(fn ($m) => [$m['key'], $m['title'], $m['weeks'], (float) $m['weight']], $sow->milestones));
        $this->assertSame('sow', $o->fresh()->stage);
        // Notes, the approved proposal and the fees' terms; no transcript, no price for the AI to restate
        $ai->assertRan('sow', function (AiRequest $r) {
            $system = implode("\n", $r->system);

            return str_contains($system, 'The close takes 15 days.') && ! str_contains($system, 'secret detail') && str_contains($system, 'APPROVED PROPOSAL')
                && str_contains($r->prompt, 'JBS Praxis LLC (“JBS Praxis”) and Kilo Pharma') && str_contains($r->prompt, 'Never write a price')
                && str_contains($r->prompt, 'Two milestones only.');
        });

        // Ticking "full transcripts" sends them, once
        $ai = FakeAi::install()->respond('sow', self::DRAFT);
        Sows::draft($sow, '', true, $lead);
        $ai->assertRan('sow', fn (AiRequest $r) => str_contains(implode("\n", $r->system), 'secret detail'));
    }

    public function test_no_sow_before_the_client_approves_a_proposal(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityFor($this->organisation(), ['lead_user_id' => $lead->id]);

        $this->assertNull(Sows::start($o, $lead));
        Livewire::test(SowTab::class, ['opportunityId' => $o->id])->assertSee('No proposal has been approved yet')->assertDontSee('Draft with AI');
        $this->assertSame(0, StatementOfWork::count());
    }

    public function test_only_the_owner_or_the_lead_approves_by_signing_and_the_rules_hold(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $member = $this->staff('member', ['sales']);
        $o = $this->approvedOpportunity($lead);
        $sow = Sows::start($o, $lead);
        Sows::save($sow, ['title' => 'A faster close', 'sections' => [['key' => 'purpose', 'title' => 'Purpose', 'body' => 'The work.']],
            'milestones' => [['title' => 'Diagnostic', 'weeks' => 2, 'weight' => 40], ['title' => 'Build', 'weeks' => 3, 'weight' => 50]]], $lead);

        // A colleague who manages opportunities edits, but doesn't approve
        $this->actingAs($member);
        Livewire::test(SowTab::class, ['opportunityId' => $o->id])->assertSee('Approve and sign for JBS Praxis')->assertDontSee('Your signature')
            ->set('signature', self::PNG)->call('approve')->assertForbidden();

        // The lead: weights must make 100%, a signature is needed, and the legal name must be set
        $this->actingAs($lead);
        $tab = Livewire::test(SowTab::class, ['opportunityId' => $o->id])->assertSee('Weights: 90% of 100%')
            ->call('approve')->assertHasErrors('signature')
            ->set('signature', self::PNG)->call('approve')->assertSee('The milestones’ weights must add up to exactly 100%.');
        $tab->set('milestones.1.weight', 60)->assertSee('Weights: 100% of 100%');

        DB::table('website_settings')->insert(['key' => 'company', 'value' => json_encode(['legal_name' => null]), 'created_at' => now(), 'updated_at' => now()]);
        Cache::flush();
        $tab->call('approve')->assertSee('JBS Praxis’s legal name isn’t set');
        $this->assertNull($sow->fresh()->approved_at);
        DB::table('website_settings')->where('key', 'company')->delete();
        Cache::flush();

        $tab->set('signature', self::PNG)->set('signerName', 'Tom Lead')->call('approve')->assertHasNoErrors()->assertSee('Approved and signed for JBS Praxis');
        $sow->refresh();
        $this->assertSame(['Tom Lead', 'JBS Praxis LLC', self::PNG], [$sow->hq_signer_name, $sow->provider_legal_name, $sow->hq_signature_b64]);
        $this->assertStringStartsWith('SHA256-', $sow->hq_signature_hash);
        $this->assertNotNull($sow->hq_signed_at);
        $this->assertFalse($sow->isEditable(), 'Approved: no longer changes');

        // Back to draft removes HQ's signature
        $tab->call('reopen');
        $sow->refresh();
        $this->assertSame([null, null, null], [$sow->approved_at, $sow->hq_signature_hash, $sow->provider_legal_name]);
    }

    public function test_sending_attaches_the_pdf_and_the_link_to_sign_in_the_opportunitys_language(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $o->forceFill(['language' => 'fr'])->save();
        $sow = $this->approvedSow($o, $lead);

        Livewire::test(SowTab::class, ['opportunityId' => $o->id])->assertSet('sendTo', ['ngozi@kilo.test'])
            ->call('send')->assertHasNoErrors()->assertSee('The statement of work is on its way.')->assertDispatched('opportunity-stage-changed');

        $sow->refresh();
        $this->assertSame([StatementOfWork::SENT, null], [$sow->status, $sow->recipients_unsent]);
        $this->assertSame('sow_sent', $o->fresh()->stage);
        Mail::assertSent(SowMail::class, function (SowMail $mail) use ($sow) {
            $parts = $mail->renderParts();

            return $mail->hasTo('ngozi@kilo.test') && $mail->locale === 'fr' && str_contains($parts['html'], '/sow/' . $sow->id)
                && str_contains($parts['subject'], 'Énoncé des travaux à signer') && count($mail->attachments()) === 1;
        });
        $this->assertStringStartsWith('%PDF', SowPdf::render($sow));
        // Sent: nothing more is approved or sent from it
        $this->assertSame('state', Sows::approve($sow, $lead, 'x', self::PNG, null));
    }

    public function test_an_active_contact_signs_with_the_code_which_raises_the_deposit_wins_the_opportunity_and_drafts_the_project(): void
    {
        Event::fake([SowSigned::class]);
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $ai = FakeAi::install()->respond('project_draft', self::PLAN);
        $sow = $this->approvedSow($o, $lead);
        Sows::send($sow, ['ngozi@kilo.test', 'cfo@kilo.test'], $lead);
        auth()->logout(); // the portal: nobody signed in to HQ

        // A meeting guest reads, but doesn't sign
        Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')
            ->set('name', 'Bola Ade')->set('email', 'cfo@kilo.test')->set('jobTitle', 'CFO')->set('signature', self::PNG)
            ->call('requestCode')->assertSee('Only someone from Kilo Pharma with an active contact record');
        // An address HQ doesn't know is refused
        Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')
            ->set('name', 'X')->set('email', 'x@else.test')->set('jobTitle', 'CFO')->set('signature', self::PNG)
            ->call('requestCode')->assertHasErrors('email');
        Mail::assertNotSent(SigningCode::class);

        // A wrong code, then the right one
        $page = Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')
            ->set('name', 'Ngozi Eze')->set('email', 'ngozi@kilo.test')->set('jobTitle', 'CFO')->set('signature', self::PNG)
            ->call('requestCode')->assertHasNoErrors()->assertSet('step', 'code');
        Mail::assertSent(SigningCode::class, fn (SigningCode $m) => $m->hasTo('ngozi@kilo.test') && $m->purpose === SigningCode::SOW);
        $code = (string) StatementOfWork::find($sow->id)->auth_otp;
        $page->set('code', $code === '000000' ? '111111' : '000000')->call('verify')->assertHasErrors('code');
        $page->set('code', $code)->call('verify')->assertHasNoErrors()->assertSet('done', 'signed')->assertSee('the statement of work is signed');

        $sow->refresh();
        $this->assertSame([StatementOfWork::SIGNED, 'Ngozi Eze', 'CFO', 'ngozi@kilo.test', null], [$sow->status, $sow->signed_by_name, $sow->signed_by_title, $sow->signed_by_email, $sow->auth_otp]);
        $this->assertStringStartsWith('SHA256-CLIENT-', $sow->client_signature_hash);

        // The project made to hold the deposit: Scoping, led by the opportunity's lead, no plan yet
        $project = DB::table('projects')->where('id', $sow->project_id)->first();
        $this->assertSame(['Scoping', $lead->id, $o->id, '1075.00', 'USD'], [$project->status, $project->lead_user_id, $project->opportunity_id, $project->total_budget, $project->currency]);
        $this->assertSame(0, DB::table('project_phases')->where('project_id', $project->id)->count());
        // Deposit: 40% of 1,000 + 7.5% VAT
        $invoice = DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first();
        $this->assertSame([$project->id, '400.00', '30.00', '430.00', $sow->reference, 'Pending'],
            [$invoice->project_id, $invoice->amount_due, $invoice->vat_amount, $invoice->total_amount, $invoice->po_sow_reference, $invoice->status]);
        // Won, but the opportunity points at the project only once it is created
        $o->refresh();
        $this->assertSame(['won', null], [$o->stage, $o->project_id]);
        $this->assertNotNull($o->closed_at);
        $this->assertSame('Active Client', DB::table('organizations')->where('id', $o->organization_id)->value('status'));
        Event::assertDispatched(SowSigned::class, fn ($e) => $e->sowId === $sow->id && $e->projectId === $project->id);

        // The project draft: the SOW's milestones, dated from the first working day after signing (Fri 6 Nov), with the AI's tasks
        $draft = ProjectDraft::where('opportunity_id', $o->id)->sole();
        $p = $draft->payload;
        $this->assertSame('ready', $draft->status);
        $this->assertSame('2026-11-06', $p['project']['start_date']);
        $this->assertSame([['Diagnostic', 40.0, 430.0, '2026-11-06', '2026-11-19', 2], ['Build', 60.0, 645.0, '2026-11-20', '2026-12-10', 1]],
            array_map(fn ($m) => [$m['title'], (float) $m['weight'], (float) $m['amount'], $m['start_date'], $m['due_date'], count($m['tasks'])], $p['milestones']));
        $this->assertSame(['analyst', 'consultant'], array_column($p['roles'], 'key'));
        $ai->assertRan('project_draft', fn (AiRequest $r) => str_contains($r->prompt, '1. Diagnostic (2 weeks') && str_contains(implode("\n", $r->system), 'STATEMENT OF WORK'));
        // The lead and finance hear about it
        $this->assertTrue(DB::table('notifications')->where('user_id', $lead->id)->where('title', 'Statement of work signed: Month-end close')->exists());

        // Signed once: the code can't sign again
        $this->assertFalse(Sows::sign($sow->fresh(), '203.0.113.9'));
        $this->assertSame(1, DB::table('invoices')->where('po_sow_reference', $sow->reference)->count());
    }

    public function test_the_code_limits_hold_for_the_sow(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $sow = $this->approvedSow($o, $lead);
        Sows::send($sow, ['ngozi@kilo.test'], $lead);
        $ask = fn () => Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')
            ->set('name', 'Ngozi Eze')->set('email', 'ngozi@kilo.test')->set('jobTitle', 'CFO')->set('signature', self::PNG)->call('requestCode');

        for ($i = 0; $i < SigningCodes::MAX_CODES; $i++) {
            $ask()->assertHasNoErrors();
        }
        $ask()->assertHasErrors('code')->assertSee('We’ve sent 3 signing codes for this statement of work in the last 15 minutes.');
        Mail::assertSent(SigningCode::class, 3);

        // Five wrong codes cancel the code
        $page = Livewire::test(SowReview::class, ['sow' => $sow->id])->assertSet('step', 'code');
        $code = (string) StatementOfWork::find($sow->id)->auth_otp;
        for ($i = 0; $i < SigningCodes::MAX_WRONG_CODES; $i++) {
            $page->set('code', $code === '000000' ? '111111' : '000000')->call('verify');
        }
        $page->assertSee('Too many wrong codes');
        $this->assertNull(StatementOfWork::find($sow->id)->auth_otp);
        $page->set('code', $code)->call('verify')->assertHasErrors('code');
        $this->assertSame(StatementOfWork::SENT, $sow->fresh()->status);
        RateLimiter::clear(SigningCodes::checkKey('sow', $sow->id));
    }

    public function test_changes_asked_for_lead_to_a_new_version_that_says_what_changed(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $sow = $this->approvedSow($o, $lead);
        Sows::send($sow, ['ngozi@kilo.test'], $lead);

        Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'changes')
            ->set('name', 'Ngozi Eze')->set('email', 'ngozi@kilo.test')->set('changeNote', 'Split the build in two.')
            ->call('requestChanges')->assertHasNoErrors()->assertSet('done', 'changes');
        $sow->refresh();
        $this->assertSame([StatementOfWork::CHANGES_REQUESTED, 'Split the build in two.', 'Ngozi Eze <ngozi@kilo.test>'], [$sow->status, $sow->changes_request, $sow->changes_requested_by]);
        $this->assertSame('sow', $o->fresh()->stage);
        $this->assertTrue(DB::table('notifications')->where('user_id', $lead->id)->where('title', 'Changes asked for on the statement of work')->exists());

        $tab = Livewire::test(SowTab::class, ['opportunityId' => $o->id])->assertSee('Split the build in two.')->call('newVersion')->assertSee('Version 2 started.');
        $v2 = StatementOfWork::where('opportunity_id', $o->id)->where('version', 2)->sole();
        $this->assertSame([StatementOfWork::DRAFT, null, null, $sow->id], [$v2->status, $v2->approved_at, $v2->hq_signature_hash, $v2->previous_version_id]);
        $tab->set('signature', self::PNG)->call('approve')->assertSee('Say what changed since the previous version first.');
        $tab->set('changesSummary', 'The build is now two milestones.')->set('signature', self::PNG)->call('approve')->assertHasNoErrors();
        $this->assertSame('state', Sows::approve($v2->fresh(), $lead, 'x', self::PNG, null), 'Approved once');

        // Sending version 2 sets version 1 aside, and its old link signs nothing
        Sows::send($v2->fresh(), ['ngozi@kilo.test'], $lead);
        $this->assertSame(StatementOfWork::SUPERSEDED, $sow->fresh()->status);
        $this->assertFalse(Sows::awaitsSignature($sow->fresh()));
    }

    public function test_without_the_ai_the_draft_is_the_sows_milestones_to_plan_by_hand(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        FakeAi::install()->unconfigured();
        $sow = $this->signedSow($o, $lead);

        $draft = ProjectDraft::where('sow_id', $sow->id)->sole();
        $this->assertSame(['ready', true, 2, 0], [$draft->status, $draft->payload['unavailable'], count($draft->payload['milestones']),
            collect($draft->payload['milestones'])->sum(fn ($m) => count($m['tasks']))]);
        $this->assertTrue(DB::table('notifications')->where('user_id', $lead->id)->where('title', 'Project draft to plan by hand: Month-end close')->exists());
    }

    public function test_the_lead_edits_the_draft_and_create_project_writes_milestones_and_tasks_through_the_project_classes(): void
    {
        Event::fake([ProjectCreatedFromOpportunity::class]);
        $lead = $this->staff('lead', ['sales']);
        $analyst = $this->staff('analyst', ['sales']);
        $member = $this->staff('member', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        FakeAi::install()->respond('project_draft', self::PLAN);
        $sow = $this->signedSow($o, $lead);
        \App\Support\Opportunities\Team::sync($o, [$member->id], $lead->id);

        // A team member reads it, but doesn't edit it or create the project
        $this->actingAs($member);
        Livewire::test(OpportunityPage::class, ['opportunity' => $o])->call('show', 'project')->assertSee('Project draft');
        Livewire::test(ProjectDraftTab::class, ['opportunityId' => $o->id])->assertSee('The owner or the opportunity’s lead (Lead Test) creates the project')
            ->call('create')->assertForbidden();

        $this->actingAs($lead);
        $tab = Livewire::test(ProjectDraftTab::class, ['opportunityId' => $o->id])
            ->assertSet('payload.milestones.0.tasks.0.title', 'Interview the finance team')->assertSee('Weights: 100% of 100%');
        // A new start moves every milestone by as many days; weights and amounts can't be changed from the page
        $tab->set('payload.project.start_date', '2026-11-09')
            ->set('payload.milestones.0.weight', 90)->set('payload.milestones.0.amount', 1)
            ->set('payload.roles.0.user_id', $analyst->id)
            ->call('addTask', 1)->set('payload.milestones.1.tasks.1.title', 'Train the team')
            ->call('moveTask', 0, 1, 1)
            ->call('addMilestone');
        $p = ProjectDraft::where('sow_id', $sow->id)->sole()->payload;
        $this->assertSame([['2026-11-09', '2026-11-22', 40.0, 430.0], ['2026-11-23', '2026-12-13', 60.0, 645.0], ['2026-12-14', '2026-12-27', 0.0, 0.0]],
            array_map(fn ($m) => [$m['start_date'], $m['due_date'], (float) $m['weight'], (float) $m['amount']], $p['milestones']));
        $this->assertSame(['Interview the finance team'], array_column($p['milestones'][0]['tasks'], 'title'));
        $this->assertSame(['Configure the ledger', 'Train the team', 'Map the month-end close'], array_column($p['milestones'][1]['tasks'], 'title'));
        $this->assertSame('Later', $p['milestones'][2]['title']);
        // The SOW's milestones can't be removed
        $tab->call('removeMilestone', 0);
        $this->assertCount(3, ProjectDraft::where('sow_id', $sow->id)->sole()->payload['milestones']);

        $tab->call('create')->assertHasNoErrors()->assertRedirect(route('hq.ops.project', ['project' => $sow->project_id, 'tab' => 'milestones']));

        $project = DB::table('projects')->where('id', $sow->project_id)->first();
        $this->assertSame(['Active', $lead->id, '2026-11-09', '2026-12-27'], [$project->status, $project->lead_user_id, $project->start_date, $project->target_end_date]);
        $phases = DB::table('project_phases')->where('project_id', $project->id)->orderBy('phase_number')->get();
        $this->assertSame([[1, 'Diagnostic', '40.00', '430.00', '2026-11-09', '2026-11-22', 'An agreed map of the close'], [2, 'Build', '60.00', '645.00', '2026-11-23', '2026-12-13', 'The new close, run once'],
            [3, 'Later', '0.00', '0.00', '2026-12-14', '2026-12-27', null]],
            $phases->map(fn ($m) => [$m->phase_number, $m->title, $m->weight_percentage, $m->amount, $m->start_date, $m->due_date, $m->goal])->all());
        $this->assertSame((float) $project->total_budget, round($phases->sum(fn ($m) => (float) $m->amount), 2), 'Amounts add up to the contract total');
        // Every task has a milestone; the role's person leads its tasks, with the estimate
        $tasks = DB::table('tasks')->whereIn('phase_id', $phases->pluck('id'))->orderBy('created_at')->get();
        $this->assertCount(4, $tasks);
        $this->assertSame([$analyst->id, '12.00'], [$tasks->firstWhere('title', 'Interview the finance team')->assigned_to, (string) $tasks->firstWhere('title', 'Interview the finance team')->estimated_hours]);
        $this->assertNull($tasks->firstWhere('title', 'Configure the ledger')->assigned_to, 'Its role has nobody');
        $this->assertSame(0, DB::table('sprints')->where('project_id', $project->id)->count(), 'No sprints');
        $this->assertSame(['created', $project->id], [ProjectDraft::where('sow_id', $sow->id)->value('status'), $o->fresh()->project_id]);
        $this->assertTrue(DB::table('system_audit_logs')->where('target_table', 'project_phases')->where('action', 'MILESTONE_CREATED')->count() === 3);
        Event::assertDispatched(ProjectCreatedFromOpportunity::class, fn ($e) => $e->projectId === $project->id);

        // Once: a second click creates nothing
        $this->assertSame('created', ProjectDrafts::create(ProjectDraft::where('sow_id', $sow->id)->sole(), $lead));
        $this->assertSame(3, DB::table('project_phases')->where('project_id', $project->id)->count());

        // The opportunity's Project tab now shows the project; the project page lists the opportunity's transcripts
        Livewire::test(OpportunityPage::class, ['opportunity' => $o->fresh()])->call('show', 'project')->assertSee('A faster close')->assertDontSee('Create project');
        Livewire::test(\App\Livewire\Hq\Projects\ProjectPage::class, ['project' => $project->id])->set('tab', 'money')->assertSee('Transcripts and minutes')->assertSee('Discovery');
    }

    public function test_scrum_master_reads_the_opportunity_and_its_signed_sow(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $o = $this->approvedOpportunity($owner);
        FakeAi::install()->unconfigured();
        $sow = $this->signedSow($o, $owner);

        $master = Livewire::test(\App\Livewire\Hq\Operations\ScrumMaster::class, ['id' => $sow->project_id]);
        $this->assertStringContainsString('STATEMENT OF WORK', $master->get('sowContext'));
        $this->assertStringContainsString('1. Diagnostic (2 weeks, 40% of the contract)', $master->get('sowContext'));
    }

    public function test_the_pdf_names_the_parties_and_the_email_samples_render_in_both_languages(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $sow = $this->approvedSow($this->approvedOpportunity($lead), $lead);
        $html = view('pdf.sow', (new \ReflectionMethod(SowPdf::class, 'data'))->invoke(null, $sow, 'en'))->render();
        $this->assertStringContainsString('JBS Praxis LLC', $html);
        $this->assertStringContainsString('Kilo Pharma', $html);
        $this->assertStringContainsString("USD\u{00A0}430.00", $html, 'The deposit');
        $this->assertStringContainsString($sow->hq_signature_hash, $html);

        foreach (SowMail::samples() as $name => $mail) {
            foreach (['en', 'fr'] as $locale) {
                $parts = (clone $mail)->locale($locale)->renderParts();
                $this->assertNotSame('', $parts['subject'], "{$name} {$locale}");
            }
        }
        $fr = SowMail::samples()['version']->locale('fr')->renderParts();
        $this->assertSame("Énoncé des travaux, version 2, à signer\u{00A0}: A faster month-end close for the Lagos entities", $fr['subject']);
        $this->assertStringContainsString("26\u{202F}875,00\u{00A0}USD", $fr['html']);
        $this->assertStringContainsString('JBS Praxis (demo legal name)', $fr['html']);
        $code = SigningCode::samples()['sow']->locale('fr')->renderParts();
        $this->assertStringContainsString('énoncé des travaux', $code['html']);
    }

    public function test_english_and_french_have_the_same_strings(): void
    {
        foreach (['sow', 'project_draft', 'emails/sow', 'emails/signing'] as $file) {
            $en = array_keys(Arr::dot(require lang_path("en/{$file}.php")));
            $fr = array_keys(Arr::dot(require lang_path("fr/{$file}.php")));
            sort($en);
            sort($fr);
            $this->assertSame($en, $fr, $file);
        }
    }
}
