<?php

namespace App\Livewire\Hq\Opportunities;

use App\Models\Opportunity;
use App\Models\ProjectDraft;
use App\Support\Ai\AiUnavailable;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\ProjectDrafts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The project draft on the opportunity's Project tab (docs/OPPORTUNITIES.md section 4, row 5; milestones as the owner
 * set them on 1 Nov 2026). Once the SOW is signed: the SOW's milestones with their dates, weights and amounts (fixed by
 * the contract; a 0% milestone such as "Later" can be added), the AI's goal and tasks in each, and the roles with a
 * person picked for each. The owner or the opportunity's lead edits it (saved as they go) and clicks Create project.
 * Everyone who can open the opportunity reads it.
 */
class ProjectDraftTab extends Component
{
    #[Locked]
    public string $opportunityId;

    public array $payload = [];

    /** The draft and the moment the editor was filled from it, so a change by the AI or a colleague reloads it */
    #[Locked]
    public string $loaded = '';

    public string $instructions = '';

    public bool $redrafting = false;

    #[Locked]
    public string $notice = '';

    public function mount(string $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        abort_unless(OpportunityAccess::canView(Auth::user(), $opportunity), 403);
        $this->opportunityId = $opportunity->id;
    }

    public function hydrate(): void
    {
        abort_unless(OpportunityAccess::canView(Auth::user(), $this->opportunity()), 403);
    }

    private function opportunity(): Opportunity
    {
        return Opportunity::with(['organization', 'lead'])->findOrFail($this->opportunityId);
    }

    private function draft(): ?ProjectDraft
    {
        return ProjectDrafts::forOpportunity($this->opportunity());
    }

    private function authorizeEdit(): ProjectDraft
    {
        abort_unless(ProjectDrafts::canEdit(Auth::user(), $this->opportunity()), 403);
        $draft = $this->draft();
        abort_unless($draft !== null, 404);
        $this->notice = '';
        $this->resetErrorBag();

        return $draft;
    }

    /** Saved as each field is left; a new start date moves every milestone by as many days */
    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'payload') || $this->loaded === '') {
            return;
        }
        $draft = $this->authorizeEdit();
        if ($name === 'payload.project.start_date') {
            $base = $this->payload;
            $base['project']['start_date'] = $draft->payload['project']['start_date'] ?? null;
            $this->payload = ProjectDrafts::shift($base, (string) ($this->payload['project']['start_date'] ?? ''));
        }
        $this->persist($draft);
    }

    public function addTask(int $m): void
    {
        $draft = $this->authorizeEdit();
        if (isset($this->payload['milestones'][$m]) && count($this->payload['milestones'][$m]['tasks'] ?? []) < ProjectDrafts::MAX_TASKS_PER_MILESTONE) {
            $this->payload['milestones'][$m]['tasks'][] = ['title' => '', 'description' => '', 'role_key' => '', 'estimate_hours' => null];
            $this->persist($draft);
        }
    }

    public function removeTask(int $m, int $t): void
    {
        $draft = $this->authorizeEdit();
        unset($this->payload['milestones'][$m]['tasks'][$t]);
        if (isset($this->payload['milestones'][$m])) {
            $this->payload['milestones'][$m]['tasks'] = array_values($this->payload['milestones'][$m]['tasks'] ?? []);
        }
        $this->persist($draft);
    }

    /** A task goes to another milestone (every task has one) */
    public function moveTask(int $m, int $t, $to): void
    {
        $draft = $this->authorizeEdit();
        $to = (int) $to;
        $task = $this->payload['milestones'][$m]['tasks'][$t] ?? null;
        if ($task === null || $to === $m || ! isset($this->payload['milestones'][$to])) {
            return;
        }
        unset($this->payload['milestones'][$m]['tasks'][$t]);
        $this->payload['milestones'][$m]['tasks'] = array_values($this->payload['milestones'][$m]['tasks']);
        $this->payload['milestones'][$to]['tasks'][] = $task;
        $this->persist($draft);
    }

    /** A milestone of our own at 0% (ideas that wait, such as "Later"), after the last one */
    public function addMilestone(): void
    {
        $draft = $this->authorizeEdit();
        $last = collect($this->payload['milestones'] ?? [])->max('due_date');
        $start = $last ? \Illuminate\Support\Carbon::parse($last)->addDay() : now('Africa/Lagos')->startOfDay();
        $this->payload['milestones'][] = [
            'key' => 'x' . substr(md5(uniqid('', true)), 0, 8), 'sow_key' => null, 'title' => __('project_draft.later', [], $this->opportunity()->language),
            'goal' => '', 'description' => '', 'weeks' => 2, 'weight' => 0, 'amount' => 0,
            'start_date' => $start->toDateString(), 'due_date' => $start->copy()->addDays(13)->toDateString(), 'tasks' => [],
        ];
        $this->persist($draft);
    }

    /** Only a milestone of our own goes; the SOW's are part of the contract */
    public function removeMilestone(int $m): void
    {
        $draft = $this->authorizeEdit();
        if (isset($this->payload['milestones'][$m]) && empty($this->payload['milestones'][$m]['sow_key'])) {
            unset($this->payload['milestones'][$m]);
            $this->payload['milestones'] = array_values($this->payload['milestones']);
            $this->persist($draft);
        }
    }

    public function addRole(): void
    {
        $draft = $this->authorizeEdit();
        if (count($this->payload['roles'] ?? []) < ProjectDrafts::MAX_ROLES) {
            $this->payload['roles'][] = ['key' => 'role_' . substr(md5(uniqid('', true)), 0, 6), 'title' => __('project_draft.roles.new', [], $this->opportunity()->language), 'responsibilities' => '', 'user_id' => null];
            $this->persist($draft);
        }
    }

    public function removeRole(int $r): void
    {
        $draft = $this->authorizeEdit();
        unset($this->payload['roles'][$r]);
        $this->payload['roles'] = array_values($this->payload['roles'] ?? []);
        $this->persist($draft);
    }

    public function openRedraft(): void
    {
        $this->authorizeEdit();
        $this->redrafting = true;
    }

    public function cancelRedraft(): void
    {
        $this->reset('redrafting', 'instructions');
        $this->resetErrorBag();
    }

    /** The AI plans the tasks and roles again; dates, weights and amounts stay */
    public function redraft(): void
    {
        $draft = $this->authorizeEdit();
        $this->instructions = trim($this->instructions);
        $this->validate(['instructions' => ['nullable', 'string', 'max:2000']], ['instructions.max' => __('sow.errors.note_long', ['max' => 2000])]);
        try {
            if (ProjectDrafts::draft($draft, $this->instructions, Auth::user())) {
                $this->reset('redrafting', 'instructions');
                $this->loaded = '';
            } elseif (! empty($draft->fresh()->payload['unavailable'])) {
                $this->addError('draft', __('project_draft.errors.unavailable'));
            }
        } catch (AiUnavailable $e) {
            $this->addError('draft', $e->getMessage());
        }
    }

    public function dismissFailure(): void
    {
        ProjectDrafts::dismissFailure($this->authorizeEdit());
    }

    public function create(): void
    {
        $draft = $this->authorizeEdit();
        $this->persist($draft);
        $why = ProjectDrafts::create($draft->fresh(), Auth::user());
        if ($why !== null) {
            $this->addError('create', __('project_draft.create.errors.' . $why));

            return;
        }
        session()->flash('success', __('project_draft.create.done'));
        $this->redirectRoute('hq.ops.project', ['project' => $draft->fresh()->created_project_id, 'tab' => 'milestones'], navigate: true);
    }

    /** wire:poll while the AI works */
    public function check(): void {}

    private function persist(ProjectDraft $draft): void
    {
        if (! ProjectDrafts::save($draft, $this->payload, Auth::user())) {
            $this->loaded = '';
            $this->addError('draft', __('project_draft.errors.not_saved'));

            return;
        }
        $this->payload = (array) $draft->payload;
        $this->loaded = $draft->id . '@' . $draft->updated_at?->format('Y-m-d H:i:s.u');
    }

    public function render()
    {
        $opportunity = $this->opportunity();
        $draft = $this->draft();
        if ($draft) {
            if (ProjectDrafts::sync($draft)) {
                $this->loaded = '';
            }
            $draft->load(['aiRun', 'sow']);
            $stamp = $draft->id . '@' . $draft->updated_at?->format('Y-m-d H:i:s.u');
            if ($draft->status === 'drafting' || ($draft->aiRun && ! $draft->aiRun->isFinished())) {
                $this->loaded = '';
                $this->payload = (array) $draft->payload;
            } elseif ($this->loaded !== $stamp) {
                $this->payload = (array) $draft->payload;
                $this->loaded = $stamp;
            }
        }
        $canEdit = $draft && ProjectDrafts::canEdit(Auth::user(), $opportunity) && $draft->status === 'ready';
        $drafting = $draft && ($draft->status === 'drafting' || ($draft->aiRun && ! $draft->aiRun->isFinished()));

        return view('livewire.hq.opportunities.project-draft-tab', [
            'o' => $opportunity,
            'draft' => $draft,
            'canEdit' => $canEdit,
            'drafting' => $drafting,
            'draftError' => $draft?->aiRun?->status === 'failed' ? $draft->aiRun->errorMessage() : null,
            'problem' => $draft ? ProjectDrafts::problem($draft) : null,
            'staff' => $canEdit ? DB::table('users')->where('status', 'Active')->whereNull('deleted_at')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']) : collect(),
            'lead' => $draft && ($this->payload['lead_user_id'] ?? null) ? DB::table('users')->where('id', $this->payload['lead_user_id'])->first(['first_name', 'last_name', 'status']) : null,
            'taskCount' => collect($this->payload['milestones'] ?? [])->sum(fn ($m) => count($m['tasks'] ?? [])),
        ]);
    }
}
