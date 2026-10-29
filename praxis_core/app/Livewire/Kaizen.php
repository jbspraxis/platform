<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\KaizenArticle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        // 1. Strict Validation Gateway
        $this->validate([
            'fullName'      => 'required|string|max:100',
            'title'         => 'required|string|max:100',
            'email'         => 'required|email|max:100',
            'linkedinUrl'   => 'required|url|max:255',
            'articleTitle'  => 'required|string|max:255',
            'execSummary'   => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (str_word_count($value) > 50) {
                        $fail('The Executive Summary must not exceed 50 words.');
                    }
                },
            ],
            'vulnerability' => 'required|string|max:10000',
            'solution'      => 'required|string|max:10000',
            'roi'           => 'required|string|max:255',
            'category'      => 'required|in:The Boardroom,The Server Room',
        ]);

        // 3 submissions per IP per hour
        $rateKey = 'kaizen-submit:' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 3)) {
            $this->errorMessage = 'Too many submissions from this connection. Please try again later.';
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 3600);

        try {
            DB::transaction(function () {
                $nameParts = explode(' ', trim($this->fullName), 2);

                // Every user needs an organisation: public contributors share one
                $contributorOrgId = DB::table('organizations')->where('name', 'Kaizen Public Contributors')->value('id');
                if (!$contributorOrgId) {
                    $contributorOrgId = (string) Str::uuid();
                    DB::table('organizations')->insert([
                        'id' => $contributorOrgId, 'name' => 'Kaizen Public Contributors',
                        'status' => 'Internal', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                // 1. Check if the email already exists in the system
                $user = User::where('email', $this->email)->first();

                // 2. If no user exists, create a heavily restricted "Quarantined" profile
                if (!$user) {
                    $user = User::create([
                        'organization_id' => $contributorOrgId,
                        'first_name'      => $nameParts[0],
                        'last_name'       => $nameParts[1] ?? 'Unknown',
                        'email'           => $this->email,
                        'job_title'       => $this->title,
                        'password'        => Hash::make(Str::random(64)), // Impossibly long hash
                        'status'          => 'Suspended', 
                        'lifecycle_stage' => 'Public Contributor' 
                    ]);
                }

                // Calculate Read Time 
                $combinedText = $this->vulnerability . ' ' . $this->solution;
                $calculatedReadTime = max(1, ceil(str_word_count($combinedText) / 200));

                // 3. Log the Architecture (Pending Moderation)
                KaizenArticle::create([
                    'slug'                => Str::slug($this->articleTitle) . '-' . Str::random(5),
                    'category'            => $this->category,
                    'title'               => $this->articleTitle,
                    'executive_summary'   => $this->execSummary,
                    'problem_statement'   => $this->vulnerability,
                    'engineered_solution' => $this->solution,
                    'final_roi'           => $this->roi,
                    'read_time_minutes'   => $calculatedReadTime,
                    'is_public'           => false, 
                    'is_internal_sop'     => false,
                    'status'              => 'Pending Moderation',
                    'created_by'          => $user->id,
                ]);

                // Tell senior staff there is an article to moderate
                $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
                $notifications = [];
                foreach ($admins as $admin) {
                    $notifications[] = [
                        'id' => (string) Str::uuid(), 'user_id' => $admin->id,
                        'title' => 'KAIZEN ARTICLE AWAITING MODERATION',
                        'message' => "'{$this->articleTitle}' was submitted by {$this->fullName}.",
                        'type' => 'System', 'created_at' => now(),
                    ];
                }
                if (!empty($notifications)) DB::table('notifications')->insert($notifications);
            });

            $this->isSuccess = true;

        } catch (\Throwable $e) {
            Log::error('Kaizen Public Submission Failure: ' . $e->getMessage());
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
        // Join the users table to get the author's name and designation
        $articles = KaizenArticle::select(
                'kaizen_articles.*', 
                'users.first_name', 
                'users.last_name', 
                'users.job_title'
            )
            ->join('users', 'kaizen_articles.created_by', '=', 'users.id')
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