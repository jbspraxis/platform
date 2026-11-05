<?php

namespace Tests\Feature;

use App\Livewire\Hq\Operations\SignoffManager;
use App\Models\User;
use App\Services\Site\KaizenService;
use App\Services\Site\SiteSignature;
use App\Support\Hosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HQ → Sign-offs → Case studies and articles → Insights articles: read an article in full, then publish it
 * (a draft, or a contributor's article waiting for review) or unpublish it (back to draft). Only kaizen.approve,
 * every change in the audit log, and the site API follows at once. Runs on a throwaway MySQL database
 * (see UsesThrowawayDatabase).
 */
class InsightsPublishTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const KEY = 'test-key';
    private const SECRET = 'test-secret-for-the-insights-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['site_api.keys' => [self::KEY => self::SECRET]]);
        Mail::fake();
    }

    /** An article as HQ stores it; returns its id and slug */
    private function article(string $status, array $values = []): array
    {
        $id = (string) Str::uuid();
        $slug = 'test-article-' . Str::lower(Str::random(6));
        $this->insertRow('kaizen_articles', $values + [
            'id' => $id, 'slug' => $slug, 'category' => 'The Boardroom', 'title' => "A {$status} article",
            'executive_summary' => 'Summary.', 'problem_statement' => 'Problem.', 'engineered_solution' => 'Solution.', 'final_roi' => 'Result.',
            'status' => $status, 'is_public' => $status === 'Published' ? 1 : 0, 'published_at' => null,
            'created_by' => User::EDITORIAL_ID, 'contributor_id' => null,
        ]);

        return ['id' => $id, 'slug' => $slug];
    }

    /** The Insights articles tab, as this person sees it */
    private function ledger(User $user)
    {
        return Livewire::actingAs($user)->test(SignoffManager::class)
            ->call('switchMainTab', 'marketing')
            ->call('switchMarketingTab', 'kaizen_ledger');
    }

    private function row(string $id): object
    {
        return DB::table('kaizen_articles')->where('id', $id)->first();
    }

    private function audit(string $action, string $id): ?object
    {
        return DB::table('system_audit_logs')->where('action', $action)->where('target_id', $id)->first();
    }

    /** Slugs in the site API's article list (GET /kaizen/articles, first page) */
    private function apiSlugs(): array
    {
        return array_column($this->siteGet('/kaizen/articles')->assertOk()->json('data.articles'), 'slug');
    }

    public function test_a_draft_is_published_logged_and_shown_by_the_site_api(): void
    {
        $draft = $this->article('Draft', ['title' => 'Why month-end takes three weeks']);
        $partner = $this->staff('partner', ['partner']);
        $this->assertNotContains($draft['slug'], $this->apiSlugs());

        $this->ledger($partner)
            ->call('askArticleChange', $draft['id'], 'publish')
            ->assertSet('confirmArticleId', $draft['id'])
            ->assertSet('confirmChange', 'publish')
            ->assertSee('Publish this article?')
            ->assertSee('‘Why month-end takes three weeks’')
            ->assertSeeHtml('aria-describedby="kz-confirm-name kz-confirm-text" x-trap.noscroll="true"')
            ->call('publishArticle', $draft['id'])
            ->assertSet('confirmArticleId', null)
            ->assertDispatched('praxis-toast', $this->toast('Published ‘Why month-end takes three weeks’. It appears on the website within 10 minutes.'))
            ->assertDontSee('HQ doesn’t email contributors');

        $row = $this->row($draft['id']);
        $this->assertSame('Published', $row->status);
        $this->assertSame(1, (int) $row->is_public);
        $this->assertNotNull($row->published_at);
        $this->assertSame($partner->id, $row->updated_by);

        $log = $this->audit('KAIZEN_ARTICLE_PUBLISHED', $draft['id']);
        $this->assertNotNull($log);
        $this->assertSame($partner->id, $log->user_id);
        $this->assertSame('kaizen_articles', $log->target_table);
        $this->assertSame('Published the Insights article ‘Why month-end takes three weeks’.', $log->description);
        $this->assertEquals(['status' => 'Draft', 'is_public' => false], json_decode($log->old_values, true));
        $this->assertEquals(['status' => 'Published', 'is_public' => true, 'published_at' => (string) $row->published_at], json_decode($log->new_values, true));

        $this->assertContains($draft['slug'], array_column(app(KaizenService::class)->articles(1, null)['articles'], 'slug'));
        $this->assertContains($draft['slug'], $this->apiSlugs());
        $this->siteGet("/kaizen/articles/{$draft['slug']}")->assertOk()->assertJsonPath('data.title', 'Why month-end takes three weeks');
        Mail::assertNothingSent();
    }

    public function test_a_contributors_article_waiting_for_review_is_published_without_an_email(): void
    {
        // The real path: the website's Write for Insights form
        app(KaizenService::class)->submit([
            'full_name' => 'Ada Okafor', 'title' => 'Head of operations', 'email' => 'ada.okafor@example.test',
            'linkedin_url' => 'https://www.linkedin.com/in/ada-test', 'article_title' => 'Closing the books in five days',
            'exec_summary' => 'How one finance team closed its books faster.', 'vulnerability' => 'The close took three weeks.',
            'solution' => 'One ledger and a checklist.', 'roi' => 'The close now takes five days.', 'category' => 'The Boardroom',
        ], '203.0.113.7');
        $pending = DB::table('kaizen_articles')->where('title', 'Closing the books in five days')->first();
        $this->assertSame('Pending Moderation', $pending->status);

        $this->ledger($this->staff('partner', ['partner']))
            ->assertSee('Waiting for review')
            ->assertSee('Ada Okafor')
            ->call('publishArticle', $pending->id)
            ->assertDispatched('praxis-toast', $this->toast('Published ‘Closing the books in five days’. It appears on the website within 10 minutes. HQ doesn’t email contributors, so let Ada Okafor know yourself (ada.okafor@example.test).'));

        $this->assertSame('Published', $this->row($pending->id)->status);
        $this->assertEquals(['status' => 'Pending Moderation', 'is_public' => false],
            json_decode($this->audit('KAIZEN_ARTICLE_PUBLISHED', $pending->id)->old_values, true));
        $this->assertContains($pending->slug, $this->apiSlugs());
        // No email exists for contributors (app/Mail), so none is sent
        Mail::assertNothingSent();
    }

    public function test_unpublishing_takes_the_article_off_the_site_and_keeps_its_first_publication_date(): void
    {
        $live = $this->article('Published', ['title' => 'Five signs your ERP is costing you', 'published_at' => '2026-10-01 09:00:00']);
        $partner = $this->staff('partner', ['partner']);
        $this->assertContains($live['slug'], $this->apiSlugs());

        $this->ledger($partner)
            ->call('askArticleChange', $live['id'], 'unpublish')
            ->assertSee('Unpublish this article?')
            ->assertSee('Keep published')
            ->call('unpublishArticle', $live['id'])
            ->assertDispatched('praxis-toast', $this->toast('Unpublished ‘Five signs your ERP is costing you’. It’s a draft again and leaves the website within 10 minutes.'));

        $row = $this->row($live['id']);
        $this->assertSame('Draft', $row->status);
        $this->assertSame(0, (int) $row->is_public);
        $this->assertSame('2026-10-01 09:00:00', (string) $row->published_at);

        $log = $this->audit('KAIZEN_ARTICLE_UNPUBLISHED', $live['id']);
        $this->assertNotNull($log);
        $this->assertEquals(['status' => 'Published', 'is_public' => true], json_decode($log->old_values, true));
        $this->assertEquals(['status' => 'Draft', 'is_public' => false], json_decode($log->new_values, true));

        $this->assertNotContains($live['slug'], array_column(app(KaizenService::class)->articles(1, null)['articles'], 'slug'));
        $this->assertNotContains($live['slug'], $this->apiSlugs());
        $this->siteGet("/kaizen/articles/{$live['slug']}")->assertNotFound();

        // Publishing it again keeps the first publication date
        $this->ledger($partner)->call('publishArticle', $live['id']);
        $this->assertSame('2026-10-01 09:00:00', (string) $this->row($live['id'])->published_at);
        $this->assertContains($live['slug'], $this->apiSlugs());
    }

    public function test_changes_outside_the_allowed_transitions_are_refused(): void
    {
        $published = $this->article('Published', ['published_at' => '2026-10-01 09:00:00']);
        $draft = $this->article('Draft');
        $pending = $this->article('Pending Moderation');
        $rejected = $this->article('Rejected');
        $component = $this->ledger($this->staff('partner', ['partner']));

        $component->call('publishArticle', $published['id'])->assertDispatched('praxis-toast', $this->toast('This article is already published, so nothing changed. The list is up to date now.'));
        $component->call('unpublishArticle', $draft['id'])->assertDispatched('praxis-toast', $this->toast('This article isn’t published, so nothing changed.'));
        $component->call('unpublishArticle', $pending['id'])->assertDispatched('praxis-toast', $this->toast('This article isn’t published, so nothing changed.'));
        $component->call('publishArticle', $rejected['id'])->assertDispatched('praxis-toast', $this->toast('Only a draft or an article waiting for review can be published, so nothing changed.'));
        $component->call('askArticleChange', $rejected['id'], 'publish')->assertSet('confirmArticleId', null)
            ->assertSee('Only a draft or an article waiting for review can be published, so nothing changed.');
        $component->call('publishArticle', (string) Str::uuid())->assertSee('We couldn’t find this article; it may have been deleted.');
        // Only the two named changes exist
        $component->call('askArticleChange', $draft['id'], 'reject')->assertForbidden();

        $this->assertSame('Published', $this->row($published['id'])->status);
        $this->assertSame('2026-10-01 09:00:00', (string) $this->row($published['id'])->published_at);
        $this->assertSame('Draft', $this->row($draft['id'])->status);
        $this->assertSame('Pending Moderation', $this->row($pending['id'])->status);
        $this->assertSame('Rejected', $this->row($rejected['id'])->status);
        $this->assertSame(0, DB::table('system_audit_logs')->whereIn('action', ['KAIZEN_ARTICLE_PUBLISHED', 'KAIZEN_ARTICLE_UNPUBLISHED'])->count());
    }

    public function test_only_people_who_approve_insights_can_publish_or_unpublish(): void
    {
        $draft = $this->article('Draft');
        $published = $this->article('Published');

        // Delivery Lead has kaizen.edit but not kaizen.approve; Auditor reads everything and changes nothing
        foreach (['delivery', 'auditor'] as $role) {
            $user = $this->staff($role, [$role]);
            $this->ledger($user)
                ->assertSet('canPublishKaizen', false)
                ->assertSee('Read')
                ->assertDontSeeHtml('askArticleChange');
            $this->ledger($user)->call('publishArticle', $draft['id'])->assertForbidden();
            $this->ledger($user)->call('unpublishArticle', $published['id'])->assertForbidden();
            $this->ledger($user)->call('askArticleChange', $draft['id'], 'publish')->assertForbidden();
        }

        $this->assertSame('Draft', $this->row($draft['id'])->status);
        $this->assertSame('Published', $this->row($published['id'])->status);
        $this->assertSame(0, DB::table('system_audit_logs')->whereIn('action', ['KAIZEN_ARTICLE_PUBLISHED', 'KAIZEN_ARTICLE_UNPUBLISHED'])->count());

        // Sales & Marketing holds kaizen.approve
        $this->ledger($this->staff('sales', ['sales']))->assertSet('canPublishKaizen', true)->assertSeeHtml('askArticleChange');

        // HR has no kaizen.view, so it can't open an article in the reader either
        Livewire::actingAs($this->staff('hr', ['hr']))->test(SignoffManager::class)
            ->call('readArticle', $draft['id'])->assertForbidden();
    }

    public function test_a_draft_saved_without_a_slug_gets_one_when_published(): void
    {
        // As "Draft an Insights article" saves it: no slug, and the website lists an article only by its slug
        $draft = $this->article('Draft', ['title' => 'Why the month-end close slips', 'slug' => null]);

        $this->ledger($this->staff('partner', ['partner']))->call('publishArticle', $draft['id']);

        $slug = $this->row($draft['id'])->slug;
        $this->assertMatchesRegularExpression('/^why-the-month-end-close-slips-[A-Za-z0-9]{5}$/', (string) $slug);
        $this->assertSame($slug, json_decode($this->audit('KAIZEN_ARTICLE_PUBLISHED', $draft['id'])->new_values, true)['slug']);
        $this->assertContains($slug, $this->apiSlugs());
        $this->siteGet("/kaizen/articles/{$slug}")->assertOk()->assertJsonPath('data.title', 'Why the month-end close slips');

        // An article that already has one keeps it
        $named = $this->article('Draft');
        $this->ledger($this->staff('partner', ['partner']))->call('publishArticle', $named['id']);
        $this->assertSame($named['slug'], $this->row($named['id'])->slug);
        $this->assertArrayNotHasKey('slug', json_decode($this->audit('KAIZEN_ARTICLE_PUBLISHED', $named['id'])->new_values, true));
    }

    public function test_the_browser_cannot_change_the_locked_state(): void
    {
        $draft = $this->article('Draft');
        $component = $this->ledger($this->staff('delivery', ['delivery']));

        foreach (['canPublishKaizen' => true, 'confirmArticleId' => $draft['id'], 'confirmChange' => 'publish', 'readingArticleId' => $draft['id']] as $property => $value) {
            try {
                $component->set($property, $value);
                $this->fail("{$property} should be locked");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_reader_shows_the_whole_article(): void
    {
        $contributorId = (string) Str::uuid();
        $this->insertRow('client_users', [
            'id' => $contributorId, 'organization_id' => \Database\Seeders\CoreSeeder::INTERNAL_ORG_ID, 'first_name' => 'Ada', 'last_name' => 'Okafor',
            'email' => 'ada.' . Str::random(6) . '@example.test', 'job_title' => 'Head of operations', 'deleted_at' => null,
        ]);
        $pending = $this->article('Pending Moderation', [
            'title' => 'Closing the books in five days', 'category' => 'The Server Room', 'read_time_minutes' => 4, 'contributor_id' => $contributorId,
            'executive_summary' => 'How one finance team closed its books faster.',
            'problem_statement' => "The close took **three weeks**.\n\nNobody trusted the numbers. <script>alert(1)</script>",
            'engineered_solution' => 'One ledger and a checklist.',
            'final_roi' => 'The close now takes five days.',
            'created_at' => '2026-10-20 10:00:00',
        ]);
        $editorial = $this->article('Draft', ['title' => 'An editorial draft']);

        $this->ledger($this->staff('partner', ['partner']))
            ->call('readArticle', $pending['id'])
            ->assertSet('readingArticleId', $pending['id'])
            ->assertSeeHtml('id="kz-reader-title"')
            ->assertSee('Closing the books in five days')
            ->assertSee('The Server Room')
            ->assertSee('Waiting for review')
            ->assertSee('Ada Okafor')
            ->assertSee('Head of operations')
            ->assertSee('20 Oct 2026')
            ->assertSee('Not yet')
            ->assertSeeInOrder(['Summary', 'How one finance team closed its books faster.', 'The problem', 'Nobody trusted the numbers.',
                'What was done', 'One ledger and a checklist.', 'The result', 'The close now takes five days.'])
            ->assertSeeHtml('<p>The close took <strong>three weeks</strong>.</p>')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSeeHtml("askArticleChange('{$pending['id']}', 'publish')")
            ->call('closeArticle')
            ->assertSet('readingArticleId', null)
            ->assertDontSeeHtml('id="kz-reader-title"')
            ->call('readArticle', $editorial['id'])
            ->assertSee('JBS Praxis Editorial');

        // Escape closes the confirmation first, then the reader
        $this->ledger($this->staff('partner', ['partner']))
            ->call('readArticle', $pending['id'])
            ->call('askArticleChange', $pending['id'], 'publish')
            // Over the reader, only the reader locks the page's scroll (two locks closing together leave it locked)
            ->assertSeeHtml('x-trap="true" style="max-width: 500px;"')
            ->call('dismissArticle')
            ->assertSet('confirmArticleId', null)
            ->assertSet('readingArticleId', $pending['id'])
            ->call('dismissArticle')
            ->assertSet('readingArticleId', null);
        $this->assertSame('Pending Moderation', $this->row($pending['id'])->status);
    }

    public function test_staff_filter_the_ledger_by_status(): void
    {
        DB::table('kaizen_articles')->delete(); // the seeded website articles, so the counts are this test's own
        $this->article('Draft', ['title' => 'Draft one']);
        $this->article('Pending Moderation', ['title' => 'Submitted one']);
        $this->article('Published', ['title' => 'Live one']);

        $this->ledger($this->staff('auditor', ['auditor']))
            ->assertSee(['Draft one', 'Submitted one', 'Live one'])
            ->assertSee('All articles (3)')
            ->assertSee('Waiting for review (1)')
            ->set('kaizenStatusFilter', 'Pending Moderation')
            ->assertSee('Submitted one')->assertDontSee('Draft one')->assertDontSee('Live one')
            ->set('kaizenStatusFilter', 'Published')
            ->assertSee('Live one')->assertDontSee('Submitted one')
            ->set('kaizenStatusFilter', 'Rejected')
            ->assertSee('No articles with this status')
            ->set('kaizenStatusFilter', 'Something else')
            ->assertSee(['Draft one', 'Submitted one', 'Live one']);
    }

    /** A GET signed as praxis_site signs it (docs/SITE_API.md section 6.1), as in SiteApiTest */
    private function siteGet(string $path): TestResponse
    {
        $fullPath = '/api/site/v1' . $path;
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(12));
        $canonical = SiteSignature::canonical('GET', $fullPath, $timestamp, $nonce, '203.0.113.5', 'en', self::KEY, '', hash('sha256', ''));

        return $this->call('GET', 'http://' . Hosts::hq() . $fullPath, [], [], [], [
            'REMOTE_ADDR' => '198.51.100.10', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SITE_KEY' => self::KEY, 'HTTP_X_SITE_TIMESTAMP' => $timestamp, 'HTTP_X_SITE_NONCE' => $nonce,
            'HTTP_X_SITE_VISITOR_IP' => '203.0.113.5', 'HTTP_X_SITE_LOCALE' => 'en',
            'HTTP_X_SITE_SIGNATURE' => SiteSignature::sign(self::SECRET, $canonical),
        ], '');
    }
}
