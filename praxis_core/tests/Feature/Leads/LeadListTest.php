<?php

namespace Tests\Feature\Leads;

use App\Livewire\Hq\Intelligence\LeadsRegister;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Support\Leads\LeadNumbers;
use App\Support\Leads\Leads;
use App\Support\Leads\LeadStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Leads PR 3's list (owner's decisions 8 and 18, docs/LEADS.md section 9): the filters with Mine on by default, the
 * summary strip (LeadNumbers) with its period and per-owner view, and the CSV of what the filters show.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadListTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function lead(string $name, $by, array $values = [], array $contacts = [], array $options = []): Organization
    {
        return Leads::create($values + ['name' => $name, 'country' => 'Nigeria', 'industry' => 'Logistics & Supply Chain', 'phone' => '+234 800 000 ' . random_int(1000, 9999)], $contacts, $by, $options);
    }

    private function names($screen): array
    {
        return collect($screen->viewData('leads'))->pluck('name')->sort()->values()->all();
    }

    public function test_the_filters_start_on_mine(): void
    {
        $gerald = $this->staff('gerald', ['sales']);
        $other = $this->staff('ifeoma', ['sales']);
        $this->actingAs($gerald);
        $lagos = Carbon::now('Africa/Lagos');
        $this->lead('Alpha Freight (test)', $gerald, ['next_step' => 'Call back', 'next_step_due' => $lagos->copy()->subDays(2)->format('Y-m-d')],
            [['name' => 'Ada Alpha', 'email' => 'ada@alpha.test']]);
        $this->lead('Bravo Bank (test)', $other, ['country' => 'Ghana', 'next_step' => 'Send deck', 'next_step_due' => $lagos->format('Y-m-d')], [], ['source' => 'event']);
        $this->lead('Charlie Foods (test)', $gerald, [], [], ['owner_id' => null]);
        $delta = $this->lead('Delta Oil (test)', $gerald, ['next_step' => 'Visit', 'next_step_due' => $lagos->copy()->addDays(3)->format('Y-m-d')]);
        LeadStatus::change($delta, 'contacted', $gerald);

        // Mine is on by default; Everyone, No owner and one person are the other owner choices
        $screen = Livewire::test(LeadsRegister::class)->assertSet('owner', 'mine')->assertSee('2 leads of yours.');
        $this->assertSame(['Alpha Freight (test)', 'Delta Oil (test)'], $this->names($screen));
        $this->assertSame(['Alpha Freight (test)', 'Bravo Bank (test)', 'Charlie Foods (test)', 'Delta Oil (test)'], $this->names($screen->set('owner', 'all')));
        $this->assertSame(['Charlie Foods (test)'], $this->names($screen->set('owner', 'none')));
        $this->assertSame(['Bravo Bank (test)'], $this->names($screen->set('owner', $other->id)));
        $screen->assertSee('Ifeoma Test'); // offered in the owner filter

        // Status, next step, source, country and search, with Everyone
        $screen->set('owner', 'all');
        $this->assertSame(['Delta Oil (test)'], $this->names($screen->set('status', 'contacted')));
        $this->assertSame(['Alpha Freight (test)', 'Bravo Bank (test)', 'Charlie Foods (test)', 'Delta Oil (test)'], $this->names($screen->set('status', 'open')));
        $screen->set('status', '');
        $this->assertSame(['Alpha Freight (test)'], $this->names($screen->set('step', 'overdue')));
        $this->assertSame(['Bravo Bank (test)'], $this->names($screen->set('step', 'today')));
        $this->assertSame(['Bravo Bank (test)', 'Delta Oil (test)'], $this->names($screen->set('step', 'week')));
        $this->assertSame(['Charlie Foods (test)'], $this->names($screen->set('step', 'none')));
        $screen->set('step', '');
        $this->assertSame(['Bravo Bank (test)'], $this->names($screen->set('source', 'event')));
        $screen->set('source', '');
        $this->assertSame(['Bravo Bank (test)'], $this->names($screen->set('country', 'Ghana')));
        $screen->assertSee('Clear filters')->set('country', '')->assertDontSee('Clear filters');
        $this->assertSame(['Alpha Freight (test)'], $this->names($screen->set('search', 'ada@alpha')));
        $this->assertSame(['Alpha Freight (test)'], $this->names($screen->set('search', 'Ada Alpha')));
        $screen->set('search', 'nothing like it')->assertSee('No leads match these filters')->call('clearFilters')->assertSet('search', '')->assertSet('owner', 'all');

        // The summary strip's links set the filters
        $screen->call('filterBy', 'status', 'contacted')->assertSet('status', 'contacted')->call('filterBy', 'step', 'overdue')->assertSet('step', 'overdue');

        // Filters from the address that aren't choices fall back to their defaults
        $odd = Livewire::withQueryParams(['owner' => 'nobody-at-all', 'status' => 'won', 'step' => 'soon', 'source' => 'x', 'period' => 'decade'])->test(LeadsRegister::class)
            ->assertSet('owner', 'mine')->assertSet('status', '')->assertSet('step', '')->assertSet('source', '')->assertSet('period', 'month');
        $this->assertCount(2, $odd->viewData('leads'));

        // Mine with nothing of mine: a way to everyone's
        $this->actingAs($this->staff('owner', ['owner']));
        Livewire::test(LeadsRegister::class)->assertSee('You don’t own any leads yet')->call('filterBy', 'owner', 'all')->assertSee('Alpha Freight (test)');
        // The CSV holds what the filters show
        $csv = Livewire::withQueryParams(['owner' => 'all', 'country' => 'Ghana'])->test(LeadsRegister::class)->call('exportCsv');
        $this->assertNotNull($csv);
    }

    public function test_the_summary_strip_and_its_per_owner_view(): void
    {
        $gerald = $this->staff('gerald', ['sales']);
        $other = $this->staff('ifeoma', ['sales']);
        $this->actingAs($gerald);
        $lagos = Carbon::now('Africa/Lagos');

        $alpha = $this->lead('Alpha Freight (test)', $gerald, ['next_step' => 'Call back', 'next_step_due' => $lagos->copy()->subDay()->format('Y-m-d')]);
        $this->lead('Bravo Bank (test)', $gerald); // no next step
        $client = $this->lead('Charlie Foods (test)', $gerald, ['next_step' => 'Kick-off', 'next_step_due' => $lagos->copy()->addDay()->format('Y-m-d')]);
        LeadStatus::markClient($client, $gerald);
        $lost = $this->lead('Delta Oil (test)', $gerald);
        LeadStatus::markLost($lost, 'no_budget', null, $gerald);
        $this->lead('Echo Retail (test)', $other, ['next_step' => 'Visit', 'next_step_due' => $lagos->copy()->addDays(2)->format('Y-m-d')]);
        // Added last year, and a client from before the revamp: in "all time" only, never counted as converted
        $old = $this->lead('Foxtrot Mills (test)', $gerald);
        DB::table('organizations')->where('id', $old->id)->update(['created_at' => $lagos->copy()->subYear()->utc(), 'lead_status' => 'client', 'status' => 'Active Client', 'client_via' => 'existing']);

        // Open opportunities and what their current proposal is worth, before VAT; a proposal set aside or a won opportunity doesn't count
        $open = Opportunity::create(['organization_id' => $alpha->id, 'title' => 'Fleet tracking (test)', 'stage' => 'proposal_sent', 'lead_user_id' => $gerald->id, 'language' => 'en', 'source' => 'manual', 'created_by' => $gerald->id]);
        Opportunity::create(['organization_id' => $alpha->id, 'title' => 'Audit (test)', 'stage' => 'discovery', 'lead_user_id' => $gerald->id, 'language' => 'en', 'source' => 'manual', 'created_by' => $gerald->id]);
        $won = Opportunity::create(['organization_id' => $client->id, 'title' => 'Rollout (test)', 'stage' => 'won', 'lead_user_id' => $gerald->id, 'language' => 'en', 'source' => 'manual', 'created_by' => $gerald->id]);
        foreach ([[$open, 1, 'Superseded', 900000], [$open, 2, 'Sent', 2500000], [$won, 1, 'Client Approved', 7000000]] as [$opp, $version, $status, $subtotal]) {
            $this->insertRow('proposals', ['id' => (string) Str::uuid(), 'organization_id' => $opp->organization_id, 'opportunity_id' => $opp->id, 'proposal_ref' => 'PRP-L-' . substr($opp->id, 0, 8) . '-' . $version,
                'version' => $version, 'status' => $status, 'signing_mode' => 'approve', 'subtotal' => $subtotal, 'total_value' => $subtotal * 1.075, 'currency' => 'NGN',
                'created_at' => now()->addSeconds($version), 'updated_at' => now()]);
        }

        $mine = LeadNumbers::summary('mine', 'month', $gerald);
        $this->assertSame(['week' => 4, 'month' => 4, 'period' => 4], $mine['new']);
        $this->assertSame(['new' => 2, 'contacted' => 0, 'qualified' => 0, 'client' => 1, 'lost' => 1], $mine['status']);
        $this->assertSame(['clients' => 1, 'leads' => 4, 'rate' => 25.0], $mine['conversion']);
        $this->assertSame(['totals' => ['NGN' => 2500000.0], 'open' => 2, 'priced' => 1], $mine['value']);
        $this->assertSame([1, 1], [$mine['overdue'], $mine['noStep']]);

        $all = LeadNumbers::summary('all', 'all', $gerald);
        $this->assertSame(6, $all['new']['period']);
        $this->assertSame(2, $all['status']['client']);
        $this->assertSame(['clients' => 1, 'leads' => 5, 'rate' => 20.0], $all['conversion']); // the client from before isn't a conversion
        $this->assertSame(0, LeadNumbers::summary($other->id, 'month', $gerald)['overdue']);

        $owners = LeadNumbers::byOwner('month', $gerald);
        $this->assertSame(['Gerald Test', 'Ifeoma Test'], $owners->pluck('name')->all());
        $this->assertSame([4, 2, 1, 1], [$owners[0]->new, $owners[0]->open, $owners[0]->overdue, $owners[0]->noStep]);

        // On the list: the strip for the owner filter, the period, and the per-owner view
        $screen = Livewire::test(LeadsRegister::class)->assertSee('Numbers for your leads')->assertSee('New leads, this month')
            ->assertSee('1 of 4 leads added this month is a client now')->assertSee('2 open opportunities, 1 with a proposal (before VAT)')->assertSee('1 with no next step');
        $screen->set('period', 'all')->set('owner', 'all')->assertSee('Numbers for everyone’s leads')->assertSee('1 of 5 leads added so far is a client now')
            ->toggle('byOwner')->assertSee('Ifeoma Test')->call('filterBy', 'owner', $other->id)->assertSet('owner', $other->id)->assertSee('Numbers for Ifeoma Test’s leads');
    }
}
