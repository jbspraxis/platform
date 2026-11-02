<?php

namespace Tests\Feature\Opportunities;

use App\Events\ProposalApprovedByClient;
use App\Events\ProposalChangesRequested;
use App\Livewire\Portal\ProposalReview;
use App\Mail\SigningCode;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\ProposalComment;
use App\Models\User;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\Proposals;
use App\Support\Signing\SigningCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The client's review of a proposal in the portal (docs/OPPORTUNITIES.md decisions 8 and 9): a signed link to a
 * version that was sent; comments by section; one-click approval with name, title and email; asking for changes;
 * a follow-up through the meetings page. Only addresses HQ knows for this work can act, comments are capped in size
 * and number, and drafts, transcripts and notes never show.
 */
class PortalProposalTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private User $lead;

    private Opportunity $o;

    private string $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
        $this->lead = $this->staff('lead', ['sales']);
        $org = $this->organisation(['name' => 'Kilo Pharma', 'preferred_language' => 'FR']);
        $this->contact = $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@kilo.test', 'job_title' => 'Finance director']);
        $this->o = $this->opportunityFor($org, ['title' => 'Month-end close', 'lead_user_id' => $this->lead->id, 'stage' => 'proposal', 'language' => 'fr']);
    }

    /** Version 1, written, approved and sent to Ngozi */
    private function sent(array $values = []): Proposal
    {
        $this->actingAs($this->lead);
        $p = Proposals::start($this->o, 'USD', $this->lead);
        Proposals::save($p, ['title' => 'A faster close', 'sections' => [
            ['key' => 'executive_summary', 'title' => 'A close in five days', 'body' => 'We recommend three phases.'],
            ['key' => 'approach', 'title' => 'We start with the ledger', 'body' => "Phase one\n- Map the close"],
        ], 'lines' => [['code' => '#1292P', 'description' => 'Diagnostic & Audit', 'quantity' => '1 Phase', 'unit_price' => 1000]]] + $values, $this->lead);
        Proposals::approve($p, $this->lead);
        Proposals::send($p, ['ngozi@kilo.test'], $this->lead);
        auth()->guard('web')->logout();

        return $p->fresh();
    }

    /** Someone invited to a client meeting of the opportunity: known for the work, but not one of the organisation's contacts */
    private function guest(string $email): void
    {
        $meeting = \App\Models\Meeting::create(['opportunity_id' => $this->o->id, 'organization_id' => $this->o->organization_id, 'kind' => 'client', 'title' => 'Discovery',
            'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 10:00:00', 'status' => 'held', 'transcript_status' => 'manual']);
        $meeting->attendees()->create(['email' => $email, 'name' => 'Bola Ade', 'role' => 'guest']);
    }

    private function page(Proposal $p, array $query = ['c' => true])
    {
        return Livewire::withQueryParams(isset($query['c']) ? ['c' => $this->contact] : [])->test(ProposalReview::class, ['proposal' => $p->id]);
    }

    public function test_the_link_is_signed_and_opens_only_a_version_that_was_sent(): void
    {
        $this->actingAs($this->lead);
        $draft = Proposals::start($this->o, 'USD', $this->lead);
        auth()->guard('web')->logout();
        $this->get(preg_replace('~^https?://[^/]+~', '', PortalLinks::proposal($draft)))->assertNotFound();

        $p = $this->sent();
        $url = preg_replace('~^https?://[^/]+~', '', PortalLinks::proposal($p, $this->contact));
        $this->get(route('portal.proposals.review', ['proposal' => $p->id]))->assertForbidden();
        $this->get($url . 'x')->assertForbidden();
        $this->get($url)->assertOk()->assertSee('A close in five days')->assertSee('Diagnostic &amp; Audit', false)
            // In the opportunity's language: French
            // No JBS Praxis company charges VAT here: no VAT line, the total is the subtotal (VatSwitchTest has VAT)
            ->assertSee('Approuver la proposition')->assertSee("1\u{202F}000,00\u{00A0}USD")->assertDontSee('TVA (');
    }

    public function test_a_known_contact_comments_on_a_section_and_the_lead_is_told(): void
    {
        $p = $this->sent();
        $this->page($p)->assertSet('email', 'ngozi@kilo.test')->assertSet('jobTitle', 'Finance director')
            ->call('openComment', 'nowhere')->assertSet('commenting', '')
            ->call('openComment', 'approach')->assertSet('commenting', 'approach')
            ->set('body', str_repeat('x', Proposals::MAX_COMMENT + 1))->call('comment')->assertHasErrors(['body'])
            ->set('body', 'Can phase one start in January?')->call('comment')->assertSet('done', 'commented')
            ->assertSee('Can phase one start in January?');

        $c = ProposalComment::sole();
        $this->assertSame(['approach', 'client', 'Ngozi Eze', 'ngozi@kilo.test'], [$c->section_key, $c->author_type, $c->author_name, $c->author_email]);
        $bell = DB::table('notifications')->where('user_id', $this->lead->id)->sole();
        $this->assertStringContainsString('Ngozi Eze commented on version 1', $bell->message);
        $this->assertStringContainsString('tab=proposals', $bell->link_url);
    }

    public function test_only_an_address_hq_knows_can_act_and_unknown_ones_are_limited(): void
    {
        $p = $this->sent();
        // A forwarded link: someone types an address HQ doesn't know for this work
        $page = $this->page($p, [])->call('openComment', 'general')->set('name', 'Someone')->set('email', 'someone@else.test')->set('body', 'Hello')
            ->call('comment')->assertHasErrors(['email']);
        $this->page($p, [])->call('choose', 'approve')->set('name', 'Someone')->set('email', 'someone@else.test')->set('jobTitle', 'CEO')
            ->call('approve')->assertHasErrors(['email']);
        $this->assertSame(0, ProposalComment::count());
        $this->assertSame(Proposal::SENT, $p->fresh()->status);

        // Anyone else is tried at most MAX_UNKNOWN times in 15 minutes from one IP (only misses count)
        foreach (range(3, ProposalReview::MAX_UNKNOWN) as $i) {
            $page->set('email', "guess{$i}@else.test")->call('comment')->assertHasErrors(['email']);
        }
        $page->set('email', 'ngozi@kilo.test')->call('comment')->assertSee('Trop d’adresses ont été essayées');
        $this->assertSame(0, ProposalComment::count());
        RateLimiter::clear(ProposalReview::unknownKey($p->id, '127.0.0.1'));
        $page->call('comment')->assertSet('done', 'commented');

        // Guesses from another IP don't lock the client out
        foreach (range(1, ProposalReview::MAX_UNKNOWN + 2) as $i) {
            RateLimiter::hit(ProposalReview::unknownKey($p->id, '203.0.113.9'), 900);
        }
        $this->page($p)->call('openComment', 'general')->set('body', 'Still here')->call('comment')->assertSet('done', 'commented');
    }

    public function test_comments_are_limited_per_person_per_hour_and_per_version(): void
    {
        $p = $this->sent();
        $page = $this->page($p)->call('openComment', 'general');
        foreach (range(1, Proposals::COMMENTS_PER_HOUR) as $i) {
            $page->call('openComment', 'general')->set('body', "Comment {$i}")->call('comment')->assertSet('done', 'commented');
        }
        $page->call('openComment', 'general')->set('body', 'One too many')->call('comment')->assertHasErrors(['body']);
        $this->assertSame(Proposals::COMMENTS_PER_HOUR, ProposalComment::count());
        // Another person HQ knows still comments: the limit is per person
        $this->guest('cfo@kilo.test');
        $this->page($p, [])->call('openComment', 'general')->set('name', 'Bola Ade')->set('email', 'cfo@kilo.test')->set('body', 'My turn')
            ->call('comment')->assertSet('done', 'commented');

        // The version's cap holds whatever the limiter says
        RateLimiter::clear('portal-proposal-comments:' . $p->id . ':' . sha1('ngozi@kilo.test'));
        foreach (range(Proposals::COMMENTS_PER_HOUR + 2, Proposals::MAX_CLIENT_COMMENTS) as $i) {
            ProposalComment::create(['proposal_id' => $p->id, 'section_key' => 'general', 'author_type' => 'client', 'author_name' => 'N', 'body' => "c{$i}"]);
        }
        $page->call('openComment', 'general')->set('body', 'Over the cap')->call('comment')->assertSee('ne peut plus recevoir de commentaires');
        $this->assertSame(Proposals::MAX_CLIENT_COMMENTS, ProposalComment::count());
    }

    public function test_only_an_active_contact_of_the_organisation_approves(): void
    {
        $p = $this->sent();
        // A meeting guest reads and comments, but doesn't approve
        $this->guest('cfo@kilo.test');
        $this->page($p, [])->call('openComment', 'general')->set('name', 'Bola Ade')->set('email', 'cfo@kilo.test')->set('body', 'Looks right')
            ->call('comment')->assertSet('done', 'commented');
        $this->page($p, [])->call('choose', 'approve')->set('name', 'Bola Ade')->set('email', 'cfo@kilo.test')->set('jobTitle', 'CFO')
            ->call('approve')->assertHasErrors(['email'])->assertSee('Seul un contact de Kilo Pharma');
        $this->assertFalse(Proposals::approveForClient($p, ['name' => 'Bola Ade', 'email' => 'cfo@kilo.test'], 'CFO'));

        // A contact who was suspended neither approves nor is offered as a recipient any more
        DB::table('client_users')->where('id', $this->contact)->update(['status' => 'Suspended']);
        $this->page($p)->call('choose', 'approve')->call('approve')->assertHasErrors(['email']);
        $this->assertSame(Proposal::SENT, $p->fresh()->status);
        $this->assertArrayNotHasKey('ngozi@kilo.test', Proposals::people($this->o));
    }

    public function test_d5_only_the_organisations_approvers_approve_the_others_comment_and_ask_for_changes(): void
    {
        $member = $this->clientContact($this->o->organization_id, ['first_name' => 'Cyn', 'last_name' => 'Okafor', 'email' => 'cyn@kilo.test', 'is_primary_client' => 0,
            'role_id' => DB::table('roles')->where('slug', 'client-member')->value('id')]);
        $p = $this->sent();
        $page = fn () => Livewire::withQueryParams(['c' => $member])->test(ProposalReview::class, ['proposal' => $p->id]);

        // A team member comments; a plain line stands where Approve would be, and the server refuses
        $page()->assertDontSee('Approuver la proposition')->assertSee('L’approbation revient aux personnes qui approuvent pour Kilo Pharma')
            ->call('choose', 'approve')->assertSet('mode', '')
            ->set('jobTitle', 'Ops')->call('approve')->assertHasErrors(['email']);
        $page()->call('openComment', 'general')->set('body', 'Can we start in January?')->call('comment')->assertSet('done', 'commented');
        $this->assertFalse(Proposals::approveForClient($p, ['name' => 'Cyn Okafor', 'email' => 'cyn@kilo.test'], 'Ops'));
        $this->assertSame(Proposal::SENT, $p->fresh()->status);

        // A link without a contact offers it; whoever acts must approve for the organisation
        $this->page($p, [])->assertSee('Approuver la proposition')->call('choose', 'approve')->set('name', 'Cyn Okafor')->set('email', 'cyn@kilo.test')
            ->set('jobTitle', 'Ops')->call('approve')->assertHasErrors(['email'])->assertSee('Seules les personnes qui approuvent pour Kilo Pharma');

        // Made a Client Approver (no team or branding), the same link approves
        DB::table('client_users')->where('id', $member)->update(['role_id' => DB::table('roles')->where('slug', 'client-approver')->value('id')]);
        \App\Support\Access::flush();
        $page()->assertSee('Approuver la proposition')->call('choose', 'approve')->set('jobTitle', 'Ops')->call('approve')->assertSet('done', 'approved');
        $this->assertSame([Proposal::CLIENT_APPROVED, 'cyn@kilo.test'], [$p->fresh()->status, $p->fresh()->client_approved_email]);
        $this->assertEqualsCanonicalizing(['portal.projects.view', 'portal.projects.approve', 'portal.qa.view', 'portal.qa.create', 'portal.documents.view',
            'portal.documents.export', 'portal.support.create'], \App\Support\Access::permissionsFor(\App\Models\ClientUser::find($member)));
    }

    public function test_d5_a_contacts_link_approves_only_as_them_and_a_link_without_one_needs_the_code_emailed_to_the_approver(): void
    {
        $role = fn (string $slug) => DB::table('roles')->where('slug', $slug)->value('id');
        $member = $this->clientContact($this->o->organization_id, ['first_name' => 'Cyn', 'last_name' => 'Okafor', 'email' => 'cyn@kilo.test', 'is_primary_client' => 0, 'role_id' => $role('client-member')]);
        $this->clientContact($this->o->organization_id, ['first_name' => 'Ade', 'last_name' => 'Bello', 'email' => 'ade@kilo.test', 'is_primary_client' => 0, 'role_id' => $role('client-approver')]);
        $p = $this->sent();

        // A team member's link with an approver's address typed: the link's own address counts, so it is refused
        Livewire::withQueryParams(['c' => $member])->test(ProposalReview::class, ['proposal' => $p->id])
            ->set('email', 'ngozi@kilo.test')->set('jobTitle', 'CFO')->call('approve')->assertHasErrors(['email'])->assertSet('email', 'cyn@kilo.test');
        $this->assertSame(Proposal::SENT, $p->fresh()->status);
        // An approver's own link shows their address read-only and approves at once (test_the_client_approves_with_one_click…)
        $this->page($p)->call('choose', 'approve')->assertSee('Ce lien vous a été envoyé à cette adresse')->assertSeeHtml('value="ngozi@kilo.test" readonly');

        // A link without a contact: typing an approver's address approves nothing; the code goes to that address
        $page = $this->page($p, [])->call('choose', 'approve')->assertSee('Nous vous envoyons ensuite un code')
            ->set('name', 'Someone Else')->set('email', 'ngozi@kilo.test')->set('jobTitle', 'CFO')->call('approve')
            ->assertHasNoErrors()->assertSet('step', 'code')->assertSet('done', '');
        $this->assertSame(Proposal::SENT, $p->fresh()->status);
        Mail::assertSent(SigningCode::class, fn (SigningCode $m) => $m->hasTo('ngozi@kilo.test') && $m->purpose === SigningCode::APPROVAL);
        $code = Mail::sent(SigningCode::class)->last()->code;
        $this->assertSame(SigningCodes::hash($code . '|ngozi@kilo.test'), $p->fresh()->auth_otp, 'Kept as an HMAC, with the address it was sent to');
        $page->set('code', $code === '000000' ? '111111' : '000000')->call('verifyApproval')->assertHasErrors('code');
        $this->assertSame(Proposal::SENT, $p->fresh()->status);

        // Another approver asks for a code: Ngozi's no longer approves; Ade's does, as Ade
        $other = $this->page($p, [])->call('choose', 'approve')->set('name', 'Ade Bello')->set('email', 'ade@kilo.test')->set('jobTitle', 'COO')->call('approve')->assertSet('step', 'code');
        $page->set('code', $code)->call('verifyApproval')->assertHasErrors('code');
        $other->set('code', Mail::sent(SigningCode::class)->last()->code)->call('verifyApproval')->assertHasNoErrors()->assertSet('done', 'approved');
        $p->refresh();
        $this->assertSame([Proposal::CLIENT_APPROVED, 'ade@kilo.test', 'Ade Bello', 'COO', null], [$p->status, $p->client_approved_email, $p->client_approved_name, $p->client_approved_title, $p->auth_otp]);
    }

    public function test_the_link_lasts_as_long_as_the_proposal_and_every_action_checks_it(): void
    {
        $p = $this->sent(['validity_days' => 90]);
        parse_str((string) parse_url(PortalLinks::proposal($p, $this->contact), PHP_URL_QUERY), $query);
        $this->assertSame($p->validUntil()->getTimestamp(), (int) $query['expires'], 'Signed until the proposal’s validity, past PortalLinks::DAYS');
        parse_str((string) parse_url(PortalLinks::proposalPdf($p), PHP_URL_QUERY), $pdf);
        $this->assertSame((int) $query['expires'], (int) $pdf['expires']);

        // A page opened just before the link expires: its next action is refused (Livewire updates skip `signed`)
        $page = Livewire::withQueryParams(['c' => $this->contact, 'expires' => now()->addMinutes(5)->getTimestamp()])->test(ProposalReview::class, ['proposal' => $p->id])
            ->call('openComment', 'general')->set('body', 'In time?');
        $this->travel(10)->minutes();
        $page->call('comment')->assertHasErrors(['mode'])->assertSee('Ce lien a expiré');
        $this->assertSame(0, ProposalComment::count());

        // Past the proposal's validity: no comment, approval or change request, whoever calls
        $this->travel(91)->days();
        $this->assertNull(Proposals::comment($p->fresh(), 'general', ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'Late'));
        $this->assertFalse(Proposals::requestChanges($p->fresh(), ['name' => 'Ngozi Eze', 'email' => 'ngozi@kilo.test'], 'Late'));
        $this->assertSame(Proposal::SENT, $p->fresh()->status);
    }

    public function test_the_client_approves_with_one_click_and_it_happens_once(): void
    {
        Event::fake([ProposalApprovedByClient::class]);
        $p = $this->sent();
        $this->o->forceFill(['stage' => 'proposal_sent'])->save();

        $page = $this->page($p)->call('choose', 'approve')->set('jobTitle', '')->call('approve')->assertHasErrors(['jobTitle'])
            ->set('jobTitle', 'Finance director')->call('approve')->assertSet('done', 'approved')->assertSee('Merci');
        $p->refresh();
        $this->assertSame([Proposal::CLIENT_APPROVED, 'Ngozi Eze', 'Finance director', 'ngozi@kilo.test'],
            [$p->status, $p->client_approved_name, $p->client_approved_title, $p->client_approved_email]);
        $this->assertNotNull($p->client_approved_at);
        $this->assertSame('proposal_approved', $this->o->fresh()->stage);
        Event::assertDispatched(ProposalApprovedByClient::class, 1);
        $this->assertTrue(DB::table('notifications')->where('user_id', $this->lead->id)->where('title', 'Proposal approved by the client')->exists());

        // Again: nothing changes, and the page no longer offers it
        $this->assertFalse(Proposals::approveForClient($p, ['name' => 'Someone', 'email' => 'ngozi@kilo.test'], 'CEO'));
        $this->page($p)->assertDontSee('Approuver la proposition')->call('approve')->assertHasErrors(['mode']);
        $this->assertSame('Ngozi Eze', $p->fresh()->client_approved_name);
        Event::assertDispatched(ProposalApprovedByClient::class, 1);
    }

    public function test_after_its_validity_the_client_can_no_longer_approve(): void
    {
        $p = $this->sent(['validity_days' => 7]);
        $this->travel(8)->days();
        $this->page($p)->assertSee('valable jusqu’au', false)->assertDontSee('Un clic')
            ->call('approve')->assertHasErrors(['mode']);
        $this->assertSame(Proposal::SENT, $p->fresh()->status);
    }

    public function test_the_client_asks_for_changes_and_the_lead_starts_the_next_version(): void
    {
        Event::fake([ProposalChangesRequested::class]);
        $p = $this->sent();
        $this->o->forceFill(['stage' => 'proposal_sent'])->save();

        $this->page($p)->call('choose', 'changes')->set('changeNote', 'Please add two training days.')->call('requestChanges')
            ->assertSet('done', 'changes')->assertSee('Please add two training days.');
        $this->assertSame(Proposal::CHANGES_REQUESTED, $p->fresh()->status);
        $this->assertSame('proposal', $this->o->fresh()->stage);
        $this->assertSame(['general', 'Please add two training days.'], [ProposalComment::sole()->section_key, ProposalComment::sole()->body]);
        Event::assertDispatched(ProposalChangesRequested::class);
        // No more comments or approval on this version
        $this->page($p)->assertDontSee('Commenter cette partie')->call('approve')->assertHasErrors(['mode']);

        // Version 2 is sent: version 1's page points to it
        $this->actingAs($this->lead);
        $v2 = Proposals::newVersion($this->o, $this->lead);
        Proposals::save($v2, ['title' => 'A faster close', 'sections' => $v2->sections, 'lines' => [], 'changes_summary' => 'Training added.'], $this->lead);
        Proposals::approve($v2, $this->lead);
        Proposals::send($v2, ['ngozi@kilo.test'], $this->lead);
        $this->page($p->fresh())->assertSee('Une version plus récente')->assertSee('Voir la dernière version');
        $this->page($v2->fresh())->assertSee('Training added.')->assertSee('Ce qui a changé depuis la version 1');
    }

    public function test_the_page_offers_a_follow_up_and_the_pdf_and_never_shows_internal_records(): void
    {
        $meeting = \App\Models\Meeting::create(['opportunity_id' => $this->o->id, 'organization_id' => $this->o->organization_id, 'kind' => 'internal', 'title' => 'Internal pricing review',
            'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 10:00:00', 'status' => 'held', 'transcript_status' => 'ready']);
        \App\Models\MeetingTranscript::create(['meeting_id' => $meeting->id, 'source' => 'paste', 'text' => 'Our margin target is 40.', 'collected_at' => now()]);
        $p = $this->sent();

        $this->page($p)->assertSee('Réserver une réunion de suivi')->assertSee('/meetings/' . $this->o->id, false)->assertSee('/proposals/' . $p->id . '/pdf', false)
            ->assertDontSee('Internal pricing review')->assertDontSee('margin target');
        // A lost opportunity takes no follow-up booking, comment or approval
        $this->o->forceFill(['stage' => 'lost'])->save();
        $this->page($p)->assertDontSee('Réserver une réunion de suivi')->assertDontSee('Commenter cette partie')->assertDontSee('Un clic')
            ->set('jobTitle', 'Finance director')->call('approve')->assertHasErrors(['mode']);
        $this->assertSame(Proposal::SENT, $p->fresh()->status);
    }
}
