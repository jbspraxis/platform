<?php

namespace App\Support\Campaigns;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * The text blocks of a broadcast (docs/CAMPAIGNS.md 4.4). What the editor sends is the browser's HTML, so nothing in
 * it is trusted: clean() keeps paragraphs, line breaks, bold, italics, underline, links (http, https, mailto),
 * lists, two heading levels and simple tables, without any attribute but a link's address and a cell's span;
 * everything else is unwrapped or dropped. forEmail() adds the inline styles email clients need. fromPlain() turns an AI draft's plain text
 * ("- " list items, **bold**, blank lines between paragraphs) into the same HTML.
 */
final class RichText
{
    /**
     * Tags kept, after renaming (b → strong, i → em, div → p, h1 → h2, h4–h6 → h3).
     *
     * The table tags are here for the schedules of an agreement (docs/DOCUMENTS.md section 12): a pay schedule or
     * a list of milestones reads as a table and nothing else. Only the structure survives — no widths, no colours,
     * no classes — so a pasted table takes the document's own look.
     */
    private const KEEP = ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li', 'h2', 'h3',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'];

    private const RENAME = ['b' => 'strong', 'i' => 'em', 'div' => 'p', 'h1' => 'h2', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3'];

    /** The only attributes kept on a cell: a merged header is the one thing a schedule's table really needs */
    private const SPANS = ['colspan', 'rowspan'];

    /** Dropped with everything inside them */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'head', 'title', 'meta', 'link', 'img',
        'svg', 'math', 'video', 'audio', 'form', 'input', 'button', 'select', 'textarea', 'canvas', 'picture', 'source'];

    /** Inline styles per tag, so the text reads like the rest of the email (docs/BRAND.md section 13) */
    private const EMAIL_STYLES = [
        'p' => "margin: 0 0 16px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'ul' => "margin: 0 0 16px; padding: 0 0 0 24px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'ol' => "margin: 0 0 16px; padding: 0 0 0 24px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'li' => "margin: 0 0 8px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'h2' => "margin: 24px 0 12px; font-family: 'Newsreader', Georgia, serif; font-weight: 400; font-size: 22px; line-height: 28px; color: #0C142B;",
        'h3' => "margin: 20px 0 8px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-weight: 600; font-size: 17px; line-height: 26px; color: #0C142B;",
        'strong' => 'font-weight: 600; color: #0C142B;',
        'em' => 'font-style: italic;',
        'u' => 'text-decoration: underline;',
        'a' => 'color: #08707C; text-decoration: underline;',
        'table' => "width: 100%; margin: 0 0 16px; border-collapse: collapse; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 15px; line-height: 22px; color: #4A5468;",
        'th' => 'padding: 6px 8px; border-bottom: 1px solid #D8DEE6; text-align: left; font-weight: 600; color: #0C142B;',
        'td' => 'padding: 6px 8px; border-bottom: 1px solid #EEF1F5; text-align: left;',
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = self::load($html);
        $root = $doc->getElementById('rt-root');
        if (! $root) {
            return '';
        }
        self::walk($root);
        self::wrapLooseText($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = trim(preg_replace('~<p>(\s|&nbsp;|<br>)*</p>~u', '', $out));

        return self::isBlank($out) ? '' : $out;
    }

    /** The cleaned HTML with the email's inline styles (run clean() first) */
    public static function forEmail(string $html): string
    {
        return preg_replace_callback('~<(p|ul|ol|li|h2|h3|strong|em|u|a|table|th|td)\b([^>]*)>~i', function ($m) {
            return '<' . $m[1] . ' style="' . self::EMAIL_STYLES[strtolower($m[1])] . '"' . $m[2] . '>';
        }, $html);
    }

    /** Plain text with "- " list items, **bold** and blank lines between paragraphs, as clean HTML */
    public static function fromPlain(?string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        if ($text === '') {
            return '';
        }
        $html = '';
        foreach (preg_split("/\n{2,}/", $text) as $part) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $part)), fn ($l) => $l !== ''));
            $bold = fn (string $s) => preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($s));
            if ($lines && count(array_filter($lines, fn ($l) => preg_match('/^[-•*]\s+/u', $l))) === count($lines)) {
                $html .= '<ul>' . implode('', array_map(fn ($l) => '<li>' . $bold(preg_replace('/^[-•*]\s+/u', '', $l)) . '</li>', $lines)) . '</ul>';
            } elseif ($lines) {
                $html .= '<p>' . implode('<br>', array_map($bold, $lines)) . '</p>';
            }
        }

        return self::clean($html);
    }

    /** The text alone (for previews, the AI's material and checks) */
    public static function text(?string $html): string
    {
        $html = preg_replace('~</(p|li|h2|h3|th|td|tr|caption)>|<br\s*/?>~i', "\n", (string) $html);

        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function isBlank(?string $html): bool
    {
        return trim(self::text($html), " \t\n\r\0\x0B\u{00A0}") === '';
    }

    /** The addresses of the links in cleaned HTML */
    public static function links(string $html): array
    {
        preg_match_all('~<a href="([^"]+)"~i', $html, $m);

        return array_map(fn ($u) => html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m[1]);
    }

    public static function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        return $url !== '' && mb_strlen($url) <= 2000 && (bool) preg_match('~^(https?://[^\s<>"]+|mailto:[^\s<>"@]+@[^\s<>"]+)$~i', $url);
    }

    private static function load(string $html): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $html = preg_replace('/<meta\b[^>]*>/i', '', $html) ?? $html;
        $doc->loadHTML('<?xml encoding="utf-8"?><html><body><div id="rt-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (! $child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            if (isset(self::RENAME[$tag])) {
                $child = self::rename($child, self::RENAME[$tag]);
                $tag = strtolower($child->tagName);
            }

            self::walk($child);

            if (! in_array($tag, self::KEEP, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : null;
            $spans = in_array($tag, ['th', 'td'], true) ? self::spans($child) : [];
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->nodeName);
            }
            foreach ($spans as $name => $value) {
                $child->setAttribute($name, (string) $value);
            }
            if ($tag === 'a') {
                if (self::isSafeUrl($href)) {
                    $child->setAttribute('href', $href);
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            }
        }
    }

    /** A cell's colspan and rowspan, when they are small whole numbers; anything else is dropped */
    private static function spans(DOMElement $cell): array
    {
        $out = [];
        foreach (self::SPANS as $name) {
            $value = (int) trim($cell->getAttribute($name));
            if ($value > 1 && $value <= 20) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /** Text and inline tags left at the top level (the editor's first line) go into a paragraph */
    private static function wrapLooseText(DOMElement $root): void
    {
        $blocks = ['p', 'ul', 'ol', 'h2', 'h3', 'table'];
        $doc = $root->ownerDocument;
        $paragraph = null;
        foreach (iterator_to_array($root->childNodes) as $child) {
            $isBlock = $child instanceof DOMElement && in_array(strtolower($child->tagName), $blocks, true);
            if ($isBlock) {
                $paragraph = null;
                continue;
            }
            if ($child instanceof DOMText && trim($child->nodeValue) === '' && $paragraph === null) {
                continue;
            }
            if ($paragraph === null) {
                $paragraph = $doc->createElement('p');
                $root->insertBefore($paragraph, $child);
            }
            $paragraph->appendChild($child);
        }
    }

    private static function rename(DOMElement $element, string $tag): DOMElement
    {
        $new = $element->ownerDocument->createElement($tag);
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $new->setAttribute($attribute->nodeName, $attribute->nodeValue);
        }
        while ($element->firstChild) {
            $new->appendChild($element->firstChild);
        }
        $element->parentNode->replaceChild($new, $element);

        return $new;
    }
}
