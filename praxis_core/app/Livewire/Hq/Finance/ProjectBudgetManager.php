<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class ProjectBudgetManager extends Component
{
    // Loaded on the server only; the browser must not change them
    #[Locked]
    public $allProjects = [];
    public $activeProjectId = '';
    #[Locked]
    public $activeProject = null;

    #[Locked]
    public $budgetLines = [];
    #[Locked]
    public $metrics = [
        'total_budget' => 0,
        'total_allocated' => 0,
        'total_spent' => 0,
        'total_accrued' => 0, 
        'remaining_pool' => 0
    ];

    // Sweep / Reallocate State
    public $showSweepModal = false;
    public $sweepFromId = '';
    public $sweepToId = '';
    public $sweepAmount = '';

    public function mount()
    {
        $this->allProjects = DB::table('projects')->orderBy('created_at', 'desc')->get()->toArray();
        if (!empty($this->allProjects)) {
            $this->switchProject($this->allProjects[0]->id);
        }
    }

    public function switchProject($projectId)
    {
        $this->activeProjectId = $projectId;
        $this->activeProject = collect($this->allProjects)->firstWhere('id', $projectId);
        $this->loadBudgetData();
    }

    public function loadBudgetData()
    {
        if (!$this->activeProject) return;

        // Fetch budget lines and join MDM dictionary for categorization
        $rawBudgets = DB::table('project_budgets')
            ->leftJoin('mdm_financial_dictionary', 'project_budgets.dict_id', '=', 'mdm_financial_dictionary.id')
            ->where('project_budgets.project_id', $this->activeProject->id)
            ->select(
                'project_budgets.*', 
                'mdm_financial_dictionary.item_code', 
                'mdm_financial_dictionary.category'
            )
            ->get();

        $totalAllocated = 0;
        $totalSpent = 0;
        $totalAccrued = 0;

        $this->budgetLines = $rawBudgets->map(function($line) use (&$totalAllocated, &$totalSpent, &$totalAccrued) {
            
            // ACCRUAL LOGIC: Calculate pending/authorized cash requests locked against this line
            $accrued = DB::table('cash_requests')
                ->where('project_id', $this->activeProject->id)
                ->where('financial_dictionary_id', $line->dict_id)
                ->whereIn('status', ['Pending Auth', 'Authorized'])
                ->sum('amount_requested');

            $line->accrued_amount = (float) $accrued;
            
            // Available Balance = Allocated - (Already Spent + Currently Requested)
            $line->available_balance = (float) $line->total_allocated - ((float) $line->total_spent + $line->accrued_amount);

            $totalAllocated += (float) $line->total_allocated;
            $totalSpent += (float) $line->total_spent;
            $totalAccrued += $line->accrued_amount;

            return (array) $line;
        })->toArray();

        $this->metrics = [
            'total_budget' => (float) $this->activeProject->total_budget,
            'total_allocated' => $totalAllocated,
            'total_spent' => $totalSpent,
            'total_accrued' => $totalAccrued,
            'remaining_pool' => (float) $this->activeProject->total_budget - $totalAllocated 
        ];
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

        try {
            DB::transaction(function () {
                $fromLine = DB::table('project_budgets')->where('id', $this->sweepFromId)->first();
                $toLine = DB::table('project_budgets')->where('id', $this->sweepToId)->first();

                // 1. Both lines must belong to the selected project
                if ($fromLine->project_id !== $this->activeProject->id || $toLine->project_id !== $this->activeProject->id) {
                    throw new \DomainException("Both budget lines must belong to the selected project. Refresh the page and try again.");
                }

                // 2. Only money that is neither spent nor held by open cash requests can move
                $accrued = DB::table('cash_requests')
                    ->where('project_id', $this->activeProject->id)
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
                    'description' => "Moved {$this->activeProject->currency} {$sweepAmountFloat} of budget from {$fromLine->description} to {$toLine->description} on project {$this->activeProject->title}",
                    'target_table' => 'project_budgets',
                    'ip_address' => request()->ip(),
                    'created_at' => now()
                ]);
            });

            $this->showSweepModal = false;
            $this->loadBudgetData();
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
        return view('livewire.hq.finance.project-budget-manager');
    }
}