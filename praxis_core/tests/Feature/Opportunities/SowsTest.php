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

    /**
     * An opportunity led by $lead whose client approved version 1 of the proposal (USD 1,000, no VAT: no JBS Praxis
     * company charges VAT here; VatSwitchTest signs one with VAT, 40/30/30). A
     * second one in the same test needs its own contact address ($email).
     */
    private function approvedOpportunity(User $lead, string $email = 'ngozi@kilo.test'): Opportunity
    {
        $org = $this->organisation(['name' => 'Kilo Pharma', 'country' => 'Nigeria']);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => $email]);
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
        Proposals::send($p->fresh(), [$email], $lead);
        $this->assertTrue(Proposals::approveForClient($p->fresh(), ['name' => 'Ngozi Eze', 'email' => $email], 'CFO'));

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

    /** Sent to Ngozi (the organisation's contact), then signed by her in the portal with the code */
    private function signedSow(Opportunity $o, User $lead): StatementOfWork
    {
        $email = DB::table('client_users')->where('organization_id', $o->organization_id)->where('first_name', 'Ngozi')->value('email');
        $sow = $this->approvedSow($o, $lead);
        $this->assertSame([], Sows::send($sow, [$email], $lead));
        $page = Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')
            ->set('name', 'Ngozi Eze')->set('email', $email)->set('jobTitle', 'CFO')->set('signature', self::PNG)
            ->call('requestCode')->assertHasNoErrors()->assertSet('step', 'code');
        $page->set('code', Mail::sent(SigningCode::class)->last()->code)->call('verify')->assertHasNoErrors()->assertDispatched('praxis-toast', $this->toast('the statement of work is signed'));

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
            ->assertSee('Weights: 100% of 100%')->assertSee("Amounts: USD\u{00A0}1,000.00 of USD\u{00A0}1,000.00, before VAT");

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
        $code = Mail::sent(SigningCode::class)->last()->code;
        $page->set('code', $code === '000000' ? '111111' : '000000')->call('verify')->assertHasErrors('code');
        $page->set('code', $code)->call('verify')->assertHasNoErrors()->assertDispatched('praxis-toast', $this->toast('the statement of work is signed'));

        $sow->refresh();
        $this->assertSame([StatementOfWork::SIGNED, 'Ngozi Eze', 'CFO', 'ngozi@kilo.test', null], [$sow->status, $sow->signed_by_name, $sow->signed_by_title, $sow->signed_by_email, $sow->auth_otp]);
        $this->assertStringStartsWith('SHA256-CLIENT-', $sow->client_signature_hash);

        // The project made to hold the deposit: Scoping, led by the opportunity's lead, no plan yet
        $project = DB::table('projects')->where('id', $sow->project_id)->first();
        $this->assertSame(['Scoping', $lead->id, $o->id, '1000.00', 'USD'], [$project->status, $project->lead_user_id, $project->opportunity_id, $project->total_budget, $project->currency]);
        $this->assertSame(0, DB::table('project_phases')->where('project_id', $project->id)->count());
        // Deposit: 40% of 1,000, no VAT (the switch is off)
        $invoice = DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first();
        $this->assertSame([$project->id, '400.00', '0.00', '400.00', $sow->reference, 'Pending'],
            [$invoice->project_id, $invoice->amount_due, $invoice->vat_amount, $invoice->total_amount, $invoice->po_sow_reference, $invoice->status]);
        // and emailed with its PDF to the client's billing contacts (docs/QA-PORTAL.md D11), once the signed page has
        // answered (Laravel's defer(): the PDF and the mail server don't hold up the client's signature)
        Mail::assertNotSent(\App\Mail\InvoiceMail::class);
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();
        Mail::assertSent(\App\Mail\InvoiceMail::class, fn ($m) => $m->hasTo('ngozi@kilo.test') && $m->invoice->id === $invoice->id && str_starts_with((string) $m->pdf, '%PDF'));
        // Won, but the opportunity points at the project only once it is created
        $o->refresh();
        $this->assertSame(['won', null], [$o->stage, $o->project_id]);
        $this->assertNotNull($o->closed_at);
        $this->assertSame('Active Client', DB::table('organizations')->where('id', $o->organization_id)->value('status'));
        // and the lead is a client since the signature, "signed" with the SOW's reference (docs/LEADS.md)
        $client = DB::table('organizations')->where('id', $o->organization_id)->first();
        $this->assertSame(['client', 'signed', $sow->reference], [$client->lead_status, $client->client_via, $client->client_reference]);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $o->organization_id, 'type' => 'marked_client', 'related_id' => $sow->id]);
        Event::assertDispatched(SowSigned::class, fn ($e) => $e->sowId === $sow->id && $e->projectId === $project->id);

        // The project draft: the SOW's milestones, dated from the first working day after signing (Fri 6 Nov), with the AI's tasks
        $draft = ProjectDraft::where('opportunity_id', $o->id)->sole();
        $p = $draft->payload;
        $this->assertSame('ready', $draft->status);
        $this->assertSame('2026-11-06', $p['project']['start_date']);
        $this->assertSame([['Diagnostic', 40.0, 400.0, '2026-11-06', '2026-11-19', 2], ['Build', 60.0, 600.0, '2026-11-20', '2026-12-10', 1]],
            array_map(fn ($m) => [$m['title'], (float) $m['weight'], (float) $m['amount'], $m['start_date'], $m['due_date'], count($m['tasks'])], $p['milestones']));
        $this->assertSame(['analyst', 'consultant'], array_column($p['roles'], 'key'));
        $ai->assertRan('project_draft', fn (AiRequest $r) => str_contains($r->prompt, '1. Diagnostic (2 weeks') && str_contains(implode("\n", $r->system), 'STATEMENT OF WORK'));
        // The lead and finance hear about it
        $this->assertTrue(DB::table('notifications')->where('user_id', $lead->id)->where('title', 'Statement of work signed: Month-end close')->exists());

        // Signed once: the code can't sign again
        $this->assertFalse(Sows::sign($sow->fresh(), $code, '203.0.113.9'));
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
        $code = Mail::sent(SigningCode::class)->last()->code;
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
            ->call('requestChanges')->assertHasNoErrors()->assertDispatched('praxis-toast', $this->toast('we will come back to you with a new version'));
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
        $analyst = $this->staff('analyst', ['delivery']); // on the project's team (projects.edit)
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
        $this->assertSame([['2026-11-09', '2026-11-22', 40.0, 400.0], ['2026-11-23', '2026-12-13', 60.0, 600.0], ['2026-12-14', '2026-12-27', 0.0, 0.0]],
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
        $this->assertSame([[1, 'Diagnostic', '40.00', '400.00', '2026-11-09', '2026-11-22', 'An agreed map of the close'], [2, 'Build', '60.00', '600.00', '2026-11-23', '2026-12-13', 'The new close, run once'],
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
        $this->assertStringContainsString('STATEMENT OF WORK', $master->get('scope'));
        $this->assertStringContainsString('1. Diagnostic (2 weeks, 40% of the contract)', $master->get('scope'));
        $this->assertContains('signed statement of work ' . $sow->reference, $master->get('sources'));
    }

    public function test_the_pdf_names_the_parties_and_the_email_samples_render_in_both_languages(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $sow = $this->approvedSow($this->approvedOpportunity($lead), $lead);
        $html = view('pdf.sow', (new \ReflectionMethod(SowPdf::class, 'data'))->invoke(null, $sow, 'en'))->render();
        $this->assertStringContainsString('JBS Praxis LLC', $html);
        $this->assertStringContainsString('Kilo Pharma', $html);
        $this->assertStringContainsString("USD\u{00A0}400.00", $html, 'The deposit');
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
        foreach (['sow', 'project_draft', 'emails/sow', 'emails/signing', 'projects'] as $file) {
            $en = array_keys(Arr::dot(require lang_path("en/{$file}.php")));
            $fr = array_keys(Arr::dot(require lang_path("fr/{$file}.php")));
            sort($en);
            sort($fr);
            $this->assertSame($en, $fr, $file);
        }
    }

    // ---- The lead chat's audit of #19 (1 Nov 2026) ----

    /** Version 2 approved from version 1, which the client asked to change */
    private function secondVersion(Opportunity $o, User $lead, StatementOfWork $v1): StatementOfWork
    {
        StatementOfWork::whereKey($v1->id)->update(['status' => StatementOfWork::CHANGES_REQUESTED]);
        $v2 = Sows::newVersion($o, $lead);
        Sows::save($v2, ['title' => 'A faster close', 'sections' => $v1->sections, 'milestones' => $v1->milestones, 'changes_summary' => 'Shorter build.'], $lead);
        $this->assertNull(Sows::approve($v2, $lead, 'Lead Test', self::PNG, null));

        return $v2->fresh();
    }

    public function test_once_a_later_version_is_claimed_as_sent_the_earlier_one_signs_nothing_even_if_the_send_is_cut_short(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $v1 = $this->approvedSow($o, $lead);
        Sows::send($v1, ['ngozi@kilo.test'], $lead);
        // Ngozi has a code for version 1 while HQ prepares version 2
        Livewire::test(SowReview::class, ['sow' => $v1->id])->call('choose', 'sign')->set('name', 'Ngozi Eze')->set('email', 'ngozi@kilo.test')
            ->set('jobTitle', 'CFO')->set('signature', self::PNG)->call('requestCode')->assertHasNoErrors();
        $code = Mail::sent(SigningCode::class)->last()->code;
        // HQ starts version 2 from the one with the client, and approves it
        $v2 = Sows::newVersion($o, $lead);
        Sows::save($v2, ['title' => 'A faster close', 'sections' => $v1->sections, 'milestones' => $v1->milestones, 'changes_summary' => 'Shorter build.'], $lead);
        $this->assertNull(Sows::approve($v2, $lead, 'Lead Test', self::PNG, null));
        DB::table('opportunities')->where('id', $o->id)->update(['stage' => 'sow']); // as if the client had asked for changes meanwhile

        // The request dies right after the claim (a PHP timeout before any email)
        $claim = new \ReflectionMethod(Sows::class, 'claim');
        $this->assertSame([$v1->id => StatementOfWork::SENT], $claim->invoke(null, $v2->fresh(), [['email' => 'ngozi@kilo.test', 'name' => 'Ngozi Eze', 'client_user_id' => null]], $lead));
        $this->assertSame([StatementOfWork::SUPERSEDED, null], [$v1->fresh()->status, $v1->fresh()->auth_otp]);
        $this->assertFalse(Sows::awaitsSignature($v1->fresh()));
        $this->assertFalse(Sows::sign($v1->fresh(), $code, '203.0.113.9'));
        $this->assertFalse(Sows::requestChanges($v1->fresh(), ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'x'));
        $this->assertSame('sow', $o->fresh()->stage, 'The stage moves only once an email has gone');
        $this->assertSame(0, DB::table('system_audit_logs')->where('action', 'SOW_SENT')->where('new_values', 'like', '%' . $v2->id . '%')->count());

        // "Send again to them" finishes the send: the email, the stage and the history line
        $this->assertSame([], Sows::resend($v2->fresh(), $lead));
        Mail::assertSent(SowMail::class, fn (SowMail $m) => $m->sow->id === $v2->id);
        $this->assertSame('sow_sent', $o->fresh()->stage);
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'SOW_SENT')->where('new_values', 'like', '%' . $v2->id . '%')->count());

        // A send that reaches nobody gives the earlier version back
        $o3 = $this->approvedOpportunity($lead, 'ngozi3@kilo.test');
        $w1 = $this->approvedSow($o3, $lead);
        Sows::send($w1, ['ngozi3@kilo.test'], $lead);
        $w2 = $this->secondVersion($o3, $lead, $w1);
        StatementOfWork::whereKey($w1->id)->update(['status' => StatementOfWork::SENT]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $this->assertSame(['ngozi3@kilo.test'], Sows::send($w2, ['ngozi3@kilo.test'], $lead));
        $this->assertSame([StatementOfWork::SENT, StatementOfWork::DRAFT], [$w1->fresh()->status, $w2->fresh()->status]);
    }

    public function test_the_code_is_stored_as_an_hmac_and_signs_only_for_the_signer_it_was_issued_for(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $this->clientContact($o->organization_id, ['first_name' => 'Tunde', 'last_name' => 'Bello', 'email' => 'tunde@kilo.test', 'is_primary_client' => 0,
            'role_id' => DB::table('roles')->where('slug', 'client-approver')->value('id')]);
        $sow = $this->approvedSow($o, $lead);
        Sows::send($sow, ['ngozi@kilo.test'], $lead);
        $ask = fn (string $name, string $email) => Livewire::test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')
            ->set('name', $name)->set('email', $email)->set('jobTitle', 'CFO')->set('signature', self::PNG)->call('requestCode');

        $page = $ask('Ngozi Eze', 'ngozi@kilo.test')->assertHasNoErrors()->assertSee('You are signing as Ngozi Eze, CFO.')->assertSee('We emailed a 6-digit code to ngozi@kilo.test');
        $first = Mail::sent(SigningCode::class)->last();
        $this->assertStringContainsString('This code signs as Ngozi Eze, CFO.', $first->render());
        $stored = StatementOfWork::find($sow->id)->auth_otp;
        $this->assertSame(64, strlen($stored));
        $this->assertNotSame($first->code, $stored);
        $this->assertSame(SigningCodes::hash($first->code), $stored);
        // Someone else opening the link sees who is signing, not their address
        Livewire::test(SowReview::class, ['sow' => $sow->id])->assertSet('step', 'code')->assertSee('You are signing as Ngozi Eze, CFO.')->assertDontSee('ngozi@kilo.test');

        // Tunde asks for a code too: the row now holds his details, so Ngozi's code signs nothing
        $ask('Tunde Bello', 'tunde@kilo.test')->assertHasNoErrors();
        $page->set('code', $first->code)->call('verify')->assertHasErrors('code');
        $this->assertSame(StatementOfWork::SENT, $sow->fresh()->status);
        // Nor does his once he is no longer an active contact
        DB::table('client_users')->where('email', 'tunde@kilo.test')->update(['status' => 'Suspended']);
        $this->assertFalse(Sows::sign($sow->fresh(), Mail::sent(SigningCode::class)->last()->code, null));
        DB::table('client_users')->where('email', 'tunde@kilo.test')->update(['status' => 'Active']);
        $this->assertTrue(Sows::sign($sow->fresh(), Mail::sent(SigningCode::class)->last()->code, null));
        $this->assertSame(['Tunde Bello', 'tunde@kilo.test'], [$sow->fresh()->signed_by_name, $sow->fresh()->signed_by_email]);
    }

    public function test_d5_only_the_organisations_approvers_sign_and_are_sent_a_code(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $member = $this->clientContact($o->organization_id, ['first_name' => 'Cyn', 'last_name' => 'Okafor', 'email' => 'cyn@kilo.test', 'is_primary_client' => 0,
            'role_id' => DB::table('roles')->where('slug', 'client-member')->value('id')]);
        $sow = $this->approvedSow($o, $lead);
        Sows::send($sow, ['ngozi@kilo.test', 'cyn@kilo.test'], $lead);
        $page = fn () => Livewire::withQueryParams(['c' => $member])->test(SowReview::class, ['sow' => $sow->id]);

        // A team member reads it and may ask for changes; a plain line stands where Sign would be, and the server refuses
        $page()->assertDontSee('With your name, title and signature')->assertSee('Signing is for the people who approve for')
            ->call('choose', 'sign')->assertSet('mode', '')
            ->set('jobTitle', 'Ops')->set('signature', self::PNG)->call('requestCode')->assertHasErrors(['email']);
        // A link without a contact offers Sign; whoever asks for the code must approve for the organisation
        Livewire::withQueryParams([])->test(SowReview::class, ['sow' => $sow->id])->call('choose', 'sign')->set('name', 'Cyn Okafor')->set('email', 'cyn@kilo.test')
            ->set('jobTitle', 'Ops')->set('signature', self::PNG)->call('requestCode')->assertHasErrors(['email'])->assertSee('Only the people who approve for Kilo Pharma can sign it.');
        Mail::assertNotSent(SigningCode::class);
        // Nor does a code asked for in their name sign
        Sows::holdSignature($sow, ['name' => 'Cyn Okafor', 'title' => 'Ops', 'email' => 'cyn@kilo.test'], self::PNG, '123456');
        $this->assertFalse(Sows::sign($sow->fresh(), '123456', null));
        $this->assertSame(StatementOfWork::SENT, $sow->fresh()->status);

        // Made a Client Approver (no team or branding), the same link signs
        DB::table('client_users')->where('id', $member)->update(['role_id' => DB::table('roles')->where('slug', 'client-approver')->value('id')]);
        \App\Support\Access::flush();
        $page()->assertSee('With your name, title and signature')->call('choose', 'sign')->assertSet('mode', 'sign')
            ->set('jobTitle', 'Ops')->set('signature', self::PNG)->call('requestCode')->assertHasNoErrors()->assertSet('step', 'code');
        $this->assertTrue(Sows::sign($sow->fresh(), Mail::sent(SigningCode::class)->last()->code, null));
        $this->assertSame('cyn@kilo.test', $sow->fresh()->signed_by_email);
    }

    public function test_signing_copies_the_budget_lines_honours_a_custom_deposit_and_fingerprints_the_text(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        Proposal::where('opportunity_id', $o->id)->update(['payment_schedule_type' => 'custom', 'custom_deposit_percent' => 25]);
        FakeAi::install()->unconfigured();
        $sow = $this->signedSow($o, $lead);
        $other = $sow->replicate();
        $other->sections = [['key' => 'purpose', 'title' => 'Purpose', 'body' => 'Other words.']];
        $this->assertNotSame(Sows::contentHash($sow), Sows::contentHash($other), 'The text is in the fingerprints');

        $this->assertSame([['Diagnostic & Audit', '1 Phase', '1000.00']], DB::table('project_budgets')->where('project_id', $sow->project_id)->get()
            ->map(fn ($b) => [$b->description, $b->quantity, $b->total_allocated])->all());
        $invoice = DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first();
        $this->assertSame(['250.00', '0.00', '250.00'], [$invoice->amount_due, $invoice->vat_amount, $invoice->total_amount]);
    }

    public function test_a_legacy_proposal_and_a_sow_never_both_sign(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $sow = $this->approvedSow($o, $lead);
        Sows::send($sow, ['ngozi@kilo.test'], $lead);
        $legacy = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('proposals', ['id' => $legacy, 'organization_id' => $o->organization_id, 'opportunity_id' => $o->id, 'proposal_ref' => 'PRP-OLD', 'title' => 'Old',
            'status' => 'Authorized', 'signing_mode' => 'legacy', 'subtotal' => 1, 'total_value' => 1, 'payment_schedule_type' => '50_50', 'validity_days' => 30, 'updated_at' => now()]);
        $this->assertFalse(Sows::awaitsSignature($sow->fresh()), 'A legacy proposal of this work was signed');
        DB::table('proposals')->where('id', $legacy)->delete();
        $this->assertTrue(Sows::awaitsSignature($sow->fresh()));

        // The other way round: a SOW signed, the legacy gateway refuses
        FakeAi::install()->unconfigured();
        $signed = $this->signedSow($this->approvedOpportunity($lead, 'ngozi2@kilo.test'), $lead);
        $token = \Illuminate\Support\Str::random(40);
        $this->insertRow('proposals', ['id' => (string) \Illuminate\Support\Str::uuid(), 'organization_id' => $signed->opportunity->organization_id, 'opportunity_id' => $signed->opportunity_id,
            'proposal_ref' => 'PRP-OLD2', 'token_hash' => $token, 'title' => 'Old', 'status' => 'Pending Auth', 'hq_signature_hash' => 'SHA256-HQ', 'signing_mode' => 'legacy',
            'subtotal' => 1, 'total_value' => 1, 'payment_schedule_type' => '50_50', 'validity_days' => 30, 'updated_at' => now()]);
        Livewire::test(\App\Livewire\Portal\ProposalGateway::class, ['token' => $token])->set('signerName', 'Ngozi Eze')->set('signerTitle', 'CFO')->set('signerEmail', 'ngozi@kilo.test')
            ->call('requestAuthorizationOtp', self::PNG)->assertHasErrors('signingCode');
    }

    public function test_an_imported_sow_shows_as_signed_on_paper_without_fees_or_a_pdf(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityFor($this->organisation(['name' => 'MyCity (test)']), ['lead_user_id' => $lead->id, 'stage' => 'won']);
        $sow = StatementOfWork::create(['opportunity_id' => $o->id, 'reference' => 'SOW/JBSP/TEST/2026-01', 'version' => 1, 'status' => 'signed',
            'sections' => [['key' => 'scope', 'title' => 'Scope', 'body' => 'The ERP.']], 'sent_at' => '2026-10-02 00:00:00', 'signed_at' => '2026-10-02 00:00:00',
            'signed_by_name' => 'MyCity App Ltd (Chief Executive Officer)', 'hq_signer_name' => 'Tom-Blake Forghab, Managing Partner']);

        Livewire::test(SowTab::class, ['opportunityId' => $o->id])
            ->assertSee('Signed on paper on 2 October 2026 and imported into HQ.')->assertSee('MyCity App Ltd (Chief Executive Officer)')->assertSee('Tom-Blake Forghab, Managing Partner')
            ->assertDontSee('From the approved proposal')->assertDontSee('Deposit')->assertDontSee('(, )')->assertDontSee('Sent to')
            ->assertDontSeeHtml(route('hq.crm.sow-pdf', ['opportunity' => $o->id, 'sow' => $sow->id]));
        $this->get(route('hq.crm.sow-pdf', ['opportunity' => $o->id, 'sow' => $sow->id]))->assertNotFound();
    }

    public function test_less_deposit_every_milestone_the_deposit_and_the_milestone_invoices_make_the_contract(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        FakeAi::install()->unconfigured();
        $sow = $this->signedSow($o, $lead);
        $draft = ProjectDraft::where('sow_id', $sow->id)->sole();
        $this->assertNull(ProjectDrafts::create($draft, $lead));
        $phases = DB::table('project_phases')->where('project_id', $sow->project_id)->orderBy('phase_number')->get();

        // 40% deposit: M1 400 → 240, M2 600 → 360; 400 + 240 + 360 = 1,000 before VAT
        $dues = \App\Support\Projects\Deposit::dues($sow->project_id);
        $this->assertSame([240.0, 360.0], [$dues[$phases[0]->id], $dues[$phases[1]->id]]);
        $this->assertSame(1000.0, round(400 + array_sum($dues), 2));
        // Finance's pre-fill and the drawer say so; project_phases.amount keeps the full share
        $finance = $this->staff('owner', ['owner']);
        $this->actingAs($finance);
        Livewire::withQueryParams(['create' => 1, 'project' => $sow->project_id, 'phase' => $phases[0]->id])->test(\App\Livewire\Hq\Finance\InvoiceLedger::class)
            ->assertSet('picked', [$phases[0]->id])->assertSet('lines.0.amount', 240.0)->assertSet('lines.0.description', 'Diagnostic, on written acceptance, less the 40% deposit');
        $this->assertSame('400.00', $phases[0]->amount);
        Livewire::test(\App\Livewire\Hq\Projects\MilestoneDrawer::class, ['projectId' => $sow->project_id])->call('openMilestone', $phases[0]->id)
            ->assertSee("Its invoice: M1 · USD\u{00A0}400 less 40% deposit = USD\u{00A0}240, before VAT.");

        // Uneven weights: the last milestone takes the rounding, so the total stays exact
        DB::table('project_phases')->where('id', $phases[0]->id)->update(['amount' => 333.33]);
        DB::table('project_phases')->where('id', $phases[1]->id)->update(['amount' => 666.67]);
        $dues = \App\Support\Projects\Deposit::dues($sow->project_id);
        $this->assertSame([200.0, 400.0], [$dues[$phases[0]->id], $dues[$phases[1]->id]]);
        $this->assertSame(1000.0, round(400 + array_sum($dues), 2));
        // A milestone a change report added isn't reduced; a project without a SOW deposit doesn't change
        DB::table('change_reports')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'project_id' => $sow->project_id, 'number' => 1, 'title' => 'Extra', 'price' => 100,
            'currency' => 'USD', 'timeline_days' => 0, 'status' => 'approved', 'phase_id' => $phases[1]->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(666.67, \App\Support\Projects\Deposit::forMilestone(DB::table('project_phases')->find($phases[1]->id))['due']);
        $this->assertNull(\App\Support\Projects\Deposit::forProject((string) \Illuminate\Support\Str::uuid()));
    }

    public function test_an_imported_deposit_as_mycity_is_reduced_by_its_share_of_the_contract(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $org = $this->organisation(['name' => 'MyCity (test)']);
        $o = $this->opportunityFor($org, ['lead_user_id' => $owner->id, 'stage' => 'won']);
        $project = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('projects', ['id' => $project, 'organization_id' => $org, 'title' => 'ERP', 'total_budget' => 3700000, 'currency' => 'NGN', 'status' => 'Active']);
        $deposit = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('invoices', ['id' => $deposit, 'project_id' => $project, 'invoice_number' => 'INV-TEST-001', 'invoice_type' => 'Tax Invoice - Initial Deposit',
            'amount_due' => 500000, 'vat_amount' => 0, 'total_amount' => 500000, 'currency' => 'NGN', 'status' => 'Paid', 'due_date' => '2026-10-12']);
        StatementOfWork::create(['opportunity_id' => $o->id, 'reference' => 'SOW/TEST', 'version' => 1, 'status' => 'signed', 'signed_at' => '2026-10-02', 'deposit_invoice_id' => $deposit]);
        $m1 = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('project_phases', ['id' => $m1, 'project_id' => $project, 'phase_number' => 1, 'title' => 'Core', 'weight_percentage' => 12, 'amount' => 444000, 'status' => 'Completed']);

        $due = \App\Support\Projects\Deposit::forMilestone(DB::table('project_phases')->find($m1));
        $this->assertSame([444000.0, 384000.0, 13.51], [$due['amount'], $due['due'], $due['deposit']['percent']]);
    }

    public function test_create_project_takes_the_opportunitys_lead_as_it_is_now_and_never_stacks_a_second_plan(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $newLead = $this->staff('newlead', ['sales']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        FakeAi::install()->unconfigured();
        $sow = $this->signedSow($o, $lead);
        $draft = ProjectDraft::where('sow_id', $sow->id)->sole();

        // No lead: refused, and fixed on the opportunity
        $o->forceFill(['lead_user_id' => null])->save();
        $this->actingAs($this->staff('owner', ['owner']));
        $this->assertSame('lead', ProjectDrafts::create($draft->fresh(), auth()->user()));
        $o->forceFill(['lead_user_id' => $newLead->id])->save();
        Livewire::test(ProjectDraftTab::class, ['opportunityId' => $o->id])->assertSee('Project lead: Newlead Test');

        // A page that is behind saves and creates nothing
        $tab = Livewire::test(ProjectDraftTab::class, ['opportunityId' => $o->id]);
        $this->assertTrue(ProjectDrafts::save($draft->fresh(), $draft->fresh()->payload, auth()->user(), $draft->fresh()->revision));
        $tab->set('payload.project.title', 'From a stale page')->assertHasErrors('draft');
        $this->assertNotSame('From a stale page', ProjectDraft::find($draft->id)->payload['project']['title']);
        $this->assertSame('stale', ProjectDrafts::create($draft->fresh(), auth()->user(), $draft->fresh()->revision - 1));

        // A role goes only to someone on the project's team
        $outsider = $this->staff('outsider', ['sales']);
        $payload = $draft->fresh()->payload;
        $payload['roles'] = [['key' => 'pm', 'title' => 'PM', 'user_id' => $outsider->id]];
        ProjectDrafts::save($draft->fresh(), $payload, auth()->user());
        $this->assertNull($draft->fresh()->payload['roles'][0]['user_id']);

        // A plan already on the project: refused
        $phase = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('project_phases', ['id' => $phase, 'project_id' => $sow->project_id, 'phase_number' => 1, 'title' => 'By hand', 'weight_percentage' => 0, 'status' => 'Pending']);
        $this->assertSame('plan_exists', ProjectDrafts::create($draft->fresh(), auth()->user()));
        DB::table('project_phases')->where('id', $phase)->delete();

        $this->assertNull(ProjectDrafts::create($draft->fresh(), auth()->user()));
        $this->assertSame($newLead->id, DB::table('projects')->where('id', $sow->project_id)->value('lead_user_id'));
    }

    public function test_create_project_rings_each_person_once_in_their_language_and_the_draft_never_reads_full_transcripts(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $analyst = $this->staff('analyst', ['delivery']);
        $this->actingAs($lead);
        $o = $this->approvedOpportunity($lead);
        $ai = FakeAi::install()->respond('project_draft', self::PLAN);
        $sow = $this->signedSow($o, $lead);
        $ai->assertRan('project_draft', fn (AiRequest $r) => ! str_contains(implode("\n", $r->system), 'secret detail') && str_contains(implode("\n", $r->system), 'The close takes 15 days.'));

        $draft = ProjectDraft::where('sow_id', $sow->id)->sole();
        $payload = $draft->payload;
        $payload['roles'][0]['user_id'] = $analyst->id; // the analyst leads two tasks
        ProjectDrafts::save($draft, $payload, $lead);
        config(['app.staff_locale' => 'fr']);
        $this->assertNull(ProjectDrafts::create($draft->fresh(), $lead));

        $bells = DB::table('notifications')->where('user_id', $analyst->id)->get(['title', 'message']);
        $this->assertSame(['Vous êtes responsable de 2 tâches sur « A faster close ».'],
            $bells->where('title', 'Vous êtes responsable de tâches d’un nouveau plan')->pluck('message')->values()->all(), 'One bell for both tasks, in HQ’s language');
        $this->assertSame(0, $bells->where('title', 'Vous êtes responsable d’une tâche')->count(), 'No bell per task');
        $this->assertTrue($bells->contains('title', 'Plan du projet prêt'));
    }

    public function test_plan_milestones_refuses_to_plan_again_a_project_with_milestones_or_a_pending_draft(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $o = $this->approvedOpportunity($owner);
        FakeAi::install()->unconfigured();
        $sow = $this->signedSow($o, $owner);

        // The plan waits in the project draft: link to it, draft nothing
        Livewire::test(\App\Livewire\Hq\Operations\ScrumMaster::class, ['id' => $sow->project_id])
            ->assertSee('Open the project draft')->assertDontSee('Draft the plan')->assertDontSee('Suggest tasks for this milestone')
            ->call('draftPlan')->assertHasErrors('plan');
        $this->assertSame(0, \App\Models\MilestonePlan::count());

        // Created: the project has milestones, so it is planned a milestone at a time, never with a second whole plan
        $this->assertNull(ProjectDrafts::create(ProjectDraft::where('sow_id', $sow->id)->sole(), $owner));
        $phases = DB::table('project_phases')->where('project_id', $sow->project_id)->count();
        Livewire::test(\App\Livewire\Hq\Operations\ScrumMaster::class, ['id' => $sow->project_id])
            ->assertSee('Suggest tasks for this milestone')->assertDontSee('Draft the plan')
            ->call('draftPlan')->assertHasErrors('plan');
        $this->assertSame($phases, DB::table('project_phases')->where('project_id', $sow->project_id)->count());
    }

    public function test_sow_amounts_take_the_rounding_on_the_last_weighted_milestone(): void
    {
        $milestones = [['weight' => 33.33], ['weight' => 33.33], ['weight' => 33.34], ['weight' => 0]];
        $this->assertSame([333.3, 333.3, 333.4, 0.0], Sows::amounts($milestones, 1000));
        $amounts = Sows::amounts([['weight' => 33.33], ['weight' => 33.33], ['weight' => 33.34]], 1075.55);
        $this->assertSame(1075.55, round(array_sum($amounts), 2));
    }
}
