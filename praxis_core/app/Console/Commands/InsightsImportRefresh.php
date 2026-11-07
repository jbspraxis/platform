<?php

namespace App\Console\Commands;

use App\Models\KaizenArticle;
use App\Models\User;
use App\Services\Site\KaizenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds the rewritten JBS Praxis Insights articles (database/data/insights_refresh_2026_10.php) to HQ as
 * unpublished drafts by JBS Praxis Editorial, as HQ's own "save as draft" does: status Draft, not public, no
 * publication date. An article whose slug is already in HQ is skipped, so running it again creates nothing and
 * never overwrites a draft staff have edited. The published originals are never changed.
 *
 *   php artisan insights:import-refresh --dry-run    what would be created, nothing written
 *   php artisan insights:import-refresh
 *
 * The drafts appear in HQ under Marketing, Insights articles, where they are read, corrected and published
 * (published = status Published, is_public 1, published_at).
 */
class InsightsImportRefresh extends Command
{
    protected $signature = 'insights:import-refresh {--dry-run : show what would be created, write nothing}';

    protected $description = 'Add the rewritten Insights articles to HQ as unpublished drafts';

    public const DATA_FILE = 'data/insights_refresh_2026_10.php';

    /** The limits of the site's article form (KaizenService::rules) */
    public const SUMMARY_WORDS = 50;
    public const RESULT_CHARACTERS = 255;
    public const TEXT_CHARACTERS = 10000;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $articles = self::articles();

        if ($problems = self::problems($articles)) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }
            $this->error('Nothing was imported. Correct ' . self::DATA_FILE . ' and run the command again.');

            return self::FAILURE;
        }

        if (!DB::table('users')->where('id', User::EDITORIAL_ID)->exists()) {
            $this->error('The JBS Praxis Editorial account is missing, so the drafts would have no author. Run php artisan db:seed --class=CoreSeeder --force, then run this command again.');

            return self::FAILURE;
        }

        // Matched on the slug only: a draft staff have retitled or edited is still the same article
        $existing = DB::table('kaizen_articles')->whereIn('slug', array_column($articles, 'slug'))->pluck('slug')->all();
        $originals = DB::table('kaizen_articles')->whereIn('slug', array_column($articles, 'replaces'))->pluck('title', 'slug');
        $new = array_filter($articles, fn ($a) => !in_array($a['slug'], $existing, true)); // keeps each article's place in the file

        $report = fn () => $this->table(['Result', 'Section', 'Title', 'Replaces'], array_map(fn ($a) => [
            in_array($a['slug'], $existing, true) ? 'already in HQ, skipped' : ($dryRun ? 'would create' : 'created'),
            $a['category'],
            $a['title'],
            $originals[$a['replaces']] ?? "{$a['replaces']} (not in HQ)",
        ], $articles));

        $skipped = count($articles) - count($new);
        if ($dryRun) {
            $report();
            $this->info('Dry run: ' . count($new) . " draft(s) would be created, {$skipped} already in HQ. Nothing was written.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($new) {
            $now = now();
            foreach ($new as $i => $a) {
                $article = new KaizenArticle([
                    'slug'                => $a['slug'],
                    'category'            => $a['category'],
                    'title'               => $a['title'],
                    'executive_summary'   => $a['executive_summary'],
                    'problem_statement'   => $a['problem_statement'],
                    'engineered_solution' => $a['engineered_solution'],
                    'final_roi'           => $a['final_roi'],
                    // As for articles sent from the site: 200 words a minute over the problem and the solution
                    'read_time_minutes'   => max(1, (int) ceil(str_word_count($a['problem_statement'] . ' ' . $a['engineered_solution']) / 200)),
                    'is_public'           => false,
                    'is_internal_sop'     => false,
                    'status'              => 'Draft',
                    'published_at'        => null,
                    'created_by'          => User::EDITORIAL_ID,
                    'contributor_id'      => null,
                ]);
                // A second apart, in the file's order (first = newest), so lists sorted by date keep that order
                $article->created_at = $now->copy()->subSeconds($i);
                $article->save();
            }
        });

        // Reported once the transaction has committed, so "created" is never shown for a failed import
        $report();
        $this->info(count($new) . " draft(s) created, {$skipped} already in HQ. They stay unpublished until someone publishes them; the articles they replace are unchanged.");

        return self::SUCCESS;
    }

    /**
     * The articles of the data file, with paragraphs joined by a blank line (the site shows each as a paragraph).
     *
     * @return list<array{slug: string, replaces: string, category: string, title: string, executive_summary: string, problem_statement: string, engineered_solution: string, final_roi: string}>
     */
    public static function articles(?string $file = null): array
    {
        $articles = require $file ?? database_path(self::DATA_FILE);

        return array_map(function (array $a) {
            foreach ($a as $field => $value) {
                $a[$field] = is_array($value) ? implode("\n\n", array_map('trim', $value)) : trim((string) $value);
            }

            return $a;
        }, array_values($articles));
    }

    /**
     * What stops the articles from being imported: missing fields, the site form's limits (a summary of
     * 50 words at most, a one-line result of 255 characters at most), unknown sections, slugs that are not
     * unique or that would replace the original itself.
     *
     * @return list<string>
     */
    public static function problems(array $articles): array
    {
        $problems = [];
        $slugs = [];
        foreach ($articles as $i => $a) {
            $name = 'Article ' . ($i + 1) . ' (' . ($a['slug'] ?? 'no slug') . ')';
            foreach (['slug', 'replaces', 'category', 'title', 'executive_summary', 'problem_statement', 'engineered_solution', 'final_roi'] as $field) {
                if (trim((string) ($a[$field] ?? '')) === '') {
                    $problems[] = "{$name}: {$field} is empty.";
                }
            }
            $slug = (string) ($a['slug'] ?? '');
            if ($slug !== '' && !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
                $problems[] = "{$name}: the slug may only use lowercase letters, digits and single hyphens.";
            }
            if ($slug !== '' && in_array($slug, $slugs, true)) {
                $problems[] = "{$name}: the slug is used twice in the file.";
            }
            $slugs[] = $slug;
            if ($slug !== '' && $slug === ($a['replaces'] ?? null)) {
                $problems[] = "{$name}: the slug is the original’s; a draft must have its own.";
            }
            if (isset($a['category']) && !in_array($a['category'], KaizenService::CATEGORIES, true)) {
                $problems[] = "{$name}: the section must be " . implode(' or ', KaizenService::CATEGORIES) . '.';
            }
            if (mb_strlen((string) ($a['title'] ?? '')) > 255) {
                $problems[] = "{$name}: the title is longer than 255 characters.";
            }
            if (str_word_count((string) ($a['executive_summary'] ?? '')) > self::SUMMARY_WORDS
                || count(preg_split('/\s+/u', trim((string) ($a['executive_summary'] ?? '')))) > self::SUMMARY_WORDS) {
                $problems[] = "{$name}: the summary is longer than " . self::SUMMARY_WORDS . ' words.';
            }
            $result = (string) ($a['final_roi'] ?? '');
            if (mb_strlen($result) > self::RESULT_CHARACTERS || preg_match('/\R/', $result)) {
                $problems[] = "{$name}: the result must be one line of at most " . self::RESULT_CHARACTERS . ' characters.';
            }
            foreach (['problem_statement', 'engineered_solution'] as $field) {
                if (mb_strlen((string) ($a[$field] ?? '')) > self::TEXT_CHARACTERS) {
                    $problems[] = "{$name}: {$field} is longer than " . self::TEXT_CHARACTERS . ' characters.';
                }
            }
        }

        return $problems;
    }
}
