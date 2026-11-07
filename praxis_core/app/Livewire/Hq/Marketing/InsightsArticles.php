<?php

namespace App\Livewire\Hq\Marketing;

use App\Mail\InsightsArticlePublishedMail;
use App\Mail\InsightsArticleRejectedMail;
use App\Services\Site\KaizenService;
use App\Support\MailLocale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * HQ → Marketing → Insights articles: where an article sent through "Write for Insights" on the website (and the
 * drafts saved from a signed project) is read, corrected, published or turned down. The "waiting for review"
 * notification opens its article here (?article=…).
 *
 *   kaizen.view     open the screen and read an article
 *   kaizen.edit     correct the wording of a draft or an article waiting for review
 *   kaizen.approve  publish, unpublish, or turn down (with a note the contributor receives by email)
 *
 * Every change is in the audit log. The website caches articles, so a change shows there within 10 minutes.
 */
#[Layout('components.layouts.hq')]
class InsightsArticles extends Component
{
    #[Locked]
    public bool $canEdit = false;
    #[Locked]
    public bool $canReview = false;

    /** Stored article statuses (kaizen_articles.status) and what staff read */
    public const STATUSES = ['Pending Moderation' => 'Waiting for review', 'Draft' => 'Draft', 'Published' => 'Published', 'Rejected' => 'Rejected'];

    /** The only status changes HQ makes to an article: from these statuses to that one */
    public const CHANGES = [
        'publish' => ['from' => ['Draft', 'Pending Moderation'], 'to' => 'Published', 'audit' => 'KAIZEN_ARTICLE_PUBLISHED'],
        'unpublish' => ['from' => ['Published'], 'to' => 'Draft', 'audit' => 'KAIZEN_ARTICLE_UNPUBLISHED'],
        'reject' => ['from' => ['Pending Moderation'], 'to' => 'Rejected', 'audit' => 'KAIZEN_ARTICLE_REJECTED'],
    ];

    /** Statuses whose wording can be corrected: a live article is unpublished first, a turned-down one is the contributor's to revise */
    public const EDITABLE = ['Draft', 'Pending Moderation'];

    /** The article's own text fields, in the order the website shows them */
    private const TEXT_FIELDS = ['title', 'category', 'executive_summary', 'problem_statement', 'engineered_solution', 'final_roi'];

    // The status shown ('' = all), the article open in the reader, the change waiting for confirmation
    public string $kaizenStatusFilter = '';
    #[Locked]
    public ?string $readingArticleId = null;
    #[Locked]
    public ?string $confirmArticleId = null;
    #[Locked]
    public ?string $confirmChange = null; // 'publish', 'unpublish' or 'reject'
    #[Locked]
    public bool $editing = false;

    /** The note a contributor receives when their article is turned down */
    public string $rejectReason = '';

    /** The wording being corrected */
    public array $form = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->canEdit = $user->can('kaizen.edit');
        $this->canReview = $user->can('kaizen.approve');

        // The notification link: /marketing/insights-articles?article=<id> opens that article
        $article = request()->query('article');
        if (is_string($article) && Str::isUuid($article)) {
            $this->readingArticleId = DB::table('kaizen_articles')->where('id', $article)->value('id');
            if (! $this->readingArticleId) {
                session()->flash('error', 'We couldn’t find this article; it may have been deleted. The list is up to date now.');
            }
        }
    }

    /** Opens an article in the reader */
    public function readArticle(string $articleId): void
    {
        abort_unless(Auth::user()->can('kaizen.view'), 403);

        $this->closeArticle();
        $this->readingArticleId = DB::table('kaizen_articles')->where('id', $articleId)->value('id');
        if (! $this->readingArticleId) {
            session()->flash('error', 'We couldn’t find this article; it may have been deleted. The list is up to date now.');
        }
    }

    /** Closes the reader, the editor and any confirmation over it */
    public function closeArticle(): void
    {
        $this->readingArticleId = null;
        $this->editing = false;
        $this->form = [];
        $this->resetErrorBag();
        $this->cancelArticleChange();
    }

    /** Escape: the confirmation first, then the editor, then the reader */
    public function dismissArticle(): void
    {
        if ($this->confirmArticleId) {
            $this->cancelArticleChange();
        } elseif ($this->editing) {
            $this->cancelEdit();
        } else {
            $this->closeArticle();
        }
    }

    // ───────────────────────── Correcting the wording ─────────────────────────

    /** Opens the editor over the article being read */
    public function startEdit(string $articleId): void
    {
        abort_unless(Auth::user()->can('kaizen.edit'), 403);

        $article = DB::table('kaizen_articles')->where('id', $articleId)->first();
        if (! $article || ! in_array($article->status, self::EDITABLE, true)) {
            session()->flash('error', ($article ? 'Only a draft or an article waiting for review can be edited. Unpublish a live article first.' : 'We couldn’t find this article; it may have been deleted.') . ' The list is up to date now.');
            $this->closeArticle();

            return;
        }

        $this->cancelArticleChange();
        $this->resetErrorBag();
        $this->readingArticleId = $article->id;
        $this->form = collect(self::TEXT_FIELDS)->mapWithKeys(fn ($field) => [$field => (string) $article->{$field}])->all();
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->form = [];
        $this->resetErrorBag();
    }

    /** Each field is checked as the person leaves it */
    public function updated($name): void
    {
        if ($this->editing && str_starts_with((string) $name, 'form.')) {
            $this->validateOnly($name, $this->formRules(), $this->formMessages(), $this->formAttributes());
        }
    }

    public function saveEdit(): void
    {
        abort_unless(Auth::user()->can('kaizen.edit') && $this->editing && $this->readingArticleId, 403);

        $article = DB::table('kaizen_articles')->where('id', $this->readingArticleId)->first();
        if (! $article || ! in_array($article->status, self::EDITABLE, true)) {
            session()->flash('error', ($article ? 'This article changed while you were editing, so nothing was saved.' : 'We couldn’t find this article; it may have been deleted.') . ' The list is up to date now.');
            $this->closeArticle();

            return;
        }

        $clean = $this->validate($this->formRules($article->category), $this->formMessages(), $this->formAttributes())['form'];
        $clean = array_map(fn ($text) => trim(strip_tags((string) $text)), $clean);

        $changes = [];
        foreach (self::TEXT_FIELDS as $field) {
            if ($clean[$field] !== (string) $article->{$field}) {
                $changes[$field] = $clean[$field];
            }
        }
        if (! $changes) {
            $this->cancelEdit();
            session()->flash('success', 'Nothing was changed.');

            return;
        }

        $values = $changes + ['updated_by' => Auth::id(), 'updated_at' => now()];
        // The read time follows the text, as when a contributor sends it
        $values['read_time_minutes'] = max(1, (int) ceil(str_word_count(($clean['problem_statement'] ?? '') . ' ' . ($clean['engineered_solution'] ?? '')) / 200));

        $saved = DB::transaction(function () use ($article, $changes, $values) {
            if (DB::table('kaizen_articles')->where('id', $article->id)->where('status', $article->status)->update($values) !== 1) {
                return false;
            }

            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'KAIZEN_ARTICLE_EDITED',
                'description' => "Edited the Insights article ‘{$article->title}’ (" . implode(', ', array_keys($changes)) . ').',
                'target_table' => 'kaizen_articles',
                'target_id' => $article->id,
                'old_values' => json_encode(array_intersect_key((array) $article, $changes)),
                'new_values' => json_encode($changes),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return true;
        });

        $this->cancelEdit();
        session()->flash($saved ? 'success' : 'error', $saved
            ? "Saved your changes to ‘{$clean['title']}’."
            : 'This article changed while you were editing, so nothing was saved. Open it again to see the latest.');
    }

    private function formRules(?string $currentCategory = null): array
    {
        $currentCategory ??= $this->readingArticleId ? DB::table('kaizen_articles')->where('id', $this->readingArticleId)->value('category') : null;

        return [
            'form.title' => 'required|string|max:255',
            'form.category' => ['required', Rule::in(array_filter([...KaizenService::CATEGORIES, $currentCategory]))],
            'form.executive_summary' => ['required', 'string', 'max:2000', function ($attribute, $value, $fail) {
                if (str_word_count((string) $value) > 50) {
                    $fail('Keep the summary to 50 words or fewer; it’s the first thing readers see.');
                }
            }],
            'form.problem_statement' => 'required|string|max:20000',
            'form.engineered_solution' => 'required|string|max:20000',
            'form.final_roi' => 'required|string|max:20000',
        ];
    }

    private function formMessages(): array
    {
        return [
            'required' => 'Add the :attribute.',
            'max' => 'Shorten the :attribute; it can be at most :max characters.',
            'form.category.in' => 'Choose one of the listed categories.',
        ];
    }

    private function formAttributes(): array
    {
        return [
            'form.title' => 'title', 'form.category' => 'category', 'form.executive_summary' => 'summary',
            'form.problem_statement' => 'problem', 'form.engineered_solution' => 'description of what was done', 'form.final_roi' => 'result',
        ];
    }

    // ───────────────────────── Publishing, unpublishing, turning down ─────────────────────────

    /** Opens the confirmation for publishing, unpublishing or turning down an article */
    public function askArticleChange(string $articleId, string $change): void
    {
        abort_unless(Auth::user()->can('kaizen.approve') && isset(self::CHANGES[$change]), 403);

        $article = DB::table('kaizen_articles')->where('id', $articleId)->first(['id', 'status']);
        if (! $article || ! in_array($article->status, self::CHANGES[$change]['from'], true)) {
            $this->closeArticle();
            session()->flash('error', $this->refusal($article, $change));

            return;
        }

        $this->resetErrorBag();
        $this->rejectReason = '';
        $this->editing = false;
        $this->confirmArticleId = $article->id;
        $this->confirmChange = $change;
    }

    public function cancelArticleChange(): void
    {
        $this->confirmArticleId = null;
        $this->confirmChange = null;
        $this->rejectReason = '';
        $this->resetErrorBag('rejectReason');
    }

    /** Draft or waiting for review -> Published. A contributor is emailed the link. */
    public function publishArticle(string $articleId): void
    {
        $this->changeArticleStatus($articleId, 'publish');
    }

    /** Published -> Draft: off the website within 10 minutes. The first publication date is kept. */
    public function unpublishArticle(string $articleId): void
    {
        $this->changeArticleStatus($articleId, 'unpublish');
    }

    /** Waiting for review -> Rejected, with a note the contributor receives by email */
    public function rejectArticle(string $articleId): void
    {
        abort_unless(Auth::user()->can('kaizen.approve'), 403);

        $this->rejectReason = trim($this->rejectReason);
        $this->validate(
            ['rejectReason' => 'required|string|min:10|max:2000'],
            ['rejectReason.required' => 'Write a short note for the contributor: what would make the article ready to publish?', 'rejectReason.min' => 'Write a little more; the contributor reads this note as it is.', 'rejectReason.max' => 'Shorten the note; it can be at most 2000 characters.'],
            ['rejectReason' => 'note'],
        );

        $this->changeArticleStatus($articleId, 'reject');
    }

    private function changeArticleStatus(string $articleId, string $change): void
    {
        abort_unless(Auth::user()->can('kaizen.approve'), 403);

        $rule = self::CHANGES[$change];
        $reason = $change === 'reject' ? $this->rejectReason : null;
        $article = DB::table('kaizen_articles')->where('id', $articleId)->first();
        $this->closeArticle();

        $values = ['status' => $rule['to'], 'is_public' => $change === 'publish' ? 1 : 0, 'updated_by' => Auth::id(), 'updated_at' => now()];
        if ($change === 'publish' && empty($article?->published_at)) {
            $values['published_at'] = now();
        }
        // Drafts saved from a sign-off have no slug, and the website lists an article only by its slug (made as in KaizenArticle::booted)
        if ($change === 'publish' && $article && blank($article->slug)) {
            $values['slug'] = (rtrim(Str::substr(Str::slug($article->title), 0, 80), '-') ?: 'insights-article') . '-' . Str::random(5);
        }
        if ($change === 'reject') {
            $values['rejected_reason'] = $reason;
            $values['rejected_at'] = now();
        }

        // Only from an allowed status, and only if nobody changed it a moment ago
        $changed = $article && in_array($article->status, $rule['from'], true)
            && DB::transaction(function () use ($article, $rule, $change, $values, $reason) {
                if (DB::table('kaizen_articles')->where('id', $article->id)->where('status', $article->status)->update($values) !== 1) {
                    return false;
                }

                $new = ['status' => $rule['to'], 'is_public' => $change === 'publish']
                    + (isset($values['published_at']) ? ['published_at' => $values['published_at']->toDateTimeString()] : [])
                    + (isset($values['slug']) ? ['slug' => $values['slug']] : [])
                    + ($reason !== null ? ['rejected_reason' => $reason] : []);

                DB::table('system_audit_logs')->insert([
                    'user_id' => Auth::id(),
                    'action' => $rule['audit'],
                    'description' => match ($change) {
                        'publish' => 'Published',
                        'unpublish' => 'Unpublished',
                        'reject' => 'Turned down',
                    } . " the Insights article ‘{$article->title}’.",
                    'target_table' => 'kaizen_articles',
                    'target_id' => $article->id,
                    'old_values' => json_encode(['status' => $article->status, 'is_public' => (bool) $article->is_public]),
                    'new_values' => json_encode($new),
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

        // A contributor hears about the first decision on their article, never about a later unpublish and publish
        $told = $article->status === 'Pending Moderation' ? $this->tellContributor($article, $change, $values['slug'] ?? $article->slug, $reason) : null;

        $headline = $change === 'publish'
            ? "Published ‘{$article->title}’. It appears on the website within 10 minutes."
            : "Turned down ‘{$article->title}’. It stays off the website.";
        if ($told === null) {
            session()->flash('success', $headline);
        } elseif ($told['sent']) {
            session()->flash('success', $headline . " {$told['name']} has been emailed" . ($change === 'reject' ? ' your note.' : ' the link.'));
        } else {
            session()->flash('error', $headline . " We couldn’t email {$told['name']}, so let them know yourself ({$told['email']}).");
        }
    }

    /**
     * Emails the contributor of an article that was waiting for review, in the language of the page it was sent from.
     * Null when it has no contributor (an editorial draft); a failed email never undoes the decision.
     *
     * @return array{name: string, email: string, sent: bool}|null
     */
    private function tellContributor(object $article, string $change, ?string $slug, ?string $reason): ?array
    {
        $contributor = $article->contributor_id
            ? DB::table('client_users')->where('id', $article->contributor_id)->first(['first_name', 'last_name', 'email'])
            : null;
        if (! $contributor || blank($contributor->email)) {
            return null;
        }

        $name = trim("{$contributor->first_name} {$contributor->last_name}") ?: $contributor->email;
        $mail = $change === 'publish'
            ? new InsightsArticlePublishedMail($article->title, (string) $slug, $contributor->first_name)
            : new InsightsArticleRejectedMail($article->title, (string) $reason, $contributor->first_name);

        try {
            Mail::to($contributor->email)->locale(MailLocale::forRecipient($article->submitted_locale ?? null))->send($mail);
            $sent = true;
        } catch (\Throwable $e) {
            report($e);
            $sent = false;
        }

        return ['name' => $name, 'email' => $contributor->email, 'sent' => $sent];
    }

    /** Why an article can't take this change: it was deleted, or its status isn't (or is no longer) one the change starts from */
    private function refusal(?object $article, string $change): string
    {
        $reason = match (true) {
            ! $article => 'We couldn’t find this article; it may have been deleted.',
            $change === 'publish' && $article->status === 'Published' => 'This article is already published, so nothing changed.',
            $change === 'publish' => 'Only a draft or an article waiting for review can be published, so nothing changed.',
            $change === 'reject' => 'Only an article waiting for review can be turned down, so nothing changed.',
            default => 'This article isn’t published, so nothing changed.',
        };

        return $reason . ' The list is up to date now.';
    }

    // ───────────────────────── The screen ─────────────────────────

    /** The list (status filter applied), how many per status, the reader and the confirmation */
    private function screen(): array
    {
        $withContributor = fn () => DB::table('kaizen_articles')
            ->leftJoin('client_users as contributor', 'contributor.id', '=', 'kaizen_articles.contributor_id')
            ->addSelect('contributor.first_name as contributor_first_name', 'contributor.last_name as contributor_last_name', 'contributor.job_title as contributor_job_title', 'contributor.email as contributor_email');

        $status = array_key_exists($this->kaizenStatusFilter, self::STATUSES) ? $this->kaizenStatusFilter : null;
        $ledger = $withContributor()
            ->addSelect('kaizen_articles.id', 'kaizen_articles.title', 'kaizen_articles.executive_summary', 'kaizen_articles.status',
                'kaizen_articles.contributor_id', 'kaizen_articles.published_at', 'kaizen_articles.created_at')
            ->when($status, fn ($q) => $q->where('kaizen_articles.status', $status))
            // What needs a decision comes first
            ->orderByRaw("kaizen_articles.status = 'Pending Moderation' desc")
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
            'ledger' => $ledger,
            'counts' => DB::table('kaizen_articles')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'reading' => $reading,
            'readingSections' => $sections,
            'confirm' => $this->confirmArticleId ? DB::table('kaizen_articles')->where('id', $this->confirmArticleId)->first(['id', 'title', 'status', 'contributor_id']) : null,
            'categories' => array_values(array_unique(array_filter([...KaizenService::CATEGORIES, $this->form['category'] ?? null]))),
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
        abort_unless(Auth::user()->can('kaizen.view'), 403);

        return view('livewire.hq.marketing.insights-articles', $this->screen());
    }
}
