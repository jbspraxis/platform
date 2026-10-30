<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\Compose\HtmlCleaner;
use App\Support\Mail\Compose\Recipients;
use App\Support\Mail\Compose\WriteWithAi;
use Tests\TestCase;

/** The composer's text handling (PR 3): cleaning the editor's HTML, plain text, addresses, quoted text. */
class ComposeTextTest extends TestCase
{
    public function test_clean_keeps_formatting_and_drops_everything_unsafe(): void
    {
        $dirty = '<p style="color:red" onclick="steal()">Hello <b>there</b> <span class="x">friend</span></p>'
            . '<script>alert(1)</script><img src="https://track.test/p.gif" onerror="x()">'
            . '<a href="javascript:alert(1)">bad link</a> <a href="https://jbspraxis.com" target="_blank" onmouseover="x()">good link</a>'
            . '<ul><li>One</li><li><i>Two</i></li></ul><blockquote>Quoted</blockquote><iframe src="https://x.test"></iframe><!-- note -->';

        $clean = HtmlCleaner::clean($dirty);

        $this->assertSame(
            '<p>Hello <b>there</b> friend</p>bad link <a href="https://jbspraxis.com">good link</a><ul><li>One</li><li><i>Two</i></li></ul><blockquote>Quoted</blockquote>',
            $clean,
        );
    }

    public function test_clean_keeps_mailto_and_tel_and_unicode(): void
    {
        $clean = HtmlCleaner::clean('<div>Réunion à Douala — <a href="mailto:ada@client.test">Ada</a>, <a href="tel:+2348000000000">appelez</a></div>');
        $this->assertSame('<div>Réunion à Douala — <a href="mailto:ada@client.test">Ada</a>, <a href="tel:+2348000000000">appelez</a></div>', $clean);
    }

    public function test_an_editor_with_only_empty_lines_is_empty(): void
    {
        $this->assertSame('', HtmlCleaner::clean('<div><br></div><p>&nbsp;</p>'));
        $this->assertTrue(HtmlCleaner::isBlank('<p><br></p>'));
        $this->assertFalse(HtmlCleaner::isBlank('<p>x</p>'));
    }

    public function test_plain_text_version(): void
    {
        $text = HtmlCleaner::toText('<p>Hello Ada,</p><p>See <a href="https://jbspraxis.com/method">the method</a>.<br>Thanks</p><ul><li>One</li><li>Two</li></ul><blockquote>Earlier</blockquote>');

        $this->assertSame("Hello Ada,\n\nSee the method (https://jbspraxis.com/method).\nThanks\n\n- One\n- Two\n\n> Earlier", $text);
    }

    public function test_text_to_html_escapes(): void
    {
        $this->assertSame('<p>Hello &lt;b&gt;Ada&lt;/b&gt;,</p><p>Line one<br>' . "\n" . 'line two</p>', HtmlCleaner::fromText("Hello <b>Ada</b>,\n\nLine one\nline two"));
    }

    public function test_addresses_typed_or_pasted(): void
    {
        $parsed = Recipients::parse('"Client, Ada" <ADA@client.test>; bob@client.test, not-an-address, bob@client.test');

        $this->assertSame([['name' => 'Client, Ada', 'email' => 'ada@client.test'], ['name' => null, 'email' => 'bob@client.test']], $parsed['valid']);
        $this->assertSame(['not-an-address'], $parsed['invalid']);
        $this->assertSame('Client, Ada <ada@client.test>', Recipients::format($parsed['valid'][0]));
        $this->assertSame([['name' => null, 'email' => 'bob@client.test']], Recipients::without($parsed['valid'], ['ADA@client.test']));
    }

    public function test_quoted_copies_are_cut_before_the_ai_reads_them(): void
    {
        $this->assertSame("Thanks, Tuesday works.", WriteWithAi::withoutQuotes("Thanks, Tuesday works.\n\nOn Mon 27 Oct 2026, Ada wrote:\n> Can we meet?"));
        $this->assertSame("Merci !", WriteWithAi::withoutQuotes("Merci !\nLe lun. 27 oct. 2026, Ada a écrit :\n> On se voit ?"));
        $this->assertSame("See below\nok", WriteWithAi::withoutQuotes("See below\n> quoted line\nok\n---------- Forwarded message ----------\nFrom: x"));
    }
}
