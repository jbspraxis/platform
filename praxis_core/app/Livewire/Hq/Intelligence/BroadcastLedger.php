<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class BroadcastLedger extends Component
{
    // Navigation
    public $activeTab = 'composer'; // 'composer', 'queued', 'sent', 'failed'

    // Query Engine (Filters)
    public $filterStatus = '';
    public $filterIndustry = '';
    public $filterTurnover = '';
    public $filterStage = '';
    
    // Extracted Dropdown Options
    public $availableIndustries = [];
    public $availableTurnovers = [];
    public $availableStages = [];

    // Dispatch State
    public $targetUsers = [];
    public $selectedTargets = [];
    public $recipientCount = 0;
    public $activeLock = false;
    public $lockEta = null;

    // Composer State
    public $subject = '';
    public $rawBody = '';
    public $enhancedBody = '';
    public $selectedTone = 'Consultative & Authoritative';

    // Ledger Data
    public $campaigns = [];
    public $queuedLogs = [];
    public $sentLogs = [];
    public $failedLogs = [];

    public function mount()
    {
        $this->loadFilterOptions();
        $this->calculateRecipients();
        $this->checkSystemLock();
        $this->loadLedgerData();
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadLedgerData();
    }

    // --- 1. THE QUERY ENGINE & TARGET ACQUISITION ---
    public function updated($propertyName)
    {
        // Re-calculate and auto-select targets when filters change
        if (in_array($propertyName, ['filterStatus', 'filterIndustry', 'filterTurnover', 'filterStage'])) {
            $this->calculateRecipients();
        }
    }

    public function updatedSelectedTargets()
    {
        // Dynamically update the count when you manually check/uncheck a box in the UI
        $this->recipientCount = count($this->selectedTargets);
    }

    public function toggleSelectAll()
    {
        // Helper for the frontend to easily select/deselect the entire visible grid
        if (count($this->selectedTargets) === count($this->targetUsers)) {
            $this->selectedTargets = [];
        } else {
            $this->selectedTargets = array_column($this->targetUsers, 'id');
        }
        $this->recipientCount = count($this->selectedTargets);
    }

    private function loadFilterOptions()
    {
        $this->availableIndustries = DB::table('organizations')->whereNotNull('industry')->distinct()->pluck('industry')->toArray();
        $this->availableTurnovers = DB::table('organizations')->whereNotNull('turnover_bracket')->distinct()->pluck('turnover_bracket')->toArray();
        $this->availableStages = DB::table('organizations')->whereNotNull('pipeline_stage')->distinct()->pluck('pipeline_stage')->toArray();
    }

    private function getRecipientQuery()
    {
        $query = DB::table('users')
            ->join('organizations', 'users.organization_id', '=', 'organizations.id')
            ->where('users.is_subscribed', 1)
            ->whereNotNull('users.email')
            ->where('users.status', 'Active');

        if ($this->filterStatus) { $query->where('organizations.status', $this->filterStatus); }
        if ($this->filterIndustry) { $query->where('organizations.industry', $this->filterIndustry); }
        if ($this->filterTurnover) { $query->where('organizations.turnover_bracket', $this->filterTurnover); }
        if ($this->filterStage) { $query->where('organizations.pipeline_stage', $this->filterStage); }

        return $query;
    }

    public function calculateRecipients()
    {
        // Fetch up to 200 matching records for UI rendering
        $records = $this->getRecipientQuery()
            ->select(
                'users.id', 
                'users.first_name', 
                'users.last_name', 
                'users.email', 
                'users.job_title', 
                'organizations.name as company_name'
            )
            ->limit(200)
            ->get();

        // Convert objects to arrays for cleaner Livewire binding
        $this->targetUsers = json_decode(json_encode($records), true);

        // Auto-select all by default
        $this->selectedTargets = array_column($this->targetUsers, 'id');
        $this->recipientCount = count($this->selectedTargets);
    }

    // --- 2. SYSTEM QUEUE LOCK ---
    public function checkSystemLock()
    {
        $activeSending = DB::table('broadcast_campaigns')
            ->where('status', 'Sending')
            ->where('estimated_completion_time', '>', now())
            ->first();

        if ($activeSending) {
            $this->activeLock = true;
            $this->lockEta = \Carbon\Carbon::parse($activeSending->estimated_completion_time)->format('h:i A (WAT)');
        } else {
            $this->activeLock = false;
            $this->lockEta = null;
            
            DB::table('broadcast_campaigns')
                ->where('status', 'Sending')
                ->where('estimated_completion_time', '<=', now())
                ->update(['status' => 'Completed', 'updated_at' => now()]);
        }
    }

    // --- 3. GEMINI AI ENHANCEMENT ---
    public function enhanceWithGemini()
    {
        $this->validate([
            'rawBody' => 'required|string|min:10',
            'selectedTone' => 'required|string'
        ]);

        $prompt = "You are the Lead Partner at JBS Praxis. Rewrite the following email draft using a strictly '{$this->selectedTone}' tone. 
        CRITICAL RULES:
        1. Keep it Human, EXTREMELY CONCISE, HIGHLY CORPORATE and punchy. Maximum 2 to 3 short paragraphs.
        2. Format the output in clean, modern HTML (only use <p>, <strong>, <ul> tags). Do not include standard <html> or <body> tags.
        3. Use these exact literal variables where appropriate: {{ first_name }}, {{ company_name }}, {{ industry }}, {{ pipeline_stage }}.
        
        Raw Draft: {$this->rawBody}";

        try {
            $apiKey = config('services.gemini.api_key') ?: '***REMOVED***';
            
            $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["parts" => [["text" => $prompt]]]]
            ]);
            
            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                $this->enhancedBody = preg_replace('/```(?:html)?\n?|\n?```/', '', $rawText);
                session()->flash('success', 'Draft enhanced by Gemini AI.');
            } else {
                session()->flash('error', 'API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            session()->flash('error', 'AI Processing Timeout.');
        }
    }

    // --- 4. SECURE DISPATCH & NOTIFICATIONS ---
    public function dispatchCampaign()
    {
        $this->checkSystemLock();
        
        if ($this->activeLock) {
            session()->flash('error', 'SYSTEM LOCKED: A broadcast is currently utilizing the SMTP port.');
            return;
        }

        if (count($this->selectedTargets) === 0) {
            session()->flash('error', 'Dispatch Aborted: Zero targets selected.');
            return;
        }

        $this->validate([
            'subject' => 'required|string|max:150',
            'enhancedBody' => 'required|string|min:10'
        ]);

        DB::transaction(function () {
            // Recalculate based strictly on what is checked in the UI
            $finalCount = count($this->selectedTargets);
            $batches = ceil($finalCount / 30);
            $totalMinutes = $batches * 5;
            $eta = now()->addMinutes($totalMinutes);
            $campaignId = (string) Str::uuid();

            // 1. Create Ledger Entry
            DB::table('broadcast_campaigns')->insert([
                'id' => $campaignId,
                'subject' => $this->subject,
                'body_html' => $this->enhancedBody,
                'gemini_tone_used' => $this->selectedTone,
                'status' => 'Sending',
                'total_recipients' => $finalCount,
                'estimated_completion_time' => $eta,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 2. Batch Insert into Broadcast Logs
            $logInserts = [];
            foreach ($this->selectedTargets as $userId) {
                $logInserts[] = [
                    'campaign_id' => $campaignId,
                    'user_id' => $userId,
                    'status' => 'Queued',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            foreach (array_chunk($logInserts, 500) as $chunk) {
                DB::table('broadcast_logs')->insert($chunk);
            }

            // 3. System Notification for Level 3+ Management
            $executiveIds = DB::table('users')
                ->where('role_id', '>=', 3)
                ->where('status', 'Active')
                ->pluck('id')
                ->toArray();

            $notificationInserts = [];
            foreach ($executiveIds as $adminId) {
                $notificationInserts[] = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminId,
                    'title' => 'INTELLIGENCE BROADCAST DISPATCHED',
                    'message' => "Campaign '{$this->subject}' successfully queued for {$finalCount} selected targets. ETA to completion: {$totalMinutes} minutes.",
                    'link_url' => '/hq/intelligence/broadcast-ledger',
                    'type' => 'System',
                    'is_read' => 0,
                    'created_at' => now()
                ];
            }

            if (!empty($notificationInserts)) {
                DB::table('notifications')->insert($notificationInserts);
            }

            $this->activeLock = true;
            $this->lockEta = $eta->format('h:i A (WAT)');
        });

        // Reset UI & Navigate to Queued Tab
        $this->reset(['subject', 'rawBody', 'enhancedBody']);
        $this->activeTab = 'queued';
        $this->loadLedgerData();
        
        session()->flash('success', "DISPATCH INITIATED. Port Locked until {$this->lockEta}. Management notified.");
    }

    // --- 5. LEDGER HYDRATION ---
    private function loadLedgerData()
    {
        $this->campaigns = DB::table('broadcast_campaigns')->orderBy('created_at', 'desc')->get()->toArray();

        if ($this->activeTab === 'queued') {
            $this->queuedLogs = DB::table('broadcast_logs')
                ->join('users', 'broadcast_logs.user_id', '=', 'users.id')
                ->join('organizations', 'users.organization_id', '=', 'organizations.id')
                ->join('broadcast_campaigns', 'broadcast_logs.campaign_id', '=', 'broadcast_campaigns.id')
                ->where('broadcast_logs.status', 'Queued')
                ->select('broadcast_logs.*', 'users.email', 'users.first_name', 'users.last_name', 'organizations.name as company_name', 'broadcast_campaigns.subject')
                ->orderBy('broadcast_logs.created_at', 'desc')->limit(200)->get()->toArray();
        }

        if ($this->activeTab === 'sent') {
            $this->sentLogs = DB::table('broadcast_logs')
                ->join('users', 'broadcast_logs.user_id', '=', 'users.id')
                ->join('organizations', 'users.organization_id', '=', 'organizations.id')
                ->join('broadcast_campaigns', 'broadcast_logs.campaign_id', '=', 'broadcast_campaigns.id')
                ->where('broadcast_logs.status', 'Sent')
                ->select('broadcast_logs.*', 'users.email', 'users.first_name', 'users.last_name', 'organizations.name as company_name', 'broadcast_campaigns.subject')
                ->orderBy('broadcast_logs.updated_at', 'desc')->limit(200)->get()->toArray();
        }

        if ($this->activeTab === 'failed') {
            $this->failedLogs = DB::table('broadcast_logs')
                ->join('users', 'broadcast_logs.user_id', '=', 'users.id')
                ->join('organizations', 'users.organization_id', '=', 'organizations.id')
                ->join('broadcast_campaigns', 'broadcast_logs.campaign_id', '=', 'broadcast_campaigns.id')
                ->where('broadcast_logs.status', 'Failed')
                ->select('broadcast_logs.*', 'users.email', 'users.first_name', 'users.last_name', 'organizations.name as company_name', 'broadcast_campaigns.subject')
                ->orderBy('broadcast_logs.updated_at', 'desc')->limit(200)->get()->toArray();
        }
    }

    public function render()
    {
        return view('livewire.hq.intelligence.broadcast-ledger');
    }
}