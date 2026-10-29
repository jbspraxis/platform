<?php

namespace App\Livewire\Hq\Mdm;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

#[Layout('components.layouts.hq')]
#[Title('Financial dictionary')]
class FinancialDictionary extends Component
{
    public $dictionary = [];
    public $editMode = false;
    public $recordId = null;
    public $showSuccessModal = false; 
    
    public $item_code = ''; 
    public $description = '';
    public $category = 'Consulting'; 
    public $uom = '1 Phase'; 
    public $default_unit_rate_usd = 0.00;

    // Each category carries its item code letter and the income and expense accounts it posts to (labels are display only)
    public $availableCategories = [
        'Consulting' =>        ['label' => 'Consulting and advisory',    'code' => 'C', 'income' => '4000', 'expense' => '5000'],
        'Strategy' =>          ['label' => 'Business strategy',        'code' => 'Z', 'income' => '4000', 'expense' => '5000'],
        'Data' =>              ['label' => 'Data systems',             'code' => 'D', 'income' => '4000', 'expense' => '5000'],
        'Engineering' =>       ['label' => 'Software engineering',     'code' => 'E', 'income' => '4000', 'expense' => '5000'],
        'Security' =>          ['label' => 'Cybersecurity',            'code' => 'S', 'income' => '4000', 'expense' => '5000'],
        'Infrastructure' =>    ['label' => 'Cloud infrastructure',     'code' => 'I', 'income' => '4100', 'expense' => '5200'],
        'Software' =>          ['label' => 'Software and SaaS licences', 'code' => 'W', 'income' => '4100', 'expense' => '5200'],
        'Human Capital' =>     ['label' => 'Payroll and staff costs',   'code' => 'H', 'income' => null,   'expense' => '6000'],
        'Marketing & Acquisition'=>['label' => 'Marketing campaigns',  'code' => 'M', 'income' => null,   'expense' => '6100'],
        'Travel' =>            ['label' => 'Travel and logistics',     'code' => 'T', 'income' => '4000', 'expense' => '6200'],
        'Operating Expense' => ['label' => 'Office costs and cash requests', 'code' => 'O', 'income' => null,   'expense' => '6300'],
        'Hardware' =>          ['label' => 'Purchases and purchase orders',   'code' => 'P', 'income' => '4200', 'expense' => '5000'],
        'Financial' =>         ['label' => 'Bank and payment fees',   'code' => 'F', 'income' => null,   'expense' => '6950'],
    ];

    public function mount()
    {
        $this->loadDictionary();
        $this->generateItemCode();
    }

    public function loadDictionary()
    {
        // Join with COA to pull the Income and Expense codes for the Front-End grid visibility
        $this->dictionary = DB::table('mdm_financial_dictionary as dict')
            ->leftJoin('chart_of_accounts as inc_coa', 'dict.income_account_id', '=', 'inc_coa.id')
            ->leftJoin('chart_of_accounts as exp_coa', 'dict.expense_account_id', '=', 'exp_coa.id')
            ->select(
                'dict.*', 
                'inc_coa.account_code as income_code', 
                'exp_coa.account_code as expense_code'
            )
            ->orderBy('dict.id', 'desc')
            ->get();
    }

    public function updatedCategory()
    {
        if (!$this->editMode) {
            $this->generateItemCode();
        }
    }

    public function generateItemCode()
    {
        // 1. Calculate the Universal Base Number (Starts at 1000)
        $totalRecords = DB::table('mdm_financial_dictionary')->count();
        $baseNumber = 1000 + $totalRecords + 1;

        // 2. Fetch the Category Appender (e.g., 'C' for Consulting)
        $suffix = $this->availableCategories[$this->category]['code'] ?? 'X';

        // 3. Lock the code
        $this->item_code = "#" . $baseNumber . $suffix;
    }

    public function saveCode()
    {
        // The route needs mdm.view; adding or changing a code needs mdm.create / mdm.edit
        abort_unless(Auth::user()->can(($this->editMode && $this->recordId) ? 'mdm.edit' : 'mdm.create'), 403);

        sleep(1); // UI/UX micro-delay for the loading spinner

        $this->validate([
            'item_code' => 'required|string|max:20',
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'uom' => 'nullable|string|max:50',
            'default_unit_rate_usd' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () {
                $categoryData = $this->availableCategories[$this->category];

                // Map Income ID from Chart of Accounts
                $incomeId = null;
                if ($categoryData['income']) {
                    $incomeAccount = DB::table('chart_of_accounts')->where('account_code', $categoryData['income'])->first();
                    if (!$incomeAccount) throw new Exception("We couldn't save the item because income account {$categoryData['income']} isn't in the chart of accounts. Add it in Chart of accounts, then save again.");
                    $incomeId = $incomeAccount->id;
                }

                // Map Expense ID from Chart of Accounts
                $expenseId = null;
                if ($categoryData['expense']) {
                    $expenseAccount = DB::table('chart_of_accounts')->where('account_code', $categoryData['expense'])->first();
                    if (!$expenseAccount) throw new Exception("We couldn't save the item because expense account {$categoryData['expense']} isn't in the chart of accounts. Add it in Chart of accounts, then save again.");
                    $expenseId = $expenseAccount->id;
                }

                $data = [
                    'description' => $this->description,
                    'category' => $this->category,
                    'default_quantity' => $this->uom ?? '1 Unit',
                    'default_unit_rate_usd' => $this->default_unit_rate_usd,
                    'income_account_id' => $incomeId,
                    'expense_account_id' => $expenseId,
                    'updated_at' => now(),
                ];

                if ($this->editMode && $this->recordId) {
                    DB::table('mdm_financial_dictionary')->where('id', $this->recordId)->update($data);
                } else {
                    $data['item_code'] = $this->item_code;
                    $data['is_active'] = 1;
                    $data['created_at'] = now();
                    DB::table('mdm_financial_dictionary')->insert($data);
                }
            });

            $this->showSuccessModal = true;
            $this->loadDictionary();

        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function editCode($id)
    {
        $record = DB::table('mdm_financial_dictionary')->where('id', $id)->first();
        if ($record) {
            $this->editMode = true;
            $this->recordId = $record->id;
            $this->item_code = $record->item_code;
            $this->description = $record->description;
            $this->category = $record->category;
            $this->uom = $record->default_quantity;
            $this->default_unit_rate_usd = $record->default_unit_rate_usd;
        }
    }

    public function toggleStatus($id, $currentStatus)
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);

        DB::table('mdm_financial_dictionary')->where('id', $id)->update([
            'is_active' => !$currentStatus,
            'updated_at' => now()
        ]);
        $this->loadDictionary();
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['editMode', 'recordId', 'description', 'default_unit_rate_usd']);
        $this->category = 'Consulting';
        $this->uom = '1 Phase';
        $this->generateItemCode();
    }

    public function render()
    {
        return view('livewire.hq.mdm.financial-dictionary');
    }
}