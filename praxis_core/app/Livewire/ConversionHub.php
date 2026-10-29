<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\CopqReportMail;

class ConversionHub extends Component
{
    public $dbIndustries = [];
    public $dbArchitectures = [];
    public $dbCurrencies = [];
    public $dbBottlenecks = [];

    public function mount()
    {
        $this->dbIndustries = DB::table('mdm_copq_industries')->orderBy('industry_name', 'asc')->get();
        $this->dbArchitectures = DB::table('mdm_copq_architectures')->orderBy('display_name', 'asc')->get();
        $this->dbCurrencies = DB::table('mdm_exchange_rates')->orderBy('currency_code', 'asc')->get();
        $this->dbBottlenecks = DB::table('mdm_copq_bottlenecks')->orderBy('bottleneck_name', 'asc')->get();
    }

    public function render()
    {
        return view('livewire.conversion-hub')->layout('layouts.app');
    }

    public function generateCopqReport($data)
    {
        $name        = trim($data['name'] ?? 'Executive');
        $company     = trim($data['company'] ?? 'Enterprise Client');
        $email       = strtolower(trim($data['email'] ?? ''));
        $industry    = $data['industry'] ?? 'General Enterprise (Other)';
        $tool        = strtolower($data['tool'] ?? 'excel');
        $bottleneck  = $data['bottleneck'] ?? 'Operations';
        $acv         = floatval($data['acv'] ?? 0);
        $headcount   = intval($data['headcount'] ?? 1);
        $mgmtNodes   = intval($data['mgmtNodes'] ?? max(1, floor($headcount * 0.20)));
        $turnover    = floatval($data['turnover'] ?? 0);
        $manualHrs   = floatval($data['manualHrs'] ?? 5);
        $hourlyCost  = floatval($data['hourlyCost'] ?? 50);
        $baseError   = floatval($data['errorRate'] ?? 3);
        $currency    = $data['currency'] ?? 'USD';

        // 1. Fetch Multipliers (Architecture & Bottleneck)
        $archData = DB::table('mdm_copq_architectures')->where('tech_key', $tool)->first();
        $bnData = DB::table('mdm_copq_bottlenecks')->where('bottleneck_name', $bottleneck)->first();
        
        $archMultiplier = $archData ? $archData->error_multiplier : 1.00;
        $actualErrorRate = $baseError * $archMultiplier;
        
        $appMult = $bnData ? $bnData->appraisal_multiplier : 1.00;
        $failMult = $bnData ? $bnData->failure_multiplier : 1.00;

        // 2. Relational CRM / ERP Injection & De-Duplication
        $cleanOrgName = trim($company);
        $emailStr = strtolower(trim($email));

        // 2A. Find or Create Organization (Case-Insensitive)
        $existingOrg = DB::table('organizations')->whereRaw('LOWER(name) = ?', [strtolower($cleanOrgName)])->first();

        if ($existingOrg) {
            $orgId = $existingOrg->id;
            // Flag the organization as having a duplicate submission / new diagnostic activity
            DB::table('organizations')->where('id', $orgId)->update([
                'is_duplicate_suspect' => 1,
                'updated_at' => now()
            ]);
        } else {
            $orgId = (string) Str::uuid();
            DB::table('organizations')->insert([
                'id' => $orgId, 'name' => $cleanOrgName, 'industry' => $industry,
                'tech_reality' => $tool, 'turnover_bracket' => $turnover,
                'status' => 'Lead', 'pipeline_stage' => 'Lead Captured',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 2B. Find or Create User
        $existingUser = DB::table('users')->where('email', $emailStr)->first();
        
        if (!$existingUser) {
            $userId = (string) Str::uuid();
            $nameParts = explode(' ', trim($name), 2);

            DB::table('users')->insert([
                'id' => $userId, 'organization_id' => $orgId, // Attaches to existing or new Org
                'first_name' => $nameParts[0], 'last_name' => $nameParts[1] ?? 'Unknown',
                'email' => $emailStr, 'password' => Hash::make(Str::random(16)), 
                'status' => 'Active', 'lifecycle_stage' => 'Lead',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 3. AI / FALLBACK ENGINE (The Digital Strike Team)
        // First, fetch the comprehensive industry constraints we injected into the database
        $indData = DB::table('mdm_copq_industries')->where('industry_name', $industry)->first();
        
        // Define the variables explicitly to avoid null errors
        $archetype = $indData->sector_archetype ?? 'COMMERCIAL';
        $archMandate = $indData->architectural_mandate ?? 'CLOUD-NATIVE MICROSERVICES';
        
        $fallbackTemplate = $indData ? $indData->fallback_diagnostic : "Systemic friction detected within {COMPANY_NAME}'s current architectural parameters. Immediate architectural review required to neutralize operational bottlenecks and secure capital workflows.";
        $aiAnalysis = str_replace('{COMPANY_NAME}', strtoupper($company), $fallbackTemplate);

        // The Elite Prompt: Forcing the AI to obey strict structural & political realities
        $systemPrompt = "You are an Elite Enterprise Architect at JBS Praxis. Write a clinical, ruthless 3-sentence diagnostic analysis (max 600 chars) for {$company}. 
        CONTEXT: Their Sector Archetype is [{$archetype}]. They are running on [{$tool}] with a severe bottleneck in [{$bottleneck}]. 
        RULE 1: Identify the systemic friction causing capital/resource leakage. 
        RULE 2: You MUST propose our mandatory cure: [{$archMandate}]. 
        RULE 3: Do not use generic software jargon; use terminology strictly native to a {$archetype} sector (e.g., if SOVEREIGN use 'public funds/civic deployment', if IMPACT use 'donor compliance/beneficiary reach', if ASSET_HEAVY use 'NPT/SCADA/supply chain spoilage', if COMMERCIAL use 'margins/client churn'). 
        Be highly authoritative and uncompromising.";
        
        try {
            $apiKey = config('services.gemini.api_key');
            $response = Http::timeout(3)->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["role" => "user", "parts" => [["text" => $systemPrompt]]]],
                "generationConfig" => ["temperature" => 0.2, "maxOutputTokens" => 150]
            ]);
            
            if ($response->successful()) {
                $aiAnalysis = $response->json('candidates.0.content.parts.0.text');
            }
        } catch (\Exception $e) {
            // Silently fail. $aiAnalysis already contains the highly personalized database fallback!
        }

        // 4. Server-Side Mathematics (The Archetype Calculus)
        $mgmtNodes = intval($data['mgmtNodes'] ?? max(1, floor($headcount * 0.20)));
        $mgmtHeadcount = $mgmtNodes; 

        // Universal Internal Costs (Time & Process Friction)
        $appraisalCost = ($mgmtHeadcount * $manualHrs * $hourlyCost * 52) * $appMult;
        $internalFailure = ($turnover * ($actualErrorRate / 100) * 0.60) * $failMult;

        // Dynamic External Costs (The end of the universal "Churn Tax")
        if ($archetype === 'SOVEREIGN') {
            // Government: Macroeconomic drag & fund misallocation risk (No ACV or Clients)
            $externalFailure = ($turnover * ($actualErrorRate / 100) * 0.85); 
        } elseif ($archetype === 'IMPACT') {
            // NGO: Beneficiary impact loss & Donor compliance clawbacks
            $externalFailure = ($turnover * ($actualErrorRate / 100) * 0.80);
        } elseif ($archetype === 'ASSET_HEAVY') {
            // O&G/Manufacturing: Non-Productive Time (NPT) & Severe Supply Chain Penalties
            $externalFailure = ($turnover * ($actualErrorRate / 100) * 0.90); 
        } else {
            // COMMERCIAL: The standard B2B/SaaS Churn Logic
            $estimatedClients = $acv > 0 ? max(1, $turnover / $acv) : 0;
            $churnProb = ($actualErrorRate / 100) * 0.40;
            $externalFailure = ($estimatedClients * $churnProb) * $acv * 1.5;
        }

        // Apply global failure multiplier to the sector-specific result
        $externalFailure = $externalFailure * $failMult;
        $totalCOPQ = $appraisalCost + $internalFailure + $externalFailure;

        // 5. Tier Scoring Logic
        $tier = 3; // Default SME/Junk
        if ($turnover >= 5000000 || $totalCOPQ >= 250000) $tier = 1; // Enterprise
        elseif ($turnover >= 1000000 || $totalCOPQ >= 50000) $tier = 2; // Mid-Market

        // 6. Lock Data into the COPQ Vault
        $token = strtoupper('PRX-' . Str::random(6) . '-' . date('Y'));
        DB::table('copq_assessments')->insert([
            'id' => (string) Str::uuid(), 'report_token' => $token, 'organization_id' => $orgId,
            'executive_name' => $name, 'company_name' => $company, 'corporate_email' => $email,
            'copq_industry' => $industry, 'primary_tool' => $tool, 'bottleneck' => $bottleneck,
            'copq_currency' => $currency, 'copq_turnover' => $turnover, 'headcount' => $headcount,
            'mgmt_nodes' => $mgmtNodes,
            'copq_latency' => $manualHrs, 'copq_hourly_cost' => $hourlyCost,
            'copq_error_rate' => $actualErrorRate, 'avg_contract_value' => $acv,
            'calculated_leakage' => $totalCOPQ, 'ai_friction_analysis' => trim($aiAnalysis),
            'lead_tier' => $tier, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // 7. SYSTEM TELEMETRY (Notify the Admins)
        $admin = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')
                   ->where('roles.clearance_level', 5)->select('users.id')->first();
        $adminId = $admin ? $admin->id : ($existingUser ? $existingUser->id : (string) Str::uuid()); // Fallback

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $adminId,
            'title' => $tier == 1 ? '🚨 TIER 1 LEAD DETECTED' : 'NEW PIPELINE ACQUISITION',
            'message' => strtoupper($company) . " generated a COPQ diagnostic. Systemic leakage estimated at " . $currency . " " . number_format($totalCOPQ) . ".",
            'link_url' => '/hq/intelligence/leads',
            'type' => 'System',
            'is_read' => 0,
            'created_at' => now()
        ]);

        return $token;
    }
    
    public function dispatchReportToEmail($token)
    {
        $assessment = DB::table('copq_assessments')->where('report_token', $token)->first();
        
        if ($assessment && $assessment->corporate_email) {
            try {
                Mail::to($assessment->corporate_email)->send(new CopqReportMail($assessment));
                return true;
            } catch (\Exception $e) {
                // Silently fail or log error so the frontend doesn't crash
                // \Log::error('Mail dispatch failed: ' . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}