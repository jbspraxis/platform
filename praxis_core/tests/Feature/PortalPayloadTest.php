<?php

namespace Tests\Feature;

use App\Livewire\ClientOs\Dashboard;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Pwa\ClientNotify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

/**
 * The Client Portal's speed (performance pass, 11 Nov 2026). The dashboard's state goes to the browser and back on
 * every click, so it holds only what the open section shows: never signature images, signing codes, IP addresses or
 * internal CRM notes, and never every section at once (it was 120 to 315 KB for a project of 100 tasks, now a few KB).
 * The invoices' "payment pending" is one query, and the bell and the sign-in settings add no queries to a page load.
 */
class PortalPayloadTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private const SIGNATURE = 'iVBORw0KGgoSignatureImage';

    private string $org;

    private string $projectId;

    private ClientUser $client;

    /** @var string[] */
    private array $tasks = [];

    /** A project of 100 tasks, 20 issues and 8 invoices, with a signed older proposal and a signed handover */
    private function seedProject(): void
    {
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'Payload Client', 'status' => 'Active Client', 'preferred_language' => 'EN',
            'ai_history_summary' => 'Internal CRM note: price sensitive', 'disqualification_reason' => 'Internal reason']);
        $lead = $this->staff('delivery', ['delivery']);
        $this->projectId = $this->project(['organization_id' => $this->org, 'lead_user_id' => $lead->id, 'description' => 'Internal project description']);
        $phases = [];
        foreach (range(1, 6) as $n) {
            $phases[] = $this->phase($this->projectId, $n, $n === 6 ? 15 : 17, ['status' => $n === 1 ? 'Completed' : ($n === 2 ? 'Active' : 'Pending')]);
        }
        $this->client = ClientUser::forceCreate([
            'organization_id' => $this->org, 'role_id' => Access::clientAdminRoleId(), 'first_name' => 'Ada', 'last_name' => 'Client',
            'email' => Str::lower(Str::random(10)) . '@client.test', 'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1,
        ]);
        $statuses = ['To-Do', 'In-Progress', 'QA_Review', 'Done'];
        for ($i = 0; $i < 100; $i++) {
            $this->tasks[] = $this->task($phases[$i % 6], ['title' => "Deliverable {$i}", 'status' => $statuses[$i % 4], 'description' => str_repeat('Task notes for the team. ', 16)]);
        }
        for ($i = 0; $i < 15; $i++) {
            DB::table('task_comments')->insert(['task_id' => $this->tasks[$i], 'user_id' => $this->client->id, 'author_type' => 'client', 'comment_text' => 'Approved by the client', 'created_at' => now()]);
        }
        for ($i = 0; $i < 20; $i++) {
            $this->insertRow('qa_tickets', ['id' => (string) Str::uuid(), 'project_id' => $this->projectId, 'task_id' => $this->tasks[$i], 'title' => "Issue {$i}",
                'description' => str_repeat('Steps to reproduce. ', 15), 'status' => 'Open', 'severity' => 'Medium', 'submitted_by' => $this->client->id, 'submitted_by_type' => 'client']);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->invoice($this->projectId, $phases[$i % 6], $i < 6 ? 'Paid' : 'Pending');
        }
        $signature = str_repeat(self::SIGNATURE, 1600); // about 40 KB, as a drawn signature
        $this->insertRow('proposals', ['id' => (string) Str::uuid(), 'organization_id' => $this->org, 'proposal_ref' => 'PRP-OLD-1', 'title' => 'Older signed proposal',
            'status' => 'Authorized', 'token_hash' => Str::random(40), 'hq_signature_b64' => $signature, 'client_signature_b64' => $signature, 'auth_otp' => '481516']);
        $this->insertRow('project_signoffs', ['id' => (string) Str::uuid(), 'project_id' => $this->projectId, 'token' => 'handover' . Str::random(30), 'executive_summary' => 'Delivered',
            'roi_metrics_achieved' => '[]', 'client_signature_b64' => $signature, 'jbs_signature_b64' => $signature, 'client_signature_hash' => hash('sha256', 'signed'),
            'client_ip_address' => '203.0.113.7', 'client_user_agent' => 'SecretBrowser/1.0', 'auth_otp' => '234242', 'status' => 'Signed', 'signed_at' => now()]);
    }

    public function test_the_state_holds_only_the_open_sections_fields_and_never_signatures_codes_or_internal_notes(): void
    {
        $this->seedProject();
        $snapshot = fn ($portal) => json_encode($portal->snapshot);

        $portal = Livewire::actingAs($this->client, 'client')->test(Dashboard::class)->assertSet('activeTab', 'overview');
        $atMount = strlen($snapshot($portal));
        $this->assertLessThan(12_000, $atMount, 'The overview\'s state (was about 120 KB)');

        foreach (['sprints', 'qa_hub', 'financials', 'vault', 'team', 'overview'] as $tab) {
            $json = $snapshot($portal->call('switchTab', $tab)->assertSet('activeTab', $tab));
            $this->assertLessThan(40_000, strlen($json), "The state on {$tab}");
            foreach ([self::SIGNATURE, '481516', '234242', '203.0.113.7', 'SecretBrowser', 'Internal CRM note', 'Internal reason', 'Internal project description', 'Task notes for the team'] as $secret) {
                $this->assertStringNotContainsString($secret, $json, "{$secret} on {$tab}");
            }
        }
        // Back on the overview, nothing from the sections visited is left: the state is as small as at first
        $this->assertSame([], $portal->get('activeTasks'));
        $this->assertSame([], $portal->get('qaTickets'));
        $this->assertSame([], $portal->get('vaultSignoffs'));
        $this->assertSame([], $portal->get('teamMembers'));
        $this->assertLessThan($atMount + 500, strlen($snapshot($portal)));

        // Each section still shows what it did
        $portal->call('switchTab', 'sprints')->assertSee('Deliverable 99')->assertSee('M1 · Milestone 1');
        $portal->call('switchTab', 'qa_hub')->assertSee('Issue 19')->assertSee(Str::limit(str_repeat('Steps to reproduce. ', 15), 100));
        $portal->call('switchTab', 'vault')->assertSee('Older signed proposal')->assertSee('PRP-OLD-1')
            ->assertSee(route('portal.signoff.gateway', ['token' => DB::table('project_signoffs')->value('token')]), false);
    }

    public function test_an_issue_opens_whole_in_the_conversation_and_only_on_the_clients_own_project(): void
    {
        $this->seedProject();
        $long = str_repeat('Every step to reproduce the problem on the map. ', 8);
        $ticket = (string) Str::uuid();
        $this->insertRow('qa_tickets', ['id' => $ticket, 'project_id' => $this->projectId, 'title' => 'Map blank', 'description' => $long, 'status' => 'Open', 'severity' => 'Critical']);
        $elsewhere = (string) Str::uuid();
        $this->insertRow('qa_tickets', ['id' => $elsewhere, 'project_id' => $this->project(), 'title' => 'Another client\'s issue', 'description' => 'Not yours', 'status' => 'Open']);

        $portal = Livewire::actingAs($this->client, 'client')->test(Dashboard::class)->call('switchTab', 'qa_hub');
        $listed = collect($portal->get('qaTickets'))->firstWhere('id', $ticket);
        $this->assertSame(Str::limit($long, 100), $listed->excerpt);
        $this->assertObjectNotHasProperty('description', $listed);

        // The list opens the issue's card in the conversation (docs/CHAT.md), where it is shown whole
        $this->assertGreaterThan(0, $listed->card_id);
        \Livewire\Livewire::actingAs($this->client, 'client')->test(\App\Livewire\ClientOs\DeliveryChat::class, ['projectId' => $this->projectId])
            ->assertSee(trim($long))->assertSee('Critical')->assertDontSee('Not yours');
        // Another organisation's project has no conversation for this client
        $other = DB::table('qa_tickets')->where('id', $elsewhere)->value('project_id');
        \Livewire\Livewire::actingAs($this->client, 'client')->test(\App\Livewire\ClientOs\DeliveryChat::class, ['projectId' => $other])->assertForbidden();
        // The section's rows go when the client moves on
        $portal->call('switchTab', 'sprints')->assertSet('qaTickets', [])->assertSet('qaChat', []);
    }

    public function test_invoices_find_their_pending_payments_in_one_query(): void
    {
        $this->seedProject();
        $waiting = DB::table('invoices')->where('project_id', $this->projectId)->where('status', 'Pending')->value('id');
        $this->insertRow('transactions', ['id' => (string) Str::uuid(), 'invoice_id' => $waiting, 'status' => 'Pending Verification', 'amount_claimed' => 1000]);
        $portal = Livewire::actingAs($this->client, 'client')->test(Dashboard::class);

        DB::enableQueryLog();
        $portal->call('switchTab', 'financials');
        $transactionQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `transactions`'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $transactionQueries, 'One query for 8 invoices (was one per invoice)');
        $invoices = collect($portal->get('invoices'));
        $this->assertCount(8, $invoices);
        $this->assertSame([$waiting], $invoices->where('has_pending_pop', true)->pluck('id')->all());
        $this->assertSame(['id', 'invoice_number', 'issued_at', 'due_date', 'status', 'total_amount', 'currency', 'has_pending_pop', 'paid_so_far', 'balance'], array_keys((array) $invoices->first()));
    }

    public function test_the_bell_and_the_sign_in_settings_add_no_queries_to_a_page_load(): void
    {
        $this->seedProject();
        ClientNotify::flush();
        $this->assertTrue(ClientNotify::ready());
        ClientNotify::flush(); // a new request: the answer comes from the cache, not information_schema

        DB::enableQueryLog();
        $portal = Livewire::actingAs($this->client, 'client')->test(Dashboard::class);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(0, $queries->filter(fn ($q) => str_contains($q, 'information_schema'))->count());
        $this->assertSame(1, $queries->filter(fn ($q) => str_contains($q, 'from `organizations`'))->count(), 'The bell uses the dashboard\'s language');
        $this->assertSame(0, $queries->filter(fn ($q) => str_contains($q, '`passkeys`') || str_contains($q, '`sign_in_pins`'))->count(), 'Sign-in settings load when opened');
        $portal->assertSeeHtml('$wire.__lazyLoad');

        // The whole page, in French: the bell speaks the dashboard's language; the drawer waits to be opened
        $this->actingAs($this->client, 'client')->withSession(['locale' => 'fr'])->get(route('client.dashboard'))->assertOk()
            ->assertSee(__('portal.inbox.empty_title', [], 'fr'))->assertSee(__('sign_in.settings.intro', [], 'fr'))
            ->assertDontSee(__('sign_in.settings.passkeys_title', [], 'fr'));
    }
}
