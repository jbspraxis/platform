<?php

namespace Tests\Feature;

use App\Livewire\Hq\Marketing\InsightsArticles;
use App\Mail\InsightsArticlePublishedMail;
use App\Mail\InsightsArticleRejectedMail;
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
 * HQ → Marketing → Insights articles: read an article in full, correct its wording (kaizen.edit), then publish it
 * (a draft, or a contributor's article waiting for review), reject it with a note, or unpublish it (back to draft).
 * Publishing and rejecting a contributor's article emails them. Only kaizen.approve decides, every change is in the
 * audit log, and the site API follows at once. Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class InsightsArticlesTest extends TestCase
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

    /** The Insights articles screen, as this person sees it */
    private function ledger(User $user)
    {
        return Livewire::actingAs($user)->test(InsightsArticles::class);
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
            ->assertDontSee('has been emailed');

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

    /** A contributor's article, sent through the website's Write for Insights form as the page at /fr or / sends it */
    private function submitted(string $locale = 'en'): object
    {
        app()->setLocale($locale);
        app(KaizenService::class)->submit([
            'full_name' => 'Ada Okafor', 'title' => 'Head of operations', 'email' => 'ada.okafor@example.test',
            'linkedin_url' => 'https://www.linkedin.com/in/ada-test', 'article_title' => 'Closing the books in five days',
            'exec_summary' => 'How one finance team closed its books faster.', 'vulnerability' => 'The close took three weeks.',
            'solution' => 'One ledger and a checklist.', 'roi' => 'The close now takes five days.', 'category' => 'The Boardroom',
        ], '203.0.113.7');
        app()->setLocale('en');

        return DB::table('kaizen_articles')->where('title', 'Closing the books in five days')->first();
    }

    public function test_a_submission_notifies_the_approvers_with_a_link_that_opens_the_article(): void
    {
        $partner = $this->staff('partner', ['partner']);
        $pending = $this->submitted();
        $this->assertSame('Pending Moderation', $pending->status);
        $this->assertSame('en', $pending->submitted_locale);

        $note = DB::table('notifications')->where('user_id', $partner->id)->where('title', 'Kaizen article waiting for review')->first();
        $this->assertNotNull($note);
        $this->assertSame('/marketing/insights-articles?article=' . $pending->id, $note->link_url);

        // The link lands on the article, open in the reader
        $this->actingAs($partner);
        Livewire::withQueryParams(['article' => $pending->id])->test(InsightsArticles::class)
            ->assertSet('readingArticleId', $pending->id)
            ->assertSee('Closing the books in five days')
            ->assertSee('Publish');
        // A link to an article that is gone says so, and the list still opens
        Livewire::withQueryParams(['article' => (string) Str::uuid()])->test(InsightsArticles::class)
            ->assertSet('readingArticleId', null)
            ->assertDispatched('praxis-toast', $this->toast('We couldn’t find this article'));
    }

    public function test_publishing_a_contributors_article_emails_them_the_link_once(): void
    {
        $pending = $this->submitted();
        $partner = $this->staff('partner', ['partner']);

        $this->ledger($partner)
            ->assertSee('Waiting for review')
            ->assertSee('Ada Okafor')
            ->call('askArticleChange', $pending->id, 'publish')
            ->assertSee('The contributor is emailed the link.')
            ->call('publishArticle', $pending->id)
            ->assertDispatched('praxis-toast', $this->toast('Published ‘Closing the books in five days’. It appears on the website within 10 minutes. Ada Okafor has been emailed the link.'));

        $this->assertSame('Published', $this->row($pending->id)->status);
        $this->assertEquals(['status' => 'Pending Moderation', 'is_public' => false],
            json_decode($this->audit('KAIZEN_ARTICLE_PUBLISHED', $pending->id)->old_values, true));
        $this->assertContains($pending->slug, $this->apiSlugs());

        Mail::assertSent(InsightsArticlePublishedMail::class, function (InsightsArticlePublishedMail $mail) use ($pending) {
            $parts = $mail->locale('en')->renderParts();

            return $mail->hasTo('ada.okafor@example.test') && $mail->slug === $pending->slug
                && $parts['subject'] === 'Your article is now on JBS Praxis Insights'
                && str_contains($parts['html'], 'Hello Ada,')
                && str_contains($parts['html'], '/kaizen/' . $pending->slug);
        });
        Mail::assertSentCount(1);

        // Unpublish and publish again: the contributor already heard, so there is no second email
        $component = $this->ledger($partner);
        $component->call('unpublishArticle', $pending->id)->call('publishArticle', $pending->id);
        Mail::assertSentCount(1);
    }

    public function test_the_email_is_written_in_the_language_of_the_page_the_article_was_sent_from(): void
    {
        $pending = $this->submitted('fr');
        $this->assertSame('fr', $pending->submitted_locale);

        $this->ledger($this->staff('partner', ['partner']))->call('publishArticle', $pending->id);

        Mail::assertSent(InsightsArticlePublishedMail::class, function (InsightsArticlePublishedMail $mail) {
            $parts = $mail->renderParts();

            return $mail->locale === 'fr'
                && $parts['subject'] === 'Votre article est en ligne sur JBS Praxis Insights'
                && str_contains($parts['html'], '/fr/kaizen/');
        });
    }

    public function test_a_failed_email_never_undoes_the_publication(): void
    {
        $pending = $this->submitted();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));

        $this->ledger($this->staff('partner', ['partner']))
            ->call('publishArticle', $pending->id)
            ->assertDispatched('praxis-toast', $this->toast('We couldn’t email Ada Okafor, so let them know yourself (ada.okafor@example.test).'));

        $this->assertSame('Published', $this->row($pending->id)->status);
        $this->assertContains($pending->slug, $this->apiSlugs());
    }

    public function test_rejecting_needs_a_note_keeps_the_article_off_the_site_and_emails_the_contributor(): void
    {
        $pending = $this->submitted();
        $partner = $this->staff('partner', ['partner']);

        $component = $this->ledger($partner)
            ->call('askArticleChange', $pending->id, 'reject')
            ->assertSet('confirmChange', 'reject')
            ->assertSee('Reject this article?')
            ->assertSee('Note to the contributor');

        // No note, or a throwaway one: the confirmation stays open and nothing changes
        $component->call('rejectArticle', $pending->id)->assertHasErrors(['rejectReason' => 'required'])->assertSet('confirmArticleId', $pending->id);
        $component->set('rejectReason', 'No.')->call('rejectArticle', $pending->id)->assertHasErrors(['rejectReason' => 'min']);
        $this->assertSame('Pending Moderation', $this->row($pending->id)->status);
        Mail::assertNothingSent();

        $note = "Please add a figure we can check: how long did the close take before?\n\nThen send it again.";
        $component->set('rejectReason', $note)
            ->call('rejectArticle', $pending->id)
            ->assertSet('confirmArticleId', null)
            ->assertSet('rejectReason', '')
            ->assertDispatched('praxis-toast', $this->toast('Turned down ‘Closing the books in five days’. It stays off the website. Ada Okafor has been emailed your note.'));

        $row = $this->row($pending->id);
        $this->assertSame('Rejected', $row->status);
        $this->assertSame(0, (int) $row->is_public);
        $this->assertNull($row->published_at);
        $this->assertSame($note, $row->rejected_reason);
        $this->assertNotNull($row->rejected_at);
        $this->assertSame($partner->id, $row->updated_by);
        $this->assertNotContains($pending->slug, $this->apiSlugs());

        $log = $this->audit('KAIZEN_ARTICLE_REJECTED', $pending->id);
        $this->assertNotNull($log);
        $this->assertSame('Turned down the Insights article ‘Closing the books in five days’.', $log->description);
        $this->assertEquals(['status' => 'Rejected', 'is_public' => false, 'rejected_reason' => $note], json_decode($log->new_values, true));

        Mail::assertSent(InsightsArticleRejectedMail::class, function (InsightsArticleRejectedMail $mail) use ($note) {
            $parts = $mail->locale('en')->renderParts();

            return $mail->hasTo('ada.okafor@example.test') && $mail->reason === $note
                && $parts['subject'] === 'About your article ‘Closing the books in five days’'
                && str_contains($parts['html'], 'Please add a figure we can check')
                && str_contains($parts['html'], '/kaizen/write');
        });
        Mail::assertNotSent(InsightsArticlePublishedMail::class);

        // The reader shows the note, and a rejected article can't be rejected or published again
        $this->ledger($partner)->call('readArticle', $pending->id)->assertSee('Your note to the contributor')->assertSee('Please add a figure we can check')
            ->assertDontSeeHtml("askArticleChange('{$pending->id}', 'publish')");
        $this->ledger($partner)->call('rejectArticle', $pending->id)->assertHasErrors('rejectReason');
        $this->ledger($partner)->call('askArticleChange', $pending->id, 'reject')
            ->assertDispatched('praxis-toast', $this->toast('Only an article waiting for review can be turned down, so nothing changed.'));
    }

    public function test_only_an_article_waiting_for_review_can_be_rejected(): void
    {
        $draft = $this->article('Draft');
        $live = $this->article('Published');

        $component = $this->ledger($this->staff('partner', ['partner']));
        foreach ([$draft['id'], $live['id']] as $id) {
            $component->call('askArticleChange', $id, 'reject')->assertSet('confirmArticleId', null)
                ->assertDispatched('praxis-toast', $this->toast('Only an article waiting for review can be turned down, so nothing changed.'));
            $component->set('rejectReason', 'Not what we are looking for, sorry.')->call('rejectArticle', $id);
        }

        $this->assertSame('Draft', $this->row($draft['id'])->status);
        $this->assertSame('Published', $this->row($live['id'])->status);
        $this->assertSame(0, DB::table('system_audit_logs')->where('action', 'KAIZEN_ARTICLE_REJECTED')->count());
        Mail::assertNothingSent();
    }

    public function test_a_pending_article_with_no_contributor_is_turned_down_quietly(): void
    {
        // A pending article with no contributor (nobody to email) is turned down quietly
        $pending = $this->article('Pending Moderation');

        $this->ledger($this->staff('partner', ['partner']))
            ->call('askArticleChange', $pending['id'], 'reject')
            ->assertDontSee('The contributor is emailed')
            ->set('rejectReason', 'Not for the website, thank you.')
            ->call('rejectArticle', $pending['id'])
            ->assertDispatched('praxis-toast', $this->toast('Turned down ‘A Pending Moderation article’. It stays off the website.'));

        $this->assertSame('Rejected', $this->row($pending['id'])->status);
        Mail::assertNothingSent();
    }

    public function test_wording_is_corrected_in_place_and_logged(): void
    {
        $pending = $this->article('Pending Moderation', [
            'title' => 'Closing the bokos in five days', 'executive_summary' => 'Short.', 'problem_statement' => 'The close took three weeks.',
            'engineered_solution' => 'A ledger.', 'final_roi' => 'Five days.', 'read_time_minutes' => 1,
        ]);
        $editor = $this->staff('sales', ['sales']);

        $this->ledger($editor)
            ->call('readArticle', $pending['id'])
            ->assertSee('Edit')
            ->call('startEdit', $pending['id'])
            ->assertSet('editing', true)
            ->assertSet('form.title', 'Closing the bokos in five days')
            ->assertSee('Save changes')
            ->set('form.title', 'Closing the books in five days')
            ->set('form.problem_statement', str_repeat('word ', 450))
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('editing', false)
            ->assertSet('readingArticleId', $pending['id'])
            ->assertDispatched('praxis-toast', $this->toast('Saved your changes to ‘Closing the books in five days’.'));

        $row = $this->row($pending['id']);
        $this->assertSame('Closing the books in five days', $row->title);
        $this->assertSame('Pending Moderation', $row->status);
        $this->assertSame($pending['slug'], $row->slug); // the address never changes
        $this->assertSame(3, (int) $row->read_time_minutes); // 450 + 2 words, 200 a minute
        $this->assertSame($editor->id, $row->updated_by);

        $log = $this->audit('KAIZEN_ARTICLE_EDITED', $pending['id']);
        $this->assertNotNull($log);
        $this->assertSame('Edited the Insights article ‘Closing the bokos in five days’ (title, problem_statement).', $log->description);
        $this->assertSame('Closing the bokos in five days', json_decode($log->old_values, true)['title']);
        $this->assertSame('Closing the books in five days', json_decode($log->new_values, true)['title']);
        $this->assertArrayNotHasKey('final_roi', json_decode($log->new_values, true));
    }

    public function test_an_edit_is_checked_before_it_is_saved(): void
    {
        $draft = $this->article('Draft', ['title' => 'Untouched']);

        $this->ledger($this->staff('partner', ['partner']))
            ->call('startEdit', $draft['id'])
            ->set('form.title', '')
            ->set('form.category', 'Not a category')
            ->set('form.executive_summary', implode(' ', array_fill(0, 51, 'word')))
            ->set('form.final_roi', '   ')
            ->call('saveEdit')
            ->assertHasErrors(['form.title' => 'required', 'form.category' => 'in', 'form.executive_summary', 'form.final_roi' => 'required'])
            ->assertSee('Add the title.')
            ->assertSee('Keep the summary to 50 words or fewer')
            ->assertSet('editing', true);

        $this->assertSame('Untouched', $this->row($draft['id'])->title);
        $this->assertNull($this->audit('KAIZEN_ARTICLE_EDITED', $draft['id']));

        // Saving without a change is not a change
        $this->ledger($this->staff('partner', ['partner']))->call('startEdit', $draft['id'])->call('saveEdit')
            ->assertDispatched('praxis-toast', $this->toast('Nothing was changed.'));
        $this->assertNull($this->audit('KAIZEN_ARTICLE_EDITED', $draft['id']));
    }

    public function test_only_people_who_may_edit_can_edit_and_only_before_it_is_live_or_turned_down(): void
    {
        $draft = $this->article('Draft');
        $live = $this->article('Published', ['title' => 'Live one']);
        $turnedDown = $this->article('Rejected');

        // Auditor reads, Engineer may write articles (kaizen.create) but not edit others'
        foreach (['auditor', 'engineer'] as $role) {
            $component = $this->ledger($this->staff($role, [$role]));
            $component->assertDontSee('startEdit');
            $component->call('startEdit', $draft['id'])->assertForbidden();
        }

        // Delivery Lead has kaizen.edit, but not kaizen.approve
        $delivery = $this->ledger($this->staff('delivery', ['delivery']))->call('readArticle', $draft['id'])->assertSeeHtml("startEdit('{$draft['id']}')");
        $delivery->call('startEdit', $draft['id'])->assertSet('editing', true)->set('form.title', 'Changed by delivery')->call('saveEdit');
        $this->assertSame('Changed by delivery', $this->row($draft['id'])->title);

        // A live article is unpublished first; a turned-down one is the contributor's to revise
        $partner = $this->ledger($this->staff('partner', ['partner']));
        $partner->call('startEdit', $live['id'])->assertSet('editing', false)
            ->assertDispatched('praxis-toast', $this->toast('Only a draft or an article waiting for review can be edited. Unpublish a live article first.'));
        $partner->call('startEdit', $turnedDown['id'])->assertSet('editing', false);
        $partner->call('startEdit', (string) Str::uuid())->assertDispatched('praxis-toast', $this->toast('We couldn’t find this article'));
        $this->assertSame('Live one', $this->row($live['id'])->title);
    }

    public function test_the_screen_opens_with_kaizen_permissions_alone_and_lists_what_waits_first(): void
    {
        // Sales & Marketing holds kaizen.* but not signoffs.view, so the old home of this screen was closed to it
        $route = app('router')->getRoutes()->getByName('hq.marketing.insights');
        $this->assertNotNull($route);
        $this->assertSame('marketing/insights-articles', ltrim($route->uri(), '/'));
        $this->assertContains('can:kaizen.view', $route->gatherMiddleware());
        $this->assertFalse($this->staff('sales', ['sales'])->can('signoffs.view'));

        DB::table('kaizen_articles')->delete();
        $this->article('Published', ['title' => 'Live one', 'created_at' => '2026-11-06 10:00:00']);
        $this->article('Pending Moderation', ['title' => 'Older submission', 'created_at' => '2026-11-01 10:00:00']);

        $this->ledger($this->staff('sales', ['sales']))
            ->assertSee('1 article is waiting for your review.')
            ->assertSeeInOrder(['Older submission', 'Live one']);

        // HR has no kaizen.view: no screen at all
        Livewire::actingAs($this->staff('hr', ['hr']))->test(InsightsArticles::class)->assertForbidden();
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
            ->assertDispatched('praxis-toast', $this->toast('Only a draft or an article waiting for review can be published, so nothing changed.'));
        $component->call('publishArticle', (string) Str::uuid())->assertDispatched('praxis-toast', $this->toast('We couldn’t find this article; it may have been deleted.'));
        // Only the three named changes exist
        $component->call('askArticleChange', $draft['id'], 'delete')->assertForbidden();

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
                ->assertSet('canReview', false)
                ->assertSee('Read')
                ->assertDontSeeHtml('askArticleChange');
            $this->ledger($user)->call('publishArticle', $draft['id'])->assertForbidden();
            $this->ledger($user)->call('unpublishArticle', $published['id'])->assertForbidden();
            $this->ledger($user)->call('askArticleChange', $draft['id'], 'publish')->assertForbidden();
            $this->ledger($user)->call('askArticleChange', $draft['id'], 'reject')->assertForbidden();
            $this->ledger($user)->set('rejectReason', 'Not for us, thank you.')->call('rejectArticle', $draft['id'])->assertForbidden();
        }

        $this->assertSame('Draft', $this->row($draft['id'])->status);
        $this->assertSame('Published', $this->row($published['id'])->status);
        $this->assertSame(0, DB::table('system_audit_logs')->whereIn('action', ['KAIZEN_ARTICLE_PUBLISHED', 'KAIZEN_ARTICLE_UNPUBLISHED', 'KAIZEN_ARTICLE_REJECTED'])->count());

        // Sales & Marketing holds kaizen.approve
        $this->ledger($this->staff('sales', ['sales']))->assertSet('canReview', true)->assertSeeHtml('askArticleChange');

        // HR has no kaizen.view: no screen at all
        Livewire::actingAs($this->staff('hr', ['hr']))->test(InsightsArticles::class)->assertForbidden();
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

        foreach (['canReview' => true, 'canEdit' => true, 'editing' => true, 'confirmArticleId' => $draft['id'], 'confirmChange' => 'publish', 'readingArticleId' => $draft['id']] as $property => $value) {
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

        // ...and the editor sits between them: Escape leaves it before the reader
        $this->ledger($this->staff('partner', ['partner']))
            ->call('readArticle', $pending['id'])->call('startEdit', $pending['id'])->assertSet('editing', true)
            ->call('dismissArticle')->assertSet('editing', false)->assertSet('readingArticleId', $pending['id'])
            ->call('dismissArticle')->assertSet('readingArticleId', null);
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
