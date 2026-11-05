<?php

namespace Tests\Feature\Campaigns;

use App\Mail\BroadcastEmail;
use App\Support\Campaigns\Blocks;
use App\Support\Campaigns\Layouts;
use App\Support\Campaigns\Renderer;
use App\Support\Campaigns\RichText;
use Tests\TestCase;

/**
 * What a broadcast is made of (docs/CAMPAIGNS.md 4.4): sanitised rich text, cleaned blocks, the layouts, and email-safe
 * HTML in both languages. No database needed.
 */
class BroadcastContentTest extends TestCase
{
    public function test_rich_text_keeps_formatting_and_drops_everything_else(): void
    {
        $dirty = '<div onclick="steal()">First <b>bold</b> <i>it</i> <u>u</u> <span style="color:red">plain</span></div>'
            . '<script>alert(1)</script><img src="https://evil.test/x.png" onerror="x()">'
            . '<p><a href="javascript:alert(1)">bad link</a> <a href="https://jbspraxis.com/schedule" target="_blank" style="x">good link</a> <a href="mailto:intelligence@jbspraxis.com">mail</a></p>'
            . '<h1>Big</h1><h4>Small</h4><ul><li class="x">One</li></ul><table><tr><td>cell</td></tr></table><iframe src="https://evil.test"></iframe>';

        $clean = RichText::clean($dirty);

        $this->assertStringContainsString('<p>First <strong>bold</strong> <em>it</em> <u>u</u> plain</p>', $clean);
        $this->assertStringContainsString('<a href="https://jbspraxis.com/schedule">good link</a>', $clean);
        $this->assertStringContainsString('<a href="mailto:intelligence@jbspraxis.com">mail</a>', $clean);
        $this->assertStringContainsString('bad link', $clean);
        $this->assertStringContainsString('<h2>Big</h2><h3>Small</h3><ul><li>One</li></ul>', $clean);
        // Tables survive, structure only: they were added for an agreement's schedules (docs/DOCUMENTS.md 12)
        $this->assertStringContainsString('<table><tr><td>cell</td></tr></table>', $clean);
        foreach (['script', 'onclick', 'onerror', 'javascript:', '<img', 'iframe', 'style=', 'target=', '<span', '<div', 'class='] as $gone) {
            $this->assertStringNotContainsString($gone, $clean);
        }
        $this->assertSame('', RichText::clean('<p><br></p><p>&nbsp;</p>'));
        $this->assertSame('<p>Loose words</p>', RichText::clean('Loose words'));
    }

    public function test_plain_ai_text_becomes_paragraphs_lists_and_bold(): void
    {
        $this->assertSame('<p>One <strong>key</strong> idea.</p><ul><li>First</li><li>Second &amp; last</li></ul>',
            RichText::fromPlain("One **key** idea.\n\n- First\n- Second & last"));
        $email = RichText::forEmail('<p>A <a href="https://jbspraxis.com">link</a></p>');
        $this->assertStringContainsString('<p style="margin: 0 0 16px;', $email);
        $this->assertStringContainsString('<a style="color: #08707C; text-decoration: underline;" href="https://jbspraxis.com">', $email);
    }

    public function test_blocks_are_cleaned_and_checked_before_they_are_stored(): void
    {
        $blocks = Blocks::clean([
            ['type' => 'heading', 'text' => "  A   heading\n"],
            ['type' => 'button', 'label' => 'Book', 'url' => 'javascript:alert(1)'],
            ['type' => 'button', 'label' => 'Book', 'url' => 'jbspraxis.com/schedule'],
            ['type' => 'image', 'src' => 'https://evil.test/tracker.png', 'alt' => 'x', 'link' => 'data:text/html,hi'],
            ['type' => 'figures', 'items' => array_fill(0, 5, ['value' => '41%', 'label' => 'fewer'])],
            ['type' => 'nonsense', 'text' => 'dropped'],
            'not a block',
        ]);

        $this->assertCount(5, $blocks);
        $this->assertSame('A heading', $blocks[0]['text']);
        $this->assertSame('', $blocks[1]['url'], 'A script address never survives');
        $this->assertSame('https://jbspraxis.com/schedule', $blocks[2]['url']);
        $this->assertSame('', $blocks[3]['src'], 'Only our own uploads can be shown');
        $this->assertSame('', $blocks[3]['link']);
        $this->assertCount(3, $blocks[4]['items']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $blocks[0]['id']);

        $problems = Blocks::problems(['subject' => '', 'blocks' => $blocks]);
        $this->assertContains('Add a subject.', $problems);
        $this->assertContains('Button 1 needs a label and a link that starts with https://.', $problems);
        $this->assertContains('Image has no picture: upload one or remove it.', $problems);
        $this->assertSame(['Write the email: it needs at least one heading or text.'], Blocks::problems(['subject' => 'Hi', 'blocks' => Blocks::clean([['type' => 'divider']])]));

        $this->assertSame(['https://jbspraxis.com/schedule', 'https://example.test/a'],
            Blocks::links(Blocks::clean([...$blocks, ['type' => 'text', 'html' => '<p><a href="https://example.test/a">a</a> <a href="mailto:x@example.test">m</a></p>']])));
    }

    public function test_every_layout_starts_with_its_blocks_in_both_languages(): void
    {
        foreach (array_keys(Layouts::ALL) as $layout) {
            foreach (['en', 'fr'] as $locale) {
                $version = Layouts::starter($layout, $locale);
                $this->assertSame(Layouts::ALL[$layout]['blocks'], array_column($version['blocks'], 'type'), "{$layout} {$locale}");
            }
        }
        $this->assertSame('Réserver un audit et une démonstration offerts', Blocks::blank('button', 'fr')['label']);
        $this->assertSame('Heure', Blocks::blank('details', 'fr')['rows'][1]['label']);
    }

    public function test_the_email_is_built_from_tables_with_the_persons_details_in_their_language(): void
    {
        $version = ['subject' => 'News for {{ company_name }}', 'headline' => '', 'preheader' => '', 'blocks' => Blocks::clean([
            ['type' => 'text', 'html' => '<p>Hello team at {{ company_name }}, <script>x</script>see <a href="https://jbspraxis.com/work">our work</a>.</p>'],
            ['type' => 'figures', 'items' => [['value' => '41%', 'label' => 'fewer late approvals']]],
            ['type' => 'details', 'rows' => [['label' => 'Date', 'value' => '20 November']]],
            ['type' => 'quote', 'text' => 'It works.', 'source' => 'A client'],
            ['type' => 'divider'],
            ['type' => 'button', 'label' => 'Book a free audit and demo', 'url' => 'https://jbspraxis.com/schedule'],
        ])];
        $person = ['first_name' => 'Ada', 'last_name' => 'Okafor', 'company' => 'Acme & Co (demo)', 'kind' => 'client'];

        $en = (new BroadcastEmail($version, 'insight', true, $person, 'https://portal.test/email/unsubscribe/x'))->locale('en')->renderParts();
        $this->assertSame('News for Acme & Co (demo)', $en['subject']);
        $this->assertStringContainsString('JBS Praxis Insights', $en['html']);
        $this->assertStringContainsString('Hello Ada,', $en['html']);
        $this->assertStringContainsString('Hello team at Acme &amp; Co (demo)', $en['html']);
        $this->assertStringContainsString('role="presentation"', $en['html']);
        $this->assertStringContainsString('41%', $en['html']);
        $this->assertStringContainsString('Kind regards,', $en['html']);
        $this->assertStringContainsString(e(__('emails.broadcast.reasons.client', [], 'en')), $en['html']);
        $this->assertStringContainsString('Unsubscribe from JBS Praxis news and insights', $en['html']);
        $this->assertStringNotContainsString('<script', $en['html']);
        $this->assertStringContainsString('Book a free audit and demo: https://jbspraxis.com/schedule', $en['text']);

        $fr = (new BroadcastEmail($version, 'insight', true, $person, 'https://portal.test/email/unsubscribe/x'))->locale('fr')->renderParts();
        $this->assertStringContainsString('Analyses JBS Praxis', $fr['html']);
        $this->assertStringContainsString('Bonjour,', $fr['html']);
        $this->assertStringNotContainsString('Bonjour Ada', $fr['html'], 'French never greets by first name alone');
        $this->assertStringContainsString('Bien cordialement,', $fr['html']);
        $this->assertStringContainsString(e(__('emails.broadcast.reasons.client', [], 'fr')), $fr['html']);
        $this->assertStringContainsString('Se désabonner des actualités et analyses de JBS Praxis', $fr['html']);

        // Counted links: only the body's links change; the unsubscribe link is the person's own
        $tracked = Renderer::track('<a href="https://jbspraxis.com/work">x</a> <a href="https://other.test">y</a>', ['https://jbspraxis.com/work' => 'https://portal.test/email/click/t/1']);
        $this->assertSame('<a href="https://portal.test/email/click/t/1">x</a> <a href="https://other.test">y</a>', $tracked);
    }

    public function test_every_sample_renders_in_its_language(): void
    {
        foreach (BroadcastEmail::samples() as $name => $mail) {
            $locale = str_ends_with($name, '-fr') ? 'fr' : 'en';
            $parts = $mail->locale($locale)->renderParts();
            $this->assertNotSame('', trim($parts['subject']), $name);
            $this->assertStringContainsString($locale === 'fr' ? 'L’équipe JBS Praxis' : 'The JBS Praxis team', $parts['html'], $name);
            $this->assertStringContainsString('List-Unsubscribe', implode(' ', array_keys($mail->headers()->text)), $name);
        }
    }
}
