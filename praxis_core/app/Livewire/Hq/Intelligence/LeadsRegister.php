<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class LeadsRegister extends Component
{
    public $viewMode = 'grid'; // 'grid' or 'kanban'
    public $leads = [];
    
    // Slide-Out Drawer State
    public $showDrawer = false;
    public $selectedLead = null;
    
    // Diff-Viewer State
    public $showDiffModal = false;
    public $diffOld = null;
    public $diffNew = null;
    public $mergeSelections = []; 
    
    // The specific fields we want to compare
    public $diffFields = [
        'company_name' => 'Company Name',
        'executive_name' => 'Executive Name',
        'copq_industry' => 'Industry',
        'primary_tool' => 'Tech Stack',
        'bottleneck' => 'Bottleneck',
        'copq_turnover' => 'Turnover ($)',
        'headcount' => 'Headcount',
        'calculated_leakage' => 'Calculated COPQ ($)'
    ];
    
    // Manual Intake State (The Full Matrix)
    public $showIntakeModal = false;
    public $name = '', $company = '', $email = '', $currency = 'NGN';
    public $industry = '', $tool = '', $bottleneck = '';
    
    // Explicitly nullifying defaults so the user MUST provide them for accurate COPQ
    public $acv = ''; 
    public $headcount = ''; 
    public $mgmtNodes = '';
    public $turnover = ''; 
    public $manualHrs = ''; 
    public $hourlyCost = ''; 
    public $errorRate = ''; 
    
    // All MDM Collections dynamically loaded
    public $dbIndustries = [], $dbArchitectures = [], $dbBottlenecks = [], $dbCurrencies = [];
    public $stages = ['Lead Captured', 'Qualifying', 'In Consultation', 'Proposal Sent', 'Won / Lost'];

    public function mount()
    {
        $this->loadMasterData();
        $this->loadLeads();
    }

    public function loadMasterData()
    {
        $this->dbIndustries = DB::table('mdm_copq_industries')->orderBy('industry_name')->get();
        $this->dbArchitectures = DB::table('mdm_copq_architectures')->orderBy('display_name')->get();
        $this->dbBottlenecks = DB::table('mdm_copq_bottlenecks')->orderBy('bottleneck_name')->get();
        $this->dbCurrencies = DB::table('mdm_exchange_rates')->orderBy('currency_code')->get();
    }

    public function loadLeads()
    {
        // Fetch raw organizations
        $organizations = DB::table('organizations')
            ->select('organizations.*', 'owner.first_name as owner_first', 'owner.last_name as owner_last', 'owner.id as owner_id')
            ->leftJoin('users as owner', 'organizations.assigned_to', '=', 'owner.id')
            ->whereIn('organizations.status', ['Lead', 'Active Client'])
            ->get();

        // Map ONLY the latest COPQ assessment to avoid double-rendering duplicates
        $mappedLeads = $organizations->map(function ($org) {
            $latestCopq = DB::table('copq_assessments')
                ->where('organization_id', $org->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $org->org_id = $org->id;
            $org->company_name = $org->name;
            $org->is_duplicate_suspect = (bool) ($org->is_duplicate_suspect ?? 0);
            $org->executive_name = $latestCopq->executive_name ?? 'Unknown';
            $org->corporate_email = $latestCopq->corporate_email ?? 'Unknown';
            $org->calculated_leakage = $latestCopq->calculated_leakage ?? 0;
            $org->copq_currency = $latestCopq->copq_currency ?? 'NGN';
            $org->lead_tier = $latestCopq->lead_tier ?? 3;
            $org->ai_friction_analysis = $latestCopq->ai_friction_analysis ?? '';
            $org->report_token = $latestCopq->report_token ?? '';
            $org->copq_turnover = $latestCopq->copq_turnover ?? 0;
            $org->copq_industry = $latestCopq->copq_industry ?? 'Unknown';
            $org->primary_tool = $latestCopq->primary_tool ?? 'Unknown';
            $org->bottleneck = $latestCopq->bottleneck ?? 'Unknown';
            
            return $org;
        });

        // Sort by highest value leads first
        $this->leads = $mappedLeads->sortByDesc('calculated_leakage')->values()->all();
    }

    public function toggleView($mode) { $this->viewMode = $mode; }

    // --- SLIDE-OUT DRAWER ---
    public function openDrawer($orgId)
    {
        $this->selectedLead = collect($this->leads)->firstWhere('org_id', $orgId);
        $this->showDrawer = true;
    }

    public function closeDrawer()
    {
        $this->showDrawer = false;
        $this->selectedLead = null;
    }

    public function updateStage($orgId, $newStage)
    {
        DB::table('organizations')->where('id', $orgId)->update(['pipeline_stage' => $newStage, 'updated_at' => now()]);
        $this->loadLeads();
        if ($this->selectedLead && $this->selectedLead->org_id === $orgId) {
            $this->selectedLead->pipeline_stage = $newStage;
        }
    }

    public function claimLead($orgId)
    {
        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';
        DB::table('organizations')->where('id', $orgId)->update(['assigned_to' => $userId, 'updated_at' => now()]);
        $this->loadLeads();
        if ($this->selectedLead && $this->selectedLead->org_id === $orgId) { $this->openDrawer($orgId); }
    }

    // --- THE DIFF-VIEWER ENGINE ---
    public function openDiffViewer($orgId)
    {
        $records = DB::table('copq_assessments')
            ->where('organization_id', $orgId)
            ->orderBy('created_at', 'desc')
            ->limit(2)
            ->get();

        if ($records->count() < 2) {
            DB::table('organizations')->where('id', $orgId)->update(['is_duplicate_suspect' => 0]);
            $this->loadLeads();
            return;
        }

        $this->diffNew = $records[0];
        $this->diffOld = $records[1];

        foreach (array_keys($this->diffFields) as $field) {
            $this->mergeSelections[$field] = 'new';
        }

        $this->showDiffModal = true;
    }

    public function closeDiffViewer()
    {
        $this->showDiffModal = false;
        $this->diffOld = null;
        $this->diffNew = null;
    }

    public function executeMerge()
    {
        $finalData = [];
        
        foreach (array_keys($this->diffFields) as $field) {
            $finalData[$field] = $this->mergeSelections[$field] === 'old' 
                ? $this->diffOld->$field 
                : $this->diffNew->$field;
        }
        
        $finalData['updated_at'] = now();
        $targetOrgId = $this->diffNew->organization_id;

        DB::table('copq_assessments')->where('id', $this->diffNew->id)->update($finalData);
        DB::table('organizations')->where('id', $targetOrgId)->update([
            'is_duplicate_suspect' => 0,
            'updated_at' => now()
        ]);

        $this->closeDiffViewer();
        $this->loadLeads();
        $this->openDrawer($targetOrgId); 
    }

    // --- MANUAL PITCH INTAKE ENGINE ---
    public function openIntakeModal() { $this->showIntakeModal = true; }
    public function closeIntakeModal() { $this->showIntakeModal = false; }

    public function submitManualPitch()
    {
        // Strict validation requiring every single variable for the COPQ formula
        $this->validate([
            'company' => 'required|string', 
            'name' => 'required|string', 
            'email' => 'required|email',
            'industry' => 'required|string', 
            'tool' => 'required|string', 
            'bottleneck' => 'required|string',
            'turnover' => 'required|numeric|min:0', 
            'headcount' => 'required|numeric|min:1', 
            'mgmtNodes' => 'required|numeric|min:1',
            'acv' => 'required|numeric|min:0',
            'manualHrs' => 'required|numeric|min:0',
            'hourlyCost' => 'required|numeric|min:0',
            'errorRate' => 'required|numeric|min:0|max:100',
            'currency' => 'required|string'
        ]);

        // 1. Fetch Multipliers
        $archData = DB::table('mdm_copq_architectures')->where('tech_key', $this->tool)->first();
        $bnData = DB::table('mdm_copq_bottlenecks')->where('bottleneck_name', $this->bottleneck)->first();
        $indData = DB::table('mdm_copq_industries')->where('industry_name', $this->industry)->first();
        
        $archMultiplier = $archData ? $archData->error_multiplier : 1.00;
        $actualErrorRate = $this->errorRate * $archMultiplier;

        $appMult = $bnData ? $bnData->appraisal_multiplier : 1.00;
        $failMult = $bnData ? $bnData->failure_multiplier : 1.00;

        // 2. Organization Check & De-Duplication
        $cleanOrgName = trim($this->company);
        
        // Find existing organization (Case-Insensitive)
        $existingOrg = DB::table('organizations')->whereRaw('LOWER(name) = ?', [strtolower($cleanOrgName)])->first();

        if ($existingOrg) {
            $orgId = $existingOrg->id;
            // Update the existing record to reflect the manual pitch activity
            DB::table('organizations')->where('id', $orgId)->update([
                'industry' => $this->industry,
                'tech_reality' => $this->tool,
                'turnover_bracket' => $this->turnover,
                'updated_at' => now()
            ]);
        } else {
            // Create new organization if it truly doesn't exist
            $orgId = (string) Str::uuid();
            DB::table('organizations')->insert([
                'id' => $orgId, 
                'name' => $cleanOrgName, 
                'industry' => $this->industry,
                'tech_reality' => $this->tool, 
                'turnover_bracket' => $this->turnover,
                'status' => 'Lead', 
                'pipeline_stage' => 'Lead Captured', 
                'assigned_to' => Auth::id() ?? null, 
                'created_at' => now(), 
                'updated_at' => now(),
            ]);
        }

        // 3. Super-Fallback Narrative
        $industryPart = $indData ? $indData->fallback_diagnostic : "Systemic friction detected within {COMPANY_NAME}.";
        $bottleneckPart = $bnData ? $bnData->bottleneck_narrative : "Immediate architectural review required.";
        $fallbackText = $industryPart . " " . $bottleneckPart;
        $aiAnalysis = str_replace('{COMPANY_NAME}', strtoupper($this->company), $fallbackText);

        // 4. Server-Side Mathematics (Using exact user inputs)
        // Hardcoded assumption: 20% of headcount are management/approval nodes.
        $mgmtHeadcount = $this->mgmtNodes;
        
        $appraisalCost = ($mgmtHeadcount * $this->manualHrs * $this->hourlyCost * 52) * $appMult;
        $internalFailure = ($this->turnover * ($actualErrorRate / 100) * 0.60) * $failMult;
        $estimatedClients = $this->acv > 0 ? max(1, $this->turnover / $this->acv) : 0;
        $churnProb = ($actualErrorRate / 100) * 0.40;
        $externalFailure = ($estimatedClients * $churnProb) * $this->acv * 1.5;
        
        $totalCOPQ = $appraisalCost + $internalFailure + $externalFailure;

        // 5. Tier Scoring Logic
        $tier = 3; // Default SME/Junk
        if ($this->turnover >= 5000000 || $totalCOPQ >= 250000) $tier = 1; 
        elseif ($this->turnover >= 1000000 || $totalCOPQ >= 50000) $tier = 2; 

        // 6. Lock Data into the Vault
        $token = 'PRX-' . strtoupper(Str::random(24)) . '-' . date('Y');
        DB::table('copq_assessments')->insert([
            'id' => (string) Str::uuid(), 'report_token' => $token, 'organization_id' => $orgId,
            'executive_name' => $this->name, 'company_name' => $this->company, 'corporate_email' => $this->email,
            'copq_industry' => $this->industry, 'primary_tool' => $this->tool, 'bottleneck' => $this->bottleneck,
            'copq_currency' => $this->currency, 'copq_turnover' => $this->turnover, 'headcount' => $this->headcount,
            'mgmt_nodes' => $this->mgmtNodes, // <-- ADD THIS LINE HERE
            'copq_latency' => $this->manualHrs, 'copq_hourly_cost' => $this->hourlyCost,
            'copq_error_rate' => $actualErrorRate, 'avg_contract_value' => $this->acv,
            'calculated_leakage' => $totalCOPQ, 'ai_friction_analysis' => trim($aiAnalysis),
            'lead_tier' => $tier, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // 7. Notification Trigger
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c',
            'title' => 'MANUAL PITCH INJECTED', 'message' => strtoupper($this->company) . " entered into pipeline. Leakage: " . $this->currency . " " . number_format($totalCOPQ),
            'link_url' => '/hq/intelligence/leads', 'type' => 'System', 'is_read' => 0, 'created_at' => now()
        ]);

        $this->closeIntakeModal();
        $this->loadLeads();
        $this->openDrawer($orgId); 
    }

    // --- AD-HOC CONSULTATION PIPELINE ---
    public function requestConsultation($orgId)
    {
        $appointmentId = (string) Str::uuid();
        
        // 1. Create a Pending Appointment in the Lobby
        DB::table('appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $orgId,
            'title' => 'Ad-Hoc Executive Consultation',
            'start_time' => now()->addMinutes(15), // Buffers the start time slightly
            'end_time' => now()->addMinutes(75),
            'status' => 'Requested',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 2. Deep-Link redirect natively to the Consultation Hub
        return $this->redirect('/hq/intelligence/consultation', navigate: true);
    }

    // --- EXPORT PROTOCOL (L4 / L5 ONLY) ---
    public function exportCsv()
    {
        $filename = "JBS_Pipeline_Extract_" . now()->format('Ymd_His') . ".csv";
        $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
        $columns = ['Company', 'Contact', 'Email', 'Stage', 'Owner', 'COPQ', 'Currency', 'Tier', 'Date Acquired'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($this->leads as $lead) {
                fputcsv($file, [
                    $lead->company_name, $lead->executive_name, $lead->corporate_email, $lead->pipeline_stage, 
                    $lead->owner_first ? $lead->owner_first . ' ' . $lead->owner_last : 'Unclaimed',
                    $lead->calculated_leakage, $lead->copq_currency, $lead->lead_tier, $lead->created_at
                ]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        return view('livewire.hq.intelligence.leads-register');
    }
}