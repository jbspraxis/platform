<?php

namespace App\Livewire\Hq\Intelligence;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\FreeAuditDeclinedMail;
use App\Mail\MeetingApprovedMail;
// use App\Mail\CopqReportMail; // Commented out for now until logic is finalized
use App\Support\MailLocale;
use App\Support\Ai\CompanyBrief;
use App\Models\Opportunity;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\OpportunityAccess;
use Carbon\CarbonImmutable;

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

    // Master Data Collections
    public $dbIndustries = [], $dbArchitectures = [], $dbBottlenecks = [], $dbCurrencies = [];

    // INLINE COPQ FORM STATE
    public $inlineIndustry = '', $inlineTool = '', $inlineBottleneck = '', $inlineCurrency = 'USD';
    public $inlineTurnover = 0, $inlineHeadcount = 1, $inlineMgmtNodes = 1, $inlineAcv = 0;
    public $inlineManualHrs = 5, $inlineHourlyCost = 50, $inlineErrorRate = 3.0;

    public function mount()
    {
        $this->loadLobby();
        
        $this->dbIndustries = DB::table('mdm_copq_industries')->orderBy('industry_name')->get();
        $this->dbArchitectures = DB::table('mdm_copq_architectures')->orderBy('display_name')->get();
        $this->dbBottlenecks = DB::table('mdm_copq_bottlenecks')->orderBy('bottleneck_name')->get();
        $this->dbCurrencies = DB::table('mdm_exchange_rates')->orderBy('currency_code')->get();
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

    public function approveMeeting($appointmentId, $meetingLink = '')
    {
        if (! Auth::user()->can('crm.approve')) {
            session()->flash('error', "You don’t have permission to approve free audits. Ask an administrator to change your role.");
            return;
        }

        // With Google connected, HQ creates the meeting itself (docs/OPPORTUNITIES.md, PR 2); otherwise a pasted link, as before
        if (app(GoogleWorkspace::class)->isConnected()) {
            $this->approveWithGoogle((string) $appointmentId);
            return;
        }

        if(empty(trim((string) $meetingLink))) {
            session()->flash('error', 'Add the meeting link, then approve the free audit again.');
            return;
        }

        // A link pasted without https:// would be a dead button in the client’s confirmation
        $meetingLink = trim($meetingLink);
        if (!preg_match('~^https?://~i', $meetingLink)) {
            $meetingLink = 'https://' . ltrim($meetingLink, '/');
        }
        if (!filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            session()->flash('error', "That meeting link isn’t complete. Enter the full link, for example https://meet.google.com/abc-defg-hij.");
            return;
        }

        // Only a request still waiting: it may have been declined, or closed hourly once its start time passed
        $approved = DB::table('appointments')->where('id', $appointmentId)->where('status', 'Requested')->update([
            'status' => 'Scheduled',
            'meeting_link' => trim($meetingLink),
            'updated_by' => Auth::id() ?? 'system',
            'updated_at' => now()
        ]) === 1;
        if (! $approved) {
            session()->flash('error', 'This request was already approved, declined or closed because its time passed, so nothing changed. The list is up to date now.');
            $this->loadLobby();
            return;
        }

        // Find the client’s email address and send the meeting link
        $appt = DB::table('appointments')->where('id', $appointmentId)->first();
        $org = DB::table('organizations')->where('id', $appt->organization_id)->first();
        $clientEmail = $this->clientEmail($appt, $org);

        if ($clientEmail) {
            try {
                // Greet the contact by first name when they have a record; the organisation’s stored language, else English
                $firstName = DB::table('client_users')->where('email', $clientEmail)->value('first_name');
                Mail::to($clientEmail)
                    ->locale(MailLocale::forRecipient($org->preferred_language ?? null))
                    ->send(new MeetingApprovedMail($appt, $org->name, trim($meetingLink), $firstName));
                session()->flash('success', "Free audit approved. We sent the meeting link to {$clientEmail}.");
            } catch (\Exception $e) {
                session()->flash('error', "Free audit approved, but we couldn’t email the meeting link to {$clientEmail}. Send it to the client yourself.");
            }
        } else {
            session()->flash('success', 'Free audit approved. There is no client email on file, so send the meeting link to the client yourself.');
        }

        $this->loadLobby();
    }

    /**
     * Approve through Google: the booking joins the organisation's open opportunity (or opens one, source
     * website_booking, led by whoever approves), becomes a client meeting in praxisjbs@gmail.com's calendar with a Meet
     * link (Google invites the client), and the client gets the free audit email with that link. If Google refuses,
     * the request waits again and nothing is sent.
     */
    private function approveWithGoogle(string $appointmentId): void
    {
        // Claim the request first, so two people approving at once can't create two meetings
        $claimed = DB::table('appointments')->where('id', $appointmentId)->where('status', 'Requested')->update([
            'status' => 'Scheduled', 'updated_by' => Auth::id() ?? 'system', 'updated_at' => now(),
        ]) === 1;
        if (! $claimed) {
            session()->flash('error', 'This request was already approved, declined or closed because its time passed, so nothing changed. The list is up to date now.');
            $this->loadLobby();
            return;
        }

        $appt = DB::table('appointments')->where('id', $appointmentId)->first();
        $org = DB::table('organizations')->where('id', $appt->organization_id)->first();
        $clientEmail = $this->clientEmail($appt, $org);
        $booker = $appt->created_by && DB::table('client_users')->where('id', $appt->created_by)->where('organization_id', $org->id)->whereNull('deleted_at')->exists()
            ? $appt->created_by : null;
        $starts = CarbonImmutable::parse($appt->start_time, Meetings::TIME_ZONE);
        $ends = $appt->end_time ? CarbonImmutable::parse($appt->end_time, Meetings::TIME_ZONE) : null;
        $ends = $ends && $ends->greaterThan($starts) ? $ends : $starts->addHour();

        try {
            $opportunity = DB::transaction(fn () => $this->opportunityForBooking($org, $appt));
            $change = Meetings::schedule($opportunity, [
                'kind' => 'client',
                'title' => $appt->title ?: 'Free audit',
                'starts_at' => $starts,
                'ends_at' => $ends,
                'staff' => array_values(array_unique(array_filter([Auth::id(), $opportunity->lead_user_id]))),
                'contacts' => $booker ? [$booker] : [],
                'guests' => ! $booker && $clientEmail ? [['email' => $clientEmail, 'name' => null]] : [],
                'appointment_id' => $appt->id,
            ], brandedEmail: false);
        } catch (\Throwable $e) {
            DB::table('appointments')->where('id', $appt->id)->update(['status' => 'Requested', 'updated_at' => now()]);
            if (! $e instanceof GoogleUnavailable) {
                report($e);
            }
            session()->flash('error', __('meetings.approval.google_failed', ['reason' => $e instanceof GoogleUnavailable ? $e->getMessage() : __('integrations.google.errors.failed')]));
            $this->loadLobby();
            return;
        }

        $link = $change->meeting->meet_link;
        DB::table('appointments')->where('id', $appt->id)->update(['meeting_link' => $link]);
        $appt = DB::table('appointments')->where('id', $appt->id)->first();

        if (! $clientEmail) {
            session()->flash('success', __('meetings.approval.done_no_email'));
        } else {
            try {
                $firstName = DB::table('client_users')->where('email', $clientEmail)->value('first_name');
                Mail::to($clientEmail)
                    ->locale(MailLocale::forRecipient($org->preferred_language ?? null))
                    ->send(new MeetingApprovedMail($appt, $org->name, $link, $firstName));
                session()->flash('success', __('meetings.approval.done', ['email' => $clientEmail]));
            } catch (\Exception $e) {
                session()->flash('error', __('meetings.approval.done_not_emailed', ['email' => $clientEmail]));
            }
        }

        $this->loadLobby();
    }

    /** The organisation's open opportunity, else a new one from this booking */
    private function opportunityForBooking(object $org, object $appt): Opportunity
    {
        $open = Opportunity::where('organization_id', $org->id)->whereNotIn('stage', Opportunity::CLOSED)->orderByDesc('updated_at')->first();
        if ($open) {
            return $open;
        }

        $title = trim(Str::after((string) $appt->title, ':')) ?: $org->name;
        $opportunity = Opportunity::create([
            'organization_id' => $org->id,
            'title' => Str::limit($title, 200, ''),
            'stage' => 'discovery',
            'lead_user_id' => Auth::id(),
            'language' => MailLocale::parse($org->preferred_language ?? null) ?? 'en',
            'source' => 'website_booking',
            'created_by' => Auth::id(),
        ]);
        OpportunityAccess::log('OPPORTUNITY_OPENED', "Opened the opportunity “{$opportunity->title}” for {$org->name} from its website booking.", 'opportunities', $opportunity->id,
            [], $opportunity->only(['organization_id', 'title', 'stage', 'lead_user_id', 'language', 'source']));

        return $opportunity;
    }

    /**
     * Decline a request that can't go ahead (wrong time, spam, a test): the appointment is Cancelled, so the
     * time is free again on the website and the visitor can book another. The reason stays with the team (audit
     * log); when $notify is on, the visitor gets a short, polite email offering another time, without the reason.
     */
    public function declineRequest($appointmentId, $reason = '', $notify = true)
    {
        if (! Auth::user()->can('crm.approve')) {
            session()->flash('error', "You don’t have permission to decline free audits. Ask an administrator to change your role.");
            return;
        }

        $notify = filter_var($notify, FILTER_VALIDATE_BOOLEAN);
        $reason = Str::limit(trim((string) $reason), 500, '');
        if ($reason === '') {
            session()->flash('error', 'Give a reason for declining, so the team knows why.');
            return;
        }

        $appt = DB::table('appointments')->where('id', $appointmentId)->first();
        // Only a request still waiting: someone may have approved or declined it a moment ago
        $declined = $appt && $appt->status === 'Requested'
            && DB::table('appointments')->where('id', $appt->id)->where('status', 'Requested')->update([
                'status' => 'Cancelled',
                'updated_by' => Auth::id() ?? 'system',
                'updated_at' => now(),
            ]) === 1;
        if (! $declined) {
            session()->flash('error', 'This request was already approved or declined, so nothing changed. The list is up to date now.');
            $this->loadLobby();
            return;
        }

        $org = DB::table('organizations')->where('id', $appt->organization_id)->first();
        $companyName = $org->name ?? 'your organisation';
        $clientEmail = $notify ? $this->clientEmail($appt, $org) : null;
        $emailed = false;

        if (! $notify) {
            session()->flash('success', 'Request declined. The time is free again on the website, and the client wasn’t emailed.');
        } elseif ($clientEmail) {
            try {
                $firstName = DB::table('client_users')->where('email', $clientEmail)->value('first_name');
                Mail::to($clientEmail)
                    ->locale(MailLocale::forRecipient($org->preferred_language ?? null))
                    ->send(new FreeAuditDeclinedMail($appt, $companyName, $firstName));
                $emailed = true;
                session()->flash('success', "Request declined. We emailed {$clientEmail} to offer another time.");
            } catch (\Exception $e) {
                session()->flash('error', "Request declined, but we couldn’t email {$clientEmail}. Let the client know yourself.");
            }
        } else {
            session()->flash('success', 'Request declined. There is no client email on file, so no one was emailed.');
        }

        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => 'FREE_AUDIT_DECLINED',
            'description' => "Declined the free audit request from {$companyName} for {$appt->start_time} WAT. Reason: {$reason}",
            'target_table' => 'appointments',
            'target_id' => $appt->id,
            'old_values' => json_encode(['status' => 'Requested']),
            'new_values' => json_encode(['status' => 'Cancelled', 'reason' => $reason, 'client_emailed' => $emailed], JSON_UNESCAPED_UNICODE),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);

        $this->loadLobby();
    }

    /** The person who booked first (website bookings store them in created_by), then the latest report contact, then the primary contact */
    private function clientEmail($appt, $org): ?string
    {
        if (! $org) {
            return null;
        }

        $bookerEmail = $appt->created_by ? DB::table('client_users')->where('id', $appt->created_by)->whereNull('deleted_at')->value('email') : null;
        $copq = DB::table('copq_assessments')->where('organization_id', $org->id)->orderBy('created_at', 'desc')->first();
        $clientEmail = $bookerEmail ?: ($copq ? $copq->corporate_email : null);

        if (!$clientEmail) {
            $user = DB::table('client_users')->where('organization_id', $org->id)->whereNull('deleted_at')->orderByDesc('is_primary_client')->first();
            $clientEmail = $user ? $user->email : null;
        }

        return $clientEmail ?: null;
    }

    // ==========================================
    // 2. START THE FREE AUDIT
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
            $prompt = "Summarise the following notes from earlier conversations with this client in one short, plain paragraph for the JBS Praxis consultant who is about to join a call with them. Use British English and everyday business words. Do not add facts that are not in the notes. Notes: " . $historyText;

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
    // 3. COST OF POOR QUALITY ESTIMATE (same formula as the website calculator)
    // ==========================================
    public function generateInlineCopq()
    {
        if (! Auth::user()->can('crm.create')) {
            session()->flash('error', "You don’t have permission to create cost of poor quality estimates. Ask an administrator to change your role.");
            return;
        }

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
        
        $companyName = $this->leadData->name;
        $fallbackTemplate = $indData ? $indData->fallback_diagnostic : "We found signs of avoidable cost in how {COMPANY_NAME} runs today. We recommend a review of the main bottleneck and of the approvals around it.";
        $aiAnalysis = str_replace('{COMPANY_NAME}', $companyName, $fallbackTemplate);

        // 3. The prompt (voice: docs/BRAND.md section 4)
        $systemPrompt = "You are a senior consultant at JBS Praxis, a consulting and engineering firm for Africa’s institutions. Write a plain, measured 3-sentence analysis (max 600 characters) for {$companyName}.
        CONTEXT: Their sector type is [{$archetype}]. They use [{$tool}] and their main bottleneck is [{$bottleneck}].
        RULE 1: Say where the bottleneck most likely costs them time or money.
        RULE 2: Recommend this approach: [{$archMandate}].
        RULE 3: Use the everyday words of a {$archetype} sector (e.g., if SOVEREIGN use 'public funds and services', if IMPACT use 'donor compliance and beneficiary reach', if ASSET_HEAVY use 'downtime and supply chain losses', if COMMERCIAL use 'margins and client churn').
        RULE 4: Use British English, short sentences and plain business words. No hype, no war, hacking or medical metaphors, no guaranteed outcomes: write 'we estimate' or 'in our assessment'.";
        
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
            // Keep the fallback summary from master data
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

        // 6. Save the assessment
        $token = 'PRX-' . strtoupper(Str::random(24)) . '-' . date('Y');
        
        $user = DB::table('client_users')->where('organization_id', $this->activeOrgId)->whereNull('deleted_at')->orderByDesc('is_primary_client')->first();
        $execEmail = $user ? $user->email : 'client@' . strtolower(str_replace(' ', '', $companyName)) . '.com';
        $execName = $user ? $user->first_name . ' ' . $user->last_name : 'Leadership team';

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
        
        session()->flash('success', 'Cost of poor quality estimate saved.');
    }

    // ==========================================
    // X. SEND THE REPORT (opens the choice of channel)
    // ==========================================
    public function dispatchCopqEmail()
    {
        // Email sending is not wired up yet; the page offers WhatsApp for now.
        
        /*
        if (!$this->copqData || empty($this->copqData->corporate_email)) {
            session()->flash('error', "We couldn’t send the report because this lead has no email address. Add one, then send the report again.");
            return;
        }

        try {
            // Re-sent from HQ: the organisation’s stored language, else English
            Mail::to($this->copqData->corporate_email)
                ->locale(MailLocale::forRecipient($this->leadData->preferred_language ?? null))
                ->send(new CopqReportMail($this->copqData, true));
            session()->flash('success', 'Cost of poor quality report sent to the client.');
        } catch (\Exception $e) {
            session()->flash('error', "We couldn’t send the report because email isn’t set up. Check the mail settings in System health.");
        }
        */

        // Triggering a frontend event to open the upcoming Email/WhatsApp modal
        $this->dispatch('open-transmission-modal');
        session()->flash('info', 'Choose how to send the report. Sending by email is not available yet.');
    }

    // ==========================================
    // 4. MARK THE LEAD AS LOST
    // ==========================================
    public function disqualifyLead($reason = '')
    {
        if (! Auth::user()->can('crm.edit')) {
            session()->flash('error', "You don’t have permission to change leads. Ask an administrator to change your role.");
            return;
        }

        // Only approvers may archive without a written reason
        if (! Auth::user()->can('crm.approve') && empty(trim($reason))) {
            session()->flash('error', 'Give a reason for marking this lead as lost. Only approvers can leave the reason blank.');
            return;
        }

        DB::table('organizations')->where('id', $this->activeOrgId)->update([
            'status' => 'Lost',
            'pipeline_stage' => 'Won / Lost',
            'disqualification_reason' => trim($reason) ?: 'Marked as lost by an approver, no reason given',
            'updated_at' => now()
        ]);

        if ($this->activeApptId) {
            DB::table('appointments')->where('id', $this->activeApptId)->update(['status' => 'Completed']);
        }

        $this->activeView = 'lobby';
        $this->loadLobby();
    }

    // ==========================================
    // 5. CLOSE THE FREE AUDIT AND DRAFT THE PROPOSAL
    // ==========================================
    public function closeAndSynthesize($rawNotesData)
    {
        if (! Auth::user()->can('crm.create')) {
            $this->dispatch('consultation-error');
            session()->flash('error', "You don’t have permission to create proposals. Ask an administrator to change your role.");
            return;
        }

        // 1. Gather Inputs
        $ctx = $rawNotesData['context'] ?? '';
        $vis = $rawNotesData['vision'] ?? '';
        $pain = $rawNotesData['pain'] ?? '';
        $sla = $rawNotesData['sla'] ?? '';
        $concatNotes = "Context and operations: $ctx \n\nGrowth plans: $vis \n\nBottlenecks: $pain \n\nRequirements and budget: $sla";

        // 2. The cost of poor quality estimate, for context
        $copq = DB::table('copq_assessments')->where('organization_id', $this->activeOrgId)->orderBy('created_at', 'desc')->first();
        $leakageStr = $copq ? "Estimated cost of poor quality a year: {$copq->copq_currency} " . number_format($copq->calculated_leakage) : "Not scored";
        $bottleneckStr = $copq ? $copq->bottleneck : "Not recorded";
        $techStackStr = $copq ? $copq->primary_tool : "Not recorded";

        // 3. Save raw notes to CRM History natively
        DB::table('crm_notes')->insert([
            'id' => (string) Str::uuid(), 'organization_id' => $this->activeOrgId,
            'author_id' => Auth::id() ?? 'system', 'note_text' => $concatNotes,
            'interaction_type' => 'Meeting', 'created_at' => now(), 'updated_at' => now()
        ]);

        // 4. Who JBS Praxis is and how it writes: the one copy every AI job reads (docs/BRAND.md sections 1, 4 and 5)
        $companyDNA = CompanyBrief::text();

        // 5. The prompt
        $prompt = "
        $companyDNA

        TASK: You are a senior consultant at JBS Praxis. Read the consultant’s notes from the free audit and the cost of poor quality estimate below, and write the narrative of a proposal (statement of work) as STRICT JSON.

        CLIENT DATA:
        - Main bottleneck: $bottleneckStr
        - Systems in use: $techStackStr
        - $leakageStr
        - Consultant notes: $concatNotes

        RULES FOR JSON KEYS:
        1. 'ai_executive_summary': Exactly 2 paragraphs. P1 states the situation and the estimated cost of poor quality ($leakageStr), as an estimate. P2 states what we recommend and what it changes for the organisation.
        2. 'ai_operational_strategy': Exactly 2 paragraphs on how we will do the work, following the Praxis Method (See, Design, Build, Hold). Mention moving from their current systems ($techStackStr) to the system we build, and how their team will take it over.
        3. 'ai_slas_json': An array of exactly 4 measurable service levels we propose, based on their notes, written as proposals, not guarantees (e.g., [{'title': 'Support response', 'value': 'Within 1 working day'}, {'title': 'Data migration', 'value': 'Reconciled before go-live'}]). Titles max 25 characters, values max 40 characters, sentence case.
        4. 'ai_case_study': Choose the most relevant item from APPROVED PROOF and describe it in 2 or 3 sentences: the situation and the system we built. Do not invent a client, a figure or a result. If nothing fits, describe how the Praxis Method would apply to this client instead, without claiming a past result.

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
            report($e);
            session()->flash('error', "We couldn’t draft the proposal because the writing assistant didn’t return a usable draft. Your notes are saved in the lead’s history. Try again in a minute.");
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
            // ai_phases_json omitted as it wasn’t requested in prompt rules, defaults to NULL based on your schema
            'generated_at' => now()
        ]);

        DB::table('organizations')->where('id', $this->activeOrgId)->update(['pipeline_stage' => 'Proposal Sent', 'updated_at' => now()]);
        
        if ($this->activeApptId) {
            DB::table('appointments')->where('id', $this->activeApptId)->update(['status' => 'Completed', 'updated_at' => now()]);
        }

        $this->dispatch('consultation-closed');
        
        $this->activeView = 'lobby';
        $this->loadLobby();
        session()->flash('success', 'Free audit closed. A draft proposal is ready in Proposals.');
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'inlineIndustry' => 'industry',
            'inlineHeadcount' => 'headcount',
            'inlineTurnover' => 'annual turnover',
            'inlineManualHrs' => 'manual hours a week',
            'inlineErrorRate' => 'error rate',
            'inlineMgmtNodes' => 'managers who approve work',
            'inlineHourlyCost' => 'hourly cost of a manager',
            'inlineTool' => 'systems in use',
            'inlineBottleneck' => 'main bottleneck',
        ];
    }

    public function render()
    {
        return view('livewire.hq.intelligence.live-consultation', [
            'googleConnected' => app(GoogleWorkspace::class)->isConnected(),
        ]);
    }
}