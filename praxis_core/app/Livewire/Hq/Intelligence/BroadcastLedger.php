<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Support\Access;

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

    // Field names in validation messages (sentence case, docs/BRAND.md section 12)
    protected $validationAttributes = ['subject' => 'subject', 'rawBody' => 'draft', 'enhancedBody' => 'email text', 'selectedTone' => 'tone'];

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

    // --- 1. RECIPIENT FILTERS ---
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
        $query = DB::table('client_users')
            ->join('organizations', 'client_users.organization_id', '=', 'organizations.id')
            ->where('client_users.is_subscribed', 1)
            ->whereNotNull('client_users.email')
            ->where('client_users.status', 'Active')
            ->whereNull('client_users.deleted_at');

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
                'client_users.id', 
                'client_users.first_name', 
                'client_users.last_name', 
                'client_users.email', 
                'client_users.job_title', 
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
            // Stored in the app's time zone (UTC); shown in Lagos time
            $this->lockEta = \Carbon\Carbon::parse($activeSending->estimated_completion_time)->timezone('Africa/Lagos')->format('H:i') . ' WAT';
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
        if (! Auth::user()->can('broadcasts.create')) {
            session()->flash('error', "You don't have permission to write broadcasts. Ask an administrator to change your role.");
            return;
        }

        $this->validate([
            'rawBody' => 'required|string|min:10',
            'selectedTone' => 'required|string'
        ]);

        // Brand voice for client emails: docs/BRAND.md sections 4 and 13. The email layout adds the sign-off.
        $prompt = "You write emails for JBS Praxis, a consulting and engineering firm in Lagos and Douala. Rewrite the draft below in a '{$this->selectedTone}' tone that stays plain, precise, warm and measured.
        RULES:
        1. Write in the draft's language: British English, or French (courant soigné, vouvoiement).
        2. Two or three short paragraphs, short sentences, sentence case. No exclamation marks, no hype (revolutionary, cutting-edge, seamless, robust, unlock, leverage, elite), no guaranteed outcomes.
        3. Output HTML using only <p>, <strong>, <ul> and <li>. No <html> or <body> tags.
        4. Open with 'Hello {{ first_name }},' in English or 'Bonjour,' in French. Do not add a sign-off or signature: the email adds one.
        5. You may use {{ company_name }} and {{ industry }}.

        Draft: {$this->rawBody}";

        try {
            $apiKey = config('services.gemini.api_key');
            
            $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["parts" => [["text" => $prompt]]]]
            ]);
            
            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                $this->enhancedBody = preg_replace('/```(?:html)?\n?|\n?```/', '', $rawText);
                session()->flash('success', 'Draft rewritten. Read the email text and edit it before you send it.');
            } else {
                logger()->warning('Broadcast rewrite failed', ['status' => $response->status()]);
                session()->flash('error', "We couldn't rewrite the draft because the writing assistant returned an error. Try again in a minute, or write the email text yourself.");
            }
        } catch (\Exception $e) {
            session()->flash('error', "The writing assistant didn't reply in time. Try again in a minute, or write the email text yourself.");
        }
    }

    // --- 4. SEND AND NOTIFY ---
    public function dispatchCampaign()
    {
        // Sending to clients authorises the broadcast
        if (! Auth::user()->can('broadcasts.approve')) {
            session()->flash('error', "You don't have permission to send broadcasts. Ask an administrator to change your role.");
            return;
        }

        $this->checkSystemLock();
        
        if ($this->activeLock) {
            session()->flash('error', "Another broadcast is still sending. You can send this one after about {$this->lockEta}.");
            return;
        }

        if (count($this->selectedTargets) === 0) {
            session()->flash('error', 'Choose at least one recipient, then send the broadcast.');
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

            // 3. System Notification for staff who follow broadcasts
            $executiveIds = Access::staffWith('broadcasts.approve');

            $notificationInserts = [];
            foreach ($executiveIds as $adminId) {
                $notificationInserts[] = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminId,
                    'title' => 'Broadcast sending',
                    'message' => "'{$this->subject}' is queued for {$finalCount} recipients. Sending should finish in about {$totalMinutes} minutes. Follow it in Broadcasts.",
                    'link_url' => route('hq.crm.broadcast', [], false),
                    'type' => 'System',
                    'is_read' => 0,
                    'created_at' => now()
                ];
            }

            if (!empty($notificationInserts)) {
                DB::table('notifications')->insert($notificationInserts);
            }

            $this->activeLock = true;
            $this->lockEta = $eta->copy()->timezone('Africa/Lagos')->format('H:i') . ' WAT';
        });

        // Reset UI & Navigate to Queued Tab
        $sentCount = count($this->selectedTargets);
        $this->reset(['subject', 'rawBody', 'enhancedBody']);
        $this->activeTab = 'queued';
        $this->loadLedgerData();
        
        session()->flash('success', "Broadcast queued for {$sentCount} recipients. Sending should finish at about {$this->lockEta}; new broadcasts can be sent after that. Staff who approve broadcasts have been notified.");
    }

    // --- 5. QUEUED, SENT AND FAILED LISTS ---
    private function loadLedgerData()
    {
        $this->campaigns = DB::table('broadcast_campaigns')->orderBy('created_at', 'desc')->get()->toArray();

        if ($this->activeTab === 'queued') {
            $this->queuedLogs = DB::table('broadcast_logs')
                ->join('client_users', 'broadcast_logs.user_id', '=', 'client_users.id')
                ->join('organizations', 'client_users.organization_id', '=', 'organizations.id')
                ->join('broadcast_campaigns', 'broadcast_logs.campaign_id', '=', 'broadcast_campaigns.id')
                ->where('broadcast_logs.status', 'Queued')
                ->select('broadcast_logs.*', 'client_users.email', 'client_users.first_name', 'client_users.last_name', 'organizations.name as company_name', 'broadcast_campaigns.subject')
                ->orderBy('broadcast_logs.created_at', 'desc')->limit(200)->get()->toArray();
        }

        if ($this->activeTab === 'sent') {
            $this->sentLogs = DB::table('broadcast_logs')
                ->join('client_users', 'broadcast_logs.user_id', '=', 'client_users.id')
                ->join('organizations', 'client_users.organization_id', '=', 'organizations.id')
                ->join('broadcast_campaigns', 'broadcast_logs.campaign_id', '=', 'broadcast_campaigns.id')
                ->where('broadcast_logs.status', 'Sent')
                ->select('broadcast_logs.*', 'client_users.email', 'client_users.first_name', 'client_users.last_name', 'organizations.name as company_name', 'broadcast_campaigns.subject')
                ->orderBy('broadcast_logs.updated_at', 'desc')->limit(200)->get()->toArray();
        }

        if ($this->activeTab === 'failed') {
            $this->failedLogs = DB::table('broadcast_logs')
                ->join('client_users', 'broadcast_logs.user_id', '=', 'client_users.id')
                ->join('organizations', 'client_users.organization_id', '=', 'organizations.id')
                ->join('broadcast_campaigns', 'broadcast_logs.campaign_id', '=', 'broadcast_campaigns.id')
                ->where('broadcast_logs.status', 'Failed')
                ->select('broadcast_logs.*', 'client_users.email', 'client_users.first_name', 'client_users.last_name', 'organizations.name as company_name', 'broadcast_campaigns.subject')
                ->orderBy('broadcast_logs.updated_at', 'desc')->limit(200)->get()->toArray();
        }
    }

    public function render()
    {
        return view('livewire.hq.intelligence.broadcast-ledger');
    }
}