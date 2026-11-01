<?php

namespace App\Livewire\Hq\Projects;

use App\Support\Projects\Dependencies;
use App\Support\Projects\Milestones;
use App\Support\Projects\ProjectHistory;
use App\Support\Projects\ProjectPlan;
use App\Support\Projects\ProjectRules;
use App\Support\Projects\Tasks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The task drawer (docs/PROJECTS.md section 3, after MyCity's task panel): details, the lead (a select, with
 * "Assign to me" beside it), the people sharing it and its watchers, status, due date (Overdue), steps, "waits on"
 * (a warning only), comments with a file, and history. The project's lead and managers edit everything; the lead
 * and the people sharing it move it, tick steps, comment and propose a due date; anyone who sees it may watch it.
 * Opened by the open-task / new-task events, or by ?task=<id> in the address (the bell links there).
 */
class TaskDrawer extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $projectId;

    #[Locked]
    public ?string $taskId = null;

    #[Locked]
    public bool $open = false;

    /** ?task= in the address: read once when the page opens, kept in step so the drawer can be linked to */
    #[Url(as: 'task', except: '')]
    public string $link = '';

    // Details (managers)
    public string $title = '';
    public string $description = '';
    public string $phaseId = '';
    public string $sprintId = '';
    public string $leadId = '';
    public array $sharing = [];
    public array $watchers = [];
    public string $dueDate = '';
    public string $hours = '';
    public bool $clientVisible = true;

    // Work on it
    public string $status = '';
    public string $newStep = '';
    public string $waitOn = '';
    public string $comment = '';
    public $attachment = null;
    public string $proposedDate = '';

    public string $notice = '';
    public string $noticeTone = 'success';

    public function mount(string $projectId): void
    {
        $this->projectId = $projectId;
        if ($this->link !== '') {
            $this->openTask($this->link);
        }
    }

    #[On('open-task')]
    public function openTask(string $id): void
    {
        $task = ProjectRules::canView($this->projectId) ? Tasks::find($this->projectId, $id) : null;
        if (! $task) {
            $this->close();

            return;
        }
        $this->resetForm();
        $this->taskId = $task->id;
        $this->link = $task->id;
        $this->open = true;
        $this->fillFrom($task);
    }

    #[On('new-task')]
    public function newTask(?string $phase = null, ?string $sprint = null): void
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        $this->resetForm();
        $this->taskId = null;
        $this->link = '';
        $this->open = true;
        $this->phaseId = Milestones::find($this->projectId, $phase)?->id ?? '';
        $this->sprintId = Milestones::findSprint($this->projectId, $sprint)?->id ?? '';
    }

    public function close(): void
    {
        $this->open = false;
        $this->taskId = null;
        $this->link = '';
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('title', 'description', 'phaseId', 'sprintId', 'leadId', 'sharing', 'watchers', 'dueDate', 'hours', 'clientVisible',
            'status', 'newStep', 'waitOn', 'comment', 'attachment', 'proposedDate', 'notice', 'noticeTone');
        $this->resetErrorBag();
    }

    private function fillFrom(object $task): void
    {
        $people = Tasks::people($task->id);
        $this->fill([
            'title' => $task->title,
            'description' => (string) $task->description,
            'phaseId' => $task->phase_id,
            'sprintId' => (string) $task->sprint_id,
            'leadId' => (string) $task->assigned_to,
            'sharing' => $people['sharing'],
            'watchers' => $people['watcher'],
            'dueDate' => (string) $task->due_date,
            'hours' => $task->estimated_hours !== null ? rtrim(rtrim((string) $task->estimated_hours, '0'), '.') : '',
            'clientVisible' => (bool) $task->is_client_visible,
            'status' => $task->status,
            'proposedDate' => (string) ($task->proposed_due_date ?: $task->due_date),
        ]);
    }

    /** The open task, checked again: it may have moved or access may have ended while the drawer was open */
    private function task(): object
    {
        abort_unless(ProjectRules::canView($this->projectId), 403, __('projects.errors.no_access'));
        $task = Tasks::find($this->projectId, $this->taskId);
        abort_unless($task, 404);

        return $task;
    }

    private function changed(string $message, string $tone = 'success'): void
    {
        $this->notice = $message;
        $this->noticeTone = $tone;
        $this->dispatch('project-changed');
    }

    public function save(): void
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        $task = $this->taskId ? $this->task() : null;
        $this->title = trim($this->title);
        $team = ProjectRules::team($this->projectId)->pluck('id')->all();
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'phaseId' => ['required', Rule::exists('project_phases', 'id')->where('project_id', $this->projectId)->whereNull('deleted_at')],
            'sprintId' => ['nullable', Rule::exists('sprints', 'id')->where('project_id', $this->projectId)->whereNull('deleted_at')],
            'leadId' => ['nullable', Rule::in($team)],
            'sharing' => ['array', 'max:25'],
            'sharing.*' => [Rule::in($team)],
            'watchers' => ['array', 'max:25'],
            'watchers.*' => [Rule::in($team)],
            'dueDate' => ['nullable', 'date_format:Y-m-d'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ], [
            'title.required' => __('projects.task.errors.title'),
            'title.max' => __('projects.task.errors.title_long'),
            'phaseId.*' => __('projects.task.errors.milestone'),
            'sprintId.*' => __('projects.task.errors.sprint'),
            'leadId.*' => __('projects.task.errors.lead'),
            'sharing.*' => __('projects.task.errors.people'),
            'watchers.*' => __('projects.task.errors.people'),
            'dueDate.*' => __('projects.errors.date'),
            'hours.*' => __('projects.task.errors.hours'),
            'description.max' => __('projects.errors.long'),
        ]);

        $data = [
            'title' => $this->title,
            'description' => trim($this->description) ?: null,
            'phase_id' => $this->phaseId,
            'sprint_id' => $this->sprintId ?: null,
            'assigned_to' => $this->leadId ?: null,
            'due_date' => $this->dueDate ?: null,
            'estimated_hours' => $this->hours === '' ? null : number_format((float) $this->hours, 2, '.', ''),
            'is_client_visible' => $this->clientVisible ? 1 : 0,
        ];
        if ($task) {
            Tasks::update($this->projectId, $task, $data);
        } else {
            $this->taskId = Tasks::create($this->projectId, $data);
            $this->link = $this->taskId;
        }
        Tasks::setPeople($this->task(), $this->sharing, $this->watchers);
        $this->fillFrom($this->task());
        $this->changed($task ? __('projects.drawer.saved') : __('projects.task.created'));
    }

    public function assignToMe(): void
    {
        $task = $this->task();
        abort_unless(Auth::user()->can('projects.edit') || ProjectRules::canManage($this->projectId), 403);
        $anyone = ProjectRules::canManage($this->projectId) || Auth::user()->can('projects.approve');
        if (Tasks::assignToMe($task, $anyone)) {
            $this->fillFrom($this->task());
            $this->changed(__('projects.task.assigned_to_you'));
        } else {
            $this->notice = __('projects.task.already_led');
            $this->noticeTone = 'warning';
        }
    }

    /** The status menu: the same move as dragging the card on the sprint board (timers, client review window) */
    public function updatedStatus(string $value): void
    {
        $task = $this->task();
        if ($value === $task->status || ! in_array($value, ProjectPlan::TASK_STATUSES, true)) {
            $this->status = $task->status;

            return;
        }
        if (! ProjectRules::canMove($task, $this->projectId)) {
            $this->status = $task->status;
            $this->notice = __('projects.task.move_own');
            $this->noticeTone = 'error';

            return;
        }
        Tasks::move($this->projectId, $task, $value);
        $this->changed(__('projects.task.moved', ['status' => __('projects.task_statuses.' . $value)]));
    }

    public function watch(bool $on): void
    {
        Tasks::watch($this->task(), $on);
        $this->watchers = Tasks::people($this->taskId)['watcher'];
        $this->changed($on ? __('projects.task.watching') : __('projects.task.not_watching'));
    }

    public function addStep(): void
    {
        $task = $this->authoriseWork();
        $this->newStep = trim($this->newStep);
        $this->validate(['newStep' => ['required', 'string', 'max:500']], ['newStep.required' => __('projects.task.errors.step'), 'newStep.max' => __('projects.task.errors.step_long')]);
        if (! Tasks::addStep($task, $this->newStep)) {
            $this->addError('newStep', __('projects.task.errors.steps_full', ['max' => Tasks::MAX_STEPS]));

            return;
        }
        $this->newStep = '';
        $this->changed(__('projects.task.step_added'));
    }

    public function toggleStep(int $stepId): void
    {
        Tasks::toggleStep($this->authoriseWork(), $stepId);
        $this->dispatch('project-changed');
    }

    public function removeStep(int $stepId): void
    {
        Tasks::removeStep($this->authoriseWork(), $stepId);
        $this->changed(__('projects.task.step_removed'));
    }

    public function addWait(): void
    {
        $task = $this->authoriseWork();
        $other = Tasks::find($this->projectId, $this->waitOn);
        if (! $other) {
            $this->addError('waitOn', __('projects.waits.errors.choose'));

            return;
        }
        if ($error = Dependencies::link('task', $task->id, $other->id)) {
            $this->addError('waitOn', __('projects.waits.errors.' . $error));

            return;
        }
        ProjectRules::log('TASK_WAITS_ADDED', "“{$task->title}” now waits on “{$other->title}”.", 'tasks', $task->id, [], ['depends_on_task_id' => $other->id]);
        $this->waitOn = '';
        $this->changed(__('projects.waits.added'));
    }

    public function removeWait(string $otherId): void
    {
        $task = $this->authoriseWork();
        if (Dependencies::unlink('task', $task->id, $otherId)) {
            ProjectRules::log('TASK_WAITS_REMOVED', "“{$task->title}” no longer waits on another task.", 'tasks', $task->id, ['depends_on_task_id' => $otherId]);
            $this->changed(__('projects.waits.removed'));
        }
    }

    public function addComment(): void
    {
        $task = $this->authoriseWork();
        $this->comment = trim($this->comment);
        $this->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ], [
            'comment.required' => __('projects.task.errors.comment'),
            'comment.max' => __('projects.errors.long'),
            'attachment.*' => __('projects.task.errors.attachment'),
        ]);
        $path = null;
        if ($this->attachment) {
            // The extension the person's file had (the live PHP has no fileinfo to guess one); served by hq.file
            $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->attachment->getClientOriginalExtension())) ?: 'bin';
            $path = $this->attachment->storeAs('task_files/' . $task->id, Str::uuid() . '.' . $extension, 'local');
        }
        Tasks::comment($this->projectId, $task, $this->comment, $path);
        $this->reset('comment', 'attachment');
        $this->changed(__('projects.task.commented'));
    }

    /** A team member's proposal for the project lead; the lead (or a manager) simply sets the date */
    public function proposeDue(): void
    {
        $task = $this->authoriseWork();
        $this->validate(['proposedDate' => ['required', 'date_format:Y-m-d']], ['proposedDate.*' => __('projects.errors.date')]);
        Tasks::proposeDueDate($this->projectId, $task, $this->proposedDate);
        $this->changed(__('projects.task.proposed'));
    }

    public function decideProposal(bool $accept): void
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        Tasks::decideProposal($this->task(), $accept);
        $this->fillFrom($this->task());
        $this->changed($accept ? __('projects.task.proposal_accepted') : __('projects.task.proposal_declined'));
    }

    /** Steps, links, comments and proposals: the lead and people sharing the task, or a manager */
    private function authoriseWork(): object
    {
        $task = $this->task();
        abort_unless(ProjectRules::canWorkOn($task, $this->projectId), 403, __('projects.task.work_own'));
        $this->resetErrorBag();

        return $task;
    }

    public function render()
    {
        if (! $this->open) {
            return view('livewire.hq.projects.task-drawer', ['task' => null]);
        }
        $task = $this->taskId ? Tasks::find($this->projectId, $this->taskId) : null;
        if ($this->taskId && ! $task) {
            $this->close();

            return view('livewire.hq.projects.task-drawer', ['task' => null]);
        }
        $canManage = ProjectRules::canManage($this->projectId);
        $milestones = ProjectPlan::milestones($this->projectId);
        $names = DB::table('users')->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);

        $data = [
            'task' => $task,
            'canManage' => $canManage,
            'milestones' => $milestones,
            'sprints' => DB::table('sprints')->where('project_id', $this->projectId)->whereNull('deleted_at')->orderBy('start_date')->get(['id', 'title', 'start_date', 'end_date']),
            'team' => $canManage ? ProjectRules::team($this->projectId) : collect(),
            'names' => $names,
        ];
        if (! $task) {
            return view('livewire.hq.projects.task-drawer', $data);
        }

        $waits = DB::table('task_dependencies')->join('tasks', 'tasks.id', '=', 'task_dependencies.depends_on_task_id')
            ->where('task_dependencies.task_id', $task->id)->whereNull('tasks.deleted_at')->orderBy('tasks.title')->get(['tasks.id', 'tasks.title', 'tasks.status']);
        $holding = DB::table('task_dependencies')->join('tasks', 'tasks.id', '=', 'task_dependencies.task_id')
            ->where('task_dependencies.depends_on_task_id', $task->id)->whereNull('tasks.deleted_at')->orderBy('tasks.title')->get(['tasks.id', 'tasks.title', 'tasks.status']);
        $people = Tasks::people($task->id);

        return view('livewire.hq.projects.task-drawer', $data + [
            'canWork' => ProjectRules::canWorkOn($task, $this->projectId),
            'canMove' => ProjectRules::canMove($task, $this->projectId),
            // "Assign to me": an unassigned task for anyone with projects.edit, anyone's for a manager (the board's rule)
            'canCheckout' => (! $task->assigned_to && Auth::user()->can('projects.edit')) || ($canManage && $task->assigned_to !== Auth::id()),
            'myRole' => $task->assigned_to === Auth::id() ? 'lead' : (in_array(Auth::id(), $people['sharing'], true) ? 'sharing' : (in_array(Auth::id(), $people['watcher'], true) ? 'watcher' : null)),
            'people' => $people,
            'milestone' => $milestones->firstWhere('id', $task->phase_id),
            'steps' => DB::table('task_steps')->where('task_id', $task->id)->orderBy('position')->get(),
            'waits' => $waits,
            'holding' => $holding,
            'candidates' => DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
                ->where('project_phases.project_id', $this->projectId)->whereNull('tasks.deleted_at')->whereNull('project_phases.deleted_at')
                ->where('tasks.id', '!=', $task->id)->whereNotIn('tasks.id', $waits->pluck('id'))
                ->orderBy('project_phases.phase_number')->orderBy('tasks.title')->get(['tasks.id', 'tasks.title', 'project_phases.phase_number']),
            'comments' => DB::table('task_comments')->leftJoin('users', 'users.id', '=', 'task_comments.user_id')
                ->where('task_comments.task_id', $task->id)->whereNull('task_comments.deleted_at')
                ->orderByDesc('task_comments.created_at')->orderByDesc('task_comments.id')->limit(50)
                ->get(['task_comments.id', 'task_comments.comment_text', 'task_comments.attachment_path', 'task_comments.created_at', 'users.first_name', 'users.last_name']),
            'history' => ProjectHistory::for('tasks', $task->id),
        ]);
    }
}
