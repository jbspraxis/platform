<?php

namespace Tests\Unit\Mail;

use App\Livewire\Hq\Mail\AskMail;
use App\Support\Mail\Ai\Gemini;
use App\Support\Mail\Ai\MailSearch;
use App\Support\Mail\Ai\MailText;
use App\Support\Pwa\PushCategories;
use PHPUnit\Framework\TestCase;

/** PR 4's text handling: what the AI reads, what the search looks for, how answers show, the Mail push group. */
class TeamTextTest extends TestCase
{
    public function test_quoted_mail_is_cut_before_the_ai_reads_it(): void
    {
        $this->assertSame("Thanks, Friday works.", MailText::withoutQuotes("Thanks, Friday works.\r\n\r\nOn Mon, 3 Nov 2026 at 10:00, Ada <ada@x.test> wrote:\r\n> Can we meet?\r\n> Thanks"));
        $this->assertSame('Merci.', MailText::withoutQuotes("Merci.\n\nLe lun. 3 nov. 2026, Ada a écrit :\n> Bonjour"));
        $this->assertSame('See below.', MailText::withoutQuotes("See below.\n\n-----Original Message-----\nFrom: Ada\nSent: Monday\nHello"));
        $this->assertSame('Noted.', MailText::withoutQuotes("Noted.\n\nFrom: Ada Okafor <ada@x.test>\nSent: Monday, 3 November 2026 10:00\nTo: us\nOld text"));
        $this->assertSame("Line one\nLine two", MailText::withoutQuotes("Line one\n> quoted\nLine two"));
        $this->assertSame('Hello team', MailText::fromHtml('<style>p{}</style><p>Hello <b>team</b></p><blockquote>old reply</blockquote>'));
        $this->assertSame('abc …', MailText::clip('abcdef', 3));
    }

    public function test_search_keeps_the_words_that_matter(): void
    {
        $this->assertSame(['ada', 'q4', 'proposal'], MailSearch::terms('What did Ada say about the Q4 proposal?'));
        $this->assertSame(['ben@client.test', 'invoice'], MailSearch::terms('Has ben@client.test sent the invoice?'));
        $this->assertSame([], MailSearch::terms('What is my latest mail?'));
    }

    public function test_answers_are_escaped_with_citations_as_links(): void
    {
        $html = AskMail::answerHtml("It is on 12 November [1] **at 10:00**.\n- Lunch [2]\n- <b>bold</b> [9]", [
            ['n' => 1, 'url' => '/mail?box=a&thread=1'],
            ['n' => 2, 'url' => '/mail?box=a&thread=2'],
        ]);
        $this->assertSame(
            '<p>It is on 12 November <a class="am-cite" href="/mail?box=a&amp;thread=1" wire:navigate title="Open message 1">1</a> at 10:00.</p>'
            . '<ul><li>Lunch <a class="am-cite" href="/mail?box=a&amp;thread=2" wire:navigate title="Open message 2">2</a></li><li>&lt;b&gt;bold&lt;/b&gt; </li></ul>',
            $html,
        );
    }

    public function test_gemini_json_answers_with_or_without_a_fence(): void
    {
        $this->assertSame(['gist' => 'x'], Gemini::json("```json\n{\"gist\": \"x\"}\n```"));
        $this->assertSame(['gist' => 'x'], Gemini::json('{"gist": "x"}'));
        $this->assertNull(Gemini::json('not json'));
        $this->assertNull(Gemini::json(null));
    }

    public function test_mail_links_are_their_own_push_group(): void
    {
        $this->assertSame('Mail', PushCategories::LABELS['mail']);
        $this->assertSame('mail', PushCategories::forNotification('/mail?box=abc&thread=12', 'Message'));
        $this->assertSame('mail', PushCategories::forNotification('/mail/mailboxes?box=abc', 'Message'));
        $this->assertSame('marketing', PushCategories::forNotification('/marketing/campaigns', 'Message'));
        $this->assertSame('other', PushCategories::forNotification(null, 'Message'));
    }
}
