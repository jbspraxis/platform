<?php

namespace Tests\Unit\Letters;

use App\Support\Campaigns\RichText;
use App\Support\Letters\LetterBody;
use PHPUnit\Framework\TestCase;

/**
 * Tables in a rich-text body, and headings kept with their text (docs/DOCUMENTS.md section 12). An agreement's
 * schedules read as tables and nothing else, so RichText::clean keeps the table tags — the structure only, no
 * widths, no colours, no classes — and LetterBody::keepHeadingsWithText stops a clause heading being left alone
 * at the foot of a page.
 */
class RichTextTablesTest extends TestCase
{
    public function test_a_table_keeps_its_structure_and_loses_everything_else(): void
    {
        $html = RichText::clean(
            '<table class="pretty" style="width: 900px" border="1" cellpadding="4">'
            . '<thead><tr><th scope="col">Instalment</th><th>Amount</th></tr></thead>'
            . '<tbody><tr><td>First</td><td align="right">N175,000</td></tr></tbody></table>'
        );

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<th>Instalment</th>', $html);
        $this->assertStringContainsString('<td>First</td>', $html);
        $this->assertStringNotContainsString('class=', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('border=', $html);
        $this->assertStringNotContainsString('scope=', $html);
        $this->assertStringNotContainsString('align=', $html);
    }

    public function test_a_cell_keeps_a_sensible_span_and_nothing_else(): void
    {
        $html = RichText::clean('<table><tr><td colspan="2" rowspan="3" width="90">Both</td></tr></table>');

        $this->assertStringContainsString('colspan="2"', $html);
        $this->assertStringContainsString('rowspan="3"', $html);
        $this->assertStringNotContainsString('width=', $html);
    }

    public function test_a_silly_span_is_dropped(): void
    {
        $html = RichText::clean('<table><tr><td colspan="9000">Too many</td><td colspan="x">Not a number</td></tr></table>');

        $this->assertStringNotContainsString('colspan', $html);
        $this->assertStringContainsString('<td>Too many</td>', $html);
    }

    public function test_what_a_table_may_not_smuggle_in_is_still_dropped(): void
    {
        $html = RichText::clean('<table><tr><td onclick="steal()"><script>steal()</script><img src="x"><b>Pay</b></td></tr></table>');

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('<strong>Pay</strong>', $html);
    }

    public function test_a_table_is_a_block_of_its_own_and_is_not_wrapped_in_a_paragraph(): void
    {
        $html = RichText::clean('<table><tr><td>Only</td></tr></table>');

        $this->assertStringStartsWith('<table>', $html);
    }

    public function test_a_table_on_its_own_is_not_blank_and_reads_as_its_cells(): void
    {
        $html = RichText::clean('<table><tr><th>When</th></tr><tr><td>End of November</td></tr></table>');

        $this->assertFalse(RichText::isBlank($html));
        $this->assertStringContainsString('End of November', RichText::text($html));
        // Each cell reads on its own line, so a schedule still reads as words for a preview or a check
        $this->assertSame("When\n\nEnd of November", RichText::text($html));
    }

    public function test_a_table_gets_the_email_styles_too(): void
    {
        $styled = RichText::forEmail(RichText::clean('<table><tr><th>A</th><td>B</td></tr></table>'));

        $this->assertStringContainsString('<table style="width: 100%;', $styled);
        $this->assertStringContainsString('<th style=', $styled);
        $this->assertStringContainsString('<td style=', $styled);
    }

    public function test_a_heading_is_carried_with_the_short_text_under_it(): void
    {
        $kept = LetterBody::keepHeadingsWithText('<h2>6. Confidentiality</h2><p>The Contractor keeps it confidential.</p><p>And afterwards.</p>');

        $this->assertStringContainsString('<div class="keep"><h2>6. Confidentiality</h2><p>The Contractor keeps it confidential.</p></div>', $kept);
        $this->assertStringContainsString('</div><p>And afterwards.</p>', $kept);
    }

    public function test_a_long_block_is_left_to_break_where_it_will(): void
    {
        $long = str_repeat('This clause runs on and on. ', 40);     // well past 600 characters
        $kept = LetterBody::keepHeadingsWithText('<h2>7. Intellectual property</h2><p>' . $long . '</p>');

        $this->assertStringContainsString('<div class="keep"><h2>7. Intellectual property</h2></div>', $kept);
    }

    public function test_a_heading_followed_by_a_heading_is_not_glued_to_it(): void
    {
        $kept = LetterBody::keepHeadingsWithText('<h2>Schedule A</h2><h3>Role</h3><p>Full-Stack Engineer.</p>');

        $this->assertStringContainsString('<div class="keep"><h2>Schedule A</h2></div>', $kept);
        $this->assertStringContainsString('<div class="keep"><h3>Role</h3><p>Full-Stack Engineer.</p></div>', $kept);
    }

    public function test_a_body_without_headings_is_left_exactly_as_it_was(): void
    {
        $html = '<p>One paragraph.</p><ul><li>One thing</li></ul>';

        $this->assertSame($html, LetterBody::keepHeadingsWithText($html));
    }
}
