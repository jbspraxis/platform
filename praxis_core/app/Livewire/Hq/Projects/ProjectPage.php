<?php

namespace App\Livewire\Hq\Projects;

use App\Models\Opportunity;
use App\Support\Access;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Projects\Milestones;
use App\Support\Projects\ProjectHistory;
use App\Support\Projects\ProjectPlan;
use App\Support\Projects\ProjectRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One project (docs/PROJECTS.md section 2): Overview, Milestones (the kanban of decision 2), Milestone board (one
 * milestone's tasks by status: the sprint board, now taking a milestone), Backlog, QA, and Meetings and money.
 * Milestones, tasks and change reports open in their drawers (MilestoneDrawer, TaskDrawer, ChangeReportDrawer), which
 * dispatch project-changed after each change. projects.view and the board rule open it; the project's lead and
 * managers edit (App\Support\Projects\ProjectRules).
 */
#[Layout('components.layouts.hq')]
class ProjectPage extends Component
{
    public const TABS = ['overview', 'milestones', 'board', 'backlog', 'qa', 'money'];

    #[Locked]
    public string $projectId;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    // The milestone the board opens on (?board=<id>, from a kanban card); the board's own menu changes it after that
    #[Url(as: 'board', except: '')]
    public string $boardMilestone = '';

    // Backlog filters: lead '' (anyone), 'none' or an id
    #[Url(as: 'lead', except: '')]
    public string $filterLead = '';

    #[Url(as: 'status', except: '')]
    public string $filterStatus = '';

    #[Url(as: 'overdue', except: false)]
    public bool $filterOverdue = false;

    // The project lead (projects.lead_user_id): set by managers (projects.create)
    public string $leadUserId = '';

    // mount() or refresh() has just checked access: render() in the same request doesn't ask again (private, so
    // worked out afresh for every request; saveLead() changes who leads, so it leaves this unset)
    private ?bool $canView = null;

    private function canView(): bool
    {
        return $this->canView ??= ProjectRules::canView($this->projectId);
    }

    public function mount(string $project): void
    {
        // render() reads the whole project; here only whether it exists and who leads it
        $row = DB::table('projects')->where('id', $project)->whereNull('deleted_at')->first(['id', 'lead_user_id']);
        abort_unless($row, 404);
        abort_unless($this->canView = ProjectRules::canView($project), 403, __('projects.errors.no_access'));
        $this->projectId = $project;
        $this->leadUserId = (string) $row->lead_user_id;
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }
    }

    public function saveLead(): void
    {
        abort_unless(ProjectRules::canView($this->projectId) && Auth::user()->can('projects.create'), 403);
        $this->validate(['leadUserId' => ['required', Rule::in(ProjectRules::team($this->projectId)->pluck('id')->all())]],
            ['leadUserId.*' => __('projects.lead.error')]);
        $old = DB::table('projects')->where('id', $this->projectId)->value('lead_user_id');
        if ($old !== $this->leadUserId) {
            DB::table('projects')->where('id', $this->projectId)->update(['lead_user_id' => $this->leadUserId, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            ProjectRules::log('PROJECT_LEAD_CHANGED', 'Changed the project lead.', 'projects', $this->projectId, ['lead_user_id' => $old], ['lead_user_id' => $this->leadUserId]);
        }
        session()->flash('success', __('projects.lead.saved'));
    }

    public function show(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    /**
     * A drawer changed something: draw the page again. $kind says what: a step, comment, watch or due-date proposal
     * ('activity') shows only in the Overview's recent activity, and a change report not yet decided ('report') only
     * there and on Meetings and money, so any other tab stays as it is.
     */
    #[On('project-changed')]
    public function refresh(string $kind = 'task'): void
    {
        abort_unless($this->canView = ProjectRules::canView($this->projectId), 403, __('projects.errors.no_access'));
        $shows = match ($kind) {
            'activity' => ['overview'],
            'report' => ['overview', 'money'],
            default => self::TABS,
        };
        if (! in_array($this->tab, $shows, true)) {
            $this->skipRender();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('filterLead', 'filterStatus', 'filterOverdue');
    }

    /**
     * A milestone card dropped on a column (or moved from its menu). The four status columns are set here (Accepted
     * asks who accepted it, in the drawer); Invoiced opens Finance's Create invoice for it, Paid follows Finance.
     */
    public function moveMilestone(string $phaseId, string $column): void
    {
        abort_unless(ProjectRules::canManage($this->projectId, $this->canView()), 403);
        $phase = Milestones::find($this->projectId, $phaseId);
        if (! $phase || ! in_array($column, ProjectPlan::COLUMNS, true)) {
            return;
        }
        $milestone = ProjectPlan::milestones($this->projectId)->firstWhere('id', $phase->id);
        if ($milestone->column === $column) {
            return;
        }

        if (in_array($milestone->column, ['invoiced', 'paid'], true)) {
            session()->flash('warning', __('projects.milestones.invoice_decides', ['code' => $milestone->code]));
        } elseif ($column === 'paid') {
            session()->flash('info', __('projects.milestones.paid_follows'));
        } elseif ($column === 'invoiced') {
            $this->invoice($milestone);
        } elseif ($column === 'accepted') {
            $this->dispatch('open-milestone', id: $phase->id, accept: true);
        } else {
            Milestones::move($phase, $column);
            session()->flash('success', __('projects.milestones.moved', ['code' => $milestone->code, 'column' => __('projects.columns.' . $column)]));
        }
    }

    /** Finance raises the invoice (finance.create); everyone else is told who can */
    private function invoice(object $milestone): void
    {
        if (Auth::user()->can('finance.create')) {
            $this->redirectRoute('hq.finance.invoices', ['create' => 1, 'project' => $this->projectId, 'phase' => $milestone->id], navigate: true);

            return;
        }
        $names = DB::table('users')->whereIn('id', Access::staffWith('finance.create'))->whereNull('deleted_at')
            ->orderBy('first_name')->get(['first_name', 'last_name'])->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name))->take(4)->implode(', ');
        session()->flash('warning', __('projects.milestones.invoice_who', ['code' => $milestone->code, 'names' => $names ?: __('projects.milestones.finance_team')]));
    }

    public function render()
    {
        $project = ProjectPlan::project($this->projectId);
        abort_unless($project, 404);
        $milestones = ProjectPlan::milestones($this->projectId);
        $canManage = ProjectRules::canManage($this->projectId, $this->canView());
        $opportunity = Opportunity::query()
            ->where(fn ($q) => $q->where('project_id', $this->projectId)->when($project->opportunity_id, fn ($q) => $q->orWhere('id', $project->opportunity_id)))
            ->orderByDesc('updated_at')->first();

        $data = [
            'project' => $project,
            'milestones' => $milestones,
            'progress' => ProjectPlan::progress($milestones),
            'weightTotal' => ProjectPlan::weightTotal($milestones),
            'nextDue' => ProjectPlan::nextDue($milestones),
            'overdueTasks' => ProjectPlan::overdueTasks($this->projectId),
            'openQa' => ProjectPlan::openQaCount($this->projectId),
            'canManage' => $canManage,
            'canSeeMoney' => ProjectRules::canSeeMoney($this->projectId, $canManage),
            'opportunity' => $opportunity,
            // The opportunity's team opens it too (OpportunityAccess, PR #14)
            'canOpenOpportunity' => $opportunity && OpportunityAccess::canView(Auth::user(), $opportunity),
            // Made when the SOW was signed (PR 5), its plan still in the project draft on the opportunity
            'planPending' => DB::table('project_drafts')->join('statements_of_work', 'statements_of_work.id', '=', 'project_drafts.sow_id')
                ->where('statements_of_work.project_id', $this->projectId)->where('project_drafts.status', '!=', 'created')->whereNull('project_drafts.deleted_at')->exists(),
        ];

        $data += match ($this->tab) {
            'overview' => [
                'current' => ProjectPlan::current($milestones),
                'activity' => ProjectHistory::activity($this->projectId, $milestones),
                'canSetLead' => Auth::user()->can('projects.create'),
                'team' => Auth::user()->can('projects.create') ? ProjectRules::team($this->projectId) : collect(),
            ],
            // A kanban card's link, else the milestone running now (the board decides)
            'board' => ['boardMilestoneId' => $milestones->firstWhere('id', $this->boardMilestone)?->id],
            'backlog' => $this->backlog(),
            'qa' => ['tickets' => DB::table('qa_tickets')->leftJoin('tasks', 'tasks.id', '=', 'qa_tickets.task_id')
                ->where('qa_tickets.project_id', $this->projectId)->whereNull('qa_tickets.deleted_at')
                ->orderByRaw("FIELD(qa_tickets.status, 'Open', 'Investigating', 'Resolved', 'Closed')")
                ->orderByRaw("FIELD(qa_tickets.severity, 'Critical', 'Medium', 'Low')")->orderByDesc('qa_tickets.created_at')
                ->get(['qa_tickets.id', 'qa_tickets.title', 'qa_tickets.severity', 'qa_tickets.status', 'qa_tickets.created_at', 'tasks.title as task_title'])],
            'money' => [
                // Each invoice with the milestones it bills ("M2, M3" when its lines bill several)
                'invoices' => (function () {
                    $invoices = DB::table('invoices')->where('invoices.project_id', $this->projectId)->whereNull('invoices.deleted_at')->orderBy('invoices.issued_at')
                        ->get(['invoices.id', 'invoices.invoice_number', 'invoices.status', 'invoices.total_amount', 'invoices.currency', 'invoices.due_date', 'invoices.paid_at']);
                    $numbers = \App\Support\Finance\MilestoneInvoices::milestoneNumbers($invoices->pluck('id'));

                    return $invoices->map(function ($inv) use ($numbers) {
                        $inv->milestones = ($n = $numbers->get((string) $inv->id)) && $n->isNotEmpty() ? $n->map(fn ($x) => 'M' . $x)->implode(', ') : null;

                        return $inv;
                    });
                })(),
                'reports' => DB::table('change_reports')->where('project_id', $this->projectId)->whereNull('deleted_at')->orderBy('number')->get(),
                'sow' => $data['opportunity'] ? DB::table('statements_of_work')->where('opportunity_id', $data['opportunity']->id)->whereNull('deleted_at')
                    ->orderByDesc('version')->first(['id', 'reference', 'version', 'status', 'signed_at']) : null,
            ],
            default => [],
        };

        return view('livewire.hq.projects.page', $data)->title($project->title . ' · ' . __('projects.title'));
    }

    /** Every task, filtered, grouped by milestone (every task has one); each with its lead, sharing count and open "waits on" */
    private function backlog(): array
    {
        $tasks = DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
            ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')
            ->where('project_phases.project_id', $this->projectId)->whereNull('project_phases.deleted_at')->whereNull('tasks.deleted_at')
            ->when($this->filterLead === 'none', fn ($q) => $q->whereNull('tasks.assigned_to'))
            ->when($this->filterLead !== '' && $this->filterLead !== 'none', fn ($q) => $q->where('tasks.assigned_to', $this->filterLead))
            ->when(in_array($this->filterStatus, ProjectPlan::TASK_STATUSES, true), fn ($q) => $q->where('tasks.status', $this->filterStatus))
            ->when($this->filterOverdue, fn ($q) => $q->whereNotNull('tasks.due_date')->where('tasks.due_date', '<', ProjectPlan::today())->where('tasks.status', '!=', 'Done'))
            ->orderByRaw("FIELD(tasks.status, 'In-Progress', 'Failed', 'QA_Review', 'To-Do', 'Done')")->orderBy('tasks.due_date')->orderBy('tasks.created_at')
            ->get(['tasks.id', 'tasks.phase_id', 'tasks.title', 'tasks.status', 'tasks.due_date', 'tasks.assigned_to', 'users.first_name', 'users.last_name']);

        $ids = $tasks->pluck('id')->all();
        $sharing = $ids ? DB::table('task_people')->whereIn('task_id', $ids)->where('role', 'sharing')->groupBy('task_id')->selectRaw('task_id, COUNT(*) AS n')->pluck('n', 'task_id') : collect();

        return [
            'tasks' => $tasks->groupBy('phase_id'),
            'taskCount' => $tasks->count(),
            'waits' => ProjectPlan::openWaits($ids),
            'sharing' => $sharing,
            'leads' => DB::table('users')->whereIn('id', DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
                ->where('project_phases.project_id', $this->projectId)->whereNotNull('tasks.assigned_to')->select('tasks.assigned_to'))
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'filtered' => $this->filterLead !== '' || $this->filterStatus !== '' || $this->filterOverdue,
        ];
    }
}
