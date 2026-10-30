<?php

namespace App\Support;

/**
 * The assistant's replies (POST /chat on HQ) use the old chat widget's light markdown: "**bold**",
 * "[text](https://…)" links and lines starting "- " or "* " as bullets (docs/SITE_API.md section 3).
 * This turns that subset into safe HTML: every piece of text is escaped, and only <strong> and <a> with
 * an http(s) link are added. resources/js/app.js applies the same rules when it adds a reply in place.
 */
final class ChatText
{
    private const PATTERN = '/\*\*(.+?)\*\*|\[([^\[\]]+)\]\((https?:\/\/[^\s()<>"]+)\)/u';

    public static function html(string $text): string
    {
        $text = preg_replace('/^[-*] /mu', '• ', $text) ?? $text;

        if (! preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return e($text);
        }

        $html = '';
        $last = 0;
        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $html .= e(substr($text, $last, $offset - $last));
            if (isset($match[3]) && $match[3][1] !== -1) {
                $html .= '<a href="' . e($match[3][0]) . '" rel="noopener">' . e($match[2][0]) . '</a>';
            } else {
                $html .= '<strong>' . e($match[1][0]) . '</strong>';
            }
            $last = $offset + strlen($whole);
        }

        return $html . e(substr($text, $last));
    }
}
