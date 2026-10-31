<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\Opportunities\OpportunityBoard;
use App\Livewire\Hq\Opportunities\OpportunityPage;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Support\Hosts;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ › Leads and proposals › Opportunities: the board and table, opening an opportunity, its page and tabs, stage
 * changes with the audit log, in English and French (docs/OPPORTUNITIES.md section 4, row 1).
 */
class OpportunityScreensTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    public function test_board_and_table_list_open_opportunities(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $acme = $this->organisation(['name' => 'Acme Foods']);
        $this->opportunityFor($acme, ['title' => 'Payroll for 3 subsidiaries', 'stage' => 'discovery', 'lead_user_id' => $sales->id]);
        $this->opportunityFor($this->organisation(['name' => 'Bravo Bank']), ['title' => 'Risk register', 'stage' => 'proposal_sent']);
        $this->opportunityFor($this->organisation(['name' => 'Charlie Ltd']), ['title' => 'Old win', 'stage' => 'won']);

        Livewire::test(OpportunityBoard::class)
            ->assertSee('Payroll for 3 subsidiaries')->assertSee('Acme Foods')->assertSee('Risk register')
            ->assertSee('Proposal sent')->assertDontSee('Old win')
            ->set('closed', true)->assertSee('Old win')
            ->set('view', 'table')->assertSee('Bravo Bank')
            ->set('search', 'acme')->assertSee('Payroll for 3 subsidiaries')->assertDontSee('Risk register')
            ->set('search', '')->set('lead', 'mine')->assertSee('Payroll for 3 subsidiaries')->assertDontSee('Risk register')
            ->set('lead', '')->set('search', 'nothing like this')->assertSee('No opportunities match');
    }

    public function test_sales_opens_an_opportunity_in_the_organisations_language(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $org = $this->organisation(['name' => 'Société Kribi', 'preferred_language' => 'FR']);

        $screen = Livewire::test(OpportunityBoard::class)
            ->call('openCreate')
            ->set('organizationId', $org)
            ->assertSet('language', 'fr')
            ->assertSet('leadUserId', $sales->id)
            ->set('title', '')
            ->call('create')
            ->assertHasErrors(['title'])
            ->assertSee('Add a title, so the team knows what the work is about.')
            ->set('title', '  Comptabilité OHADA  ')
            ->call('create')
            ->assertHasNoErrors();

        $o = Opportunity::where('organization_id', $org)->firstOrFail();
        $screen->assertRedirect(route('hq.crm.opportunity', $o));
        $this->assertSame(['Comptabilité OHADA', 'discovery', 'fr', 'manual', $sales->id], [$o->title, $o->stage, $o->language, $o->source, $o->lead_user_id]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'OPPORTUNITY_OPENED', 'target_table' => 'opportunities', 'target_id' => $o->id, 'user_id' => $sales->id]);

        // ?new=1&organization=… opens the form for that organisation
        Livewire::withQueryParams(['new' => 1, 'organization' => $org])->test(OpportunityBoard::class)
            ->assertSet('creating', true)->assertSet('organizationId', $org);
    }

    public function test_view_only_roles_cannot_open_or_change(): void
    {
        $this->actingAs($this->staff('finance', ['finance']));
        $o = $this->opportunityFor($this->organisation());

        Livewire::test(OpportunityBoard::class)->assertSet('canCreate', false)->assertDontSee('Open an opportunity')
            ->call('openCreate')->assertForbidden();
        Livewire::test(OpportunityPage::class, ['opportunity' => $o])->assertSet('canManage', false)->assertDontSee('Edit details')
            ->set('stage', 'lost')->set('lostReason', 'x')->call('saveStage')->assertForbidden();
        $this->assertSame('discovery', $o->fresh()->stage);
    }

    public function test_page_tabs_and_stage_changes(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $org = $this->organisation(['name' => 'Kilo Pharma', 'industry' => 'Pharmaceuticals', 'country' => 'Nigeria', 'city' => 'Lagos']);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@kilo.test']);
        $o = $this->opportunityFor($org, ['title' => 'Governance review', 'lead_user_id' => $sales->id]);
        Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => 'Free operations audit',
            'starts_at' => '2026-10-01 09:00:00', 'ends_at' => '2026-10-01 10:00:00', 'status' => 'held', 'transcript_status' => 'manual']);
        $this->insertRow('proposals', ['id' => (string) Str::uuid(), 'organization_id' => $org, 'opportunity_id' => $o->id, 'proposal_ref' => 'PRX-0042',
            'title' => 'Governance review', 'status' => 'Pending Auth', 'subtotal' => 0, 'total_value' => 12500, 'currency' => 'USD', 'payment_schedule_type' => 'Standard']);

        $page = Livewire::test(OpportunityPage::class, ['opportunity' => $o])
            ->assertSee('Governance review')->assertSee('Kilo Pharma')->assertSee('Ngozi Eze')->assertSee('No estimate yet')
            ->call('show', 'meetings')->assertSee('Free operations audit')->assertSee('1 October 2026')->assertSee('10:00 (WAT)')->assertSee('Add by hand')
            ->call('show', 'proposals')->assertSee('PRX-0042')->assertSee('Waiting for signature')->assertSee("USD\u{00A0}12,500")
            ->call('show', 'questionnaire')->assertSee('Draft the questionnaire')
            ->call('show', 'sow')->assertSee('No statement of work yet')
            ->call('show', 'project')->assertSee('No project yet')
            ->call('show', 'nonsense')->assertSet('tab', 'overview');

        // Lost needs a reason
        $page->set('stage', 'lost')->set('lostReason', '')->call('saveStage')
            ->assertHasErrors(['lostReason'])->assertSee('Say why the opportunity was lost');
        $page->set('lostReason', 'Chose an in-house build')->call('saveStage')->assertHasNoErrors();
        $o->refresh();
        $this->assertSame(['lost', 'Chose an in-house build'], [$o->stage, $o->lost_reason]);
        $this->assertNotNull($o->closed_at);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'OPPORTUNITY_STAGE_CHANGED', 'target_id' => $o->id]);

        // Back to work: closed_at and the reason clear
        $page->set('stage', 'proposal')->call('saveStage');
        $o->refresh();
        $this->assertSame(['proposal', null, null], [$o->stage, $o->lost_reason, $o->closed_at]);

        // Edit details
        $delivery = $this->staff('delivery', ['delivery']);
        $page->call('edit')->set('title', 'Governance and risk review')->set('leadUserId', $delivery->id)->set('language', 'fr')->call('saveDetails')->assertHasNoErrors();
        $o->refresh();
        $this->assertSame(['Governance and risk review', $delivery->id, 'fr'], [$o->title, $o->lead_user_id, $o->language]);
        $log = DB::table('system_audit_logs')->where('action', 'OPPORTUNITY_UPDATED')->where('target_id', $o->id)->first();
        // MySQL's JSON column orders keys its own way
        $this->assertEquals(['title' => 'Governance and risk review', 'lead_user_id' => $delivery->id, 'language' => 'fr'], json_decode($log->new_values, true));
        $page->assertSee('Changed the details.');
    }

    public function test_french(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $o = $this->opportunityFor($this->organisation(['name' => 'Société Bafoussam']), ['title' => 'Paie et RH', 'stage' => 'proposal_sent']);
        App::setLocale('fr');

        Livewire::test(OpportunityBoard::class)
            ->assertSee('Opportunités')->assertSee('Proposition envoyée')->assertSee('Ouvrir une opportunité')->assertSee('Paie et RH');
        Livewire::test(OpportunityPage::class, ['opportunity' => $o])
            ->assertSee('Vue d’ensemble')->assertSee('Énoncé des travaux')->assertSee('Coût de la non-qualité')
            ->call('show', 'meetings')->assertSee('Aucune réunion pour l’instant');

        // Every key in one language is in the other
        foreach (['opportunities', 'integrations', 'ai'] as $file) {
            $en = array_keys(\Illuminate\Support\Arr::dot(require lang_path("en/{$file}.php")));
            $fr = array_keys(\Illuminate\Support\Arr::dot(require lang_path("fr/{$file}.php")));
            sort($en);
            sort($fr);
            $this->assertSame($en, $fr, "lang/en/{$file}.php and lang/fr/{$file}.php have different keys");
        }
    }

    public function test_the_rail_shows_opportunities_and_integrations_to_who_may_open_them(): void
    {
        $hq = fn (string $path) => 'https://' . Hosts::hq() . $path;

        $this->actingAs($this->staff('owner', ['owner']))->get($hq('/opportunities'))->assertOk()
            ->assertSee(route('hq.crm.opportunities'), false);
        $this->actingAs($this->staff('owner2', ['owner']))->get($hq('/system/integrations'))->assertOk()
            ->assertSee(route('hq.system.integrations'), false);
        $this->actingAs($this->staff('engineer', ['engineer']))->get($hq('/dashboard/workspace'))->assertOk()
            ->assertDontSee(route('hq.crm.opportunities'), false)->assertDontSee(route('hq.system.integrations'), false);
    }
}
