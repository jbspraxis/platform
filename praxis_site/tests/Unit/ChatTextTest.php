<?php

namespace Tests\Unit;

use App\Support\ChatText;
use Tests\TestCase;

/** The assistant's light markdown (HQ's chat replies) rendered safely: escaped text, <strong> and http(s) links only. */
class ChatTextTest extends TestCase
{
    public function test_links_bold_and_bullets_are_rendered(): void
    {
        $html = ChatText::html("We offer **a free audit and demo**.\n- Book at [jbspraxis.com/schedule](https://jbspraxis.com/schedule)");

        $this->assertSame(
            "We offer <strong>a free audit and demo</strong>.\n• Book at <a href=\"https://jbspraxis.com/schedule\" rel=\"noopener\">jbspraxis.com/schedule</a>",
            $html
        );
    }

    public function test_everything_else_is_escaped(): void
    {
        $html = ChatText::html('<script>x()</script> [click](javascript:alert(1)) **<b>bold</b>** [a"b](https://example.com/?q="x")');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
        $this->assertStringContainsString('<strong>&lt;b&gt;bold&lt;/b&gt;</strong>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_plain_text_is_unchanged_apart_from_escaping(): void
    {
        $this->assertSame('It takes about an hour &amp; a half.', ChatText::html('It takes about an hour & a half.'));
    }
}
