<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class SignoffManager extends Component
{
    // What this user may do here (set in mount, see config/access.php)
    #[Locked]
    public bool $canDraft = false;      // signoffs.create: synthesize handovers
    #[Locked]
    public bool $canUnfreeze = false;   // signoffs.approve: defrost a frozen signoff
    #[Locked]
    public bool $canViewKaizen = false; // kaizen.view: AI marketing push tab and its ledgers (portfolio + Kaizen)
    #[Locked]
    public bool $canPushKaizen = false; // kaizen.create: generate and commit portfolio/Kaizen drafts

    public $mainTab = 'ledger';
    public $marketingTab = 'portfolio_gen'; 

    public $signoffs = [];
    public $eligibleProjects = [];
    public $portfolioLedger = [];
    public $kaizenLedger = [];

    public $isGenerating = false;
    public $draftSignatoryEmail = '';
    public $draftProjectId = '';

    public $selectedSignoffForMarketing = '';
    public $marketingTone = 'Clinical & Authoritative'; 
    public $marketingSanitizationRules = 'Strip all raw financial data and exact company names. Retain percentages.';
    
    public $generatedTitle = '';
    public $generatedNarrative = '';
    public $generatedQuote = '';
    public $generatedDallePrompt = ''; 

    public function mount()
    {
        $user = Auth::user();
        $this->canDraft = $user->can('signoffs.create');
        $this->canUnfreeze = $user->can('signoffs.approve');
        $this->canViewKaizen = $user->can('kaizen.view');
        $this->canPushKaizen = $user->can('kaizen.create');
        if (! $this->canPushKaizen) {
            $this->marketingTab = 'portfolio_ledger';
        }
        $this->loadLedgers();
    }

    public function loadLedgers()
    {
        $this->signoffs = DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->select('project_signoffs.*', 'projects.title as project_title', 'organizations.name as client_name')
            ->orderBy('project_signoffs.created_at', 'desc')
            ->get()
            // The sign-off link works like a client password: only people who issue sign-offs receive it
            ->map(function ($signoff) {
                if (! $this->canDraft) {
                    $signoff->token = null;
                }
                return $signoff;
            })
            ->toArray();

        // FIXED: Bridging through project_phases to check task eligibility
        $this->eligibleProjects = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereIn('projects.status', ['Active', 'Locked'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('tasks')
                      ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                      ->whereColumn('project_phases.project_id', 'projects.id')
                      ->where('tasks.status', '!=', 'Done');
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('tasks')
                      ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                      ->whereColumn('project_phases.project_id', 'projects.id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('project_signoffs')
                      ->whereColumn('project_signoffs.project_id', 'projects.id');
            })
            ->select('projects.id', 'projects.title', 'organizations.name as client_name')
            ->get()->toArray();

        if ($this->canViewKaizen) {
            $this->portfolioLedger = DB::table('portfolio_case_studies')->orderBy('created_at', 'desc')->get()->toArray();
            $this->kaizenLedger = DB::table('kaizen_articles')->orderBy('created_at', 'desc')->get()->toArray();
        }
    }

    public function switchMainTab($tab) { $this->mainTab = $tab; $this->loadLedgers(); }
    public function switchMarketingTab($tab) { $this->marketingTab = $tab; $this->loadLedgers(); }

    public function generateSignoffDraft()
    {
        abort_unless(Auth::user()->can('signoffs.create'), 403);

        $this->validate([
            'draftProjectId' => 'required',
            'draftSignatoryEmail' => 'required|email'
        ], [], ['draftProjectId' => 'project', 'draftSignatoryEmail' => 'signatory email']);

        $this->isGenerating = true;

        $project = DB::table('projects')->where('id', $this->draftProjectId)->first();
        
        // FIXED: Bridging through project_phases
        $tasks = DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->draftProjectId)
            ->get();
            
        $timeLogs = DB::table('task_time_logs')
            ->join('tasks', 'task_time_logs.task_id', '=', 'tasks.id')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->draftProjectId)
            ->get();
            
        $qaTickets = DB::table('qa_tickets')->where('project_id', $this->draftProjectId)->get();

        $totalPoints = $tasks->sum('story_points');
        $totalHours = $timeLogs->sum('duration_minutes') / 60;
        $reworkHours = $timeLogs->where('is_rework', 1)->sum('duration_minutes') / 60;
        $bugsReported = $qaTickets->count();

        $prompt = "You are an elite Enterprise Architect generating a legal Project Signoff Document.
        Analyze this project execution data:
        - Project Title: {$project->title}
        - Total Story Points Delivered: {$totalPoints}
        - Total Hours Executed: {$totalHours}
        - COPQ Rework Hours: {$reworkHours}
        - QA Bugs Patched: {$bugsReported}
        
        Generate ONLY a valid JSON response with this exact structure:
        {
            \"executive_summary\": \"A highly professional, 2-paragraph summary of what was built and delivered, focusing on architectural stability.\",
            \"roi_metrics\": [
                {\"metric\": \"Metric Name (e.g., Latency Reduction)\", \"value\": \"85%\", \"description\": \"Short context\"}
            ]
        }";

        try {
            $apiKey = config('services.gemini.api_key'); 
            $response = Http::timeout(60)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["parts" => [["text" => $prompt]]]]
            ]);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                $jsonString = preg_replace('/```(?:json)?\n?|\n?```/', '', $rawText);
                $parsedData = json_decode($jsonString, true);

                if (isset($parsedData['executive_summary']) && isset($parsedData['roi_metrics'])) {
                    DB::table('project_signoffs')->insert([
                        'id' => (string) Str::uuid(),
                        'project_id' => $this->draftProjectId,
                        'token' => hash('sha256', Str::random(40)),
                        'signatory_email' => $this->draftSignatoryEmail,
                        'executive_summary' => $parsedData['executive_summary'],
                        'roi_metrics_achieved' => json_encode($parsedData['roi_metrics']),
                        'status' => 'Draft',
                        'created_at' => now()
                    ]);
                    session()->flash('success', 'Sign-off drafted. Copy its link and send it to the signatory.');
                    $this->draftProjectId = ''; $this->draftSignatoryEmail = '';
                } else {
                    session()->flash('error', 'We couldn’t read the assistant’s draft. Draft the sign-off again.');
                }
            } else {
                session()->flash('error', 'We couldn’t reach the assistant. Try again in a few minutes; if it keeps failing, ask an administrator to check the AI settings.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'The assistant took too long to answer. Try again in a few minutes.');
        }

        $this->isGenerating = false;
        $this->loadLedgers();
    }

    public function generateMarketingAsset()
    {
        abort_unless(Auth::user()->can('kaizen.create'), 403);

        $this->validate(['selectedSignoffForMarketing' => 'required'], [], ['selectedSignoffForMarketing' => 'signed project']);
        $this->isGenerating = true;

        $signoff = DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('project_signoffs.id', $this->selectedSignoffForMarketing)
            ->select('project_signoffs.*', 'projects.title', 'organizations.name', 'organizations.industry')
            ->first();

        if(!$signoff) return;

        $isPortfolio = $this->marketingTab === 'portfolio_gen';
        
        $prompt = "You are the Chief Marketing Architect for JBS Praxis. 
        Sanitize this project into a public " . ($isPortfolio ? "Portfolio Case Study" : "Kaizen Blog Article") . ".
        Tone: {$this->marketingTone}.
        Sanitization Rule: {$this->marketingSanitizationRules}.
        
        Original Data: Client: {$signoff->name} | Industry: {$signoff->industry} | Project: {$signoff->title}
        Exec Summary: {$signoff->executive_summary}
        ROI: {$signoff->roi_metrics_achieved}
        
        Return ONLY JSON: { \"title\": \"...\", \"narrative\": \"...\", \"suggested_quote\": \"...\", \"dalle_prompt\": \"...\" }";

        try {
            $apiKey = config('services.gemini.api_key'); 
            $response = Http::timeout(60)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["parts" => [["text" => $prompt]]]]
            ]);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                $parsedData = json_decode(preg_replace('/```(?:json)?\n?|\n?```/', '', $rawText), true);

                $this->generatedTitle = $parsedData['title'] ?? '';
                $this->generatedNarrative = $parsedData['narrative'] ?? '';
                $this->generatedQuote = $parsedData['suggested_quote'] ?? '';
                $this->generatedDallePrompt = $parsedData['dalle_prompt'] ?? '';
                session()->flash('success', 'Draft ready. Check and edit it, then save it as a draft.');
            }
        } catch (\Exception $e) { session()->flash('error', 'We couldn’t create the draft. Try again in a few minutes.'); }

        $this->isGenerating = false;
    }

    public function commitMarketingAsset()
    {
        abort_unless(Auth::user()->can('kaizen.create'), 403);

        if ($this->marketingTab === 'portfolio_gen') {
            DB::table('portfolio_case_studies')->insert([
                'id' => (string) Str::uuid(),
                'client_pseudonym' => 'Confidential Client',
                'title' => $this->generatedTitle,
                'industry' => 'Various',
                'challenge' => 'Generated via automated signoff pipeline.',
                'audit' => $this->generatedNarrative,
                'architecture_deployed' => 'N/A',
                'roi_metrics' => json_encode(['info' => 'See full text']),
                'is_published' => 0,
                'created_by' => Auth::id(),
                'created_at' => now()
            ]);
        } else {
            DB::table('kaizen_articles')->insert([
                'id' => (string) Str::uuid(),
                'category' => 'The Boardroom',
                'title' => $this->generatedTitle,
                'executive_summary' => Str::limit(strip_tags($this->generatedNarrative), 200),
                'problem_statement' => $this->generatedNarrative,
                'engineered_solution' => 'See narrative.',
                'final_roi' => 'N/A',
                'is_public' => 0,
                'status' => 'Draft',
                'created_by' => Auth::id(),
                'created_at' => now()
            ]);
        }

        DB::table('project_signoffs')->where('id', $this->selectedSignoffForMarketing)->update(['is_pushed_to_kaizen' => 1]);
        $this->generatedTitle = ''; $this->generatedNarrative = ''; $this->generatedQuote = ''; $this->generatedDallePrompt = '';
        $this->loadLedgers();
        session()->flash('success', 'Draft saved. It stays unpublished until someone publishes it.');
    }

    public function unfreezeSignoff($signoffId)
    {
        if (! Auth::user()->can('signoffs.approve')) return;
        DB::transaction(function () use ($signoffId) {
            $signoff = DB::table('project_signoffs')->where('id', $signoffId)->first();
            if (!$signoff) return;

            DB::table('project_signoffs')->where('id', $signoffId)->update(['status' => 'Signed', 'frozen_at' => null, 'updated_at' => now()]);
            DB::table('projects')->where('id', $signoff->project_id)->update(['status' => 'Active', 'updated_at' => now()]);
        });
        $this->loadLedgers();
        session()->flash('success', 'Sign-off unfrozen. The project is active again and its sprint board is open.');
    }

    public function render() { return view('livewire.hq.operations.signoff-manager'); }
}