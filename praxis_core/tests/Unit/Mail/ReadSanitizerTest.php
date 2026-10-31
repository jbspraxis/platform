<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\Read\HtmlSanitizer;
use App\Support\Mail\Read\TextFormatter;
use App\Support\Mail\Read\ThreadList;
use Tests\TestCase;

/** The reader's HTML sanitiser and text formatting (docs/MAIL.md, PR 2): what a sender writes never runs in HQ. */
class ReadSanitizerTest extends TestCase
{
    private function clean(string $html, bool $remote = false, ?callable $cid = null): array
    {
        return HtmlSanitizer::clean($html, $remote, $cid);
    }

    public function test_scripts_and_their_content_are_removed(): void
    {
        $out = $this->clean('<p>Hello</p><script>alert(1)</script><noscript><img src="x"></noscript><SCRIPT src="//evil.test/x.js"></SCRIPT><template><b>x</b></template>');

        $this->assertStringContainsString('<p>Hello</p>', $out['html']);
        $this->assertStringNotContainsStringIgnoringCase('script', $out['html']);
        $this->assertStringNotContainsString('alert', $out['html']);
        $this->assertStringNotContainsString('template', $out['html']);
    }

    public function test_event_handlers_and_unknown_attributes_are_dropped(): void
    {
        $out = $this->clean('<div onclick="steal()" ONMOUSEOVER="x()" data-x="1" style="color:red"><img src="data:image/png;base64,iVBORw0KGgo=" onerror="alert(1)" alt="dot"><a href="https://ok.test" onfocus="x()" autofocus>ok</a><body onload="x()">b</body></div>');

        $this->assertDoesNotMatchRegularExpression('/\son\w+=/i', $out['html']);
        $this->assertStringNotContainsString('data-x', $out['html']);
        $this->assertStringNotContainsString('autofocus', $out['html']);
        $this->assertStringContainsString('style="color:red"', $out['html']);
        $this->assertStringContainsString('src="data:image/png;base64,iVBORw0KGgo="', $out['html']);
    }

    public function test_javascript_and_other_unsafe_links_are_removed(): void
    {
        $links = [
            'javascript:alert(1)', 'JaVaScRiPt:alert(1)', "java\tscript:alert(1)", 'jav&#x61;script:alert(1)', '&#106;avascript:alert(1)',
            ' javascript:alert(1)', 'vbscript:msgbox(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'file:///etc/passwd', '//evil.test',
        ];
        foreach ($links as $href) {
            $out = $this->clean('<a href="' . $href . '">x</a>');
            $this->assertStringNotContainsString('href', $out['html'], "Kept: {$href}");
        }

        $ok = $this->clean('<a href="https://jbspraxis.com/a?b=1&amp;c=2">site</a> <a href="mailto:ada@client.test">mail</a> <a href="#top">top</a>');
        $this->assertStringContainsString('href="https://jbspraxis.com/a?b=1&amp;c=2"', $ok['html']);
        $this->assertStringContainsString('target="_blank"', $ok['html']);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $ok['html']);
        $this->assertStringContainsString('href="mailto:ada@client.test"', $ok['html']);
        $this->assertStringContainsString('href="#top"', $ok['html']);
    }

    public function test_css_url_expression_and_imports_are_neutralised(): void
    {
        $out = $this->clean(
            '<style>@import url(https://evil.test/a.css); .x { background: url("https://track.test/p.gif") } .y { width: expression(alert(1)); color: blue } .z { background: \75 rl(https://track.test/esc.gif) }</style>'
            . '<div style="background-image: url(https://track.test/inline.gif); color: green">a</div>'
            . '<div style="width: expression(alert(1))">b</div>'
            . '<div style="behavior: url(x.htc)">c</div>'
            . '<div style="background: url(javascript:alert(1))">d</div>'
        );

        $all = $out['html'] . $out['styles'];
        $this->assertStringNotContainsString('track.test', $all);
        $this->assertStringNotContainsString('evil.test', $all);
        $this->assertStringNotContainsStringIgnoringCase('expression', $all);
        $this->assertStringNotContainsStringIgnoringCase('javascript', $all);
        $this->assertStringNotContainsStringIgnoringCase('behavior', $all);
        $this->assertStringNotContainsString('@import', $all);
        $this->assertStringContainsString('color: blue', $out['styles']);
        $this->assertStringContainsString('color: green', $out['html']);
        $this->assertGreaterThanOrEqual(3, $out['blocked']);

        $allowed = $this->clean('<div style="background-image: url(https://cdn.test/bg.png)">a</div>', true);
        // libxml writes the quotes inside the attribute as " or &quot; depending on its version; browsers read both alike
        $this->assertStringContainsString('url("https://cdn.test/bg.png")', html_entity_decode($allowed['html'], ENT_QUOTES | ENT_HTML5));
    }

    public function test_style_blocks_cannot_break_out(): void
    {
        $out = $this->clean('<style>p { color: red } </style><script>alert(1)</script><style>a{}</style><p>x</p>');
        $this->assertStringNotContainsString('<', $out['styles']);
        $this->assertStringNotContainsString('alert', $out['html'] . $out['styles']);
    }

    public function test_forms_frames_and_embedded_objects_are_removed(): void
    {
        $out = $this->clean(
            '<form action="https://phish.test/login" method="post"><p>Sign in again</p><input name="password" type="password"><button>Go</button><select><option>a</option></select><textarea>t</textarea></form>'
            . '<iframe src="https://evil.test"></iframe><object data="x.swf"></object><embed src="x"><svg onload="alert(1)"><circle/></svg>'
            . '<meta http-equiv="refresh" content="0;url=https://evil.test"><link rel="stylesheet" href="https://evil.test/a.css"><base href="https://evil.test/">'
        );

        $this->assertStringContainsString('Sign in again', $out['html']);
        foreach (['<form', 'action=', '<input', '<button', '<select', '<textarea', '<iframe', '<object', '<embed', '<svg', '<meta', '<link', '<base', 'evil.test', 'phish.test'] as $bad) {
            $this->assertStringNotContainsStringIgnoringCase($bad, $out['html'], "Kept: {$bad}");
        }
    }

    public function test_remote_pictures_are_held_back_until_allowed(): void
    {
        $html = '<p>Hi</p><img src="https://track.test/open.gif" width="1" height="1"><img src="http://cdn.test/logo.png" alt="Logo"><table background="https://cdn.test/bg.jpg"><tr><td>x</td></tr></table>';

        $held = $this->clean($html);
        $this->assertSame(3, $held['blocked']);
        $this->assertStringNotContainsString('track.test', $held['html']);
        $this->assertStringNotContainsString('cdn.test', $held['html']);
        $this->assertStringContainsString('[Logo]', $held['html'], 'A held-back picture keeps its words');

        $shown = $this->clean($html, true);
        $this->assertSame(0, $shown['blocked']);
        $this->assertStringContainsString('src="http://cdn.test/logo.png"', $shown['html']);
        $this->assertStringContainsString('background="https://cdn.test/bg.jpg"', $shown['html']);
    }

    public function test_cid_pictures_go_through_hq_and_svg_data_does_not(): void
    {
        $out = $this->clean(
            '<img src="cid:logo@x.test" alt="Logo"><img src="CID:missing@x.test"><img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=">',
            false,
            fn (string $id) => $id === 'logo@x.test' ? '/mail/read/inline/7/logo@x.test' : null,
        );

        $this->assertStringContainsString('src="/mail/read/inline/7/logo@x.test"', $out['html']);
        $this->assertStringNotContainsString('missing', $out['html']);
        $this->assertStringNotContainsString('svg', $out['html']);
        $this->assertSame(0, $out['blocked']);
    }

    public function test_quoted_text_is_folded_but_never_the_whole_message_or_a_forward(): void
    {
        $gmail = $this->clean('<div dir="ltr">Thanks, see you Thursday.</div><br><div class="gmail_quote"><div class="gmail_attr">On Mon, Ada wrote:</div><blockquote>Earlier text</blockquote></div>');
        $this->assertMatchesRegularExpression('~<details class="hq-quote"><summary>Show quoted text</summary><div class="gmail_quote">~', $gmail['html']);
        $this->assertStringContainsString('Thanks, see you Thursday.', $gmail['html']);

        $outlook = $this->clean('<p>Fine by me.</p><div id="divRplyFwdMsg"><b>From:</b> Ada</div><div>Old message</div>');
        $this->assertMatchesRegularExpression('~<details class="hq-quote">.*divRplyFwdMsg.*Old message.*</details>~s', $outlook['html']);

        $apple = $this->clean('<div>Yes.</div><blockquote type="cite">Will you come?</blockquote>');
        $this->assertStringContainsString('<details class="hq-quote">', $apple['html']);

        $onlyQuote = $this->clean('<div class="gmail_quote">All of it</div>');
        $this->assertStringNotContainsString('<details', $onlyQuote['html']);

        $forward = $this->clean('<div>FYI</div><div class="gmail_quote">---------- Forwarded message ---------<br>From: Ada</div>');
        $this->assertStringNotContainsString('<details', $forward['html']);
    }

    public function test_text_and_accents_survive(): void
    {
        $out = $this->clean('<html><head><title>T</title></head><body bgcolor="#ffeecc"><p>Réunion à 10h — café ☕</p><o:p>Outlook</o:p></body></html>');
        $this->assertStringContainsString('Réunion à 10h — café ☕', $out['html']);
        $this->assertStringContainsString('Outlook', $out['html']);
        $this->assertStringNotContainsString('<o:p', $out['html']);
        $this->assertStringContainsString('background-color:#ffeecc', $out['html']);
        $this->assertStringNotContainsString('<title', $out['html']);
    }

    public function test_plain_text_is_escaped_linked_and_its_quote_folded(): void
    {
        $html = TextFormatter::toHtml("Hello <b>team</b> & co,\nSee https://jbspraxis.com/a?x=1&y=2. Write to ada@client.test\n\nOn Mon, 2 Nov 2026 at 10:00, Ada <ada@client.test> wrote:\n> Earlier\n> text");

        $this->assertStringContainsString('Hello &lt;b&gt;team&lt;/b&gt; &amp; co', $html);
        $this->assertStringContainsString('<a href="https://jbspraxis.com/a?x=1&amp;y=2" target="_blank" rel="noopener noreferrer nofollow">https://jbspraxis.com/a?x=1&amp;y=2</a>.', $html);
        $this->assertStringContainsString('href="mailto:ada@client.test"', $html);
        $this->assertMatchesRegularExpression('~<details class="hq-quote"><summary>Show quoted text</summary><div class="mr-text">On Mon~', $html);

        $this->assertStringNotContainsString('<details', TextFormatter::toHtml("> only a quote\n> here"));
        $this->assertStringNotContainsString('<script', TextFormatter::toHtml('<script>alert(1)</script>'));
        $this->assertStringNotContainsString('<a', TextFormatter::toHtml('javascript:alert(1)'));
    }

    public function test_search_words_and_from(): void
    {
        $this->assertSame([['budget', 'q4 review'], ['ada']], ThreadList::parse('budget from:ada "q4 review"'));
        $this->assertSame([[], []], ThreadList::parse('   '));
    }

    public function test_the_senders_charset_declaration_never_garbles_the_text(): void
    {
        // Outlook's HTML says windows-1252; MIME decoding has already made it UTF-8 (owner report, 30 Oct 2026)
        foreach (['<meta http-equiv="Content-Type" content="text/html; charset=windows-1252">', '<META charset="iso-8859-1">'] as $meta) {
            $out = HtmlSanitizer::clean("<html><head>{$meta}</head><body><p>we don\u{2019}t want – café</p></body></html>")['html'];
            $this->assertStringContainsString("we don\u{2019}t want – café", html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $this->assertStringNotContainsString('â€', $out);
        }
    }
}
