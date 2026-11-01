<?php

namespace App\Livewire\Hq\Opportunities;

use App\Livewire\Hq\Opportunities\Concerns\PicksStaff;
use App\Models\Opportunity;
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
 * history) and a tab for each later step. Meetings, Questionnaire, Proposals, SOW and Project list what exists
 * today (meetings from free audits, legacy proposals, the project) until PRs 2–5 build them out.
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
        ], [
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

        $meetings = $o->meetings()->withCount('transcripts')->with('attendees')->get();
        $proposals = $o->proposals()->get(['id', 'opportunity_id', 'proposal_ref', 'title', 'status', 'total_value', 'currency', 'version', 'signing_mode', 'created_at']);
        $copq = DB::table('copq_assessments')->where('organization_id', $o->organization_id)->orderByDesc('created_at')->first();
        $contact = DB::table('client_users')->where('organization_id', $o->organization_id)->whereNull('deleted_at')
            ->orderByDesc('is_primary_client')->orderBy('created_at')->first(['first_name', 'last_name', 'email', 'job_title']);
        $history = DB::table('system_audit_logs')
            ->leftJoin('users', 'users.id', '=', 'system_audit_logs.user_id')
            ->where('target_table', 'opportunities')->where('target_id', $o->id)
            ->orderByDesc('system_audit_logs.created_at')->orderByDesc('system_audit_logs.id')->limit(10)
            ->get(['system_audit_logs.action', 'system_audit_logs.description', 'system_audit_logs.created_at', 'users.first_name', 'users.last_name']);

        return view('livewire.hq.opportunities.page', [
            'o' => $o,
            'meetings' => $meetings,
            'proposals' => $proposals,
            'sows' => $o->statementsOfWork()->get(['id', 'opportunity_id', 'reference', 'version', 'status', 'signed_at', 'created_at']),
            'project' => $o->project_id ? DB::table('projects')->where('id', $o->project_id)->first(['id', 'title', 'status', 'start_date', 'target_end_date']) : null,
            // The project draft (PR 5) shows until Create project
            'draftOpen' => $o->projectDrafts()->where('status', '!=', 'created')->exists(),
            'copq' => $copq,
            'contact' => $contact,
            'history' => $history,
            'team' => Team::of($o),
            'canApprove' => OpportunityAccess::canApprove($user, $o),
            'canReadTranscripts' => OpportunityAccess::canReadTranscripts($user, $o),
            'canOpenProjects' => (bool) $user?->can('projects.view'),
        ] + ($this->editing ? $this->staffPickers() : []))->title($o->title . ' · ' . __('opportunities.title'));
    }
}
