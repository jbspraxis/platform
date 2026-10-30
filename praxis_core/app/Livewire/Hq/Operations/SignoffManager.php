<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

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
    #[Locked]
    public bool $canPublishKaizen = false; // kaizen.approve: publish and unpublish Insights articles

    /** Stored Insights article statuses (kaizen_articles.status) and what staff read */
    public const KAIZEN_STATUSES = ['Pending Moderation' => 'Waiting for review', 'Draft' => 'Draft', 'Published' => 'Published', 'Rejected' => 'Rejected'];

    /** The only status changes HQ makes to an article: from these statuses to that one */
    public const KAIZEN_CHANGES = [
        'publish' => ['from' => ['Draft', 'Pending Moderation'], 'to' => 'Published', 'audit' => 'KAIZEN_ARTICLE_PUBLISHED'],
        'unpublish' => ['from' => ['Published'], 'to' => 'Draft', 'audit' => 'KAIZEN_ARTICLE_UNPUBLISHED'],
    ];

    public $mainTab = 'ledger';
    public $marketingTab = 'portfolio_gen';

    public $signoffs = [];
    public $eligibleProjects = [];
    public $portfolioLedger = [];

    // Insights articles: the status shown ('' = all), the article open in the reader, the change waiting for confirmation
    public string $kaizenStatusFilter = '';
    #[Locked]
    public ?string $readingArticleId = null;
    #[Locked]
    public ?string $confirmArticleId = null;
    #[Locked]
    public ?string $confirmChange = null; // 'publish' or 'unpublish'

    public $isGenerating = false;
    public $draftSignatoryEmail = '';
    public $draftProjectId = '';

    public $selectedSignoffForMarketing = '';
    public $marketingTone = 'Clinical & Authoritative'; 
    public $marketingSanitizationRules = 'Leave out all financial figures and the client’s name. Keep percentages.';
    
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
        $this->canPublishKaizen = $user->can('kaizen.approve');
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

        // The Insights articles are read in render(), with the status filter
        if ($this->canViewKaizen) {
            $this->portfolioLedger = DB::table('portfolio_case_studies')->orderBy('created_at', 'desc')->get()->toArray();
        }
    }

    public function switchMainTab($tab) { $this->mainTab = $tab; $this->closeArticle(); $this->loadLedgers(); }
    public function switchMarketingTab($tab) { $this->marketingTab = $tab; $this->closeArticle(); $this->loadLedgers(); }

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

        $prompt = "You are a senior consultant at JBS Praxis writing a project sign-off document that the client will read and sign.
        Write in plain, measured British English, in sentence case, with no hype, no exclamation marks and no military or technical jargon.
        Use only the project data below. Do not invent any figure, outcome or saving.
        - Project: {$project->title}
        - Story points delivered: {$totalPoints}
        - Hours worked: {$totalHours}
        - Rework hours (cost of poor quality): {$reworkHours}
        - Issues reported and resolved: {$bugsReported}

        Generate ONLY a valid JSON response with this exact structure:
        {
            \"executive_summary\": \"Two short paragraphs: what was delivered, and how the work was checked and accepted.\",
            \"roi_metrics\": [
                {\"metric\": \"Metric name in sentence case (e.g. Story points delivered)\", \"value\": \"A figure taken from the data above\", \"description\": \"One short sentence of context\"}
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
                    $signoffId = (string) Str::uuid();
                    DB::table('project_signoffs')->insert([
                        'id' => $signoffId,
                        'project_id' => $this->draftProjectId,
                        'token' => hash('sha256', Str::random(40)),
                        'signatory_email' => $this->draftSignatoryEmail,
                        'executive_summary' => $parsedData['executive_summary'],
                        'roi_metrics_achieved' => json_encode($parsedData['roi_metrics']),
                        'status' => 'Draft',
                        'created_at' => now()
                    ]);
                    // The client's approvers also find it in their portal, with its signing link (docs/PWA.md)
                    \App\Support\Pwa\ClientNotify::signoff($signoffId);
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
        
        $prompt = "You are the editor of JBS Praxis Insights.
        Turn this project into a public " . ($isPortfolio ? "case study for the JBS Praxis website" : "JBS Praxis Insights article") . ".
        Write in plain, measured British English, in sentence case, with no hype, no exclamation marks and no military or technical jargon.
        Use only the facts below. Do not invent any figure, outcome or saving.
        Tone: {$this->marketingTone}.
        Confidentiality rules: {$this->marketingSanitizationRules}.
        
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

            // project_signoffs has no updated_at column (live schema)
            DB::table('project_signoffs')->where('id', $signoffId)->update(['status' => 'Signed', 'frozen_at' => null]);
            DB::table('projects')->where('id', $signoff->project_id)->update(['status' => 'Active', 'updated_at' => now()]);
        });
        $this->loadLedgers();
        session()->flash('success', 'Sign-off unfrozen. The project is active again and its sprint board is open.');
    }

    /** Opens an Insights article in the reader */
    public function readArticle(string $articleId)
    {
        abort_unless(Auth::user()->can('kaizen.view'), 403);

        $this->cancelArticleChange();
        $this->readingArticleId = DB::table('kaizen_articles')->where('id', $articleId)->value('id');
        if (! $this->readingArticleId) {
            session()->flash('error', 'We couldn’t find this article; it may have been deleted. The list is up to date now.');
        }
    }

    /** Closes the reader and any confirmation over it */
    public function closeArticle()
    {
        $this->readingArticleId = null;
        $this->cancelArticleChange();
    }

    /** Escape: the confirmation first, then the reader */
    public function dismissArticle()
    {
        if ($this->confirmArticleId) {
            $this->cancelArticleChange();
        } else {
            $this->closeArticle();
        }
    }

    /** Opens the confirmation for publishing or unpublishing an article */
    public function askArticleChange(string $articleId, string $change)
    {
        abort_unless(Auth::user()->can('kaizen.approve') && isset(self::KAIZEN_CHANGES[$change]), 403);

        $article = DB::table('kaizen_articles')->where('id', $articleId)->first(['id', 'status']);
        if (! $article || ! in_array($article->status, self::KAIZEN_CHANGES[$change]['from'], true)) {
            $this->closeArticle();
            session()->flash('error', $this->refusal($article, $change));
            return;
        }

        $this->confirmArticleId = $article->id;
        $this->confirmChange = $change;
    }

    public function cancelArticleChange()
    {
        $this->confirmArticleId = null;
        $this->confirmChange = null;
    }

    /** Draft or waiting for review -> Published. The website caches articles, so it shows there within 10 minutes. */
    public function publishArticle(string $articleId)
    {
        $this->changeArticleStatus($articleId, 'publish');
    }

    /** Published -> Draft: off the website within 10 minutes. The first publication date is kept. */
    public function unpublishArticle(string $articleId)
    {
        $this->changeArticleStatus($articleId, 'unpublish');
    }

    private function changeArticleStatus(string $articleId, string $change)
    {
        abort_unless(Auth::user()->can('kaizen.approve'), 403);

        $rule = self::KAIZEN_CHANGES[$change];
        $article = DB::table('kaizen_articles')->where('id', $articleId)->first();
        $this->closeArticle();

        $values = ['status' => $rule['to'], 'is_public' => $change === 'publish' ? 1 : 0, 'updated_by' => Auth::id(), 'updated_at' => now()];
        if ($change === 'publish' && empty($article?->published_at)) {
            $values['published_at'] = now();
        }

        // Only from an allowed status, and only if nobody changed it a moment ago
        $changed = $article && in_array($article->status, $rule['from'], true)
            && DB::transaction(function () use ($article, $rule, $change, $values) {
                if (DB::table('kaizen_articles')->where('id', $article->id)->where('status', $article->status)->update($values) !== 1) {
                    return false;
                }

                DB::table('system_audit_logs')->insert([
                    'user_id' => Auth::id(),
                    'action' => $rule['audit'],
                    'description' => ($change === 'publish' ? 'Published' : 'Unpublished') . " the Insights article ‘{$article->title}’.",
                    'target_table' => 'kaizen_articles',
                    'target_id' => $article->id,
                    'old_values' => json_encode(['status' => $article->status, 'is_public' => (bool) $article->is_public]),
                    'new_values' => json_encode(['status' => $rule['to'], 'is_public' => $change === 'publish']
                        + (isset($values['published_at']) ? ['published_at' => $values['published_at']->toDateTimeString()] : [])),
                    'ip_address' => request()?->ip(),
                    'created_at' => now(),
                ]);

                return true;
            });

        if (! $changed) {
            $current = $article ? DB::table('kaizen_articles')->where('id', $article->id)->first(['id', 'status']) : null;
            session()->flash('error', $this->refusal($current, $change));
            return;
        }

        if ($change === 'unpublish') {
            session()->flash('success', "Unpublished ‘{$article->title}’. It’s a draft again and leaves the website within 10 minutes.");
            return;
        }

        // There is no email for contributors (app/Mail), so the person who publishes tells them
        $contributor = $article->contributor_id
            ? DB::table('client_users')->where('id', $article->contributor_id)->first(['first_name', 'last_name', 'email'])
            : null;
        session()->flash('success', "Published ‘{$article->title}’. It appears on the website within 10 minutes."
            . ($contributor ? ' HQ doesn’t email contributors, so let ' . trim("{$contributor->first_name} {$contributor->last_name}") . " know yourself ({$contributor->email})." : ''));
    }

    /** Why an article can't take this change: it was deleted, or its status isn't (or is no longer) one the change starts from */
    private function refusal(?object $article, string $change): string
    {
        $reason = match (true) {
            ! $article => 'We couldn’t find this article; it may have been deleted.',
            $change === 'publish' && $article->status === 'Published' => 'This article is already published, so nothing changed.',
            $change === 'publish' => 'Only a draft or an article waiting for review can be published, so nothing changed.',
            default => 'This article isn’t published, so nothing changed.',
        };

        return $reason . ' The list is up to date now.';
    }

    /** The Insights articles tab: the list (status filter applied), how many per status, the reader and the confirmation */
    private function kaizenView(): array
    {
        $withContributor = fn () => DB::table('kaizen_articles')
            ->leftJoin('client_users as contributor', 'contributor.id', '=', 'kaizen_articles.contributor_id')
            ->addSelect('contributor.first_name as contributor_first_name', 'contributor.last_name as contributor_last_name', 'contributor.job_title as contributor_job_title');

        $status = array_key_exists($this->kaizenStatusFilter, self::KAIZEN_STATUSES) ? $this->kaizenStatusFilter : null;
        $ledger = $withContributor()
            ->addSelect('kaizen_articles.id', 'kaizen_articles.title', 'kaizen_articles.executive_summary', 'kaizen_articles.status',
                'kaizen_articles.contributor_id', 'kaizen_articles.published_at', 'kaizen_articles.created_at')
            ->when($status, fn ($q) => $q->where('kaizen_articles.status', $status))
            ->orderBy('kaizen_articles.created_at', 'desc')
            ->get()->all();

        $reading = $this->readingArticleId ? $withContributor()->addSelect('kaizen_articles.*')->where('kaizen_articles.id', $this->readingArticleId)->first() : null;
        // The same sections, in the same order and under the same headings, as the article page on the website
        $sections = $reading ? array_filter(array_map(fn ($text) => self::paragraphs($text), [
            'Summary' => $reading->executive_summary,
            'The problem' => $reading->problem_statement,
            'What was done' => $reading->engineered_solution,
            'The result' => $reading->final_roi,
        ]), fn (HtmlString $html) => $html->isNotEmpty()) : [];

        return [
            'kaizenLedger' => $ledger,
            'kaizenCounts' => DB::table('kaizen_articles')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'readingArticle' => $reading,
            'readingSections' => $sections,
            'confirmArticle' => $this->confirmArticleId ? DB::table('kaizen_articles')->where('id', $this->confirmArticleId)->first(['id', 'title', 'status']) : null,
        ];
    }

    /** Article text as the website shows it: tags stripped, then paragraphs of escaped text with **bold** kept */
    private static function paragraphs(?string $text): HtmlString
    {
        $text = trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $html = '';
        foreach ($text === '' ? [] : preg_split('/\R{2,}/', $text) as $paragraph) {
            $p = e(trim($paragraph));
            $html .= '<p>' . nl2br(preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $p) ?? $p, false) . '</p>';
        }

        return new HtmlString($html);
    }

    public function render()
    {
        $kaizen = ['kaizenLedger' => [], 'kaizenCounts' => [], 'readingArticle' => null, 'readingSections' => [], 'confirmArticle' => null];
        if ($this->canViewKaizen && $this->mainTab === 'marketing' && $this->marketingTab === 'kaizen_ledger') {
            $kaizen = $this->kaizenView();
        }

        return view('livewire.hq.operations.signoff-manager', $kaizen);
    }
}