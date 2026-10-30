<?php

namespace Tests\Feature;

use App\Console\Commands\InsightsImportRefresh;
use App\Models\User;
use App\Services\Site\KaizenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * php artisan insights:import-refresh and its articles (database/data/insights_refresh_2026_10.php): unpublished
 * drafts by JBS Praxis Editorial, no duplicates on a second run, the originals untouched, the site form's limits,
 * and the brand's rules on wording (docs/BRAND.md sections 1, 4 and 5; docs/glossary.csv).
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase), seeded with the six published originals.
 */
class InsightsRefreshTest extends TestCase
{
    use UsesThrowawayDatabase;

    /** Words and marks no draft may contain, beyond docs/glossary.csv (patterns, case-insensitive) */
    private const FORBIDDEN = [
        'predictive' => '\bpredictive\b', 'the ™ sign' => '™', 'certification' => '\bcertifi',
        'the old method name' => '\bpraxis framework\b', 'GitHub' => 'github',
        'hype' => '\b(corporate suicide|death of|hard-?cod\w*|cognitive|code as law|leverag\w*|synerg\w*|disrupt\w*|unlock\w*|empower\w*|best-in-class|premier|fatal\w*|war ?zones?|blindfold\w*|bleed\w*|ha?emorrhag\w*|incinerat\w*|battle\w*|weapon\w*|guarantee\w*|flawless\w*|absolute\w*|ruthless\w*|dominat\w*|high-velocity|ultra\w*|zero[- ]downtime|game[- ]chang\w*|revolutionar\w*)\b',
        'prices and amounts' => '\b(fees?|pric(e|es|ed|ing)|FCFA|XAF|NGN)\b|[₦$€£%]',
        'exclamation marks' => '!',
        'straight quotes (use ’ ‘ “ ”)' => '[\'"]',
        'HTML' => '[<>]',
    ];

    /**
     * Names that must never be published (unapproved clients, the code host's account), ROT13-encoded so this
     * file does not spell them out either.
     */
    private const NEVER_NAMED = ['Svqfba', 'Crgvgr Pbhe', 'YCP', 'Rgf.', 'Ubevmba', 'Pbatb', 'gbzoynxrnfnnu196'];

    /**
     * Capitalised words allowed inside a sentence: our names, approved clients (BRAND.md section 1), places,
     * standards and the tools named in the articles. Anything else (a person, a client) fails the guard.
     */
    private const PROPER_NOUNS = [
        'JBS', 'Praxis', 'Method', 'AI', 'Nigeria', 'Cameroon', 'OHADA', 'IFRS', 'ISO', 'HR', 'CFA', 'API', 'ETL',
        'PostgreSQL', 'Laravel', 'React', 'Vue', 'WhatsApp', 'IBANs', 'RIB', 'NIU',
        'Smart', 'Logistics', 'Services', 'LS', 'MyCity', 'App', 'Pixie', 'Girl', 'Global', 'Orika', 'Living', 'Tales', 'Consulting',
    ];

    private const FIELDS = ['title', 'executive_summary', 'problem_statement', 'engineered_solution', 'final_roi'];

    private function slugs(): array
    {
        return array_column(InsightsImportRefresh::articles(), 'slug');
    }

    /** Every column of the articles that are not drafts from the file, as they are now */
    private function originals(): array
    {
        return DB::table('kaizen_articles')->whereNotIn('slug', $this->slugs())->orderBy('id')->get()
            ->map(fn ($row) => (array) $row)->all();
    }

    public function test_the_import_creates_unpublished_drafts_by_jbs_praxis_editorial(): void
    {
        $before = $this->originals();
        $this->assertCount(6, $before, 'the six published originals are seeded');

        $this->artisan('insights:import-refresh')->assertSuccessful()->expectsOutputToContain('6 draft(s) created, 0 already in HQ');

        $articles = InsightsImportRefresh::articles();
        $this->assertCount(6, $articles);
        $previous = null;
        foreach ($articles as $a) {
            $row = DB::table('kaizen_articles')->where('slug', $a['slug'])->first();
            $this->assertNotNull($row, $a['slug']);
            $this->assertSame('Draft', $row->status);
            $this->assertSame(0, (int) $row->is_public);
            $this->assertSame(0, (int) $row->is_internal_sop);
            $this->assertNull($row->published_at);
            $this->assertSame(User::EDITORIAL_ID, $row->created_by);
            $this->assertNull($row->contributor_id);
            foreach (['category', 'title', 'executive_summary', 'problem_statement', 'engineered_solution', 'final_roi'] as $field) {
                $this->assertSame($a[$field], $row->{$field}, "{$a['slug']}: {$field}");
            }
            $this->assertGreaterThanOrEqual(1, $row->read_time_minutes);
            // Listed in the file's order, first = newest
            if ($previous) {
                $this->assertLessThan($previous, $row->created_at);
            }
            $previous = $row->created_at;
        }

        // None is published: the site API only reads published articles
        $site = app(KaizenService::class);
        $this->assertSame(0, DB::table('kaizen_articles')->whereIn('slug', $this->slugs())->where('status', 'Published')->count());
        $this->assertSame(6, $site->articles(1, null)['total']);
        $this->assertSame([], array_intersect($this->slugs(), array_column($site->articles(1, null)['articles'], 'slug')));
        foreach ($this->slugs() as $slug) {
            $this->assertNull($site->article($slug));
        }

        // The originals are exactly as they were
        $this->assertSame($before, $this->originals());
    }

    public function test_running_it_again_creates_no_duplicates_and_keeps_edited_drafts(): void
    {
        $this->artisan('insights:import-refresh')->assertSuccessful();
        $first = $this->slugs()[0];
        DB::table('kaizen_articles')->where('slug', $first)->update(['title' => 'A title edited in HQ']);
        $count = DB::table('kaizen_articles')->count();

        $this->artisan('insights:import-refresh')->assertSuccessful()->expectsOutputToContain('0 draft(s) created, 6 already in HQ');

        $this->assertSame($count, DB::table('kaizen_articles')->count());
        $this->assertSame('A title edited in HQ', DB::table('kaizen_articles')->where('slug', $first)->value('title'));
        foreach ($this->slugs() as $slug) {
            $this->assertSame(1, DB::table('kaizen_articles')->where('slug', $slug)->count(), $slug);
        }
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $count = DB::table('kaizen_articles')->count();

        $this->artisan('insights:import-refresh', ['--dry-run' => true])->assertSuccessful()
            ->expectsOutputToContain('6 draft(s) would be created, 0 already in HQ. Nothing was written.');

        $this->assertSame($count, DB::table('kaizen_articles')->count());
    }

    public function test_the_import_stops_without_the_editorial_account(): void
    {
        DB::table('users')->where('id', User::EDITORIAL_ID)->delete();

        $this->artisan('insights:import-refresh')->assertFailed();

        $this->assertSame(0, DB::table('kaizen_articles')->whereIn('slug', $this->slugs())->count());
    }

    public function test_every_article_fits_the_article_form(): void
    {
        $articles = InsightsImportRefresh::articles();
        $this->assertSame([], InsightsImportRefresh::problems($articles));

        foreach ($articles as $a) {
            // The site form's own rules (KaizenService::rules), for the fields an article has
            $validator = Validator::make([
                'article_title' => $a['title'], 'exec_summary' => $a['executive_summary'], 'vulnerability' => $a['problem_statement'],
                'solution' => $a['engineered_solution'], 'roi' => $a['final_roi'], 'category' => $a['category'],
            ], array_intersect_key(KaizenService::rules('The summary is longer than 50 words.'), array_flip(['article_title', 'exec_summary', 'vulnerability', 'solution', 'roi', 'category'])));
            $this->assertSame([], $validator->errors()->all(), $a['slug']);

            $this->assertLessThanOrEqual(50, str_word_count($a['executive_summary']), $a['slug']);
            $this->assertLessThanOrEqual(50, count(preg_split('/\s+/u', $a['executive_summary'])), $a['slug']);
            $this->assertLessThanOrEqual(255, mb_strlen($a['final_roi']), $a['slug']);
            $this->assertDoesNotMatchRegularExpression('/\R/', $a['final_roi'], "{$a['slug']}: the result is one line");
            $this->assertNotSame($a['slug'], $a['replaces']);
            // Each rewrites one of the published originals
            $this->assertSame('Published', DB::table('kaizen_articles')->where('slug', $a['replaces'])->value('status'), $a['replaces']);
        }
        $this->assertSame(count($articles), count(array_unique(array_column($articles, 'replaces'))), 'one draft per original');
    }

    public function test_an_article_outside_the_limits_is_refused(): void
    {
        $good = InsightsImportRefresh::articles()[0];
        $bad = array_merge($good, [
            'slug' => $good['replaces'],
            'category' => 'The Kitchen',
            'executive_summary' => str_repeat('word ', 51),
            'final_roi' => str_repeat('x', 256),
        ]);

        $problems = implode("\n", InsightsImportRefresh::problems([$good, $bad, $good]));

        $this->assertStringContainsString('the slug is the original’s', $problems);
        $this->assertStringContainsString('the section must be The Boardroom or The Server Room', $problems);
        $this->assertStringContainsString('the summary is longer than 50 words', $problems);
        $this->assertStringContainsString('the result must be one line of at most 255 characters', $problems);
        $this->assertStringContainsString('the slug is used twice in the file', $problems);
        $this->assertStringNotContainsString('Article 1', $problems);
    }

    public function test_no_draft_contains_forbidden_words(): void
    {
        $csv = base_path('../docs/glossary.csv');
        $this->assertFileExists($csv, 'docs/glossary.csv lists the retired words');
        $rows = array_map('str_getcsv', array_filter(explode("\n", file_get_contents($csv))));
        array_shift($rows);
        $rules = self::FORBIDDEN;
        foreach ($rows as $r) {
            if (count($r) >= 3) {
                $rules["retired word ‘{$r[0]}’ (use: {$r[2]})"] = $r[1];
            }
        }
        $rules['a name that is never published'] = '(?<!\w)(' . implode('|', array_map(fn ($n) => preg_quote(str_rot13($n), '~'), self::NEVER_NAMED)) . ')(?!\w)';

        $hits = [];
        foreach (InsightsImportRefresh::articles() as $a) {
            foreach (self::FIELDS as $field) {
                $text = $a[$field];
                foreach ($rules as $what => $pattern) {
                    if (preg_match('~' . $pattern . '~iu', $text, $m)) {
                        $hits[] = "{$a['slug']} {$field}: {$what}: “{$m[0]}”";
                    }
                }
                // No figures: numbers are claims, and none of these is in the claims register (step numbers and ISO standards aside)
                if (preg_match('/\d/', preg_replace(['/\*\*\d+\. /', '/\bISO \d+\b/'], '', $text), $m)) {
                    $hits[] = "{$a['slug']} {$field}: a figure";
                }
                foreach ($this->unapprovedNames($text) as $word) {
                    $hits[] = "{$a['slug']} {$field}: a name not on the approved list: “{$word}”";
                }
            }
        }

        $this->assertSame([], $hits);
    }

    /** Capitalised words inside a sentence that are not on PROPER_NOUNS (a person, a client, a place we don't name) */
    private function unapprovedNames(string $text): array
    {
        preg_match_all('/(?<![\p{L}\p{N}])\p{Lu}[\p{L}\p{N}]*/u', $text, $m, PREG_OFFSET_CAPTURE);
        $found = [];
        foreach ($m[0] as [$word, $offset]) {
            $before = preg_replace('/[\s*‘“(]+$/u', '', substr($text, 0, $offset));
            $startsSentence = $before === '' || preg_match('/[.?!:]$/u', $before);
            if (!$startsSentence && !in_array($word, self::PROPER_NOUNS, true)) {
                $found[] = $word;
            }
        }

        return $found;
    }
}
