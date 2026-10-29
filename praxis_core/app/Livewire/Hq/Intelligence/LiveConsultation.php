<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\MeetingApprovedMail;
// use App\Mail\CopqReportMail; // Commented out for now until logic is finalized

#[Layout('components.layouts.hq')]
class LiveConsultation extends Component
{
    // State Management
    public $activeView = 'lobby'; // 'lobby' or 'terminal'
    public $privacyMode = false;
    
    // Lobby Data
    public $requestedAppointments = [];
    public $scheduledAppointments = [];
    
    // Terminal Data (Left Pane)
    public $activeOrgId = null;
    public $activeApptId = null;
    public $leadData = null;
    public $copqData = null;
    public $historyData = [];
    public $historySummary = null;

    // User Clearance
    public $userClearance = 1;

    // Master Data Collections
    public $dbIndustries = [], $dbArchitectures = [], $dbBottlenecks = [], $dbCurrencies = [];

    // INLINE COPQ FORM STATE
    public $inlineIndustry = '', $inlineTool = '', $inlineBottleneck = '', $inlineCurrency = 'USD';
    public $inlineTurnover = 0, $inlineHeadcount = 1, $inlineMgmtNodes = 1, $inlineAcv = 0;
    public $inlineManualHrs = 5, $inlineHourlyCost = 50, $inlineErrorRate = 3.0;

    public function mount()
    {
        $this->loadClearance();
        $this->loadLobby();
        
        $this->dbIndustries = DB::table('mdm_copq_industries')->orderBy('industry_name')->get();
        $this->dbArchitectures = DB::table('mdm_copq_architectures')->orderBy('display_name')->get();
        $this->dbBottlenecks = DB::table('mdm_copq_bottlenecks')->orderBy('bottleneck_name')->get();
        $this->dbCurrencies = DB::table('mdm_exchange_rates')->orderBy('currency_code')->get();
    }

    private function loadClearance()
    {
        $user = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', Auth::id())->select('roles.clearance_level')->first();
        $this->userClearance = $user ? (int) $user->clearance_level : 5;
    }

    // ==========================================
    // 1. THE LOBBY (SCHEDULING & APPROVALS)
    // ==========================================
    public function loadLobby()
    {
        $baseQuery = DB::table('appointments')
            ->join('organizations', 'appointments.organization_id', '=', 'organizations.id')
            ->select('appointments.*', 'organizations.name as company_name', 'organizations.industry');

        $this->requestedAppointments = (clone $baseQuery)->where('appointments.status', 'Requested')->orderBy('created_at', 'desc')->get();
        $this->scheduledAppointments = (clone $baseQuery)->where('appointments.status', 'Scheduled')->orderBy('start_time', 'asc')->get();
    }

    public function approveMeeting($appointmentId, $meetingLink)
    {
        if(empty(trim($meetingLink))) {
            session()->flash('error', 'Meeting link is required for approval.');
            return;
        }

        DB::table('appointments')->where('id', $appointmentId)->update([
            'status' => 'Scheduled',
            'meeting_link' => trim($meetingLink),
            'updated_by' => Auth::id() ?? 'system',
            'updated_at' => now()
        ]);

        // FETCH EMAIL AND DISPATCH
        $appt = DB::table('appointments')->where('id', $appointmentId)->first();
        $org = DB::table('organizations')->where('id', $appt->organization_id)->first();
        
        $copq = DB::table('copq_assessments')->where('organization_id', $org->id)->orderBy('created_at', 'desc')->first();
        $clientEmail = $copq ? $copq->corporate_email : null;

        if (!$clientEmail) {
            $user = DB::table('users')->where('organization_id', $org->id)->first();
            $clientEmail = $user ? $user->email : null;
        }

        if ($clientEmail) {
            try {
                Mail::to($clientEmail)->send(new MeetingApprovedMail($appt, $org->name, trim($meetingLink)));
                session()->flash('success', 'Consultation Approved & Link Dispatched to Client.');
            } catch (\Exception $e) {
                session()->flash('error', 'Consultation Approved, but SMTP Email Dispatch failed.');
            }
        } else {
            session()->flash('success', 'Consultation Approved (No client email on file).');
        }

        $this->loadLobby();
    }

    // ==========================================
    // 2. THE WAR ROOM (TERMINAL LAUNCH)
    // ==========================================
    public function enterTerminal($orgId, $appId)
    {
        $this->activeOrgId = $orgId;
        $this->activeApptId = $appId;

        $this->leadData = DB::table('organizations')->where('id', $orgId)->first();
        $this->copqData = DB::table('copq_assessments')->where('organization_id', $orgId)->orderBy('created_at', 'desc')->first();

        // Process Historical Interactions AI Summary
        $this->processHistorySummary($orgId);

        $this->activeView = 'terminal';
    }

    public function togglePrivacy()
    {
        $this->privacyMode = !$this->privacyMode;
    }

    private function processHistorySummary($orgId)
    {
        $notes = DB::table('crm_notes')->where('organization_id', $orgId)->orderBy('created_at', 'desc')->get();
        
        if ($this->leadData->ai_history_summary) {
            $this->historySummary = $this->leadData->ai_history_summary;
            $this->historyData = $notes->take(3); 
            return;
        }

        if ($notes->count() > 2) {
            $historyText = $notes->pluck('note_text')->implode(" | ");
            $prompt = "Summarize the following historical CRM interactions with this client into one single, highly concise, authoritative briefing paragraph for a Lead Architect about to jump on a call. Notes: " . $historyText;

            try {
                $apiKey = config('services.gemini.api_key');
                $response = Http::timeout(4)->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                    "contents" => [["role" => "user", "parts" => [["text" => $prompt]]]],
                    "generationConfig" => ["temperature" => 0.2, "maxOutputTokens" => 150]
                ]);

                if ($response->successful()) {
                    $this->historySummary = $response->json('candidates.0.content.parts.0.text');
                    DB::table('organizations')->where('id', $orgId)->update(['ai_history_summary' => trim($this->historySummary)]);
                } else {
                    $this->historySummary = null; 
                }
            } catch (\Exception $e) {
                $this->historySummary = null; 
            }
        }
        
        $this->historyData = $this->historySummary ? $notes->take(3) : $notes->take(5);
    }

    // ==========================================
    // 3. FULL INLINE COPQ ENGINE (Matched to Frontend)
    // ==========================================
    public function generateInlineCopq()
    {
        $this->validate([
            'inlineIndustry' => 'required|string', 'inlineTool' => 'required|string', 'inlineBottleneck' => 'required|string',
            'inlineTurnover' => 'required|numeric', 'inlineHeadcount' => 'required|numeric', 'inlineMgmtNodes' => 'required|numeric',
            'inlineManualHrs' => 'required|numeric', 'inlineHourlyCost' => 'required|numeric', 'inlineErrorRate' => 'required|numeric'
        ]);

        $industry    = $this->inlineIndustry;
        $tool        = strtolower($this->inlineTool);
        $bottleneck  = $this->inlineBottleneck;
        $acv         = (float) $this->inlineAcv;
        $headcount   = (int) $this->inlineHeadcount;
        $mgmtNodes   = (int) $this->inlineMgmtNodes;
        $turnover    = (float) $this->inlineTurnover;
        $manualHrs   = (float) $this->inlineManualHrs;
        $hourlyCost  = (float) $this->inlineHourlyCost;
        $baseError   = (float) $this->inlineErrorRate;
        $currency    = $this->inlineCurrency;

        // 1. Fetch Multipliers (Architecture & Bottleneck)
        $archData = DB::table('mdm_copq_architectures')->where('tech_key', $tool)->first();
        $bnData = DB::table('mdm_copq_bottlenecks')->where('bottleneck_name', $bottleneck)->first();
        
        $archMultiplier = $archData ? $archData->error_multiplier : 1.00;
        $actualErrorRate = $baseError * $archMultiplier;
        
        $appMult = $bnData ? $bnData->appraisal_multiplier : 1.00;
        $failMult = $bnData ? $bnData->failure_multiplier : 1.00;

        // 2. Fetch Industry Constrains & AI Fallback
        $indData = DB::table('mdm_copq_industries')->where('industry_name', $industry)->first();
        
        $archetype = $indData->sector_archetype ?? 'COMMERCIAL';
        $archMandate = $indData->architectural_mandate ?? 'CLOUD-NATIVE MICROSERVICES';
        
        $companyName = strtoupper($this->leadData->name);
        $fallbackTemplate = $indData ? $indData->fallback_diagnostic : "Systemic friction detected within {COMPANY_NAME}'s current architectural parameters. Immediate architectural review required to neutralize operational bottlenecks and secure capital workflows.";
        $aiAnalysis = str_replace('{COMPANY_NAME}', $companyName, $fallbackTemplate);

        // 3. The Elite Prompt
        $systemPrompt = "You are an Elite Enterprise Architect at JBS Praxis. Write a clinical, ruthless 3-sentence diagnostic analysis (max 600 chars) for {$companyName}. 
        CONTEXT: Their Sector Archetype is [{$archetype}]. They are running on [{$tool}] with a severe bottleneck in [{$bottleneck}]. 
        RULE 1: Identify the systemic friction causing capital/resource leakage. 
        RULE 2: You MUST propose our mandatory cure: [{$archMandate}]. 
        RULE 3: Do not use generic software jargon; use terminology strictly native to a {$archetype} sector (e.g., if SOVEREIGN use 'public funds/civic deployment', if IMPACT use 'donor compliance/beneficiary reach', if ASSET_HEAVY use 'NPT/SCADA/supply chain spoilage', if COMMERCIAL use 'margins/client churn'). 
        Be highly authoritative and uncompromising.";
        
        try {
            $apiKey = config('services.gemini.api_key');
            $response = Http::timeout(4)->withHeaders(['Content-Type' => 'application/json'])
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
        $mgmtHeadcount = $mgmtNodes; 

        // Universal Internal Costs
        $appraisalCost = ($mgmtHeadcount * $manualHrs * $hourlyCost * 52) * $appMult;
        $internalFailure = ($turnover * ($actualErrorRate / 100) * 0.60) * $failMult;

        // Dynamic External Costs
        if ($archetype === 'SOVEREIGN') {
            $externalFailure = ($turnover * ($actualErrorRate / 100) * 0.85); 
        } elseif ($archetype === 'IMPACT') {
            $externalFailure = ($turnover * ($actualErrorRate / 100) * 0.80);
        } elseif ($archetype === 'ASSET_HEAVY') {
            $externalFailure = ($turnover * ($actualErrorRate / 100) * 0.90); 
        } else {
            $estimatedClients = $acv > 0 ? max(1, $turnover / $acv) : 0;
            $churnProb = ($actualErrorRate / 100) * 0.40;
            $externalFailure = ($estimatedClients * $churnProb) * $acv * 1.5;
        }

        $externalFailure = $externalFailure * $failMult;
        $totalCOPQ = $appraisalCost + $internalFailure + $externalFailure;

        // 5. Tier Scoring Logic
        $tier = 3; 
        if ($turnover >= 5000000 || $totalCOPQ >= 250000) $tier = 1; 
        elseif ($turnover >= 1000000 || $totalCOPQ >= 50000) $tier = 2; 

        // 6. Lock Data into the COPQ Vault
        $token = strtoupper('PRX-' . Str::random(6) . '-' . date('Y'));
        
        $user = DB::table('users')->where('organization_id', $this->activeOrgId)->first();
        $execEmail = $user ? $user->email : 'client@' . strtolower(str_replace(' ', '', $companyName)) . '.com';
        $execName = $user ? $user->first_name . ' ' . $user->last_name : 'Executive Board';

        DB::table('copq_assessments')->insert([
            'id' => (string) Str::uuid(), 'report_token' => $token, 'organization_id' => $this->activeOrgId,
            'executive_name' => $execName, 'company_name' => $this->leadData->name, 'corporate_email' => $execEmail,
            'copq_industry' => $industry, 'primary_tool' => $tool, 'bottleneck' => $bottleneck,
            'copq_currency' => $currency, 'copq_turnover' => $turnover, 'headcount' => $headcount,
            'mgmt_nodes' => $mgmtNodes,
            'copq_latency' => $manualHrs, 'copq_hourly_cost' => $hourlyCost,
            'copq_error_rate' => $actualErrorRate, 'avg_contract_value' => $acv,
            'calculated_leakage' => $totalCOPQ, 'ai_friction_analysis' => trim($aiAnalysis),
            'lead_tier' => $tier, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->copqData = DB::table('copq_assessments')->where('organization_id', $this->activeOrgId)->orderBy('created_at', 'desc')->first();
        
        // Update Org Industry to match
        DB::table('organizations')->where('id', $this->activeOrgId)->update(['industry' => $industry]);
        
        session()->flash('success', 'Diagnostic Matrix Compiled and Secured.');
    }

    // ==========================================
    // X. DISPATCH PLACEHOLDER (Pending Frontend Modal)
    // ==========================================
    public function dispatchCopqEmail()
    {
        // This will be wired up to the frontend modal you are building next.
        // For now, it alerts the user that the logic will be deployed shortly.
        
        /*
        if (!$this->copqData || empty($this->copqData->corporate_email)) {
            session()->flash('error', 'Cannot dispatch: Valid corporate email missing from records.');
            return;
        }

        try {
            Mail::to($this->copqData->corporate_email)->send(new CopqReportMail($this->copqData));
            session()->flash('success', 'COPQ Dossier transmitted to client inbox.');
        } catch (\Exception $e) {
            session()->flash('error', 'Transmission failed: SMTP Configuration required.');
        }
        */

        // Triggering a frontend event to open the upcoming Email/WhatsApp modal
        $this->dispatch('open-transmission-modal');
        session()->flash('success', 'Transmission logic pending frontend deployment.');
    }

    // ==========================================
    // 4. DISQUALIFICATION PROTOCOL
    // ==========================================
    public function disqualifyLead($reason = '')
    {
        if ($this->userClearance < 5 && empty(trim($reason))) {
            session()->flash('error', 'AUTHORIZATION DENIED: Written justification required.');
            return;
        }

        DB::table('organizations')->where('id', $this->activeOrgId)->update([
            'status' => 'Lost',
            'pipeline_stage' => 'Won / Lost',
            'disqualification_reason' => trim($reason) ?: 'Archived directly by Level 5 Command',
            'updated_at' => now()
        ]);

        if ($this->activeApptId) {
            DB::table('appointments')->where('id', $this->activeApptId)->update(['status' => 'Completed']);
        }

        $this->activeView = 'lobby';
        $this->loadLobby();
    }

    // ==========================================
    // 5. PROPOSAL SYNTHESIS (THE CLOSER)
    // ==========================================
    public function closeAndSynthesize($rawNotesData)
    {
        // 1. Gather Inputs
        $ctx = $rawNotesData['context'] ?? '';
        $vis = $rawNotesData['vision'] ?? '';
        $pain = $rawNotesData['pain'] ?? '';
        $sla = $rawNotesData['sla'] ?? '';
        $concatNotes = "CONTEXT & OPERATIONS: $ctx \n\nSCALE & VISION: $vis \n\nBOTTLENECKS: $pain \n\nREQUIREMENTS & BUDGET: $sla";

        // 2. Fetch COPQ Context to make the AI lethal
        $copq = DB::table('copq_assessments')->where('organization_id', $this->activeOrgId)->orderBy('created_at', 'desc')->first();
        $leakageStr = $copq ? "Calculated Annual Chaos Tax / Leakage: {$copq->copq_currency} " . number_format($copq->calculated_leakage) : "Unscored";
        $bottleneckStr = $copq ? $copq->bottleneck : "Operational Friction";
        $techStackStr = $copq ? $copq->primary_tool : "Legacy Systems";

        // 3. Save raw notes to CRM History natively
        DB::table('crm_notes')->insert([
            'id' => (string) Str::uuid(), 'organization_id' => $this->activeOrgId,
            'author_id' => Auth::id() ?? 'system', 'note_text' => $concatNotes,
            'interaction_type' => 'Meeting', 'created_at' => now(), 'updated_at' => now()
        ]);

        // 4. THE JBS PRAXIS CORPORATE DNA (Anti-Hallucination Matrix)
        $companyDNA = "
COMPANY IDENTITY: JBS Praxis. We are an elite hybrid management consulting & systems architecture and engineering firm. We engineer the Predictive Praxis Framework™ (PPF), shifting organizational governance from reactive, post-mortem firefighting to precognitive, deterministic forecasting. We conduct forensic operational audits and build algorithmic ecosystems that predict and neutralize bottlenecks, compliance risks, and capital leakage before they manifest.
 
SLOGAN: 'Beyond Strategy'. We do not write useless PDF reports; we engineer bespoke digital operating systems that make compliance structurally unavoidable.
 
TONE: Clinical, authoritative, aggressive yet highly corporate. Do not use fluffy marketing jargon (e.g., 'synergy', 'transformative journey'). Use technical terminology (e.g., 'architectural overhaul', 'capital leakage', 'zero-trust workflows', 'relational data vaulting', 'deterministic resilience', 'digital Poka-Yoke', 'algorithmic foresight', 'sprint velocity').
 
CORE METHODOLOGY (THE PPF & EXECUTION ENGINE):
1. Diagnostics & Truth: Deploying Lean Six Sigma mapping and the PAF Matrix to quantify the Cost of Poor Quality (COPQ) and establish absolute empirical truth.
2. Relational Data Vault: Eradicating data silos by sanitizing and centralizing data into high-velocity databases to form a Single Source of Truth.
3. Algorithmic Foresight: Utilizing predictive engines to continuously analyze operational velocity and project future system failures.
4. Autonomous Governance: Hardcoding ISO compliance into 'Code as Law' via digital Poka-Yoke logic gates, complete with an immutable, audited 'Glass-Break' emergency override protocol.
5. Sovereign Handover & Kaizen: Executing continuous algorithmic recalibration and database indexing to guarantee zero-latency performance and unbreakable security.
6. Execution Transparency (Agile Mastery): We operate with zero obscurity. Clients are integrated into a real-time Agile infrastructure, granting absolute visibility over Kanban boards, sprint backlogs, and live QA ticketing. This continuous feedback loop structurally eliminates scope creep and reassures stakeholders through empirical project velocity.
 
CORE CAPABILITIES & TECH STACK: 
1. Corporate Governance: ISO 9001/27001, Internal Control Systems, Six Sigma, Poka-Yoke error prevention, Regulatory Gap Analysis, Agile/Scrum Methodologies.
2. Data Engineering & Infrastructure: PostgreSQL, MongoDB, Apache Kafka (Event Streaming), AWS EKS (Kubernetes), Docker, CI/CD Pipelines, Zero-latency BI.
3. Custom Enterprise Systems: Golang (Zero-Latency Microservices), Python/TensorFlow (AI/ML), Java/Spring Boot, C#/.NET Core, Node.js.
4. Frontend & Mobile: Next.js/React, Vue.js, Flutter (Mobile OS), Swift (iOS), Kotlin (Android).
 
APPROVED CASE STUDIES (USE THESE STRICTLY TO DEMONSTRATE DETERMINISTIC ROI. MATCH THE CASE STUDY TO THE LEAD'S INDUSTRY):
 
- CS_1 (Beverage Production & Distribution): 'Tier-1 FMCG Asset Hemorrhage. Built an End-to-End Logistics & Fleet ERP. Deployed a web command center paired with a Mobile-First PWA for field operators. Integrated strict odometer validation APIs and cryptographic QR-code signatures for Proof of Delivery. ROI: Achieved 100% Asset Accountability and eradicated fuel log fraud.'
 
- CS_2 (Logistics & Procurement): 'Tier-1 Logistics ERP Deployment. Enterprise was bleeding capital via untracked manual approvals. Dismantled legacy workflows and engineered a bespoke ERP (Laravel/PostgreSQL/Livewire) with live fleet tracking APIs. ROI: Decimated manual approval times, achieving an 85% reduction in systemic latency and zero compliance breaches.'
 
- CS_3 (Fintech & Unified Commerce): 'Digital Escrow & Trust Infrastructure. A multi-billion informal trade sector was paralyzed by cash-on-delivery risk. Engineered a dual-layer Unified Commerce OS (Flutter/PostgreSQL) compliant with BEAC financial regulations. Integrated Mobile Money escrow, logistics webhooks, and banking-grade biometric KYC. ROI: Engineered a Zero Fraud Loop, instantly structuring informal vendors.'
 
- CS_4 (IT Infrastructure & Security): 'Global IT Operations Architecture. Client suffered from fragmented project lifecycles and opaque client reporting. We deployed a unified DevOps and Client Transparency Portal using Node.js and React. Hardcoded real-time Kanban tracking and sprint backlog matrices directly into the client-facing dashboard. ROI: 100% visibility into QA tickets, eradicating scope creep and reducing deployment friction by 60%.'
 
- CS_5 (Heavy Manufacturing): 'Predictive Maintenance OS. Unplanned machine downtime was causing severe capital leakage. Deployed IoT edge sensors feeding a Python/TensorFlow predictive engine via MQTT protocols. Applied Six Sigma mapping to hardcode digital Poka-Yoke logic into the assembly line. ROI: Predicted localized part failures up to 3 weeks in advance, reducing operational paralysis by 72%.'
 
- CS_6 (Pharmaceutical Supply Chain): 'Cold-Chain Regulatory Vault. Fragmented Excel sheets resulted in imminent ISO audit failures. We engineered an immutable distributed ledger (Java/Spring Boot framework) to track temperature-sensitive assets. ROI: Converted physical compliance manuals into automated logic gates, passing multinational audits with zero non-conformities.'
";

        // 5. The Ultimate Prompt
        $prompt = "
        $companyDNA
        
        TASK: You are the Lead Systems Architect at JBS Praxis. Analyze the consultant's rough notes and the COPQ diagnostic data below. Output a STRICT JSON payload representing the SOW narrative.
        
        CLIENT DATA:
        - Identified Bottleneck: $bottleneckStr
        - Current Tech Stack: $techStackStr
        - $leakageStr
        - Consultant Notes: $concatNotes
        
        RULES FOR JSON KEYS:
        1. 'ai_executive_summary': Exactly 2 paragraphs. P1 must state their current reality and the exact capital they are losing ($leakageStr). P2 must state the JBS Praxis mandate to dismantle this chaos and engineer an unbreakable system.
        2. 'ai_operational_strategy': Exactly 2 paragraphs detailing the technical deployment. Mention migrating from their current stack ($techStackStr) to a secure JBS Praxis architecture (e.g., PostgreSQL, Laravel, API bridges).
        3. 'ai_slas_json': Generate an array of exactly 4 strict, measurable Service Level Agreements based on their notes. (e.g., [{'title': 'System Uptime', 'value': '99.9% Guaranteed'}, {'title': 'Data Migration', 'value': 'Zero-Loss Protocol'}]). Titles max 25 chars, values max 40 chars.
        4. 'ai_case_study': Select the most relevant 'APPROVED CASE STUDY' from the DNA block above. Adapt it slightly to resonate with the client's industry, but you can invent sanitized cases that best adapt if you can't adapt it to any of our cases.
        
        OUTPUT FORMAT: STRICT JSON ONLY. NO MARKDOWN BACKTICKS (```json).
        {
          \"ai_executive_summary\": \"...\",
          \"ai_operational_strategy\": \"...\",
          \"ai_slas_json\": [{\"title\": \"...\", \"value\": \"...\"}],
          \"ai_case_study\": \"...\"
        }
        ";

        $aiPayload = [];
        try {
            // FIX: Removed the env() wrapper around the hardcoded fallback key. Increased timeout to 15s.
            $apiKey = config('services.gemini.api_key');
            
            $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["role" => "user", "parts" => [["text" => $prompt]]]], 
                "generationConfig" => ["temperature" => 0.2, "response_mime_type" => "application/json"]
            ]);
            
            if (!$response->successful()) {
                throw new \Exception("API Connection Failed (Status: {$response->status()})");
            }

            $rawText = $response->json('candidates.0.content.parts.0.text');
            if (!$rawText) {
                throw new \Exception("AI generated an empty response.");
            }

            $cleanText = preg_replace('/```(?:json)?|```/', '', $rawText);
            $aiPayload = json_decode(trim($cleanText), true);

            if (json_last_error() !== JSON_ERROR_NONE || empty($aiPayload) || !isset($aiPayload['ai_executive_summary'])) {
                throw new \Exception("AI failed to structure the JSON correctly.");
            }

        } catch (\Exception $e) {
            // HARD HALT: Stop execution, hide frontend modal, show styled error.
            $this->dispatch('consultation-error');
            session()->flash('error', 'NEURAL SYNTHESIS FAILED: ' . $e->getMessage());
            return; 
        }

        // --- ONLY EXECUTE IF AI SUCCEEDED ---
        $proposalId = (string) Str::uuid();
        DB::table('proposals')->insert([
            'id' => $proposalId, 
            'organization_id' => $this->activeOrgId,
            'created_by' => Auth::id() ?? 'system', 
            'status' => 'Draft',
            'subtotal' => 0,
            'total_value' => 0,
            'payment_schedule_type' => 'Standard',
            'created_at' => now(), 
            'updated_at' => now()
        ]);

        DB::table('proposal_ai_narratives')->insert([
            'proposal_id' => $proposalId,
            'raw_consultation_notes' => $concatNotes,
            'ai_executive_summary' => $aiPayload['ai_executive_summary'],
            'ai_operational_strategy' => $aiPayload['ai_operational_strategy'],
            'ai_slas_json' => isset($aiPayload['ai_slas_json']) ? json_encode($aiPayload['ai_slas_json']) : '[]',
            'ai_case_study' => $aiPayload['ai_case_study'] ?? '',
            // ai_phases_json omitted as it wasn't requested in prompt rules, defaults to NULL based on your schema
            'generated_at' => now()
        ]);

        DB::table('organizations')->where('id', $this->activeOrgId)->update(['pipeline_stage' => 'Proposal Sent', 'updated_at' => now()]);
        
        if ($this->activeApptId) {
            DB::table('appointments')->where('id', $this->activeApptId)->update(['status' => 'Completed', 'updated_at' => now()]);
        }

        $this->dispatch('consultation-closed');
        
        $this->activeView = 'lobby';
        $this->loadLobby();
        session()->flash('success', 'Consultation Synthesized. Proposal Draft Generated.');
    }

    public function render()
    {
        return view('livewire.hq.intelligence.live-consultation');
    }
}