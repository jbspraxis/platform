<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * A contributor's Insights article was not published: sent when a reviewer turns down an article that was waiting
 * for review (HQ → Marketing → Insights articles), with the reviewer's note written for the contributor. Invites
 * them to send a revised article. English or French, as the page it was sent from (kaizen_articles.submitted_locale).
 */
class InsightsArticleRejectedMail extends BrandedMail
{
    public function __construct(public string $articleTitle, public string $reason, public ?string $firstName = null)
    {
    }

    protected function subjectLine(): string
    {
        return __('emails.insights_rejected.subject', ['title' => $this->articleTitle]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.insights-rejected', with: [
            'articleTitle' => $this->articleTitle,
            'reason' => $this->reason,
            'writeUrl' => InsightsArticlePublishedMail::pageUrl('/kaizen/write'),
        ]);
    }

    public static function samples(): array
    {
        $reason = "The result needs a figure we can check: how long did the close take before, and after?\n\nPlease also shorten the summary to a few lines.";

        return [
            'named' => new static('Closing the books in five days', $reason, 'Ada'),
            'no-name' => new static('Closing the books in five days', $reason),
        ];
    }
}
