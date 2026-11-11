<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class ProjectBudgetManager extends Component
{
    // The project chosen; its budget lines and totals are read when the page renders (never kept in the page)
    public $activeProjectId = '';

    // Sweep / Reallocate State
    public $showSweepModal = false;
    public $sweepFromId = '';
    public $sweepToId = '';
    public $sweepAmount = '';

    public function mount()
    {
        $first = DB::table('projects')->orderBy('created_at', 'desc')->value('id');
        if ($first) {
            $this->switchProject($first);
        }
    }

    public function switchProject($projectId)
    {
        $this->activeProjectId = $projectId;
    }

    /** The projects to choose from, newest first: only what the picker and the totals use */
    private function allProjects()
    {
        return DB::table('projects')->orderBy('created_at', 'desc')->get(['id', 'title', 'currency', 'total_budget']);
    }

    /** The project chosen, as it is now in the database */
    private function activeProject(): ?object
    {
        return $this->activeProjectId ? DB::table('projects')->where('id', (string) $this->activeProjectId)->first(['id', 'title', 'currency', 'total_budget']) : null;
    }

    /** The project's budget lines (as arrays) and its totals */
    private function budgetData(object $activeProject): array
    {
        // Fetch budget lines and join MDM dictionary for categorization
        $rawBudgets = DB::table('project_budgets')
            ->leftJoin('mdm_financial_dictionary', 'project_budgets.dict_id', '=', 'mdm_financial_dictionary.id')
            ->where('project_budgets.project_id', $activeProject->id)
            ->select(
                'project_budgets.id', 'project_budgets.dict_id', 'project_budgets.description',
                'project_budgets.total_allocated', 'project_budgets.total_spent',
                'mdm_financial_dictionary.item_code', 
                'mdm_financial_dictionary.category'
            )
            ->get();

        // ACCRUAL LOGIC: pending/authorized cash requests locked against each line, summed per expense item in one query
        $accruedByItem = DB::table('cash_requests')
            ->where('project_id', $activeProject->id)
            ->whereIn('status', ['Pending Auth', 'Authorized'])
            ->groupBy('financial_dictionary_id')
            ->selectRaw('financial_dictionary_id, SUM(amount_requested) as accrued')
            ->pluck('accrued', 'financial_dictionary_id');

        $totalAllocated = 0;
        $totalSpent = 0;
        $totalAccrued = 0;

        $budgetLines = $rawBudgets->map(function($line) use (&$totalAllocated, &$totalSpent, &$totalAccrued, $accruedByItem) {

            $line->accrued_amount = (float) ($line->dict_id === null ? 0 : ($accruedByItem[$line->dict_id] ?? 0));
            
            // Available Balance = Allocated - (Already Spent + Currently Requested)
            $line->available_balance = (float) $line->total_allocated - ((float) $line->total_spent + $line->accrued_amount);

            $totalAllocated += (float) $line->total_allocated;
            $totalSpent += (float) $line->total_spent;
            $totalAccrued += $line->accrued_amount;

            return (array) $line;
        })->toArray();

        return [$budgetLines, [
            'total_budget' => (float) $activeProject->total_budget,
            'total_allocated' => $totalAllocated,
            'total_spent' => $totalSpent,
            'total_accrued' => $totalAccrued,
            'remaining_pool' => (float) $activeProject->total_budget - $totalAllocated 
        ]];
    }

    public function openSweepModal()
    {
        $this->reset(['sweepFromId', 'sweepToId', 'sweepAmount']);
        $this->showSweepModal = true;
    }

    public function executeSweep()
    {
        abort_unless(Auth::user()->can('finance.edit'), 403);

        $this->validate([
            'sweepFromId' => 'required|string|exists:project_budgets,id',
            'sweepToId' => 'required|string|different:sweepFromId|exists:project_budgets,id',
            'sweepAmount' => 'required|numeric|min:0.01'
        ], [
            'sweepToId.different' => 'Choose a different line to move the budget to.',
        ], [
            'sweepFromId' => 'line to move from',
            'sweepToId' => 'line to move to',
            'sweepAmount' => 'amount',
        ]);

        $activeProject = $this->activeProject();

        try {
            DB::transaction(function () use ($activeProject) {
                $fromLine = DB::table('project_budgets')->where('id', $this->sweepFromId)->first();
                $toLine = DB::table('project_budgets')->where('id', $this->sweepToId)->first();

                // 1. Both lines must belong to the selected project
                if (! $activeProject || $fromLine->project_id !== $activeProject->id || $toLine->project_id !== $activeProject->id) {
                    throw new \DomainException("Both budget lines must belong to the selected project. Refresh the page and try again.");
                }

                // 2. Only money that is neither spent nor held by open cash requests can move
                $accrued = DB::table('cash_requests')
                    ->where('project_id', $activeProject->id)
                    ->where('financial_dictionary_id', $fromLine->dict_id)
                    ->whereIn('status', ['Pending Auth', 'Authorized'])
                    ->sum('amount_requested');
                    
                $available = (float) $fromLine->total_allocated - ((float) $fromLine->total_spent + (float) $accrued);
                $sweepAmountFloat = (float) $this->sweepAmount;

                if ($sweepAmountFloat > $available) {
                    throw new \DomainException("That’s more than the line has available. Money already spent or held by open cash requests can’t be moved.");
                }

                // 3. Move the budget
                DB::table('project_budgets')->where('id', $this->sweepFromId)->decrement('total_allocated', $sweepAmountFloat);
                DB::table('project_budgets')->where('id', $this->sweepToId)->increment('total_allocated', $sweepAmountFloat);
                
                // 4. Audit log (null user for system actions, to respect UUID foreign keys)
                DB::table('system_audit_logs')->insert([
                    // Assuming id is auto-increment or UUID, ensure it’s handled. If UUID: 'id' => str()->uuid(),
                    'user_id' => Auth::check() ? Auth::id() : null, // Use null instead of 'SYSTEM' to respect UUID FKs
                    'action' => 'BUDGET_SWEEP_EXECUTED',
                    'description' => "Moved {$activeProject->currency} {$sweepAmountFloat} of budget from {$fromLine->description} to {$toLine->description} on project {$activeProject->title}",
                    'target_table' => 'project_budgets',
                    'ip_address' => request()->ip(),
                    'created_at' => now()
                ]);
            });

            $this->showSweepModal = false;
            session()->flash('success', 'Budget moved. The line totals below are updated.');

        } catch (\DomainException $e) {
            session()->flash('error', "We couldn’t move the budget. " . $e->getMessage());
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t move the budget, so nothing was changed. Try again, and if it still fails, ask an administrator to check the error log.");
        }
    }

    public function render()
    {
        $activeProject = $this->activeProject();
        [$budgetLines, $metrics] = $activeProject
            ? $this->budgetData($activeProject)
            : [[], ['total_budget' => 0, 'total_allocated' => 0, 'total_spent' => 0, 'total_accrued' => 0, 'remaining_pool' => 0]];

        return view('livewire.hq.finance.project-budget-manager', [
            'allProjects' => $this->allProjects(),
            'activeProject' => $activeProject,
            'budgetLines' => $budgetLines,
            'metrics' => $metrics,
        ]);
    }
}