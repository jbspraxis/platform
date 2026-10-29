<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\KaizenArticle;
use Illuminate\Support\Facades\DB;

class KaizenArticleView extends Component
{
    public $slug;
    public $article;

    public function mount($slug)
    {
        $this->slug = $slug;
        
        // Fetch the article and join the author data in a single optimized query
        $this->article = KaizenArticle::select(
                'kaizen_articles.*', 
                'users.first_name', 
                'users.last_name', 
                'users.job_title'
            )
            ->join('users', 'kaizen_articles.created_by', '=', 'users.id')
            ->where('kaizen_articles.slug', $this->slug)
            ->where('kaizen_articles.status', 'Published')
            ->first();

        // If the slug doesn't exist or isn't published, trigger a hard 404 for Googlebot
        abort_if(!$this->article, 404, 'Intelligence Dossier not found or classified.');

        // Pass the raw database text through the SEO Velocity Engine
        $this->article->executive_summary = $this->injectSemanticLinks($this->article->executive_summary);
        $this->article->problem_statement = $this->injectSemanticLinks($this->article->problem_statement);
        $this->article->engineered_solution = $this->injectSemanticLinks($this->article->engineered_solution);
        $this->article->final_roi = $this->injectSemanticLinks($this->article->final_roi);
    }

    /**
     * PHASE 5: THE SEO VELOCITY ENGINE
     * Automatically converts key enterprise terms into high-authority internal links.
     */
    private function injectSemanticLinks($text)
    {
        if (empty($text)) return $text;

        // Escape first (public submissions can reach this text), then allow **bold**
        $text = e($text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

        $dictionary = [
            '\b(PostgreSQL|relational database|data silos|ETL)\b' => '<a href="/services#node-data" class="seo-matrix-link">$1</a>',
            '\b(ISO 9001|ISO 27001|compliance|Internal Control Systems|corporate governance)\b' => '<a href="/services#node-governance" class="seo-matrix-link">$1</a>',
            '\b(ERP|digital operating system|bespoke software|API gateways)\b' => '<a href="/services#node-systems" class="seo-matrix-link">$1</a>',
            '\b(Predictive Praxis Framework|operational chaos|COPQ)\b' => '<a href="/services" class="seo-matrix-link">$1</a>',
            '\b(RAG|augmented intelligence|cognitive backends)\b' => '<a href="/services#node-systems" class="seo-matrix-link">$1</a>',
        ];

        foreach ($dictionary as $pattern => $replacement) {
            $text = preg_replace('/' . $pattern . '/i', $replacement, $text, 1); 
        }

        return $text;
    }

    public function render()
    {
        // Fix: Use Str::limit for a mathematically safe, SEO-compliant length (150 chars max)
        $metaDescription = \Illuminate\Support\Str::limit(strip_tags($this->article->executive_summary), 150, '...');

        return view('livewire.kaizen-article-view')->layout('layouts.app', [
            'title' => $this->article->title . ' | JBS Praxis Intelligence',
            'meta_description' => $metaDescription,
            // Fix: Force the HTTPS canonical mathematically using secure_url()
            'canonical' => secure_url('/kaizen/' . $this->article->slug) 
        ]);
    }
}