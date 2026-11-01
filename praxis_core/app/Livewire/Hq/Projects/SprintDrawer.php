<?php

namespace App\Livewire\Hq\Projects;

use App\Support\Projects\Milestones;
use App\Support\Projects\ProjectHistory;
use App\Support\Projects\ProjectPlan;
use App\Support\Projects\ProjectRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The sprint drawer (docs/PROJECTS.md section 3): title, start and end, status, goal and notes, and the milestones it
 * delivers (from its tasks) with their weight. The project's lead and managers edit and add sprints; a new one starts
 * the day after the last one ends and runs two weeks.
 */
class SprintDrawer extends Component
{
    #[Locked]
    public string $projectId;

    #[Locked]
    public ?string $sprintId = null;

    #[Locked]
    public bool $open = false;

    #[Url(as: 'sprint_id', except: '')]
    public string $link = '';

    public string $title = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $status = 'Planned';
    public string $goal = '';
    public string $notes = '';

    public string $notice = '';

    public function mount(string $projectId): void
    {
        $this->projectId = $projectId;
        if ($this->link !== '') {
            $this->openSprint($this->link);
        }
    }

    #[On('open-sprint')]
    public function openSprint(string $id): void
    {
        $sprint = ProjectRules::canView($this->projectId) ? Milestones::findSprint($this->projectId, $id) : null;
        if (! $sprint) {
            $this->close();

            return;
        }
        $this->resetForm();
        $this->sprintId = $sprint->id;
        $this->link = $sprint->id;
        $this->open = true;
        $this->fill([
            'title' => $sprint->title, 'startDate' => (string) $sprint->start_date, 'endDate' => (string) $sprint->end_date,
            'status' => $sprint->status, 'goal' => (string) $sprint->goal, 'notes' => (string) $sprint->notes,
        ]);
    }

    #[On('new-sprint')]
    public function newSprint(): void
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        $this->resetForm();
        $this->sprintId = null;
        $this->link = '';
        $this->open = true;
        $last = DB::table('sprints')->where('project_id', $this->projectId)->whereNull('deleted_at')->max('end_date');
        $start = $last ? Carbon::parse($last)->addDay() : now(ProjectPlan::TIME_ZONE)->startOfDay();
        $count = DB::table('sprints')->where('project_id', $this->projectId)->whereNull('deleted_at')->count();
        $this->fill([
            'title' => __('projects.sprint.default_title', ['n' => $count + 1]),
            'startDate' => $start->toDateString(),
            'endDate' => $start->copy()->addDays(13)->toDateString(),
        ]);
    }

    public function close(): void
    {
        $this->open = false;
        $this->sprintId = null;
        $this->link = '';
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('title', 'startDate', 'endDate', 'status', 'goal', 'notes', 'notice');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        abort_unless(ProjectRules::canManage($this->projectId), 403);
        $sprint = $this->sprintId ? Milestones::findSprint($this->projectId, $this->sprintId) : null;
        abort_if($this->sprintId && ! $sprint, 404);
        $this->title = trim($this->title);
        $this->validate([
            'title' => ['required', 'string', 'max:100'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'status' => ['required', Rule::in(Milestones::SPRINT_STATUSES)],
            'goal' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ], [
            'title.required' => __('projects.sprint.errors.title'),
            'title.max' => __('projects.sprint.errors.title_long'),
            'startDate.*' => __('projects.errors.date'),
            'endDate.after_or_equal' => __('projects.sprint.errors.end'),
            'endDate.*' => __('projects.errors.date'),
            'status.*' => __('projects.sprint.errors.status'),
            'goal.max' => __('projects.errors.long'),
            'notes.max' => __('projects.errors.long'),
        ]);
        $data = [
            'title' => $this->title, 'start_date' => $this->startDate, 'end_date' => $this->endDate, 'status' => $this->status,
            'goal' => trim($this->goal) ?: null, 'notes' => trim($this->notes) ?: null,
        ];
        if ($sprint) {
            Milestones::updateSprint($sprint, $data);
        } else {
            $this->sprintId = Milestones::createSprint($this->projectId, $data);
            $this->link = $this->sprintId;
        }
        $this->notice = $sprint ? __('projects.drawer.saved') : __('projects.sprint.created');
        $this->dispatch('project-changed');
    }

    public function render()
    {
        $sprint = null;
        if ($this->open && $this->sprintId) {
            $sprint = ProjectPlan::sprints($this->projectId, ProjectPlan::milestones($this->projectId))->firstWhere('id', $this->sprintId);
            if (! $sprint) {
                $this->close();
            }
        }

        return view('livewire.hq.projects.sprint-drawer', [
            'sprint' => $sprint,
            'canManage' => $this->open && ProjectRules::canManage($this->projectId),
            'history' => $sprint ? ProjectHistory::for('sprints', $sprint->id) : collect(),
        ]);
    }
}
