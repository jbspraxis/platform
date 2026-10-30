<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\KaizenArticle;
use App\Services\Site\Fields;
use App\Services\Site\KaizenService;
use App\Services\Site\LimitReached;
use App\Support\Monitor\ErrorMonitor;
use Illuminate\Support\Facades\Log;

class Kaizen extends Component
{
    // Frontend Entanglement
    public $isUploadOpen = false; 

    // Step 1: Architect Credentials
    public $fullName;
    public $title;
    public $email;
    public $linkedinUrl;

    // Step 2: Protocol Architecture
    public $category = 'The Boardroom';
    public $articleTitle;
    public $execSummary;
    public $vulnerability;
    public $solution;
    public $roi;

    // System States
    public $isSuccess = false;
    public $errorMessage = null;

    public function setCategory($cat)
    {
        $this->category = $cat;
    }

    public function submitInsight()
    {
        // Clear previous system errors
        $this->errorMessage = null;

        // 1. Strict Validation Gateway (the same rules as the site API: App\Services\Site\KaizenService)
        $this->validate(Fields::rulesFor(KaizenService::rules(), KaizenService::FIELDS));

        try {
            // Contributor contact, the article (waiting for moderation) and the notice to moderators;
            // the limits are the service's, shared with the site API
            app(KaizenService::class)->submit(Fields::fromComponent($this, KaizenService::FIELDS), request()->ip());

            $this->isSuccess = true;

        } catch (LimitReached $e) {
            $this->errorMessage = 'Too many submissions from this connection. Please try again later.';
        } catch (\Throwable $e) {
            // Without bound values: a database error must not copy the contributor's details into the log
            Log::error('Kaizen Public Submission Failure: ' . ErrorMonitor::safeMessage($e));
            $this->errorMessage = 'Your submission could not be saved. Please try again later or email info@jbspraxis.com.';
        }
    }

    /**
     * PHASE 5: THE SEO VELOCITY ENGINE
     * Automatically converts key enterprise terms into high-authority internal links.
     */
    private function injectSemanticLinks($text)
    {
        if (empty($text)) return $text;

        $dictionary = [
            // Target => Link
            '\b(PostgreSQL|relational database|data silos|ETL)\b' => '<a href="/services#node-data" class="seo-matrix-link">$1</a>',
            '\b(ISO 9001|ISO 27001|compliance|Internal Control Systems|corporate governance)\b' => '<a href="/services#node-governance" class="seo-matrix-link">$1</a>',
            '\b(ERP|digital operating system|bespoke software|API gateways)\b' => '<a href="/services#node-systems" class="seo-matrix-link">$1</a>',
            '\b(Predictive Praxis Framework|operational chaos|COPQ)\b' => '<a href="/services" class="seo-matrix-link">$1</a>',
            '\b(RAG|augmented intelligence|cognitive backends)\b' => '<a href="/services#node-systems" class="seo-matrix-link">$1</a>',
        ];

        foreach ($dictionary as $pattern => $replacement) {
            // Use regex to match exact phrases (case-insensitive) and replace them with the anchor tag
            // Limit to 1 replacement per keyword per text block to avoid spamming Google
            $text = preg_replace('/' . $pattern . '/i', $replacement, $text, 1); 
        }

        return $text;
    }

    public function render()
    {
        // The byline: public contributor if any, otherwise the staff author
        $articles = KaizenArticle::select('kaizen_articles.*')
            ->withAuthor()
            ->where('kaizen_articles.status', 'Published')
            ->orderBy('kaizen_articles.created_at', 'desc')
            ->get()
            ->map(function($art) {
                return [
                    'id'          => $art->id,
                    'slug'        => $art->slug, // PRAXIS OVERRIDE: Inject slug for JS Router
                    'category'    => $art->category,
                    'time'        => (string) $art->read_time_minutes,
                    'title'       => $art->title,
                    'author'      => trim($art->first_name . ' ' . $art->last_name),
                    'designation' => $art->job_title ?? 'Contributor',
                    'summary'     => $this->injectSemanticLinks($art->executive_summary),
                    'problem'     => $this->injectSemanticLinks($art->problem_statement),
                    'solution'    => $this->injectSemanticLinks($art->engineered_solution),
                    'roi'         => $this->injectSemanticLinks($art->final_roi)
                ];
            });

        return view('livewire.kaizen', [
            'dbArticles' => $articles
        ])->layout('layouts.app', ['title' => 'JBS PRAXIS | The Kaizen Hub']);
    }
}