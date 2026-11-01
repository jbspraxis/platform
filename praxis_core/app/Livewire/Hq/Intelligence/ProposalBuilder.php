<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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
    // Set on the server only: the browser must not choose another proposal or unlock a signed one.
    // Saving and signing also re-read the status from the database (proposalIsLocked()).
    #[Locked]
    public $proposalId;
    #[Locked]
    public $proposal;
    #[Locked]
    public $organization;
    #[Locked]
    public $isLocked = false;
    public $magicLinkUrl = '';
    
    // --- 3. MDM DATA ---
    public $financialDictionary = [];
    public $exchangeRates = [];
    public $techCatalog = []; // Grouped by Category
    
    // --- 4. SETUP PARAMETERS (TAB 1) ---
    public $title = 'Proposal';
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

    // --- 7. HQ SIGNATURE (TAB 3) ---
    public $hqSignerName = '';
    public $hqSignatureBase64 = '';

    protected $validationAttributes = ['hqSignerName' => 'your name', 'hqSignatureBase64' => 'signature'];

    public function mount($id = null)
    {
        $this->loadMdmData();
        $this->hqSignerName = Auth::user()->first_name . ' ' . Auth::user()->last_name;

        if ($id) {
            if ($this->openReviewed($id)) {
                return;
            }
            $this->proposalId = $id;
            $this->activeTab = 'setup';
            $this->loadProposalData();
        } else {
            $this->activeTab = 'ledger';
            $this->loadLedgerData();
        }
    }

    // A proposal can be edited only while it is a draft. Read from the database, not from the page.
    private const LOCKED_STATUSES = ['Pending Auth', 'Authorized', 'Converted', 'Expired'];

    private function proposalIsLocked(): bool
    {
        if (! $this->proposalId) return true;
        $proposal = DB::table('proposals')->where('id', $this->proposalId)->first(['status', 'signing_mode']);
        // A proposal v2 (signing_mode approve) is written in its opportunity's Proposals tab, never here
        return $proposal === null || $proposal->signing_mode === 'approve' || in_array($proposal->status, self::LOCKED_STATUSES, true);
    }

    /**
     * A proposal v2 belongs to its opportunity's Proposals tab (docs/OPPORTUNITIES.md, PR 4): open it there.
     * True when the page is on its way there.
     */
    private function openReviewed(string $id): bool
    {
        $proposal = DB::table('proposals')->where('id', $id)->first(['id', 'opportunity_id', 'signing_mode']);
        if (! $proposal || $proposal->signing_mode !== 'approve' || ! $proposal->opportunity_id) {
            return false;
        }
        $this->redirectRoute('hq.crm.opportunity', ['opportunity' => $proposal->opportunity_id, 'tab' => 'proposals', 'version' => $proposal->id], navigate: true);

        return true;
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
        if (! Auth::user()->can('crm.create')) {
            session()->flash('error', "You don’t have permission to create proposals. Ask an administrator to change your role.");
            return;
        }
        if (empty($this->selectedLeadId)) return;

        $this->proposalId = (string) Str::uuid();
        $ref = 'SOW-' . date('Ymd') . '-' . rand(100, 999);
        $token = 'TKN-' . strtoupper(Str::random(10));

        // Both rows or neither, so a failed insert never leaves a proposal without its narrative
        // (exclusions live on proposals, not on the narrative)
        DB::transaction(function () use ($ref, $token) {
            DB::table('proposals')->insert([
                'id' => $this->proposalId,
                'organization_id' => $this->selectedLeadId,
                'title' => 'Draft proposal',
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
                'ai_executive_summary' => '',
                'ai_operational_strategy' => '',
                'ai_phases_json' => '[]',
                'generated_at' => now()
            ]);
        });

        $this->activeTab = 'setup';
        $this->loadProposalData();
        session()->flash('success', 'Draft proposal created. Fill in the setup, then write the narrative.');
    }

    public function loadExistingProposal($id)
    {
        if ($this->openReviewed((string) $id)) {
            return;
        }
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
        
        // 'Pending Auth' is locked too, so the page shows the signing link instead of the editor
        $this->isLocked = in_array($this->proposal->status, self::LOCKED_STATUSES, true);
        $this->magicLinkUrl = route('portal.proposal', ['token' => $this->proposal->token_hash]);

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
    // II. DRAFTING WITH THE ASSISTANT
    // =========================================================================

    public function triggerAI()
    {
        if ($this->isLocked || $this->proposalIsLocked()) return;
        if (! Auth::user()->can('crm.edit')) {
            session()->flash('error', "You don’t have permission to edit proposals. Ask an administrator to change your role.");
            return;
        }

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
        
        // Voice and claims: docs/BRAND.md sections 1 and 4 (plain, measured, no guaranteed outcomes, no invented results)
        $prompt = "You are a senior consultant at JBS Praxis, a consulting and engineering firm for Africa’s institutions. Write the narrative of a proposal (statement of work) for the client '{$this->organization->name}'.
        Services in scope: {$archContext}.
        Technology to use: {$techNames}.
        Voice: plain, precise and measured, like a Big Four proposal. British English, short sentences, active voice, everyday business words, sentence case. Write 'we recommend' and 'we estimate'. No hype words, no metaphors from war, space or hacking, no guaranteed outcomes, and no invented clients, figures or results.

        Output strictly valid JSON with these keys:
        {
            \"project_title\": \"A plain 4 to 6 word title that names the outcome, in sentence case\",
            \"executive_summary\": \"One paragraph: the situation, what we recommend and what it changes for the client.\",
            \"operational_strategy\": \"Two paragraphs on how we will do the work: the system we will build, how it replaces today’s tools, and the controls and compliance it supports.\",
            \"exclusions\": \"List 3 things not included in this project (e.g. data entry of old records, cloud hosting fees).\",
            \"phases\": [
                {\"body\": \"Describe phase 1, diagnosis and design...\", \"weight\": 1.0},
                {\"body\": \"Describe phase 2, build...\", \"weight\": 1.5},
                {\"body\": \"Describe phase 3, go-live...\", \"weight\": 1.5},
                {\"body\": \"Describe phase 4, testing and handover...\", \"weight\": 0.5}
            ]
        }
        Keep exactly 4 phases; adapt what each one covers to this project. Do not use markdown blocks. Return pure JSON.";

        try {
            $apiKey = config('services.gemini.api_key'); 
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
                    session()->flash('error', "We couldn’t read the assistant’s draft. Try again, or write the narrative yourself.");
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

                session()->flash('ai_success', 'Draft written. Review each section before you continue.');
            } else {
                session()->flash('error', "We couldn’t reach the writing assistant. Try again in a minute, or write the narrative yourself.");
            }
        } catch (\Exception $e) {
            session()->flash('error', "We couldn’t reach the writing assistant. Try again in a minute, or write the narrative yourself.");
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
            session()->flash('ai_success', 'Previous text restored.');
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
        $desc = "Custom item"; $qty = "1 phase"; $price = 0.00; $code = "CUST-01";

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
        // Also runs on every tab switch, so viewers without crm.edit just don’t save
        if ($this->isLocked || $this->proposalIsLocked() || ! Auth::user()->can('crm.edit')) return;
        $this->calculateCommercials();

        // Structure Phases for JSON
        $phasesJson = json_encode([
            ['title' => 'Phase 1: Diagnosis and design', 'body' => $this->p1Content, 'weight' => (float)$this->w1],
            ['title' => 'Phase 2: Build', 'body' => $this->p2Content, 'weight' => (float)$this->w2],
            ['title' => 'Phase 3: Go-live', 'body' => $this->p3Content, 'weight' => (float)$this->w3],
            ['title' => 'Phase 4: Testing and handover', 'body' => $this->p4Content, 'weight' => (float)$this->w4],
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

            // Insert when missing: drafts created before the fix above have no narrative row
            DB::table('proposal_ai_narratives')->updateOrInsert(['proposal_id' => $this->proposalId], [
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
    // IV. HQ SIGNATURE (locks the proposal before it goes to the client)
    // =========================================================================

    public function compileAndSecure()
    {
        if ($this->isLocked) return;
        if (! Auth::user()->can('crm.approve')) {
            session()->flash('error', "You don’t have permission to sign proposals. Ask an administrator to change your role.");
            return;
        }
        if ($this->proposalIsLocked()) {
            $this->loadProposalData();
            session()->flash('error', "This proposal can’t be signed because it is no longer a draft. Reload the page to see its current status.");
            return;
        }

        $this->validate([
            'hqSignerName' => 'required|string',
            'hqSignatureBase64' => 'required|string'
        ]);

        $this->saveBlueprintProgress(); // Ensure latest data is saved

        $timestamp = now();
        $ip = Request::ip();
        
        // SHA-256 fingerprint of the signed content, so a later change can be detected
        $rawPayload = $this->proposalId . $this->grandTotal . $this->hqSignerName . $timestamp;
        $secureHash = 'SHA256-' . strtoupper(hash('sha256', $rawPayload));

        $signed = DB::transaction(function () use ($timestamp, $ip, $secureHash) {

            // Use the existing token, or create one
            $currentToken = DB::table('proposals')->where('id', $this->proposalId)->value('token_hash');
            if (empty($currentToken)) {
                $currentToken = 'TKN-' . strtoupper(Str::random(10));
            }

            // Only while still unlocked: the status may have changed since the check above
            return DB::table('proposals')->where('id', $this->proposalId)->whereNotIn('status', self::LOCKED_STATUSES)->update([
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
        if (! $signed) {
            session()->flash('error', "This proposal is no longer a draft, so it wasn’t signed. Its current status is shown above.");
            return;
        }
        $this->logProposalStep('PROPOSAL_SIGNED_BY_HQ', 'Draft', 'Pending Auth', "Signed proposal {$this->proposal->proposal_ref} and sent it for the client’s signature.");
        // The client's approvers with a portal account find it in their portal, with its signing link (docs/PWA.md)
        \App\Support\Pwa\ClientNotify::proposal($this->proposalId);
        session()->flash('dispatch_success', 'Proposal signed and locked. Send the client the signing link below.');
    }
    
    public function unlockProposal()
    {
        if (! Auth::user()->can('crm.approve')) {
            session()->flash('error', "You don’t have permission to unlock signed proposals. Ask an administrator to change your role.");
            return;
        }

        // Prevent unlocking if the client already signed it (status read from the database, not the page)
        $status = DB::table('proposals')->where('id', $this->proposalId)->where('signing_mode', 'legacy')->value('status');
        $statusBefore = $status;
        if ($status === null || in_array($status, ['Authorized', 'Converted'])) {
            session()->flash('error', "This proposal can’t be unlocked because the client has already signed it.");
            return;
        }
        // A draft stays as it is: no new signing link, nothing in the audit log
        if ($status === 'Draft') {
            $this->loadProposalData();
            session()->flash('error', "This proposal is already a draft.");
            return;
        }

        // Back to draft: remove the HQ signature, any signature or code the client had started, and
        // replace the signing link (token_hash), so the link already sent can no longer be used.
        // The status guard covers a client who signs (or someone else unlocks) between the check above and this update
        $unlocked = DB::table('proposals')->where('id', $this->proposalId)->whereNotIn('status', ['Authorized', 'Converted', 'Draft'])->update([
            'status' => 'Draft',
            'token_hash' => 'TKN-' . strtoupper(Str::random(10)),
            'hq_signer_name' => null,
            'hq_signature_b64' => null,
            'hq_signature_hash' => null,
            'hq_ip_address' => null,
            'hq_signed_at' => null,
            'auth_otp' => null,
            'otp_expires_at' => null,
            'signed_by_name' => null,
            'client_signature_b64' => null,
            'updated_at' => now()
        ]);

        $this->loadProposalData();
        if (! $unlocked) {
            $status = DB::table('proposals')->where('id', $this->proposalId)->value('status');
            session()->flash('error', in_array($status, ['Authorized', 'Converted'], true)
                ? "This proposal can’t be unlocked because the client has already signed it."
                : "This proposal is already a draft.");
            return;
        }
        $this->logProposalStep('PROPOSAL_UNLOCKED', $statusBefore, 'Draft', "Unlocked proposal {$this->proposal->proposal_ref}: back to draft, signatures removed and the old signing link cancelled.");
        session()->flash('success', 'Proposal unlocked and back to draft. Your signature was removed and the old signing link no longer works; sign it again to get a new link.');
    }

    /** Signing and unlocking go to the audit log: who, which proposal, from which status to which */
    private function logProposalStep(string $action, ?string $from, string $to, string $description): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'target_table' => 'proposals',
            'target_id' => $this->proposalId,
            'old_values' => json_encode(['status' => $from]),
            'new_values' => json_encode(['status' => $to]),
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }

    public function render() { return view('livewire.hq.intelligence.proposal-builder'); }
}