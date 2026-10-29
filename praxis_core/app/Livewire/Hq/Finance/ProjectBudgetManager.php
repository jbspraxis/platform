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
    public $allProjects = [];
    public $activeProjectId = '';
    public $activeProject = null;

    public $budgetLines = [];
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
        $this->validate([
            'sweepFromId' => 'required|string|exists:project_budgets,id',
            'sweepToId' => 'required|string|different:sweepFromId|exists:project_budgets,id',
            'sweepAmount' => 'required|numeric|min:0.01'
        ]);

        try {
            DB::transaction(function () {
                $fromLine = DB::table('project_budgets')->where('id', $this->sweepFromId)->first();
                $toLine = DB::table('project_budgets')->where('id', $this->sweepToId)->first();

                // 1. SECURITY LOCK: Prevent Cross-Project Sweeping
                if ($fromLine->project_id !== $this->activeProject->id || $toLine->project_id !== $this->activeProject->id) {
                    throw new \Exception("SECURITY VIOLATION: Budget lines do not belong to the active project workspace.");
                }

                // 2. ACCRUAL LOCK: Calculate real availability to prevent over-sweeping locked funds
                $accrued = DB::table('cash_requests')
                    ->where('project_id', $this->activeProject->id)
                    ->where('financial_dictionary_id', $fromLine->dict_id)
                    ->whereIn('status', ['Pending Auth', 'Authorized'])
                    ->sum('amount_requested');
                    
                $available = (float) $fromLine->total_allocated - ((float) $fromLine->total_spent + (float) $accrued);
                $sweepAmountFloat = (float) $this->sweepAmount;

                if ($sweepAmountFloat > $available) {
                    throw new \Exception("Insufficient available funds. Funds may be locked in pending Cash Requests.");
                }

                // 3. EXECUTE SWEEP
                DB::table('project_budgets')->where('id', $this->sweepFromId)->decrement('total_allocated', $sweepAmountFloat);
                DB::table('project_budgets')->where('id', $this->sweepToId)->increment('total_allocated', $sweepAmountFloat);
                
                // 4. IMMUTABLE AUDIT LOG (Safe Null handling for UUID Foreign Keys)
                DB::table('system_audit_logs')->insert([
                    // Assuming id is auto-increment or UUID, ensure it's handled. If UUID: 'id' => str()->uuid(),
                    'user_id' => Auth::check() ? Auth::id() : null, // Use null instead of 'SYSTEM' to respect UUID FKs
                    'action' => 'BUDGET_SWEEP_EXECUTED',
                    'description' => "Swept {$this->activeProject->currency} {$sweepAmountFloat} from [{$fromLine->description}] to [{$toLine->description}] on Project [{$this->activeProject->name}]",
                    'target_table' => 'project_budgets',
                    'ip_address' => request()->ip(),
                    'created_at' => now()
                ]);
            });

            $this->showSweepModal = false;
            $this->loadBudgetData();
            session()->flash('success', '> CAPITAL SWEEP SUCCESSFUL. MATRIX REBALANCED.');

        } catch (\Exception $e) {
            session()->flash('error', '> SWEEP REJECTED: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.hq.finance.project-budget-manager');
    }
}