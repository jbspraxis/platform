<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class ProposalBuilder extends Component
{
    // --- 1. WORKSPACE STATE ---
    public $activeTab = 'ledger'; // ledger, setup, ai_engine, preview
    public $proposalsLedger = [];
    public $availableLeads = [];
    public $selectedLeadId = '';
    
    // --- 2. ACTIVE PROPOSAL STATE ---
    public $proposalId;
    public $proposal;
    public $organization;
    public $isLocked = false;
    public $magicLinkUrl = '';
    
    // --- 3. MDM DATA ---
    public $financialDictionary = [];
    public $exchangeRates = [];
    public $techCatalog = []; // Grouped by Category
    
    // --- 4. SETUP PARAMETERS (TAB 1) ---
    public $title = 'Enterprise Architecture SOW';
    public $archetype = [];
    public $currency = 'USD';
    public $vatRate = 7.50;
    public $paymentSchedule = '40_30_30';
    public $customDepositPercent = 0.00;
    public $validityDays = 14;
    public $selectedTech = []; // Array of Tech IDs
    
    // --- 5. COMMERCIALS ---
    public $lineItems = [];
    public $subtotal = 0;
    public $taxAmount = 0;
    public $grandTotal = 0;

    // --- 6. AI ENGINE & NARRATIVE (TAB 2) ---
    public $aiExecutiveSummary = '';
    public $aiOperationalStrategy = '';
    public $exclusions = '';
    
    public $totalWeeks = 12;
    public $w1 = 1.0; public $p1Content = '';
    public $w2 = 1.0; public $p2Content = '';
    public $w3 = 1.0; public $p3Content = '';
    public $w4 = 1.0; public $p4Content = '';

    // The Undo Cache
    public $previousAiState = null;

    // --- 7. CRYPTOGRAPHY (TAB 3) ---
    public $hqSignerName = '';
    public $hqSignatureBase64 = '';

    public function mount($id = null)
    {
        $this->loadMdmData();
        $this->hqSignerName = Auth::user()->first_name . ' ' . Auth::user()->last_name;

        if ($id) {
            $this->proposalId = $id;
            $this->activeTab = 'setup';
            $this->loadProposalData();
        } else {
            $this->activeTab = 'ledger';
            $this->loadLedgerData();
        }
    }

    public function switchTab($tab)
    {
        if (in_array($this->activeTab, ['setup', 'ai_engine']) && $this->proposalId && !$this->isLocked) {
            $this->saveBlueprintProgress();
        }
        $this->activeTab = $tab;
        
        if ($tab === 'ledger') $this->loadLedgerData();
        if ($tab === 'preview') {
            $this->calculateCommercials();
            // TELL THE BROWSER TO WAKE UP THE CANVAS
            $this->dispatch('initialize-canvas'); 
        }
    }

    // =========================================================================
    // I. DATA HYDRATION & MDM
    // =========================================================================

    private function loadMdmData()
    {
        $this->financialDictionary = DB::table('mdm_financial_dictionary')->where('is_active', 1)->get()->toArray();
        $this->exchangeRates = DB::table('mdm_exchange_rates')->get()->toArray();
        
        // Group Tech Catalog by Category
        $rawTech = DB::table('mdm_tech_catalog')->where('is_active', 1)->get();
        $this->techCatalog = collect($rawTech)->groupBy('category')->toArray();

        $taxRegion = DB::table('mdm_tax_compliance')->where('country_code', 'NG')->where('is_active', 1)->first();
        $this->vatRate = $taxRegion ? (float) $taxRegion->vat_rate : 7.50;
    }

    private function loadLedgerData()
    {
        $this->proposalsLedger = DB::table('proposals')
            ->join('organizations', 'proposals.organization_id', '=', 'organizations.id')
            ->select('proposals.*', 'organizations.name as client_name')
            ->orderBy('proposals.created_at', 'desc')
            ->get()->toArray();

        $this->availableLeads = DB::table('organizations')->orderBy('name', 'asc')->get()->toArray();
    }

    public function initiateForLead()
    {
        if (empty($this->selectedLeadId)) return;

        $this->proposalId = (string) Str::uuid();
        $ref = 'SOW-' . date('Ymd') . '-' . rand(100, 999);
        $token = 'TKN-' . strtoupper(Str::random(10));

        DB::table('proposals')->insert([
            'id' => $this->proposalId,
            'organization_id' => $this->selectedLeadId,
            'title' => 'Executive Enterprise Architecture Draft',
            'created_by' => Auth::id(),
            'proposal_ref' => $ref,
            'token_hash' => $token,
            'subtotal' => 0.00, 
            'total_value' => 0.00, 
            'payment_schedule_type' => '40_30_30', 
            'status' => 'Draft',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('proposal_ai_narratives')->insert([
            'proposal_id' => $this->proposalId,
            'ai_executive_summary' => 'Awaiting Strategic Generation...',
            'ai_operational_strategy' => 'Awaiting Architecture Definition...',
            'exclusions' => '',
            'ai_phases_json' => '[]',
            'generated_at' => now()
        ]);

        $this->activeTab = 'setup';
        $this->loadProposalData();
        session()->flash('success', '> BLANK MATRIX INITIALIZED.');
    }

    public function loadExistingProposal($id)
    {
        $this->proposalId = $id;
        $this->activeTab = 'setup';
        $this->loadProposalData();
    }

    private function loadProposalData()
    {
        $this->proposal = DB::table('proposals')->where('id', $this->proposalId)->first();
        if (!$this->proposal) return;

        $this->organization = DB::table('organizations')->where('id', $this->proposal->organization_id)->first();
        $narrative = DB::table('proposal_ai_narratives')->where('proposal_id', $this->proposalId)->first();
        
        // ADDED 'Pending Auth' to the locked array so the UI shifts to the Dispatch Screen
        $this->isLocked = in_array($this->proposal->status, ['Pending Auth', 'Authorized', 'Converted', 'Expired']);
        $this->magicLinkUrl = url('/portal/proposal/' . $this->proposal->token_hash);

        // Hydrate Setup
        $this->title = $this->proposal->title ?? 'Project Proposal';
        
        $rawExc = $this->proposal->exclusions ?? '';
        $decodedExc = json_decode($rawExc, true);
        $this->exclusions = is_array($decodedExc) ? "- " . implode("\n- ", $decodedExc) : $rawExc;

        $this->archetype = $this->proposal->archetype_id ? explode(' | ', $this->proposal->archetype_id) : [];
        $this->currency = $this->proposal->currency ?? 'USD';
        $this->vatRate = (float) $this->proposal->vat_rate;
        $this->paymentSchedule = $this->proposal->payment_schedule_type ?? '40_30_30';
        $this->customDepositPercent = (float) $this->proposal->custom_deposit_percent;
        $this->validityDays = $this->proposal->validity_days ?? 14;

        // Hydrate Commercials
        $lines = DB::table('proposal_line_items')->where('proposal_id', $this->proposalId)->orderBy('sort_order')->get();
        $this->lineItems = json_decode(json_encode($lines), true);
        $this->calculateCommercials();

        // Hydrate Tech Stack
        $this->selectedTech = DB::table('proposal_tech_stack')->where('proposal_id', $this->proposalId)->pluck('tech_id')->toArray();

        // Hydrate AI Narrative
        if ($narrative) {
            $this->aiExecutiveSummary = $narrative->ai_executive_summary;
            $this->aiOperationalStrategy = $narrative->ai_operational_strategy;
            
            $phases = json_decode($narrative->ai_phases_json, true) ?? [];
            $this->p1Content = $phases[0]['body'] ?? ''; $this->w1 = (float) ($phases[0]['weight'] ?? 1.0);
            $this->p2Content = $phases[1]['body'] ?? ''; $this->w2 = (float) ($phases[1]['weight'] ?? 1.0);
            $this->p3Content = $phases[2]['body'] ?? ''; $this->w3 = (float) ($phases[2]['weight'] ?? 1.0);
            $this->p4Content = $phases[3]['body'] ?? ''; $this->w4 = (float) ($phases[3]['weight'] ?? 1.0);
        }
    }

    // =========================================================================
    // II. THE AI ENGINE & HYBRID GENERATOR
    // =========================================================================

    public function triggerAI()
    {
        if ($this->isLocked) return;

        // Cache the current state before overwriting
        $this->previousAiState = [
            'title' => $this->title,
            'summary' => $this->aiExecutiveSummary,
            'strategy' => $this->aiOperationalStrategy,
            'exclusions' => $this->exclusions,
            'p1' => $this->p1Content, 'w1' => $this->w1,
            'p2' => $this->p2Content, 'w2' => $this->w2,
            'p3' => $this->p3Content, 'w3' => $this->w3,
            'p4' => $this->p4Content, 'w4' => $this->w4,
        ];

        // Fetch selected tech names for context
        $techNames = DB::table('mdm_tech_catalog')->whereIn('id', $this->selectedTech)->pluck('tech_name')->implode(', ');
        $archContext = implode(', ', $this->archetype); // Join archetypes for the prompt
        
        $prompt = "You are the Lead Architect for JBS Praxis. Write a highly authoritative, technical Statement of Work (SOW) for client '{$this->organization->name}'.
        Industry/Operational Focus: {$archContext}. 
        Tech Stack constraints: {$techNames}.
        
        Output strictly valid JSON with these keys:
        {
            \"project_title\": \"A strong 4-6 word enterprise title\",
            \"executive_summary\": \"One elite paragraph explaining the business value.\",
            \"operational_strategy\": \"Two paragraphs explaining the technical architecture and compliance goals.\",
            \"exclusions\": \"List 3 things NOT included in this project (e.g. legacy data entry, AWS fees).\",
            \"phases(below is the ideal structure but you should take full control adn suggest what is best for the project. But let us maintain 4 Phases or sprints)\" [
                {\"body\": \"Describe Phase 1 Diagnostic...\", \"weight\": 1.0},
                {\"body\": \"Describe Phase 2 Build...\", \"weight\": 1.5},
                {\"body\": \"Describe Phase 3 Execute...\", \"weight\": 1.5},
                {\"body\": \"Describe Phase 4 Handover...\", \"weight\": 0.5}
            ]
        }
        Do not use markdown blocks. Return pure JSON.";

        try {
            $apiKey = '***REMOVED***'; 
            $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["parts" => [["text" => $prompt]]]], 
                "generationConfig" => ["response_mime_type" => "application/json"]
            ]);
            
            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                
                // BULLETPROOF PARSING: Extract strictly the JSON object, ignoring conversational text/markdown
                preg_match('/\{[\s\S]*\}/', $rawText, $matches);
                $cleanJson = $matches[0] ?? '{}';
                $aiPayload = json_decode($cleanJson, true);
                
                if (!$aiPayload) {
                    session()->flash('error', '> AI RETURNED CORRUPTED PAYLOAD. PLEASE RETRY.');
                    return;
                }
                
                $this->title = $aiPayload['project_title'] ?? $this->title;
                $this->aiExecutiveSummary = $aiPayload['executive_summary'] ?? '';
                $this->aiOperationalStrategy = $aiPayload['operational_strategy'] ?? '';
                
                $aiExc = $aiPayload['exclusions'] ?? '';
                $this->exclusions = is_array($aiExc) ? "- " . implode("\n- ", $aiExc) : $aiExc;
                
                // Safe extraction of nested phase arrays
                $this->p1Content = $aiPayload['phases'][0]['body'] ?? ''; $this->w1 = (float) ($aiPayload['phases'][0]['weight'] ?? 1.0);
                $this->p2Content = $aiPayload['phases'][1]['body'] ?? ''; $this->w2 = (float) ($aiPayload['phases'][1]['weight'] ?? 1.5);
                $this->p3Content = $aiPayload['phases'][2]['body'] ?? ''; $this->w3 = (float) ($aiPayload['phases'][2]['weight'] ?? 1.5);
                $this->p4Content = $aiPayload['phases'][3]['body'] ?? ''; $this->w4 = (float) ($aiPayload['phases'][3]['weight'] ?? 0.5);

                session()->flash('ai_success', '> ARCHITECTURE GENERATED. REVIEW MATRICES.');
            } else {
                session()->flash('error', '> AI CONNECTION FAILED. HTTP ERROR.');
            }
        } catch (\Exception $e) {
            session()->flash('error', '> AI LINK SEVERED. FALLBACK TO MANUAL OVERRIDE.');
        }
    }

    public function revertAI()
    {
        if ($this->previousAiState) {
            $this->title = $this->previousAiState['title'];
            $this->aiExecutiveSummary = $this->previousAiState['summary'];
            $this->aiOperationalStrategy = $this->previousAiState['strategy'];
            $this->exclusions = $this->previousAiState['exclusions'];
            $this->p1Content = $this->previousAiState['p1']; $this->w1 = $this->previousAiState['w1'];
            $this->p2Content = $this->previousAiState['p2']; $this->w2 = $this->previousAiState['w2'];
            $this->p3Content = $this->previousAiState['p3']; $this->w3 = $this->previousAiState['w3'];
            $this->p4Content = $this->previousAiState['p4']; $this->w4 = $this->previousAiState['w4'];
            $this->previousAiState = null;
            session()->flash('ai_success', '> PREVIOUS STATE RESTORED.');
        }
    }

    // =========================================================================
    // III. COMMERCIALS & SAVING LOGIC
    // =========================================================================

    public function toggleTech($techId)
    {
        if (in_array($techId, $this->selectedTech)) {
            $this->selectedTech = array_diff($this->selectedTech, [$techId]);
        } else {
            $this->selectedTech[] = $techId;
        }
    }

    public function addLineItem($dictId = null)
    {
        if ($this->isLocked) return;
        $desc = "Custom Architecture Component"; $qty = "1 Phase"; $price = 0.00; $code = "CUST-01";

        if ($dictId) {
            $item = DB::table('mdm_financial_dictionary')->where('id', $dictId)->first();
            if ($item) {
                $desc = $item->description; $qty = $item->default_quantity; 
                $price = $item->default_unit_rate_usd; $code = $item->item_code;
                if ($this->currency !== 'USD') {
                    $rate = DB::table('mdm_exchange_rates')->where('currency_code', $this->currency)->value('rate_to_usd');
                    $price = $price * ($rate ?: 1);
                }
            }
        }
        $this->lineItems[] = ['code' => $code, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price, 'total_price' => $price];
        $this->calculateCommercials();
    }

    public function updateLine($index, $field, $value)
    {
        if ($this->isLocked) return;
        $this->lineItems[$index][$field] = $value;
        if ($field === 'unit_price') $this->lineItems[$index]['total_price'] = (float)$value; 
        $this->calculateCommercials();
    }

    public function removeLineItem($index)
    {
        if ($this->isLocked) return;
        unset($this->lineItems[$index]);
        $this->lineItems = array_values($this->lineItems);
        $this->calculateCommercials();
    }

    public function calculateCommercials()
    {
        $this->subtotal = array_sum(array_column($this->lineItems, 'total_price'));
        $this->taxAmount = $this->subtotal * ($this->vatRate / 100);
        $this->grandTotal = $this->subtotal + $this->taxAmount;
    }

    public function saveBlueprintProgress()
    {
        if ($this->isLocked) return;
        $this->calculateCommercials();

        // Structure Phases for JSON
        $phasesJson = json_encode([
            ['title' => 'Phase 1: Diagnostic & Blueprint', 'body' => $this->p1Content, 'weight' => (float)$this->w1],
            ['title' => 'Phase 2: Architecture Build', 'body' => $this->p2Content, 'weight' => (float)$this->w2],
            ['title' => 'Phase 3: Execution Engine', 'body' => $this->p3Content, 'weight' => (float)$this->w3],
            ['title' => 'Phase 4: QA & Handover', 'body' => $this->p4Content, 'weight' => (float)$this->w4],
        ]);

        DB::transaction(function () use ($phasesJson) {
            DB::table('proposals')->where('id', $this->proposalId)->update([
                'title' => $this->title,
                'exclusions' => $this->exclusions, 
                'archetype_id' => implode(' | ', $this->archetype), // Saves as "Tech A | Tech B"
                'currency' => $this->currency,
                'vat_rate' => $this->vatRate, 
                'payment_schedule_type' => $this->paymentSchedule,
                'custom_deposit_percent' => $this->paymentSchedule === 'custom' ? $this->customDepositPercent : null,
                'validity_days' => $this->validityDays, 
                'subtotal' => $this->subtotal,
                'total_value' => $this->grandTotal,
                'updated_at' => now()
            ]);

            DB::table('proposal_ai_narratives')->where('proposal_id', $this->proposalId)->update([
                'ai_executive_summary' => $this->aiExecutiveSummary,
                'ai_operational_strategy' => $this->aiOperationalStrategy,
                'ai_phases_json' => $phasesJson
            ]);

            DB::table('proposal_line_items')->where('proposal_id', $this->proposalId)->delete();
            foreach ($this->lineItems as $idx => $line) {
                DB::table('proposal_line_items')->insert([
                    'proposal_id' => $this->proposalId, 'code' => $line['code'],
                    'description' => $line['description'], 'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'], 'total_price' => $line['total_price'], 'sort_order' => $idx
                ]);
            }

            // Sync Pivot Table
            DB::table('proposal_tech_stack')->where('proposal_id', $this->proposalId)->delete();
            $techInserts = array_map(fn($tId) => ['proposal_id' => $this->proposalId, 'tech_id' => $tId], $this->selectedTech);
            if (!empty($techInserts)) DB::table('proposal_tech_stack')->insert($techInserts);
        });
    }

    // =========================================================================
    // IV. THE CRYPTOGRAPHIC DISPATCH (HQ SIGNATURE)
    // =========================================================================

    public function compileAndSecure()
    {
        if ($this->isLocked) return;

        $this->validate([
            'hqSignerName' => 'required|string',
            'hqSignatureBase64' => 'required|string'
        ]);

        $this->saveBlueprintProgress(); // Ensure latest data is saved

        $timestamp = now();
        $ip = Request::ip();
        
        // Generate actual SHA-256 Hash to mathematically prove immutability
        $rawPayload = $this->proposalId . $this->grandTotal . $this->hqSignerName . $timestamp;
        $secureHash = 'SHA256-' . strtoupper(hash('sha256', $rawPayload));

        DB::transaction(function () use ($timestamp, $ip, $secureHash) {
            
            // BULLETPROOF: Fetch or generate the token hash on the fly
            $currentToken = DB::table('proposals')->where('id', $this->proposalId)->value('token_hash');
            if (empty($currentToken)) {
                $currentToken = 'TKN-' . strtoupper(Str::random(10));
            }

            DB::table('proposals')->where('id', $this->proposalId)->update([
                'status' => 'Pending Auth', 
                'token_hash' => $currentToken,
                'hq_signer_name' => $this->hqSignerName,
                'hq_signature_b64' => $this->hqSignatureBase64,
                'hq_signature_hash' => $secureHash,
                'hq_ip_address' => $ip,
                'hq_signed_at' => $timestamp,
                'updated_at' => $timestamp
            ]);
            
            // NOTE: We have removed the insert into `project_magic_links` here.
            // That table requires a valid `project_id` which does not exist yet.
            // The Client Gateway authenticates purely via the `token_hash` in the `proposals` table.
        });
        
        $this->loadProposalData();
        session()->flash('dispatch_success', '> TOKEN SECURED. READY FOR CLIENT TRANSMISSION.');
    }
    
    public function unlockProposal()
    {
        // Prevent unlocking if the client already signed it
        if (in_array($this->proposal->status, ['Authorized', 'Converted'])) {
            session()->flash('error', '> CANNOT UNLOCK: Client has already counter-signed.');
            return;
        }

        DB::transaction(function () {
            // Revert to Draft and destroy the HQ cryptographic seal
            DB::table('proposals')->where('id', $this->proposalId)->update([
                'status' => 'Draft',
                'hq_signer_name' => null,
                'hq_signature_b64' => null,
                'hq_signature_hash' => null,
                'hq_ip_address' => null,
                'hq_signed_at' => null,
                'updated_at' => now()
            ]);

            // Destroy the active Magic Link
            DB::table('project_magic_links')->where('project_id', $this->proposalId)->delete();
        });

        $this->loadProposalData();
        session()->flash('success', '> SECURITY LOCK DESTROYED. DOCUMENT REVERTED TO EDITABLE DRAFT.');
    }

    public function render() { return view('livewire.hq.intelligence.proposal-builder'); }
}