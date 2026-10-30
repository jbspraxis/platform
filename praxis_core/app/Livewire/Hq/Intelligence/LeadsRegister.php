<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\Site\BookingService;

#[Layout('components.layouts.hq')]
class LeadsRegister extends Component
{
    public $viewMode = 'grid'; // 'grid' or 'kanban'
    public $leads = [];
    
    // Slide-Out Drawer State
    public $showDrawer = false;
    public $selectedLead = null;
    
    // Diff-Viewer State: the two records being merged are set on the server only
    public $showDiffModal = false;
    #[Locked]
    public $diffOld = null;
    #[Locked]
    public $diffNew = null;
    public $mergeSelections = []; 
    
    // The only copq_assessments columns a merge may write, with their labels (sentence case, docs/BRAND.md section 12)
    private const MERGE_FIELDS = [
        'company_name' => 'Company name',
        'executive_name' => 'Contact name',
        'copq_industry' => 'Industry',
        'primary_tool' => 'Systems in use',
        'bottleneck' => 'Main bottleneck',
        'copq_turnover' => 'Annual turnover',
        'headcount' => 'Headcount',
        'calculated_leakage' => 'Cost of poor quality (a year)',
    ];

    // Shown in the merge dialog; locked so the browser cannot name other columns for executeMerge to write
    #[Locked]
    public $diffFields = self::MERGE_FIELDS;
    
    // Manual intake state
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
    
    // Book a free audit from HQ: the date and time are entered in Lagos time (WAT), like website bookings
    public $showBookingModal = false;
    #[Locked]
    public $bookingOrgId = null;
    #[Locked]
    public $bookingCompany = '';
    public $bookingDate = '', $bookingTime = '';

    // Field names in validation messages (sentence case, docs/BRAND.md section 12)
    protected $validationAttributes = [
        'company' => 'organisation name', 'name' => 'contact name', 'email' => 'work email',
        'industry' => 'industry', 'tool' => 'system in use', 'bottleneck' => 'main bottleneck',
        'turnover' => 'annual turnover', 'headcount' => 'total headcount', 'mgmtNodes' => 'number of managers who approve work',
        'acv' => 'average contract value', 'manualHrs' => 'manual hours a week', 'hourlyCost' => 'hourly cost of a manager',
        'errorRate' => 'error rate', 'currency' => 'currency', 'bookingDate' => 'date', 'bookingTime' => 'time',
    ];

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
            $org->executive_name = $latestCopq->executive_name ?? 'No contact recorded';
            $org->corporate_email = $latestCopq->corporate_email ?? '';
            $org->calculated_leakage = $latestCopq->calculated_leakage ?? 0;
            $org->copq_currency = $latestCopq->copq_currency ?? 'NGN';
            $org->lead_tier = $latestCopq->lead_tier ?? 3;
            $org->ai_friction_analysis = $latestCopq->ai_friction_analysis ?? '';
            $org->report_token = $latestCopq->report_token ?? '';
            $org->copq_turnover = $latestCopq->copq_turnover ?? 0;
            $org->copq_industry = $latestCopq->copq_industry ?? 'Industry not given';
            $org->primary_tool = $latestCopq->primary_tool ?? 'Not given';
            $org->bottleneck = $latestCopq->bottleneck ?? 'Not given';
            
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
        abort_unless(Auth::user()->can('crm.edit'), 403);

        DB::table('organizations')->where('id', $orgId)->update(['pipeline_stage' => $newStage, 'updated_at' => now()]);
        $this->loadLeads();
        if ($this->selectedLead && $this->selectedLead->org_id === $orgId) {
            $this->selectedLead->pipeline_stage = $newStage;
        }
    }

    public function claimLead($orgId)
    {
        abort_unless(Auth::user()->can('crm.edit'), 403);

        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';
        DB::table('organizations')->where('id', $orgId)->update(['assigned_to' => $userId, 'updated_at' => now()]);
        $this->loadLeads();
        if ($this->selectedLead && $this->selectedLead->org_id === $orgId) { $this->openDrawer($orgId); }
    }

    // --- THE DIFF-VIEWER ENGINE ---
    public function openDiffViewer($orgId)
    {
        abort_unless(Auth::user()->can('crm.edit'), 403);

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

        foreach (array_keys(self::MERGE_FIELDS) as $field) {
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
        abort_unless(Auth::user()->can('crm.edit'), 403);

        if (!$this->diffOld || !$this->diffNew) return;

        $finalData = [];
        
        // Only the whitelisted columns, whatever the component state says
        foreach (array_keys(self::MERGE_FIELDS) as $field) {
            $finalData[$field] = ($this->mergeSelections[$field] ?? 'new') === 'old' 
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
        abort_unless(Auth::user()->can('crm.create'), 403);

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
            // Create new organization if it truly doesn’t exist
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

        // 3. Fallback summary (plain wording, docs/BRAND.md section 4)
        $industryPart = $indData ? $indData->fallback_diagnostic : "We found signs of avoidable cost in how {COMPANY_NAME} runs.";
        $bottleneckPart = $bnData ? $bnData->bottleneck_narrative : "We recommend a review of the main bottleneck.";
        $fallbackText = $industryPart . " " . $bottleneckPart;
        $aiAnalysis = str_replace('{COMPANY_NAME}', $cleanOrgName, $fallbackText);

        // 4. Server-Side Mathematics (Using exact user inputs)
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

        // 6. Save the assessment
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
            'title' => 'Lead added', 'message' => $cleanOrgName . " was added to the pipeline. Estimated cost of poor quality: " . $this->currency . " " . number_format($totalCOPQ) . " a year.",
            'link_url' => route('hq.crm.leads', [], false), 'type' => 'System', 'is_read' => 0, 'created_at' => now()
        ]);

        $this->closeIntakeModal();
        $this->loadLeads();
        $this->openDrawer($orgId); 
    }

    // --- AD-HOC CONSULTATION PIPELINE ---
    // Opens the booking dialog, suggesting 15 minutes from now on the next 5 minutes (Lagos time)
    public function requestConsultation($orgId)
    {
        abort_unless(Auth::user()->can('crm.create'), 403);

        $org = DB::table('organizations')->where('id', $orgId)->first();
        if (! $org) {
            return;
        }

        $suggested = Carbon::now(BookingService::TIMEZONE)->addMinutes(15)->second(0);
        $suggested->addMinutes((5 - $suggested->minute % 5) % 5);

        $this->bookingOrgId = $org->id;
        $this->bookingCompany = $org->name;
        $this->bookingDate = $suggested->format('Y-m-d');
        $this->bookingTime = $suggested->format('H:i');
        $this->resetErrorBag(['bookingDate', 'bookingTime']);
        $this->showBookingModal = true;
    }

    public function closeBookingModal()
    {
        $this->showBookingModal = false;
        $this->reset(['bookingOrgId', 'bookingCompany', 'bookingDate', 'bookingTime']);
    }

    /**
     * Books the free audit as a request in the Consultation hub, where it is approved with the meeting link.
     * The time is entered and stored as Lagos wall-clock time (WAT), the same way as website bookings
     * (BookingService; the app itself runs in UTC), so the hub, My workspace, the website's free times and
     * the client's emails all show the same time.
     */
    public function bookConsultation()
    {
        abort_unless(Auth::user()->can('crm.create'), 403);
        if (! $this->bookingOrgId) {
            return;
        }

        $this->validate([
            'bookingDate' => 'required|date_format:Y-m-d',
            'bookingTime' => 'required|date_format:H:i',
        ]);

        $start = Carbon::createFromFormat('Y-m-d H:i', $this->bookingDate . ' ' . $this->bookingTime, BookingService::TIMEZONE)->second(0);
        if ($start->lt(Carbon::now(BookingService::TIMEZONE)->subMinutes(5))) {
            $this->addError('bookingTime', 'That time has passed. Choose a time that is still ahead (Lagos time).');
            return;
        }

        $taken = DB::table('appointments')->where('start_time', $start->format('Y-m-d H:i:s'))
            ->whereIn('status', ['Requested', 'Scheduled'])->exists();
        if ($taken) {
            $this->addError('bookingTime', 'Another free audit is booked at that time. Choose another time.');
            return;
        }

        DB::table('appointments')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->bookingOrgId,
            'title' => 'Free audit (booked from HQ)',
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $start->copy()->addMinutes(60)->format('Y-m-d H:i:s'),
            'status' => 'Requested',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        session()->flash('success', "Free audit booked for {$this->bookingCompany} on {$start->format('D j M, H:i')} WAT. Approve it with the meeting link to email the client.");

        // Deep-link to the Consultation hub
        return $this->redirect(route('hq.crm.consult'), navigate: true);
    }

    // --- CSV export (crm.export) ---
    public function exportCsv()
    {
        abort_unless(Auth::user()->can('crm.export'), 403);

        $filename = "JBS_Praxis_leads_" . now()->format('Ymd_His') . ".csv";
        $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
        $columns = ['Company', 'Contact', 'Email', 'Stage', 'Owner', 'Cost of poor quality', 'Currency', 'Tier', 'Date added'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($this->leads as $lead) {
                fputcsv($file, [
                    $lead->company_name, $lead->executive_name, $lead->corporate_email, $lead->pipeline_stage, 
                    $lead->owner_first ? $lead->owner_first . ' ' . $lead->owner_last : 'Not assigned',
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