<?php

namespace App\Services\Site;

use App\Models\ClientUser;
use App\Models\KaizenArticle;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * JBS Praxis Insights (Kaizen articles): contributor submissions from POST /kaizen/submissions,
 * and the published articles for GET /kaizen/articles[/{slug}].
 * Submission limits (docs/SITE_API.md section 6.2): 2 a day per visitor, 2 a week per email, 20 an hour
 * for everyone, on the old page's limiter key. The API's articles carry no author or job title.
 */
final class KaizenService
{
    public const CATEGORIES = ['The Boardroom', 'The Server Room'];

    public const PER_PAGE = 12;

    /** The site API's fields */
    public const FIELDS = [
        'full_name', 'title', 'email', 'linkedin_url',
        'article_title', 'exec_summary', 'vulnerability',
        'solution', 'roi', 'category',
    ];

    /** @param  string  $summaryWordsMessage  what a summary over 50 words gets, in the request's language */
    public static function rules(string $summaryWordsMessage): array
    {
        return [
            'full_name'     => 'required|string|max:100',
            'title'         => 'required|string|max:100',
            'email'         => 'required|email|max:100',
            'linkedin_url'  => 'required|url|max:255',
            'article_title' => 'required|string|max:255',
            'exec_summary'  => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($summaryWordsMessage) {
                    if (str_word_count($value) > 50) {
                        $fail($summaryWordsMessage);
                    }
                },
            ],
            'vulnerability' => 'required|string|max:10000',
            'solution'      => 'required|string|max:10000',
            'roi'           => 'required|string|max:255',
            'category'      => 'required|in:' . implode(',', self::CATEGORIES),
        ];
    }

    /**
     * Saves a contributor's article for moderation (contributor contact, the article, a notice to the
     * staff who approve Kaizen). Input is validated with rules().
     *
     * @param  string|null  $ip  the visitor's IP (limits)
     *
     * @throws LimitReached
     */
    public function submit(array $in, ?string $ip = null): void
    {
        $limitIds = ['email' => strtolower(trim((string) ($in['email'] ?? '')))];
        SiteLimits::check('kaizen', $ip, $limitIds);

        DB::transaction(function () use ($in) {
            $nameParts = Fields::nameParts($in['full_name']);

            // 1. The contributor is a client contact (no password, so no portal access).
            //    An existing client or lead keeps their own record and organisation.
            $contributor = ClientUser::withTrashed()->where('email', $in['email'])->first();

            // 2. New contributors share one organisation
            if (!$contributor) {
                $contributorOrgId = DB::table('organizations')->where('name', 'Kaizen Public Contributors')->value('id');
                if (!$contributorOrgId) {
                    $contributorOrgId = (string) Str::uuid();
                    DB::table('organizations')->insert([
                        'id' => $contributorOrgId, 'name' => 'Kaizen Public Contributors',
                        'status' => 'Internal', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                $contributor = ClientUser::create([
                    'organization_id' => $contributorOrgId,
                    'first_name'      => $nameParts[0],
                    'last_name'       => $nameParts[1],
                    'email'           => $in['email'],
                    'job_title'       => $in['title'],
                    'status'          => 'Active',
                    'lifecycle_stage' => 'Public Contributor',
                    'source'          => 'kaizen',
                ]);
            }

            // Calculate Read Time
            $combinedText = $in['vulnerability'] . ' ' . $in['solution'];
            $calculatedReadTime = max(1, ceil(str_word_count($combinedText) / 200));

            // 3. The article, waiting for moderation
            $article = KaizenArticle::create([
                'slug'                => Str::slug($in['article_title']) . '-' . Str::random(5),
                'category'            => $in['category'],
                'title'               => $in['article_title'],
                'executive_summary'   => $in['exec_summary'],
                'problem_statement'   => $in['vulnerability'],
                'engineered_solution' => $in['solution'],
                'final_roi'           => $in['roi'],
                'read_time_minutes'   => $calculatedReadTime,
                'is_public'           => false,
                'is_internal_sop'     => false,
                'status'              => 'Pending Moderation',
                'created_by'          => User::EDITORIAL_ID,
                'contributor_id'      => $contributor->id,
                // The language of the page it was sent from: the email about the decision is written in it
                'submitted_locale'    => app()->getLocale() === 'fr' ? 'fr' : 'en',
            ]);

            // Tell the staff who moderate Kaizen there is an article to moderate
            $notifications = [];
            foreach (Access::staffWith('kaizen.approve') as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $adminId,
                    'title' => 'Kaizen article waiting for review',
                    'message' => "‘{$in['article_title']}’ was sent by {$in['full_name']}.",
                    // Opens the article in the review screen (HQ → Marketing → Insights articles)
                    'link_url' => route('hq.marketing.insights', ['article' => $article->id], false),
                    'type' => 'System', 'created_at' => now(),
                ];
            }
            if (!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        SiteLimits::hit('kaizen', $ip, $limitIds);
    }

    /** Published articles, newest first, one page at a time */
    public function articles(int $page, ?string $category): array
    {
        $query = KaizenArticle::select('kaizen_articles.*')
            ->where('kaizen_articles.status', 'Published')
            ->when($category, fn ($q) => $q->where('kaizen_articles.category', $category))
            ->orderBy('kaizen_articles.created_at', 'desc');

        $total = (clone $query)->count();
        $rows = $query->forPage($page, self::PER_PAGE)->get();

        return [
            'articles' => $rows->map(fn ($a) => $this->summary($a))->values()->all(),
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
        ];
    }

    /** One published article, or null */
    public function article(string $slug): ?array
    {
        $a = KaizenArticle::select('kaizen_articles.*')
            ->where('kaizen_articles.slug', $slug)
            ->where('kaizen_articles.status', 'Published')
            ->first();

        if (!$a) {
            return null;
        }

        $summaryHtml = self::html($a->executive_summary);

        return $this->summary($a) + [
            'problem' => $a->problem_statement,
            'solution' => $a->engineered_solution,
            'roi' => $a->final_roi,
            'html' => [
                'summary' => $summaryHtml,
                'problem' => self::html($a->problem_statement),
                'solution' => self::html($a->engineered_solution),
                'roi' => self::html($a->final_roi),
            ],
            'meta_description' => Str::limit(strip_tags((string) $summaryHtml), 150, '...'),
        ];
    }

    /** An article's public fields: no author or job title (the site credits every article to JBS Praxis) */
    private function summary(KaizenArticle $a): array
    {
        return [
            'slug' => $a->slug,
            'category' => $a->category,
            'title' => $a->title,
            'summary' => $a->executive_summary,
            'read_time_minutes' => (int) $a->read_time_minutes,
            'published_at' => $a->published_at ? \Carbon\Carbon::parse($a->published_at)->toIso8601String() : null,
            'created_at' => $a->created_at ? \Carbon\Carbon::parse($a->created_at)->toIso8601String() : null,
        ];
    }

    /**
     * Article text as safe HTML, as the old article page shows it: escaped first (public submissions
     * reach this text), **bold** allowed, and the first mention of key terms linked to the services page.
     */
    public static function html(?string $text): ?string
    {
        if (empty($text)) return $text;

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
}
