<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * A contributor's Insights article was published: sent when a reviewer publishes an article that was waiting for review
 * (HQ → Marketing → Insights articles). Thanks them and links the live article. English or French, as the page it
 * was sent from (kaizen_articles.submitted_locale).
 */
class InsightsArticlePublishedMail extends BrandedMail
{
    public function __construct(public string $articleTitle, public string $slug, public ?string $firstName = null)
    {
    }

    protected function subjectLine(): string
    {
        return __('emails.insights_published.subject');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.insights-published', with: [
            'articleTitle' => $this->articleTitle,
            'articleUrl' => self::pageUrl('/kaizen/' . rawurlencode($this->slug)),
        ]);
    }

    /** A page of the website in the mail's language: /kaizen/x, or /fr/kaizen/x */
    public static function pageUrl(string $path): string
    {
        return rtrim((string) config('mail.brand.site_url'), '/') . (app()->getLocale() === 'fr' ? '/fr' : '') . $path;
    }

    public static function samples(): array
    {
        return [
            'named' => new static('Closing the books in five days', 'closing-the-books-in-five-days-a1b2c', 'Ada'),
            'no-name' => new static('Closing the books in five days', 'closing-the-books-in-five-days-a1b2c'),
        ];
    }
}
