<?php

namespace App\Livewire\Hq\Projects;

use App\Models\User;
use App\Support\Projects\Dependencies;
use App\Support\Projects\Milestones;
use App\Support\Projects\ProjectHistory;
use App\Support\Projects\ProjectPlan;
use App\Support\Projects\ProjectRules;
use App\Support\Projects\Tasks;
use Illuminate\Support\Collection;
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
 * The task drawer (docs/PROJECTS.md section 3, after MyCity's task panel): details, its milestone (every task has
 * one), the lead (a picker, x-hq.picker, with "Me" beside it), the people sharing it and its watchers, status, due
 * date (Overdue), steps, "waits on" (a warning only), comments with a file, and history. The project's lead and
 * managers edit everything; the lead and the people sharing it move it, tick steps, comment and propose a due date;
 * anyone who sees it may watch it. Opened by the open-task / new-task events, or by ?task=<id> (the bell links there).
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
    public string $leadId = '';
    public array $sharing = [];
    public array $watchers = [];

    // What is typed in the three pickers (the lists narrow to the project's team)
    public string $leadSearch = '';
    public string $sharingSearch = '';
    public string $watcherSearch = '';
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

    /** The pickers' people (the project's team when it was last worked out), so a keystroke doesn't work it out again */
    #[Locked]
    public array $teamIds = [];

    // Worked out once per request and kept in private properties, which Livewire never sends to the browser nor keeps
    // between requests: the access answers (forgotten after a change to who is on the task) and the team
    private ?bool $canView = null;
    private ?bool $canManage = null;
    private ?Collection $team = null;

    public function mount(string $projectId): void
    {
        $this->projectId = $projectId;
        if ($this->link !== '') {
            $this->openTask($this->link);
        }
    }

    private function canView(): bool
    {
        return $this->canView ??= ProjectRules::canView($this->projectId);
    }

    private function canManage(): bool
    {
        return $this->canManage ??= ProjectRules::canManage($this->projectId, $this->canView());
    }

    private function canWork(object $task): bool
    {
        return ProjectRules::canWorkOn($task, $this->projectId, $this->canManage(), $this->canView());
    }

    /** Someone joined or left the task (or its lead changed): the next check looks again */
    private function forgetAccess(): void
    {
        $this->canView = $this->canManage = null;
    }

    /** ProjectRules::team, and its ids for the pickers' next keystrokes */
    private function team(): Collection
    {
        $this->team ??= ProjectRules::team($this->projectId);
        $this->teamIds = $this->team->pluck('id')->all();

        return $this->team;
    }

    #[On('open-task')]
    public function openTask(string $id): void
    {
        $task = $this->canView() ? Tasks::find($this->projectId, $id) : null;
        if (! $task) {
            $this->close();

            return;
        }
        $this->resetForm();
        $this->taskId = $task->id;
        $this->link = $task->id;
        $this->open = true;
        $this->fillFrom($task);
        // The pickers are for the project's lead and managers only
        if ($this->canManage()) {
            $this->team();
        }
    }

    #[On('new-task')]
    public function newTask(?string $phase = null): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetForm();
        $this->taskId = null;
        $this->link = '';
        $this->open = true;
        $this->phaseId = Milestones::find($this->projectId, $phase)?->id ?? '';
        $this->team();
    }

    // The pickers (managers): the lead is never also sharing or watching
    public function chooseLead(string $userId): void
    {
        $this->authorizePicking();
        if ($this->onTeam($userId)) {
            $this->leadId = $userId;
            $this->sharing = array_values(array_diff($this->sharing, [$userId]));
            $this->watchers = array_values(array_diff($this->watchers, [$userId]));
        }
        $this->leadSearch = '';
    }

    public function clearLead(): void
    {
        $this->authorizePicking();
        $this->leadId = '';
        $this->leadSearch = '';
    }

    public function addSharing(string $userId): void
    {
        $this->authorizePicking();
        if ($userId !== $this->leadId && $this->onTeam($userId) && ! in_array($userId, $this->sharing, true)) {
            $this->sharing[] = $userId;
            $this->watchers = array_values(array_diff($this->watchers, [$userId]));
        }
        $this->sharingSearch = '';
    }

    public function removeSharing(string $userId): void
    {
        $this->authorizePicking();
        $this->sharing = array_values(array_diff($this->sharing, [$userId]));
    }

    public function addWatcher(string $userId): void
    {
        $this->authorizePicking();
        if ($userId !== $this->leadId && $this->onTeam($userId) && ! in_array($userId, [...$this->sharing, ...$this->watchers], true)) {
            $this->watchers[] = $userId;
        }
        $this->watcherSearch = '';
    }

    public function removeWatcher(string $userId): void
    {
        $this->authorizePicking();
        $this->watchers = array_values(array_diff($this->watchers, [$userId]));
    }

    private function authorizePicking(): void
    {
        abort_unless($this->open && $this->canManage(), 403);
    }

    /** Checked against the team as it is now, whatever the pickers listed */
    private function onTeam(string $userId): bool
    {
        return $this->team()->contains('id', $userId);
    }

    /** Options and choices for the three pickers: the project's team, narrowed by what is typed */
    private function pickers(): array
    {
        if (! $this->teamIds) {
            $this->team();
        }
        $people = User::whereIn('id', $this->teamIds)->get(['id', 'first_name', 'last_name', 'avatar_path', 'job_title'])->keyBy('id');
        $item = fn (User $u) => ['id' => $u->id, 'label' => trim($u->first_name . ' ' . $u->last_name), 'meta' => $u->job_title, 'user' => $u];
        $match = fn (string $term, array $exclude) => $people->reject(fn ($u) => in_array($u->id, $exclude, true))
            ->filter(fn ($u) => trim($term) === '' || Str::contains(Str::lower($u->first_name . ' ' . $u->last_name), Str::lower(trim($term))))
            ->sortBy(fn ($u) => Str::lower($u->first_name . ' ' . $u->last_name))->take(30)->map($item)->values();
        $chosen = fn (array $ids) => collect($ids)->map(fn ($id) => $people->get($id))->filter()->map($item)->values();
        $taken = array_filter([$this->leadId, ...$this->sharing, ...$this->watchers]);

        return [
            'leadOptions' => $match($this->leadSearch, []),
            'leadSelected' => $this->leadId !== '' ? $chosen([$this->leadId])->first() : null,
            'sharingOptions' => $match($this->sharingSearch, $taken),
            'sharingSelected' => $chosen($this->sharing),
            'watcherOptions' => $match($this->watcherSearch, $taken),
            'watcherSelected' => $chosen($this->watchers),
        ];
    }

    public function close(): void
    {
        $this->open = false;
        $this->taskId = null;
        $this->link = '';
        $this->teamIds = [];
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('title', 'description', 'phaseId', 'leadId', 'sharing', 'watchers', 'leadSearch', 'sharingSearch', 'watcherSearch', 'dueDate', 'hours', 'clientVisible',
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
        abort_unless($this->canView(), 403, __('projects.errors.no_access'));
        $task = Tasks::find($this->projectId, $this->taskId);
        abort_unless($task, 404);

        return $task;
    }

    /**
     * $kind tells the page and the milestone board what changed, so they draw again only where it shows: 'task' (its
     * status, dates, people or waits) or 'activity' (steps, comments, watching, a proposal: only the Overview's history)
     */
    private function changed(string $message, string $tone = 'success', string $kind = 'task'): void
    {
        $this->notice = $message;
        $this->noticeTone = $tone;
        $this->dispatch('project-changed', kind: $kind);
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        $task = $this->taskId ? $this->task() : null;
        $this->title = trim($this->title);
        // The team (active staff), plus whoever is on the task already: someone who has left doesn't block a save
        $team = $this->team()->pluck('id')->all();
        $already = $task ? Tasks::people($task->id) : ['sharing' => [], 'watcher' => []];
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'phaseId' => ['required', Rule::exists('project_phases', 'id')->where('project_id', $this->projectId)->whereNull('deleted_at')],
            'leadId' => ['nullable', Rule::in(array_filter([...$team, $task?->assigned_to]))],
            'sharing' => ['array', 'max:25'],
            'sharing.*' => [Rule::in([...$team, ...$already['sharing'], ...$already['watcher']])],
            'watchers' => ['array', 'max:25'],
            'watchers.*' => [Rule::in([...$team, ...$already['sharing'], ...$already['watcher']])],
            'dueDate' => ['nullable', 'date_format:Y-m-d'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ], [
            'title.required' => __('projects.task.errors.title'),
            'title.max' => __('projects.task.errors.title_long'),
            'phaseId.*' => __('projects.task.errors.milestone'),
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
        // The lead may have changed, then the people: each task() looks at access again, as before
        $this->forgetAccess();
        Tasks::setPeople($this->task(), $this->sharing, $this->watchers);
        $this->forgetAccess();
        $this->fillFrom($this->task());
        $this->changed($task ? __('projects.drawer.saved') : __('projects.task.created'));
    }

    public function assignToMe(): void
    {
        $task = $this->task();
        abort_unless(Auth::user()->can('projects.edit') || $this->canManage(), 403);
        $anyone = $this->canManage() || Auth::user()->can('projects.approve');
        if (Tasks::assignToMe($task, $anyone)) {
            $this->forgetAccess();
            $this->fillFrom($this->task());
            $this->changed(__('projects.task.assigned_to_you'));
        } else {
            $this->notice = __('projects.task.already_led');
            $this->noticeTone = 'warning';
        }
    }

    /** The status menu: the same move as dragging the card on the milestone board (timers, client review window) */
    public function updatedStatus(string $value): void
    {
        $task = $this->task();
        if ($value === $task->status || ! in_array($value, ProjectPlan::TASK_STATUSES, true)) {
            $this->status = $task->status;

            return;
        }
        if (! ProjectRules::canMove($task, $this->projectId, $this->canWork($task), $this->canView())) {
            $this->status = $task->status;
            $this->notice = __('projects.task.move_own');
            $this->noticeTone = 'error';

            return;
        }
        Tasks::move($this->projectId, $task, $value);
        $this->dispatch('timers-changed'); // the page-wide 45-minute check follows (App\Livewire\Hq\TimerPresence)
        $this->changed(__('projects.task.moved', ['status' => __('projects.task_statuses.' . $value)]));
    }

    public function watch(bool $on): void
    {
        Tasks::watch($this->task(), $on);
        $this->forgetAccess();
        $this->watchers = Tasks::people($this->taskId)['watcher'];
        $this->changed($on ? __('projects.task.watching') : __('projects.task.not_watching'), kind: 'activity');
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
        $this->changed(__('projects.task.step_added'), kind: 'activity');
    }

    public function toggleStep(int $stepId): void
    {
        Tasks::toggleStep($this->authoriseWork(), $stepId);
        $this->dispatch('project-changed', kind: 'activity');
    }

    public function removeStep(int $stepId): void
    {
        Tasks::removeStep($this->authoriseWork(), $stepId);
        $this->changed(__('projects.task.step_removed'), kind: 'activity');
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
        $this->changed(__('projects.task.commented'), kind: 'activity');
    }

    /** A team member's proposal for the project lead; the lead (or a manager) simply sets the date */
    public function proposeDue(): void
    {
        $task = $this->authoriseWork();
        $this->validate(['proposedDate' => ['required', 'date_format:Y-m-d']], ['proposedDate.*' => __('projects.errors.date')]);
        Tasks::proposeDueDate($this->projectId, $task, $this->proposedDate);
        // Only the drawer shows a proposal; the due date itself changes when the lead accepts it
        $this->changed(__('projects.task.proposed'), kind: 'activity');
    }

    public function decideProposal(bool $accept): void
    {
        abort_unless($this->canManage(), 403);
        Tasks::decideProposal($this->task(), $accept);
        $this->fillFrom($this->task());
        $this->changed($accept ? __('projects.task.proposal_accepted') : __('projects.task.proposal_declined'));
    }

    /** Steps, links, comments and proposals: the lead and people sharing the task, or a manager */
    private function authoriseWork(): object
    {
        $task = $this->task();
        abort_unless($this->canWork($task), 403, __('projects.task.work_own'));
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
        $canManage = $this->canManage();
        // The milestone menu and the drawer's heading need each milestone's code and name only (not its progress or invoice)
        $milestones = DB::table('project_phases')->where('project_id', $this->projectId)->whereNull('deleted_at')
            ->orderBy('phase_number')->orderBy('created_at')->get(['id', 'phase_number', 'title'])
            ->each(function ($m) {
                $m->code = ProjectPlan::code($m);
                $m->name = ProjectPlan::title($m);
            });

        $data = [
            'task' => $task,
            'canManage' => $canManage,
            'milestones' => $milestones,
            'names' => collect(),
        ] + ($canManage ? $this->pickers() : []);
        if (! $task) {
            return view('livewire.hq.projects.task-drawer', $data);
        }

        $waits = DB::table('task_dependencies')->join('tasks', 'tasks.id', '=', 'task_dependencies.depends_on_task_id')
            ->where('task_dependencies.task_id', $task->id)->whereNull('tasks.deleted_at')->orderBy('tasks.title')->get(['tasks.id', 'tasks.title', 'tasks.status']);
        $holding = DB::table('task_dependencies')->join('tasks', 'tasks.id', '=', 'task_dependencies.task_id')
            ->where('task_dependencies.depends_on_task_id', $task->id)->whereNull('tasks.deleted_at')->orderBy('tasks.title')->get(['tasks.id', 'tasks.title', 'tasks.status']);
        $people = Tasks::people($task->id);
        $steps = DB::table('task_steps')->where('task_id', $task->id)->orderBy('position')->get();
        // Names for the people this drawer shows (its lead, who shares and watches it, who proposed a date, who ticked
        // a step), whoever they are, as before; not every account
        $nameIds = collect([$task->assigned_to, $task->proposed_due_by, ...$people['sharing'], ...$people['watcher']])
            ->merge($steps->pluck('done_by'))->filter()->unique()->values();
        $data['names'] = $nameIds->isEmpty() ? collect() : DB::table('users')->whereIn('id', $nameIds)->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);
        $canWork = $this->canWork($task);

        return view('livewire.hq.projects.task-drawer', $data + [
            'canWork' => $canWork,
            'canMove' => ProjectRules::canMove($task, $this->projectId, $canWork, $this->canView()),
            // "Assign to me": an unassigned task for anyone with projects.edit, anyone's for a manager (the board's rule)
            'canCheckout' => (! $task->assigned_to && Auth::user()->can('projects.edit')) || ($canManage && $task->assigned_to !== Auth::id()),
            'myRole' => $task->assigned_to === Auth::id() ? 'lead' : (in_array(Auth::id(), $people['sharing'], true) ? 'sharing' : (in_array(Auth::id(), $people['watcher'], true) ? 'watcher' : null)),
            'people' => $people,
            'milestone' => $milestones->firstWhere('id', $task->phase_id),
            'steps' => $steps,
            'waits' => $waits,
            'holding' => $holding,
            // The "waits on" menu: only for someone who may add one
            'candidates' => ! $canWork ? collect() : DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
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
