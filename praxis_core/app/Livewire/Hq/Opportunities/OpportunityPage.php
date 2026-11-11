<?php

namespace App\Livewire\Hq\Opportunities;

use App\Livewire\Hq\Opportunities\Concerns\PicksStaff;
use App\Models\Opportunity;
use App\Support\Access;
use App\Support\Finance\Vat;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One opportunity (docs/OPPORTUNITIES.md section 2): Overview (details, stage, organisation, cost of poor quality,
 * history) and a tab for each later step, each its own component (PRs 2–5): Meetings, Questionnaire, Proposals, SOW,
 * and Project (the project draft until Create project, then the project).
 * opportunities.view opens it; opportunities.manage edits it; the opportunity's own team (lead and members) can do
 * both whatever their roles say. Every change is in the audit log.
 */
#[Layout('components.layouts.hq')]
class OpportunityPage extends Component
{
    use PicksStaff;

    public const TABS = ['overview', 'meetings', 'questionnaire', 'proposals', 'sow', 'project'];

    #[Locked]
    public string $opportunityId;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    #[Locked]
    public bool $canManage = false;

    // Edit details
    public bool $editing = false;

    public string $title = '';

    public string $language = 'en';

    /** The company that bills the work (empty: Nigeria's; Vat), and the client organisation's VAT or tax number */
    public string $billingEntityId = '';

    public string $clientTaxId = '';

    /** The organisation is QA data that qa:wipe may delete (organizations.is_test): only the owner sets it */
    public bool $testClient = false;

    // Change the stage
    public string $stage = '';

    public string $lostReason = '';

    public function mount(Opportunity $opportunity): void
    {
        abort_unless(OpportunityAccess::canView(Auth::user(), $opportunity), 403);
        $this->opportunityId = $opportunity->id;
        $this->canManage = OpportunityAccess::canManage(Auth::user(), $opportunity);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }
        $this->fill(['stage' => $opportunity->stage, 'lostReason' => (string) $opportunity->lost_reason]);
    }

    private function opportunity(): Opportunity
    {
        return Opportunity::with(['organization', 'lead'])->findOrFail($this->opportunityId);
    }

    public function show(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    /** A tab moved the stage (the questionnaire, when drafted and when complete): show it, and keep Move to current */
    #[On('opportunity-stage-changed')]
    public function stageChanged(): void
    {
        $this->stage = $this->opportunity()->stage;
    }

    public function edit(): void
    {
        $this->authorizeManage();
        $o = $this->opportunity();
        $this->fill(['title' => $o->title, 'leadUserId' => (string) $o->lead_user_id, 'language' => $o->language, 'editing' => true,
            'billingEntityId' => (string) $o->billing_entity_id, 'clientTaxId' => (string) $o->organization?->tax_identification_number,
            'testClient' => (bool) $o->organization?->is_test,
            'leadSearch' => '', 'teamSearch' => '', 'teamUserIds' => Team::memberIds($o)]);
    }

    protected function authorizePicking(): void
    {
        abort_unless($this->canManage, 403);
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->resetErrorBag();
    }

    public function saveDetails(): void
    {
        $this->authorizeManage();
        $this->title = trim($this->title);
        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'leadUserId' => ['required', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'teamUserIds' => ['array', 'max:20'],
            'teamUserIds.*' => ['string', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'language' => ['required', Rule::in(Opportunity::LANGUAGES)],
            'billingEntityId' => ['nullable', 'string', Rule::exists('mdm_legal_entities', 'id')->where('is_active', 1)],
            'clientTaxId' => ['nullable', 'string', 'max:100'],
        ], [
            'billingEntityId.exists' => __('vat.billing.invalid'),
            'title.required' => __('opportunities.create.errors.title'),
            'title.max' => __('opportunities.create.errors.title_long'),
            'leadUserId.required' => __('opportunities.create.errors.lead'),
            'leadUserId.exists' => __('opportunities.create.errors.lead'),
            'teamUserIds.max' => __('opportunities.create.errors.team'),
            'teamUserIds.*.exists' => __('opportunities.create.errors.team'),
            'teamUserIds.*.string' => __('opportunities.create.errors.team'),
            'language.required' => __('opportunities.create.errors.language'),
            'language.in' => __('opportunities.create.errors.language'),
        ]);

        $o = $this->opportunity();
        // The billing company (Nigeria's is the default, stored as null): only the owner or the opportunity's lead changes it
        $billing = $this->billingEntityId !== '' && $this->billingEntityId !== Vat::issuer(null)?->id ? $this->billingEntityId : null;
        if ($billing !== $o->billing_entity_id && ! OpportunityAccess::canApprove(Auth::user(), $o)) {
            $this->addError('billingEntityId', __('vat.billing.only_lead'));

            return;
        }
        $this->saveBillingAndTaxId($o, $billing);
        $this->saveTestFlag($o);
        $old = $o->only(['title', 'lead_user_id', 'language']);
        $o->fill(['title' => $this->title, 'lead_user_id' => $this->leadUserId, 'language' => $this->language, 'updated_by' => Auth::id()]);
        if ($o->isDirty()) {
            $new = $o->only(['title', 'lead_user_id', 'language']);
            $o->save();
            OpportunityAccess::log('OPPORTUNITY_UPDATED', "Changed the details of “{$o->title}”.", 'opportunities', $o->id,
                array_intersect_key($old, array_diff_assoc($new, $old)), array_diff_assoc($new, $old));
        }
        Team::sync($o, $this->teamUserIds, Auth::id());
        // Someone who took themselves off the team may no longer edit
        $this->canManage = OpportunityAccess::canManage(Auth::user(), $o->fresh());
        $this->editing = false;
        session()->flash('success', __('opportunities.page.saved'));
    }

    /** The billing company and the organisation's tax number, each change with its own history line */
    private function saveBillingAndTaxId(Opportunity $o, ?string $billing): void
    {
        if ($billing !== $o->billing_entity_id) {
            $name = fn (?string $id) => Vat::companyName($id ?? Vat::issuer(null)?->id) ?? 'Nigeria’s company';
            $before = $o->billing_entity_id;
            $from = $name($before);
            $o->forceFill(['billing_entity_id' => $billing, 'updated_by' => Auth::id()])->save();
            OpportunityAccess::log('OPPORTUNITY_BILLING_COMPANY_CHANGED', "Changed the billing company of “{$o->title}” from {$from} to {$name($billing)}.", 'opportunities', $o->id,
                ['billing_entity_id' => $before], ['billing_entity_id' => $billing]);
        }
        $taxId = trim($this->clientTaxId) ?: null;
        $organisation = $o->organization;
        if ($organisation && $taxId !== ($organisation->tax_identification_number ?: null)) {
            $before = $organisation->tax_identification_number;
            DB::table('organizations')->where('id', $organisation->id)->update(['tax_identification_number' => $taxId, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            OpportunityAccess::log('ORGANISATION_TAX_ID_CHANGED', "Changed the VAT or tax number of {$organisation->name}.", 'opportunities', $o->id,
                ['tax_identification_number' => $before], ['tax_identification_number' => $taxId]);
        }
    }

    /** The owner flags (or unflags) the organisation as a test client; anyone else's checkbox is ignored */
    private function saveTestFlag(Opportunity $o): void
    {
        $organisation = $o->organization;
        if (! $organisation || ! Access::isOwner(Auth::user()) || $this->testClient === (bool) $organisation->is_test) {
            return;
        }
        DB::table('organizations')->where('id', $organisation->id)->update(['is_test' => $this->testClient, 'updated_by' => Auth::id(), 'updated_at' => now()]);
        OpportunityAccess::log('ORGANISATION_TEST_FLAG_CHANGED', $this->testClient
            ? "Flagged {$organisation->name} as a test client: qa:wipe may delete it."
            : "{$organisation->name} is no longer flagged as a test client.", 'opportunities', $o->id,
            ['is_test' => (bool) $organisation->is_test], ['is_test' => $this->testClient]);
    }

    public function saveStage(): void
    {
        $this->authorizeManage();
        $this->lostReason = trim($this->lostReason);
        $this->validate([
            'stage' => ['required', Rule::in(Opportunity::STAGES)],
            'lostReason' => [Rule::requiredIf($this->stage === 'lost'), 'nullable', 'string', 'max:2000'],
        ], [
            'stage.required' => __('opportunities.page.errors.stage'),
            'stage.in' => __('opportunities.page.errors.stage'),
            'lostReason.required' => __('opportunities.page.errors.lost_reason'),
            'lostReason.max' => __('opportunities.page.errors.lost_reason_long'),
        ]);

        $o = $this->opportunity();
        if ($o->stage === $this->stage && ($this->stage !== 'lost' || $o->lost_reason === $this->lostReason)) {
            return;
        }
        $old = ['stage' => $o->stage, 'lost_reason' => $o->lost_reason];
        $o->fill([
            'stage' => $this->stage,
            'lost_reason' => $this->stage === 'lost' ? $this->lostReason : null,
            'closed_at' => in_array($this->stage, Opportunity::CLOSED, true) ? ($o->isClosed() && $o->closed_at ? $o->closed_at : now()) : null,
            'updated_by' => Auth::id(),
        ])->save();
        OpportunityAccess::log('OPPORTUNITY_STAGE_CHANGED', "Moved “{$o->title}” from {$old['stage']} to {$o->stage}.", 'opportunities', $o->id,
            $old, ['stage' => $o->stage, 'lost_reason' => $o->lost_reason]);
        session()->flash('success', __('opportunities.page.stage_saved', ['stage' => __('opportunities.stages.' . $o->stage)]));
    }

    private function authorizeManage(): void
    {
        abort_unless(OpportunityAccess::canManage(Auth::user(), $this->opportunity()), 403);
        $this->resetErrorBag();
    }

    /** The Meetings tab added or changed a meeting: redraw the tab count and the history */
    #[On('meetings-changed')]
    public function meetingsChanged(): void {}

    /** The Proposals tab started, sent or discarded a version: redraw the tab count and the history */
    #[On('proposals-changed')]
    public function proposalsChanged(): void {}

    /** The SOW tab started, sent or discarded a version (PR 5): redraw the tab count and the history */
    #[On('sow-changed')]
    public function sowChanged(): void {}

    public function render()
    {
        $o = $this->opportunity();
        $user = Auth::user();
        // The tab labels' counts, in one query (the same relations, cancelled meetings included)
        $o->loadCount(['meetings', 'proposals', 'statementsOfWork']);
        $overview = $this->tab === 'overview';

        return view('livewire.hq.opportunities.page', [
            'o' => $o,
            'counts' => ['meetings' => $o->meetings_count, 'proposals' => $o->proposals_count, 'sow' => $o->statements_of_work_count],
            // The Project tab: the project draft (PR 5) shows until Create project, then the project
            'project' => $this->tab === 'project' && $o->project_id ? DB::table('projects')->where('id', $o->project_id)->first(['id', 'title', 'status', 'start_date', 'target_end_date']) : null,
            'draftOpen' => $this->tab === 'project' && $o->projectDrafts()->where('status', '!=', 'created')->exists(),
            // The Overview's cards, read only while it is shown
            'copq' => $overview ? DB::table('copq_assessments')->where('organization_id', $o->organization_id)->orderByDesc('created_at')->first() : null,
            'contact' => $overview ? DB::table('client_users')->where('organization_id', $o->organization_id)->whereNull('deleted_at')
                ->orderByDesc('is_primary_client')->orderBy('created_at')->first(['first_name', 'last_name', 'email', 'job_title']) : null,
            'history' => $overview ? DB::table('system_audit_logs')
                ->leftJoin('users', 'users.id', '=', 'system_audit_logs.user_id')
                ->where('target_table', 'opportunities')->where('target_id', $o->id)
                ->orderByDesc('system_audit_logs.created_at')->orderByDesc('system_audit_logs.id')->limit(10)
                ->get(['system_audit_logs.action', 'system_audit_logs.description', 'system_audit_logs.created_at', 'users.first_name', 'users.last_name']) : collect(),
            'team' => $overview ? Team::of($o) : collect(),
            'billingCompany' => Vat::companyName($o->billing_entity_id ?? Vat::issuer(null)?->id),
            'billingCompanies' => $this->editing && $overview ? Vat::companies() : collect(),
            'canChooseBilling' => $this->editing && $overview && OpportunityAccess::canApprove($user, $o),
            'canFlagTest' => $user && Access::isOwner($user),
            'canOpenProjects' => (bool) $user?->can('projects.view'),
        ] + ($this->editing && $overview ? $this->staffPickers() : []))->title($o->title . ' · ' . __('opportunities.title'));
    }
}
