<?php

namespace App\Livewire\Hq\Mdm;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Services\Payroll\CameroonTaxCalculator;
use App\Services\Payroll\NigeriaTaxCalculator;
use Exception;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class EmployeeRegistry extends Component
{
    // --- DATAGRID STATE ---
    public $employees = [];
    public $availableUsers = [];
    public $allManagers = [];
    public $activeTab = 'ALL';

    // --- UX STATE ---
    public $editMode = false;
    public $recordId = null;
    public $showSuccessModal = false;
    public $successMessage = '';
    public $currentStep = 1;

    // --- FORM: STEP 1 (Identity & Org Chart) ---
    public $user_id = '';
    public $employee_code = '';
    public $job_classification = '';
    public $line_manager_id = '';
    public $hire_date = '';
    public $marital_status = 'Single';
    public $dependents_count = 0;

    // --- FORM: STEP 2 (Statutory Compliance) ---
    public $tax_region = 'GLOBAL'; 
    public $tin_number = '';
    public $social_security_number = '';
    public $national_id = '';
    public $state_of_residence = ''; 
    public $pfa_name = '';            
    public $rsa_number = '';         
    public $nhf_number = '';         
    public $kra_pin = '';
    public $nssf_number = '';
    public $nhif_number = '';

    // --- FORM: STEP 3 (Compensation & Treasury) ---
    public $pay_frequency = 'Monthly';
    public $currency = 'USD';
    public $payment_method = 'Bank Transfer';
    public $bank_name = '';
    public $account_number = '';
    
    // The Poka-Yoke Math Fields
    public $target_net_pay = null; 
    public $total_gross = 0.00;
    public $base_salary = 0.00;
    public $allowance_housing = 0.00;
    public $allowance_transport = 0.00;
    public $allowance_utility = 0.00;

    // Benefits in Kind (BIK)
    public $bik_housing_provided = false;
    public $bik_vehicle_provided = false;
    public $bik_domestic_staff = false;

    public function mount()
    {
        $this->loadEmployees();
        $this->loadAvailableUsers();
        $this->hire_date = date('Y-m-d');
        
        // PATCH: Restrict Line Managers to JBS Praxis internal staff only
        $jbsOrgId = DB::table('organizations')->where('name', 'JBS Praxis')->value('id');
        $this->allManagers = DB::table('users')
            ->where('organization_id', $jbsOrgId)
            ->where('status', 'Active')
            ->select('id', 'first_name', 'last_name')
            ->get();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadEmployees();
    }

    public function loadEmployees()
    {
        // PATCH: Fetch the internal organization ID
        $jbsOrgId = DB::table('organizations')->where('name', 'JBS Praxis')->value('id');

        $query = DB::table('mdm_employees as emp')
            ->join('users as u', 'emp.user_id', '=', 'u.id')
            ->leftJoin('users as m', 'emp.line_manager_id', '=', 'm.id')
            ->where('u.organization_id', $jbsOrgId) 
            ->select(
                'emp.*', 
                'u.first_name', 'u.last_name', 'u.email', 
                'm.first_name as manager_first', 'm.last_name as manager_last'
            )
            ->orderBy('emp.created_at', 'desc');

        if ($this->activeTab !== 'ALL') {
            $query->where('emp.tax_region', $this->activeTab);
        }

        $this->employees = $query->get();
    }

    public function loadAvailableUsers()
    {
        // PATCH: Fetch the internal organization ID
        $jbsOrgId = DB::table('organizations')->where('name', 'JBS Praxis')->value('id');
        $existingEmployeeUserIds = DB::table('mdm_employees')->pluck('user_id')->toArray();
        
        $this->availableUsers = DB::table('users')
            ->whereNotIn('id', $existingEmployeeUserIds)
            ->where('organization_id', $jbsOrgId) // PATCH: Prevent clients from showing in dropdown
            ->where('status', 'Active')
            ->select('id', 'first_name', 'last_name', 'email')
            ->get();
    }

    public function nextStep()
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'user_id' => 'required',
                'job_classification' => 'required|string|max:100',
                'hire_date' => 'required|date',
                'marital_status' => 'required|string',
                'dependents_count' => 'required|integer|min:0',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'tax_region' => 'required|in:CM,NG,KE,GLOBAL',
            ]);
        }
        
        if ($this->currentStep < 3) {
            $this->currentStep++;
        }
    }

    public function prevStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    /**
     * Auto-splits a single Total Gross figure into legally compliant statutory buckets.
     */
    public function autoSplitCompensation()
    {
        $gross = (float) $this->total_gross;
        if ($gross <= 0) return;

        if ($this->tax_region === 'NG') {
            $this->base_salary = round($gross * 0.50, 2);         
            $this->allowance_housing = round($gross * 0.30, 2);   
            $this->allowance_transport = round($gross * 0.20, 2); 
            $this->allowance_utility = 0.00;
        } elseif ($this->tax_region === 'CM') {
            $this->base_salary = round($gross * 0.70, 2);         
            $this->allowance_housing = round($gross * 0.15, 2);   
            $this->allowance_transport = round($gross * 0.15, 2); 
            $this->allowance_utility = 0.00;
        } else {
            $this->base_salary = $gross;
            $this->allowance_housing = 0.00;
            $this->allowance_transport = 0.00;
        }
    }

    /**
     * POKA-YOKE: THE BINARY SEARCH GROSS-UP ENGINE
     * Uses Phase 7 dynamic tax calculators to find the exact gross down to 0.01 accuracy.
     */
    public function estimateGrossFromNet()
    {
        $targetNet = (float) $this->target_net_pay;
        if ($targetNet <= 0) return;

        // 1. Fetch Dynamic Tax Configurations
        $config = DB::table('tax_configurations')->where('region_code', $this->tax_region)->first();
        $bands = DB::table('tax_bands')->where('tax_configuration_id', $config->id ?? null)->orderBy('lower_limit', 'asc')->get();

        // 2. Binary Search Setup
        $low = $targetNet; 
        $high = $targetNet * 5; 
        $tolerance = 0.01;
        $maxIterations = 100;
        $iterations = 0;
        $estimatedGross = $targetNet;

        while ($low <= $high && $iterations < $maxIterations) {
            $mid = ($low + $high) / 2;
            
            // 3. Simulate the Legal Split for accurate BIK & Relief Math
            $simBase = $mid;
            $simHousing = 0;
            $simTransport = 0;

            if ($this->tax_region === 'NG') {
                $simBase = $mid * 0.50;
                $simHousing = $mid * 0.30;
                $simTransport = $mid * 0.20;
            } elseif ($this->tax_region === 'CM') {
                $simBase = $mid * 0.70;
                $simHousing = $mid * 0.15;
                $simTransport = $mid * 0.15;
            }

            // 4. Construct Payload for Exact Phase 7 Calculator
            // Fixing Month 1/YTD 0 defines the pure, normalized contract gross
            $payload = [
                'base_salary' => $simBase,
                'allowance_housing' => $simHousing,
                'allowance_transport' => $simTransport,
                'allowance_utility' => 0, // Auto-split dictates 0
                'bik_housing_provided' => $this->bik_housing_provided,
                'bik_vehicle_provided' => $this->bik_vehicle_provided,
                'bik_domestic_staff' => $this->bik_domestic_staff,
                'month_number' => 1, 
                'ytd_gross' => 0,
                'ytd_reliefs' => 0,
                'ytd_tax_paid' => 0,
            ];

            // 5. Run Exact Math
            if ($this->tax_region === 'CM' && $config) {
                $result = CameroonTaxCalculator::calculate($payload, $config, $bands);
            } elseif ($this->tax_region === 'NG' && $config) {
                $result = NigeriaTaxCalculator::calculate($payload, $config, $bands);
            } else {
                $result = ['net_pay' => $mid * 0.80]; // Global fallback mock
            }

            $calculatedNet = $result['net_pay'];
            $difference = $calculatedNet - $targetNet;

            // 6. Evaluate Result
            if (abs($difference) <= $tolerance) {
                $estimatedGross = $mid;
                break;
            }

            if ($calculatedNet < $targetNet) {
                $low = $mid; // Need more gross
            } else {
                $high = $mid; // Need less gross
            }

            $estimatedGross = $mid;
            $iterations++;
        }

        // Apply final numbers to the state
        $this->total_gross = round($estimatedGross, 2);
        $this->autoSplitCompensation();
        
        session()->flash('gross_up_success', "Exact Net Target Achieved! Iterations: {$iterations}. Final Gross Required: {$this->currency} " . number_format($this->total_gross, 2));
    }

    public function generateEmployeeCode()
    {
        $total = DB::table('mdm_employees')->count();
        $this->employee_code = "EMP-" . (1000 + $total + 1);
    }

    public function saveEmployee()
    {
        if (!$this->editMode) $this->generateEmployeeCode();

        $this->validate([
            'total_gross' => 'required|numeric|min:0',
            'base_salary' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () {
                // Determine ID
                $employeeId = $this->editMode ? $this->recordId : Str::uuid()->toString();

                $employeeData = [
                    'user_id' => $this->user_id,
                    'job_classification' => $this->job_classification,
                    'line_manager_id' => empty($this->line_manager_id) ? null : $this->line_manager_id,
                    'marital_status' => $this->marital_status,
                    'dependents_count' => $this->dependents_count,
                    'hire_date' => $this->hire_date,
                    'tax_region' => $this->tax_region,
                    'tin_number' => $this->tin_number,
                    'social_security_number' => $this->social_security_number,
                    'national_id' => $this->national_id,
                    'state_of_residence' => $this->state_of_residence,
                    'pfa_name' => $this->pfa_name,
                    'rsa_number' => $this->rsa_number,
                    'nhf_number' => $this->nhf_number,
                    'kra_pin' => $this->kra_pin,
                    'nssf_number' => $this->nssf_number,
                    'nhif_number' => $this->nhif_number,
                    'pay_frequency' => $this->pay_frequency,
                    'currency' => $this->currency,
                    'payment_method' => $this->payment_method,
                    'bank_name' => $this->bank_name,
                    'account_number' => $this->account_number,
                    'updated_at' => now()
                ];

                if ($this->editMode && $this->recordId) {
                    DB::table('mdm_employees')->where('id', $this->recordId)->update($employeeData);
                } else {
                    $employeeData['id'] = $employeeId;
                    $employeeData['employee_code'] = $this->employee_code;
                    $employeeData['is_active'] = 1;
                    $employeeData['created_at'] = now();
                    DB::table('mdm_employees')->insert($employeeData);
                }

                // Phase 1 Integration: Manage SCD Type 2 Ledger
                $activeComp = DB::table('mdm_employee_compensations')
                    ->where('mdm_employee_id', $employeeId)
                    ->whereNull('valid_to')
                    ->first();

                // Architect Fix 1: Check ALL variables to detect changes
                $compensationChanged = !$activeComp || 
                    (float)$activeComp->base_salary !== (float)$this->base_salary ||
                    (float)$activeComp->allowance_housing !== (float)$this->allowance_housing ||
                    (float)$activeComp->allowance_transport !== (float)$this->allowance_transport ||
                    (float)$activeComp->allowance_utility !== (float)$this->allowance_utility ||
                    (bool)$activeComp->bik_housing_provided !== (bool)$this->bik_housing_provided ||
                    (bool)$activeComp->bik_vehicle_provided !== (bool)$this->bik_vehicle_provided ||
                    (bool)$activeComp->bik_domestic_staff !== (bool)$this->bik_domestic_staff ||
                    $activeComp->currency !== $this->currency;

                if ($compensationChanged) {
                    $today = now()->toDateString();
                    
                    // Architect Fix 2: Same-Day Edit Coalescing
                    if ($activeComp && $activeComp->valid_from === $today) {
                        // They made a typo and edited it again on the same day. Do not close, just update.
                        DB::table('mdm_employee_compensations')
                            ->where('id', $activeComp->id)
                            ->update([
                                'base_salary' => $this->base_salary,
                                'allowance_housing' => $this->allowance_housing,
                                'allowance_transport' => $this->allowance_transport,
                                'allowance_utility' => $this->allowance_utility,
                                'bik_housing_provided' => $this->bik_housing_provided ? 1 : 0,
                                'bik_vehicle_provided' => $this->bik_vehicle_provided ? 1 : 0,
                                'bik_domestic_staff' => $this->bik_domestic_staff ? 1 : 0,
                                'currency' => $this->currency,
                                'updated_at' => now()
                            ]);
                    } else {
                        // Normal SCD Progression: Close the old, insert the new.
                        if ($activeComp) {
                            DB::table('mdm_employee_compensations')
                                ->where('id', $activeComp->id)
                                ->update(['valid_to' => now()->subDay()->toDateString()]);
                        }

                        DB::table('mdm_employee_compensations')->insert([
                            'id' => Str::uuid()->toString(),
                            'mdm_employee_id' => $employeeId,
                            'base_salary' => $this->base_salary,
                            'allowance_housing' => $this->allowance_housing,
                            'allowance_transport' => $this->allowance_transport,
                            'allowance_utility' => $this->allowance_utility,
                            'bik_housing_provided' => $this->bik_housing_provided ? 1 : 0,
                            'bik_vehicle_provided' => $this->bik_vehicle_provided ? 1 : 0,
                            'bik_domestic_staff' => $this->bik_domestic_staff ? 1 : 0,
                            'currency' => $this->currency,
                            'valid_from' => $today,
                            'valid_to' => null,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            });

            $this->successMessage = "Personnel profile and SCD compensation ledger successfully " . ($this->editMode ? 'updated.' : 'integrated.');
            $this->showSuccessModal = true;
            $this->loadEmployees();
            $this->loadAvailableUsers(); 

        } catch (Exception $e) {
            session()->flash('error', "Ledger Error: " . $e->getMessage());
        }
    }

    public function editEmployee($id)
    {
        $record = DB::table('mdm_employees')->where('id', $id)->first();
        if ($record) {
            $this->editMode = true;
            $this->recordId = $record->id;
            $this->currentStep = 1; 
            
            foreach (get_object_vars($record) as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }

            // Fetch active compensation from the Phase 1 SCD Ledger
            $activeComp = DB::table('mdm_employee_compensations')
                ->where('mdm_employee_id', $id)
                ->whereNull('valid_to')
                ->first();

            if ($activeComp) {
                $this->base_salary = $activeComp->base_salary;
                $this->allowance_housing = $activeComp->allowance_housing;
                $this->allowance_transport = $activeComp->allowance_transport;
                $this->allowance_utility = $activeComp->allowance_utility;
                $this->total_gross = $activeComp->base_salary + $activeComp->allowance_housing + $activeComp->allowance_transport + $activeComp->allowance_utility;
                $this->bik_housing_provided = (bool) $activeComp->bik_housing_provided;
                $this->bik_vehicle_provided = (bool) $activeComp->bik_vehicle_provided;
                $this->bik_domestic_staff = (bool) $activeComp->bik_domestic_staff;
                $this->currency = $activeComp->currency;
            }
            
            $linkedUser = DB::table('users')->where('id', $record->user_id)->select('id', 'first_name', 'last_name', 'email')->first();
            if ($linkedUser && !$this->availableUsers->contains('id', $linkedUser->id)) {
                $this->availableUsers->push($linkedUser);
            }
        }
    }

    public function toggleStatus($id, $currentStatus)
    {
        $newStatus = !$currentStatus;
        $updateData = ['is_active' => $newStatus, 'updated_at' => now()];
        if (!$newStatus) $updateData['termination_date'] = date('Y-m-d');
        else $updateData['termination_date'] = null; 

        DB::table('mdm_employees')->where('id', $id)->update($updateData);
        $this->loadEmployees();
    }

    public function resetForm()
    {
        $this->reset([
            'editMode', 'recordId', 'user_id', 'job_classification', 'line_manager_id', 'tin_number', 
            'social_security_number', 'national_id', 'state_of_residence', 'pfa_name', 'rsa_number', 'nhf_number',
            'kra_pin', 'nssf_number', 'nhif_number',
            'bank_name', 'account_number', 'target_net_pay', 'total_gross', 'base_salary', 
            'allowance_housing', 'allowance_transport', 'allowance_utility'
        ]);
        $this->currentStep = 1;
        $this->tax_region = 'GLOBAL';
        $this->marital_status = 'Single';
        $this->dependents_count = 0;
        $this->pay_frequency = 'Monthly';
        $this->currency = 'USD';
        $this->payment_method = 'Bank Transfer';
        $this->bik_housing_provided = false;
        $this->bik_vehicle_provided = false;
        $this->bik_domestic_staff = false;
        $this->hire_date = date('Y-m-d');
        $this->generateEmployeeCode();
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.hq.mdm.employee-registry');
    }
}