<?php

namespace App\Livewire\Hq\Operations;

use App\Models\MilestonePlan;
use App\Support\Ai\AiUnavailable;
use App\Support\Projects\Planner;
use App\Support\Projects\ProjectPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

/**
 * Plan milestones (PR 5b; the owner's decisions of 1 Nov 2026, docs/PROJECTS.md section 9). The scope box is
 * prefilled from the project's opportunity, with the sources used, and stays editable. A project with milestones is
 * planned one milestone at a time: the AI suggests new tasks, edits to that milestone's tasks and notes, in the
 * background; the adopted plan shows in the review pane with every milestone collapsed to one line, its suggestions
 * inside it with tick boxes, and saving writes only the ticked ones. A project whose plan waits in its project draft
 * (PR 5) is sent there; one with neither gets a whole plan to check and save. App\Support\Projects\Planner holds the
 * rules; every request checks the right to plan this project again (Planner::canPlan).
 */
#[Layout('components.layouts.hq')]
class ScrumMaster extends Component
{
    #[Locked]
    public string $projectId;

    /** The scope box: prefilled, editable before drafting */
    public string $scope = '';

    /** "Sources used" */
    #[Locked]
    public array $sources = [];

    /** The milestone to plan */
    public string $phaseId = '';

    /** The suggestions the lead keeps */
    public array $ticked = [];

    /** The plan under review and its revision when the page showed it (a page that is behind saves nothing) */
    #[Locked]
    public string $planId = '';

    #[Locked]
    public int $revision = 0;

    #[Locked]
    public string $notice = '';

    public function mount($id): void
    {
        $this->projectId = (string) $id;
        abort_unless(DB::table('projects')->where('id', $this->projectId)->whereNull('deleted_at')->exists(), 404);
        abort_unless(Planner::canPlan($this->projectId), 403, __('planner.errors.rights'));
        [$this->scope, $this->sources] = Planner::scope($this->projectId, Auth::user());
        $this->phaseId = (string) (ProjectPlan::boardDefault(ProjectPlan::milestones($this->projectId))?->id ?? '');
    }

    /** Every later request: still allowed to plan this project */
    public function hydrate(): void
    {
        abort_unless(Planner::canPlan($this->projectId), 403);
    }

    /** One milestone: the AI's suggestions, in the background */
    public function suggest(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        $this->validateScope();
        $this->validate(['phaseId' => ['required', 'string']], ['phaseId.*' => __('planner.errors.milestone')]);
        $this->startPlan($this->phaseId);
    }

    /** A whole plan, for a project with no milestones and no project draft */
    public function draftPlan(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        $this->validateScope();
        $this->startPlan(null);
    }

    private function validateScope(): void
    {
        $this->scope = trim($this->scope);
        $this->validate(['scope' => ['required', 'string', 'min:50', 'max:' . Planner::MAX_SCOPE]], [
            'scope.required' => __('planner.errors.scope'),
            'scope.min' => __('planner.errors.scope'),
            'scope.max' => __('planner.errors.scope_long', ['max' => Planner::MAX_SCOPE]),
        ]);
    }

    private function startPlan(?string $phaseId): void
    {
        try {
            $plan = Planner::start($this->projectId, $phaseId, $this->scope, Auth::user());
        } catch (AiUnavailable $e) {
            $this->addError('plan', $e->getMessage());

            return;
        } catch (LogicException) {
            $this->addError('plan', __('planner.errors.state'));

            return;
        }
        $this->planId = '';
        $this->ticked = [];
        if ($plan->status === 'ready') {
            $this->notice = __('planner.ready');
        }
    }

    /** wire:poll while the AI works: render() files what it finished */
    public function check(): void {}

    /** Saves the ticked suggestions; the others are set aside */
    public function save(): void
    {
        $this->resetErrorBag();
        $plan = $this->plan();
        if (! $plan) {
            return;
        }
        $why = Planner::save($plan, $this->ticked, Auth::user(), $this->revision);
        if ($why !== null) {
            $this->addError('save', __('planner.errors.' . $why));

            return;
        }
        $kept = count($this->ticked);
        $this->ticked = [];
        $this->planId = '';
        $this->notice = trans_choice('planner.saved', $kept, ['count' => $kept]);
    }

    /** A whole plan: leave out one drafted task, or a drafted milestone */
    public function removeDrafted(int $m, ?int $t = null): void
    {
        $this->resetErrorBag();
        if (($plan = $this->plan()) && ! Planner::removeDrafted($plan, $m, $t, $this->revision)) {
            $this->planId = '';
            $this->addError('save', __('planner.errors.stale'));
        }
    }

    public function saveWhole(): void
    {
        $this->resetErrorBag();
        $plan = $this->plan();
        if (! $plan) {
            return;
        }
        $why = Planner::saveWhole($plan, Auth::user(), $this->revision);
        if ($why !== null) {
            $this->addError('save', __('planner.errors.' . $why));

            return;
        }
        session()->flash('success', __('planner.whole.saved'));
        $this->redirectRoute('hq.ops.project', ['project' => $this->projectId, 'tab' => 'milestones'], navigate: true);
    }

    private function plan(): ?MilestonePlan
    {
        return $this->planId !== '' ? MilestonePlan::where('project_id', $this->projectId)->find($this->planId) : null;
    }

    public function render()
    {
        $project = ProjectPlan::project($this->projectId);
        $mode = Planner::mode($this->projectId);
        $plan = Planner::current($this->projectId);
        if ($plan) {
            Planner::sync($plan);
            $plan->refresh()->load(['aiRun', 'transcriptMeeting']);
            if ($plan->status === 'saved') {
                $plan = null;
            }
        }
        // A new review (or one changed elsewhere) starts with nothing ticked
        if ($plan && $plan->status === 'ready' && ($this->planId !== $plan->id || $this->revision !== $plan->revision)) {
            $this->planId = $plan->id;
            $this->revision = $plan->revision;
            $this->ticked = [];
        } elseif (! $plan) {
            // The suggestions this page showed were saved or replaced in another tab
            if ($this->planId !== '') {
                $this->resetErrorBag('save');
                $this->addError('save', __('planner.errors.gone'));
            }
            $this->planId = '';
        }

        $milestones = ProjectPlan::milestones($this->projectId);
        $tasks = DB::table('tasks')->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')
            ->whereIn('tasks.phase_id', $milestones->pluck('id'))->whereNull('tasks.deleted_at')->orderBy('tasks.created_at')->orderBy('tasks.id')
            ->get(['tasks.id', 'tasks.phase_id', 'tasks.title', 'tasks.description', 'tasks.status', 'tasks.estimated_hours', 'users.first_name', 'users.last_name'])
            ->groupBy('phase_id');
        $suggestions = $plan && $plan->status === 'ready' && $plan->phase_id ? $plan->suggestions()->where('status', 'pending')->get() : collect();

        return view('livewire.hq.operations.scrum-master', [
            'project' => $project,
            'mode' => $mode,
            'plan' => $plan,
            'drafting' => $plan?->status === 'drafting',
            'milestones' => $milestones,
            'tasks' => $tasks,
            'newByPhase' => $suggestions->where('kind', 'new')->groupBy('phase_id'),
            'editByTask' => $suggestions->where('kind', 'edit')->keyBy('task_id'),
            'notesByTask' => $suggestions->where('kind', 'note')->groupBy('task_id'),
            'countByPhase' => $suggestions->whereIn('kind', ['new', 'edit'])->groupBy('phase_id')->map->count(),
            'draftOpportunityId' => $mode === 'draft_pending' ? Planner::pendingDraftOpportunity($this->projectId) : null,
        ])->title(__('planner.title') . ' · ' . ($project->title ?? ''));
    }
}
