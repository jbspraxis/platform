<?php

namespace App\Livewire\Hq\Projects;

use App\Support\Projects\ChangeReports;
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
 * The change report drawer (docs/PROJECTS.md, decision 7 and section 3): number, title, description, price, effect on
 * the timeline, status, the milestone and tasks it would add, and the client's decision. A draft or sent report is
 * edited by the project's lead and managers; the decision is recorded once and then the report is read only.
 * Approval adds its milestone and tasks to the plan and its price to the contract total (App\Support\Projects\ChangeReports).
 */
class ChangeReportDrawer extends Component
{
    #[Locked]
    public string $projectId;

    #[Locked]
    public ?string $reportId = null;

    #[Locked]
    public bool $open = false;

    #[Url(as: 'report', except: '')]
    public string $link = '';

    public string $title = '';
    public string $description = '';
    public string $price = '';
    public string $timelineDays = '0';
    public string $milestoneTitle = '';
    public string $milestoneDescription = '';
    public string $milestoneDue = '';
    public array $planTasks = [];
    public string $newPlanTask = '';

    // The client's decision
    public string $decision = '';
    public string $decidedOn = '';
    public string $decidedBy = '';
    public string $decidedByTitle = '';
    #[Locked]
    public bool $confirming = false;

    public string $notice = '';
    public string $noticeTone = 'success';

    // Worked out once per request and kept in private properties, which Livewire never sends to the browser nor keeps
    // between requests (nothing here changes who sees the project)
    private ?bool $canView = null;
    private ?bool $canManage = null;

    private function canView(): bool
    {
        return $this->canView ??= ProjectRules::canView($this->projectId);
    }

    private function canManage(): bool
    {
        return $this->canManage ??= ProjectRules::canManage($this->projectId, $this->canView());
    }

    /** $kind: 'report' while it is a draft or sent; an approval adds a milestone and tasks, so the board follows too */
    private function changed(string $message, string $kind = 'report'): void
    {
        $this->notice = $message;
        $this->noticeTone = 'success';
        $this->dispatch('project-changed', kind: $kind);
    }

    public function mount(string $projectId): void
    {
        $this->projectId = $projectId;
        if ($this->link !== '') {
            $this->openReport($this->link);
        }
    }

    #[On('open-report')]
    public function openReport(string $id): void
    {
        $report = $this->canView() ? ChangeReports::find($this->projectId, $id) : null;
        if (! $report) {
            $this->close();

            return;
        }
        $this->resetForm();
        $this->reportId = $report->id;
        $this->link = $report->id;
        $this->open = true;
        $this->fillFrom($report);
    }

    #[On('new-report')]
    public function newReport(): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetForm();
        $this->reportId = null;
        $this->link = '';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->reportId = null;
        $this->link = '';
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('title', 'description', 'price', 'timelineDays', 'milestoneTitle', 'milestoneDescription', 'milestoneDue', 'planTasks', 'newPlanTask',
            'decision', 'decidedOn', 'decidedBy', 'decidedByTitle', 'confirming', 'notice', 'noticeTone');
        $this->resetErrorBag();
    }

    private function fillFrom(object $report): void
    {
        $plan = json_decode((string) $report->plan, true) ?: [];
        $this->fill([
            'title' => $report->title,
            'description' => (string) $report->description,
            'price' => rtrim(rtrim((string) $report->price, '0'), '.'),
            'timelineDays' => (string) $report->timeline_days,
            'milestoneTitle' => (string) ($plan['milestone']['title'] ?? ''),
            'milestoneDescription' => (string) ($plan['milestone']['description'] ?? ''),
            'milestoneDue' => (string) ($plan['milestone']['due_date'] ?? ''),
            'planTasks' => array_values(array_map(fn ($t) => (string) ($t['title'] ?? ''), $plan['tasks'] ?? [])),
            'decidedOn' => (string) ($report->decided_on ?: ProjectPlan::today()),
        ]);
    }

    /** An open report (draft or sent) this person may change */
    private function report(): object
    {
        abort_unless($this->canManage(), 403);
        $report = ChangeReports::find($this->projectId, $this->reportId);
        abort_unless($report, 404);
        abort_unless(ChangeReports::isOpen($report), 403, __('projects.cr.locked'));

        return $report;
    }

    public function addPlanTask(): void
    {
        $text = trim($this->newPlanTask);
        if ($text !== '' && count($this->planTasks) < 50) {
            $this->planTasks[] = mb_substr($text, 0, 252);
        }
        $this->newPlanTask = '';
    }

    public function removePlanTask(int $index): void
    {
        unset($this->planTasks[$index]);
        $this->planTasks = array_values($this->planTasks);
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        $report = $this->reportId ? $this->report() : null;
        $this->title = trim($this->title);
        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'timelineDays' => ['required', 'integer', 'min:0', 'max:3650'],
            'milestoneTitle' => ['nullable', 'string', 'max:150'],
            'milestoneDescription' => ['nullable', 'string', 'max:10000'],
            'milestoneDue' => ['nullable', 'date_format:Y-m-d'],
            'planTasks' => ['array', 'max:50'],
            'planTasks.*' => ['string', 'max:252'],
        ], [
            'title.required' => __('projects.cr.errors.title'),
            'title.max' => __('projects.cr.errors.title_long'),
            'price.*' => __('projects.cr.errors.price'),
            'timelineDays.*' => __('projects.cr.errors.days'),
            'milestoneTitle.max' => __('projects.milestone.errors.title_long'),
            'milestoneDue.*' => __('projects.errors.date'),
            'planTasks.*' => __('projects.cr.errors.tasks'),
            'description.max' => __('projects.errors.long'),
            'milestoneDescription.max' => __('projects.errors.long'),
        ]);
        $data = [
            'title' => $this->title,
            'description' => trim($this->description) ?: null,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'timeline_days' => (int) $this->timelineDays,
            'plan' => [
                'milestone' => ['title' => trim($this->milestoneTitle), 'description' => trim($this->milestoneDescription), 'due_date' => $this->milestoneDue ?: null],
                'tasks' => array_map(fn ($t) => ['title' => $t], array_values(array_filter(array_map('trim', $this->planTasks), 'strlen'))),
            ],
        ];
        if ($report) {
            ChangeReports::update($report, $data);
        } else {
            $this->reportId = ChangeReports::create($this->projectId, $data);
            $this->link = $this->reportId;
        }
        $this->fillFrom(ChangeReports::find($this->projectId, $this->reportId));
        $this->changed($report ? __('projects.drawer.saved') : __('projects.cr.created'));
    }

    public function markSent(): void
    {
        $report = $this->report();
        ChangeReports::markSent($report);
        $this->changed(__('projects.cr.sent'));
    }

    /** First step: check the decision, then show what it will do and ask to confirm */
    public function reviewDecision(): void
    {
        $this->report();
        $this->decidedBy = trim($this->decidedBy);
        $this->decidedByTitle = trim($this->decidedByTitle);
        $this->validate([
            'decision' => ['required', Rule::in(['approved', 'declined'])],
            'decidedOn' => ['required', 'date_format:Y-m-d'],
            'decidedBy' => ['required', 'string', 'max:150'],
            'decidedByTitle' => ['nullable', 'string', 'max:150'],
        ], [
            'decision.*' => __('projects.cr.errors.decision'),
            'decidedOn.*' => __('projects.errors.date'),
            'decidedBy.*' => __('projects.cr.errors.decided_by'),
            'decidedByTitle.*' => __('projects.cr.errors.decided_by'),
        ]);
        $this->confirming = true;
    }

    public function cancelDecision(): void
    {
        $this->confirming = false;
    }

    /** The milestone an approved report added: this drawer closes and the milestone's opens */
    public function openMilestone(string $id): void
    {
        $this->close();
        $this->dispatch('open-milestone', id: $id);
    }

    public function recordDecision(): void
    {
        abort_unless($this->confirming, 422);
        // Checked again: the fields can change between the review and the click
        $this->reviewDecision();
        $report = $this->report();
        ChangeReports::decide($report, $this->decision === 'approved', $this->decidedOn, $this->decidedBy, $this->decidedByTitle);
        $this->confirming = false;
        $approved = $this->decision === 'approved';
        $this->changed($approved ? __('projects.cr.approved') : __('projects.cr.declined'), $approved ? 'milestone' : 'report');
    }

    public function render()
    {
        $report = null;
        if ($this->open && $this->reportId) {
            $report = ChangeReports::find($this->projectId, $this->reportId);
            if (! $report) {
                $this->close();
            }
        }
        $project = DB::table('projects')->where('id', $this->projectId)->first(['total_budget', 'currency', 'target_end_date']);

        return view('livewire.hq.projects.change-report-drawer', [
            'report' => $report,
            'canManage' => $this->open && $this->canManage(),
            'canSeeMoney' => $this->open && ProjectRules::canSeeMoney($this->projectId, $this->canManage()),
            'project' => $project,
            'preview' => $report && ChangeReports::isOpen($report) ? ChangeReports::preview($report) : null,
            'phase' => $report && $report->phase_id ? DB::table('project_phases')->where('id', $report->phase_id)->whereNull('deleted_at')->first(['id', 'phase_number', 'title', 'weight_percentage']) : null,
            'history' => $report ? ProjectHistory::for('change_reports', $report->id) : collect(),
        ]);
    }
}
