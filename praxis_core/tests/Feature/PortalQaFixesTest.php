<?php

namespace Tests\Feature;

use App\Livewire\ClientOs\Dashboard;
use App\Models\ClientUser;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

/**
 * Fixes from the live portal QA (docs/QA-PORTAL.md section 4): the portal opens before the first project, Documents
 * lists the statements of work and proposals the client was sent, progress and lead match HQ, deleted tickets stay
 * out, only a task in review can be approved (and its lead hears of it), and the portal's colours are hex colours.
 */
class PortalQaFixesTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private function organisation(): string
    {
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'QA Test Client (delete)', 'status' => 'Lead', 'preferred_language' => 'EN']);

        return $org;
    }

    private function client(string $organisationId, array $values = []): ClientUser
    {
        return ClientUser::forceCreate($values + [
            'organization_id' => $organisationId, 'role_id' => Access::clientAdminRoleId(),
            'first_name' => 'Adaeze', 'last_name' => 'Client', 'email' => Str::lower(Str::random(10)) . '@client.test',
            'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1,
        ]);
    }

    private function opportunity(string $org, string $lead): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('opportunities', ['id' => $id, 'organization_id' => $org, 'title' => 'Fleet portal', 'stage' => 'proposal_sent', 'lead_user_id' => $lead, 'language' => 'en']);

        return $id;
    }

    public function test_before_the_first_project_the_portal_opens_on_documents_with_the_proposal_sent(): void
    {
        $org = $this->organisation();
        $lead = $this->staff('sales', ['sales']);
        $opportunity = $this->opportunity($org, $lead->id);
        $proposal = (string) Str::uuid();
        $this->insertRow('proposals', ['id' => $proposal, 'organization_id' => $org, 'opportunity_id' => $opportunity, 'proposal_ref' => 'PRP-QA-1', 'version' => 2,
            'title' => 'Fleet portal proposal', 'status' => 'Sent', 'sent_at' => now()->subDay(), 'validity_days' => 30]);
        $this->insertRow('proposals', ['id' => (string) Str::uuid(), 'organization_id' => $org, 'opportunity_id' => $opportunity, 'proposal_ref' => 'PRP-QA-1', 'version' => 3,
            'title' => 'Draft not sent', 'status' => 'Draft']);
        $client = $this->client($org);

        $portal = Livewire::actingAs($client, 'client')->test(Dashboard::class)
            ->assertOk()->assertSet('activeTab', 'vault')
            ->assertSee('Fleet portal proposal')->assertDontSee('Draft not sent')->assertSee('waiting for your answer');
        $link = $portal->get('vaultProposals')[0]['open'];
        $this->assertStringContainsString('/proposals/' . $proposal, $link);
        $this->assertStringContainsString('signature=', $link);
        $this->assertStringContainsString('c=' . $client->id, $link);

        // The project sections say why they're empty; nothing breaks
        $portal->call('switchTab', 'overview')->assertSee('Your project space opens when the work starts')
            ->call('switchTab', 'financials')->assertSee('Your project space opens when the work starts')
            ->call('switchTab', 'team')->assertSee('Adaeze');
    }

    public function test_documents_list_the_signed_sow_with_fresh_links_and_an_imported_one_as_signed_on_paper(): void
    {
        $org = $this->organisation();
        $lead = $this->staff('sales', ['sales']);
        $opportunity = $this->opportunity($org, $lead->id);
        $project = $this->project(['organization_id' => $org, 'opportunity_id' => $opportunity, 'lead_user_id' => $lead->id]);
        $proposal = (string) Str::uuid();
        $this->insertRow('proposals', ['id' => $proposal, 'organization_id' => $org, 'opportunity_id' => $opportunity, 'proposal_ref' => 'PRP-QA-1', 'version' => 1,
            'title' => 'Fleet portal proposal', 'status' => 'Client Approved', 'sent_at' => now()->subDays(70), 'client_approved_at' => now()->subDays(65), 'validity_days' => 30]);
        $signed = (string) Str::uuid();
        $this->insertRow('statements_of_work', ['id' => $signed, 'opportunity_id' => $opportunity, 'proposal_id' => $proposal, 'reference' => 'SOW-QA-1', 'version' => 1,
            'title' => 'Fleet portal SOW', 'status' => 'signed', 'sent_at' => now()->subDays(60), 'signed_at' => now()->subDays(58), 'validity_days' => 30, 'project_id' => $project]);
        $imported = (string) Str::uuid();
        $this->insertRow('statements_of_work', ['id' => $imported, 'opportunity_id' => $opportunity, 'proposal_id' => null, 'reference' => 'SOW-OLD', 'version' => 1,
            'title' => 'Earlier SOW', 'status' => 'signed', 'sent_at' => null, 'signed_at' => now()->subDays(90), 'project_id' => $project]);
        $client = $this->client($org);

        $portal = Livewire::actingAs($client, 'client')->test(Dashboard::class)->call('switchTab', 'vault')
            ->assertSee('Fleet portal SOW')->assertSee('Earlier SOW')->assertSee('Signed on paper');
        $docs = collect($portal->get('vaultContracts'))->keyBy('id');
        $this->assertNull($docs[$imported]['pdf']);
        $this->assertSame('signed', $docs[$signed]['state']);

        // The email's link ended 30 days after sending; the portal's link opens the PDF today
        $this->get($docs[$signed]['pdf'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('/sow/' . $signed, $docs[$signed]['open']);

        // A member limited to another project sees neither
        $other = $this->project(['organization_id' => $org]);
        $member = $this->client($org, ['is_primary_client' => 0, 'restricted_project_id' => $other]);
        $memberView = Livewire::actingAs($member, 'client')->test(Dashboard::class)->call('switchTab', 'vault')->assertSet('activeTab', 'vault');
        $this->assertSame([], $memberView->get('vaultContracts'));
        $this->assertSame([], $memberView->get('vaultProposals'));
    }

    public function test_progress_and_lead_match_hq_and_deleted_tickets_stay_out(): void
    {
        $org = $this->organisation();
        $lead = $this->staff('delivery', ['delivery']);
        $project = $this->project(['organization_id' => $org, 'lead_user_id' => $lead->id, 'created_by' => null]);
        $m1 = $this->phase($project, 1, 40, ['status' => 'Active']);
        $m2 = $this->phase($project, 2, 60);
        $this->task($m1, ['status' => 'Done']);
        $this->task($m1, ['status' => 'To-Do']);
        $this->insertRow('qa_tickets', ['id' => (string) Str::uuid(), 'project_id' => $project, 'title' => 'Shown ticket', 'status' => 'Open']);
        $this->insertRow('qa_tickets', ['id' => (string) Str::uuid(), 'project_id' => $project, 'title' => 'Deleted ticket', 'status' => 'Open', 'deleted_at' => now()]);
        $client = $this->client($org);

        // M1 half done (40% × 50%) = 20%, as HQ's project page says; before, no milestone accepted meant 0%
        $portal = Livewire::actingAs($client, 'client')->test(Dashboard::class)->call('switchTab', 'overview')
            ->assertSet('overallProgress', 20);
        $this->assertStringStartsWith($lead->first_name, $portal->get('leadArchitect'));

        $titles = collect($portal->call('switchTab', 'qa_hub')->get('qaTickets'))->pluck('title')->all();
        $this->assertSame(['Shown ticket'], $titles);
    }

    public function test_only_a_task_in_review_can_be_approved_and_the_lead_hears_of_it(): void
    {
        $org = $this->organisation();
        $lead = $this->staff('delivery', ['delivery']);
        $project = $this->project(['organization_id' => $org, 'lead_user_id' => $lead->id]);
        $m1 = $this->phase($project, 1, 100, ['status' => 'Active']);
        $todo = $this->task($m1, ['title' => 'Not started yet', 'status' => 'To-Do']);
        $review = $this->task($m1, ['title' => 'Map view', 'status' => 'QA_Review']);
        $client = $this->client($org);

        $portal = Livewire::actingAs($client, 'client')->test(Dashboard::class)->call('switchTab', 'sprints');
        $portal->call('approveTask', $todo);
        $this->assertSame('To-Do', DB::table('tasks')->where('id', $todo)->value('status'));

        $portal->call('approveTask', $review);
        $this->assertSame('Done', DB::table('tasks')->where('id', $review)->value('status'));
        $bell = DB::table('notifications')->where('user_id', $lead->id)->sole();
        $this->assertStringContainsString('approved “Map view”', $bell->message);
        $this->assertStringContainsString('/operations/projects/' . $project, $bell->link_url);
    }

    public function test_a_suspended_or_removed_accounts_invitation_opens_nothing(): void
    {
        $org = $this->organisation();
        foreach ([['status' => 'Suspended'], ['deleted_at' => now()]] as $i => $state) {
            $token = Str::random(60);
            $this->client($org, $state + ['password' => null, 'invitation_token' => $token, 'lifecycle_stage' => 'Onboarding', 'is_primary_client' => 0]);
            $this->get('http://' . config('app.domains.portal') . '/setup/' . $token)->assertNotFound();
        }
        $token = Str::random(60);
        $this->client($org, ['password' => null, 'invitation_token' => $token, 'lifecycle_stage' => 'Onboarding']);
        $this->get('http://' . config('app.domains.portal') . '/setup/' . $token)->assertOk();
    }

    public function test_portal_colours_must_be_hex_colours(): void
    {
        $org = $this->organisation();
        $project = $this->project(['organization_id' => $org]);
        $this->phase($project, 1, 100);
        $client = $this->client($org);

        Livewire::actingAs($client, 'client')->test(Dashboard::class)->call('switchTab', 'vault')
            ->set('brandPrimary', 'red;x:y')->call('saveBrandSettings')->assertHasErrors('brandPrimary')
            ->set('brandPrimary', '#123ABC')->call('saveBrandSettings')->assertHasNoErrors();
        $this->assertSame('#123ABC', DB::table('client_portal_settings')->where('organization_id', $org)->value('primary_color'));
    }
}
