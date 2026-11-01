<?php

namespace App\Livewire\Hq\Projects;

use App\Support\Projects\Dependencies;
use App\Support\Projects\Milestones;
use App\Support\Projects\ProjectHistory;
use App\Support\Projects\ProjectPlan;
use App\Support\Projects\ProjectRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The milestone drawer (docs/PROJECTS.md section 3): code and title, description, weight (with the project's total and
 * a warning when it isn't 100%), status, start and end (its due date), goal, acceptance, notes, "waits on" other milestones, its tasks and its
 * invoice. Everyone who sees the project reads it; the project's lead and managers edit, add and delete
 * (only a milestone with no tasks and no invoice). Dropping a card on Accepted opens it to record who accepted it.
 */
class MilestoneDrawer extends Component
{
    #[Locked]
    public string $projectId;

    #[Locked]
    public ?string $phaseId = null;

    #[Locked]
    public bool $open = false;

    #[Url(as: 'milestone', except: '')]
    public string $link = '';

    public string $number = '';
    public string $title = '';
    public string $description = '';
    public string $weight = '0';
    public string $column = 'not_started';
    public string $startDate = '';
    public string $dueDate = '';
    public string $acceptedAt = '';
    public string $acceptedBy = '';
    public string $goal = '';
    public string $notes = '';
    public string $waitOn = '';

    #[Locked]
    public bool $confirmingDelete = false;

    public string $notice = '';
    public string $noticeTone = 'success';

    public function mount(string $projectId): void
    {
        $this->projectId = $projectId;
        if ($this->link !== '') {
            $this->openMilestone($this->link);
        }
    }

    /** $accept: the card was dropped on Accepted; the form asks who accepted it and when */
    #[On('open-milestone')]
    public function openMilestone(string $id, bool $accept = false): void
    {
        $phase = ProjectRules::canView($this->projectId) ? Milestones::find($this->projectId, $id) : null;
        if (! $phase) {
            $this->close();

            return;
        }
        $this->resetForm();
        $this->phaseId = $phase->id;
        $this->link = $phase->id;
        $this->open = true;
        $this->fillFrom($phase);
        if ($accept && ProjectRules::canManage($this->projectId) && ! in_array($this->column, ProjectPlan::DELIVERED, true)) {
            $this->column = 'accepted';
            $this->acceptedAt = $this->acceptedAt ?: ProjectPlan::today();
            $this->notice = __('projects.milestone.accept_help');
            $this->noticeTone = 'info';
        }
    }

    #[On('new-milestone')]
    public function newMilestone(): void
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        $this->resetForm();
        $this->phaseId = null;
        $this->link = '';
        $this->open = true;
        $this->number = (string) Milestones::nextNumber($this->projectId);
    }

    public function close(): void
    {
        $this->open = false;
        $this->phaseId = null;
        $this->link = '';
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('number', 'title', 'description', 'weight', 'column', 'startDate', 'dueDate', 'acceptedAt', 'acceptedBy', 'goal', 'notes', 'waitOn',
            'confirmingDelete', 'notice', 'noticeTone');
        $this->resetErrorBag();
    }

    private function fillFrom(object $phase): void
    {
        $milestone = ProjectPlan::milestones($this->projectId)->firstWhere('id', $phase->id);
        $this->fill([
            'number' => (string) $phase->phase_number,
            'title' => ProjectPlan::title($phase),
            'description' => (string) $phase->description,
            'weight' => rtrim(rtrim((string) $phase->weight_percentage, '0'), '.') ?: '0',
            'column' => $milestone->column,
            'startDate' => (string) $phase->start_date,
            'dueDate' => (string) $phase->due_date,
            'acceptedAt' => (string) $phase->accepted_at,
            'acceptedBy' => (string) $phase->accepted_by_name,
            'goal' => (string) $phase->goal,
            'notes' => (string) $phase->notes,
        ]);
    }

    private function phase(): object
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        $phase = Milestones::find($this->projectId, $this->phaseId);
        abort_unless($phase, 404);

        return $phase;
    }

    private function changed(string $message): void
    {
        $this->notice = $message;
        $this->noticeTone = 'success';
        $this->dispatch('project-changed');
    }

    public function save(): void
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        $phase = $this->phaseId ? $this->phase() : null;
        $current = $phase ? ProjectPlan::milestones($this->projectId)->firstWhere('id', $phase->id)->column : null;
        // Invoiced and Paid follow Finance: the status underneath stays as it was
        $invoiced = in_array($current, ['invoiced', 'paid'], true);
        $this->title = trim($this->title);
        $this->acceptedBy = trim($this->acceptedBy);
        $this->validate([
            'number' => ['required', 'integer', 'min:0', 'max:999'],
            'title' => ['required', 'string', 'max:140'], // room for an imported title's "M4 · " (the column holds 150)
            'description' => ['nullable', 'string', 'max:10000'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'column' => $invoiced ? ['nullable'] : ['required', Rule::in(array_keys(ProjectPlan::STATUS_FOR))],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'dueDate' => ['nullable', 'date_format:Y-m-d', $this->startDate ? 'after_or_equal:startDate' : 'nullable'],
            'acceptedAt' => [Rule::requiredIf(! $invoiced && $this->column === 'accepted'), 'nullable', 'date_format:Y-m-d'],
            'acceptedBy' => [Rule::requiredIf(! $invoiced && $this->column === 'accepted'), 'nullable', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ], [
            'number.*' => __('projects.milestone.errors.number'),
            'title.required' => __('projects.milestone.errors.title'),
            'title.max' => __('projects.milestone.errors.title_long'),
            'weight.*' => __('projects.milestone.errors.weight'),
            'column.*' => __('projects.milestone.errors.status'),
            'dueDate.after_or_equal' => __('projects.errors.due_before_start'),
            'startDate.*' => __('projects.errors.date'),
            'dueDate.*' => __('projects.errors.date'),
            'acceptedAt.*' => __('projects.milestone.errors.accepted_at'),
            'acceptedBy.*' => __('projects.milestone.errors.accepted_by'),
            'description.max' => __('projects.errors.long'),
            'goal.max' => __('projects.errors.long'),
            'notes.max' => __('projects.errors.long'),
        ]);

        // An imported title carries its code ("M4 · Recruitment"): keep that form when only the words change
        $title = $phase && preg_match('/^M\d+\s*·\s*/u', $phase->title) ? 'M' . (int) $this->number . ' · ' . $this->title : $this->title;
        $data = [
            'phase_number' => (int) $this->number,
            'title' => $title,
            'description' => trim($this->description) ?: null,
            'weight_percentage' => number_format((float) $this->weight, 2, '.', ''),
            'start_date' => $this->startDate ?: null,
            'due_date' => $this->dueDate ?: null,
            'goal' => trim($this->goal) ?: null,
            'notes' => trim($this->notes) ?: null,
        ];
        if (! $invoiced) {
            $data['status'] = ProjectPlan::STATUS_FOR[$this->column];
            $data += $this->column === 'accepted' ? ['accepted_at' => $this->acceptedAt, 'accepted_by_name' => $this->acceptedBy] : [];
        }

        if ($phase) {
            Milestones::update($phase, $data, $current !== $this->column && ! $invoiced ? 'MILESTONE_MOVED' : 'MILESTONE_UPDATED');
        } else {
            $this->phaseId = Milestones::create($this->projectId, $data);
            $this->link = $this->phaseId;
        }
        $this->fillFrom(Milestones::find($this->projectId, $this->phaseId));
        $this->changed($phase ? __('projects.drawer.saved') : __('projects.milestone.created'));
    }

    public function addWait(): void
    {
        $phase = $this->phase();
        $other = Milestones::find($this->projectId, $this->waitOn);
        $this->resetErrorBag();
        if (! $other) {
            $this->addError('waitOn', __('projects.waits.errors.choose'));

            return;
        }
        if ($error = Dependencies::link('phase', $phase->id, $other->id)) {
            $this->addError('waitOn', __('projects.waits.errors.' . $error));

            return;
        }
        ProjectRules::log('MILESTONE_WAITS_ADDED', "M{$phase->phase_number} now waits on M{$other->phase_number}.", 'project_phases', $phase->id, [], ['depends_on_phase_id' => $other->id]);
        $this->waitOn = '';
        $this->changed(__('projects.waits.added'));
    }

    public function removeWait(string $otherId): void
    {
        $phase = $this->phase();
        if (Dependencies::unlink('phase', $phase->id, $otherId)) {
            ProjectRules::log('MILESTONE_WAITS_REMOVED', "M{$phase->phase_number} no longer waits on another milestone.", 'project_phases', $phase->id, ['depends_on_phase_id' => $otherId]);
            $this->changed(__('projects.waits.removed'));
        }
    }

    /** One of its tasks: this drawer closes and the task drawer opens */
    public function openTask(string $id): void
    {
        $this->close();
        $this->dispatch('open-task', id: $id);
    }

    public function newTask(): void
    {
        $phaseId = $this->phase()->id;
        $this->close();
        $this->dispatch('new-task', phase: $phaseId);
    }

    /** Delete asks first, naming the milestone */
    public function askDelete(bool $ask): void
    {
        $this->phase();
        $this->confirmingDelete = $ask;
    }

    public function delete(): void
    {
        $phase = $this->phase();
        abort_unless($this->confirmingDelete, 422);
        if (! Milestones::delete($phase)) {
            $this->notice = __('projects.milestone.cannot_delete');
            $this->noticeTone = 'error';
            $this->confirmingDelete = false;

            return;
        }
        $this->close();
        $this->dispatch('project-changed');
        session()->flash('success', __('projects.milestone.deleted', ['code' => 'M' . $phase->phase_number]));
        $this->redirectRoute('hq.ops.project', ['project' => $this->projectId, 'tab' => 'milestones'], navigate: true);
    }

    public function render()
    {
        if (! $this->open) {
            return view('livewire.hq.projects.milestone-drawer', ['milestone' => null]);
        }
        $milestones = ProjectPlan::milestones($this->projectId);
        $milestone = $this->phaseId ? $milestones->firstWhere('id', $this->phaseId) : null;
        if ($this->phaseId && ! $milestone) {
            $this->close();

            return view('livewire.hq.projects.milestone-drawer', ['milestone' => null]);
        }
        $project = DB::table('projects')->where('id', $this->projectId)->first(['total_budget', 'currency']);

        return view('livewire.hq.projects.milestone-drawer', [
            'milestone' => $milestone,
            'canManage' => ProjectRules::canManage($this->projectId),
            'canSeeMoney' => ProjectRules::canSeeMoney($this->projectId),
            'project' => $project,
            'weightTotal' => ProjectPlan::weightTotal($milestones),
            'others' => $milestones->reject(fn ($m) => $m->id === $this->phaseId || ($milestone && $milestone->waits_on->contains('id', $m->id)))->values(),
            'tasks' => $milestone ? DB::table('tasks')->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')->where('tasks.phase_id', $milestone->id)->whereNull('tasks.deleted_at')
                ->orderByRaw("FIELD(tasks.status, 'In-Progress', 'Failed', 'QA_Review', 'To-Do', 'Done')")->orderBy('tasks.title')
                ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.due_date', 'users.first_name', 'users.last_name']) : collect(),
            'holding' => $milestone ? $milestones->filter(fn ($m) => $m->waits_on->contains('id', $milestone->id))->values() : collect(),
            'canDelete' => $milestone && Milestones::canDelete($milestone),
            'history' => $milestone ? ProjectHistory::for('project_phases', $milestone->id) : collect(),
        ]);
    }
}
