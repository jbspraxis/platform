<?php

namespace App\Support\Campaigns;

use App\Mail\BroadcastEmail;
use App\Models\Campaigns\Broadcast;

/**
 * A broadcast version as email HTML (docs/CAMPAIGNS.md 4.4): the blocks drawn with the <x-mail.*> components
 * (tables and inline styles, so inboxes show them as designed), the person's details in the placeholders, and,
 * for a real send, every http(s) link pointed at its counted address (/email/click/…).
 */
final class Renderer
{
    /** The blocks as HTML for one person, in the current locale; $links maps an address to its counted one */
    public static function body(array $blocks, array $person, array $links = []): string
    {
        $html = view('emails.partials.broadcast-blocks', [
            'blocks' => array_map(fn ($b) => self::filled($b, $person), $blocks),
        ])->render();

        return $links ? self::track($html, $links) : $html;
    }

    /** {{ first_name }}, {{ company_name }}, {{ industry }} with this person's details or the language's fallback */
    public static function fill(string $text, array $person, bool $html = false): string
    {
        $values = [
            'first_name' => trim((string) ($person['first_name'] ?? '')) ?: __('emails.broadcast.fallback_first_name'),
            'company_name' => trim((string) ($person['company'] ?? '')) ?: __('emails.broadcast.fallback_company'),
            'industry' => trim((string) ($person['industry'] ?? '')) ?: __('emails.broadcast.fallback_industry'),
        ];

        return preg_replace_callback('/\{\{\s*(first_name|company_name|industry)\s*\}\}/i', function ($m) use ($values, $html) {
            $value = $values[strtolower($m[1])];

            return $html ? e($value) : $value;
        }, $text);
    }

    /** Every link of the body pointed at its counted address (the logo and footer links are outside the body) */
    public static function track(string $html, array $links): string
    {
        return preg_replace_callback('~\bhref="([^"]+)"~i', function ($m) use ($links) {
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return isset($links[$url]) ? 'href="' . e($links[$url]) . '"' : $m[0];
        }, $html);
    }

    /** The whole email as one person would get it, for the editor's preview (no counted links) */
    public static function preview(Broadcast $broadcast, string $locale, ?array $person = null): string
    {
        $mail = self::mail($broadcast, $locale, $person ?? self::samplePerson($locale), unsubscribeUrl: route('email.unsubscribe', ['token' => 'preview']));

        return $mail->renderParts()['html'];
    }

    public static function mail(Broadcast $broadcast, string $locale, array $person, ?string $unsubscribeUrl = null, ?string $pixelUrl = null, array $links = [], bool $test = false): BroadcastEmail
    {
        $locale = $broadcast->localeFor($locale);

        return (new BroadcastEmail($broadcast->version($locale), (string) $broadcast->layout, (bool) $broadcast->greeting, $person, $unsubscribeUrl, $pixelUrl, $links, $test))
            ->locale($locale);
    }

    /** A fictional reader for previews and tests: a lead's contact */
    public static function samplePerson(string $locale): array
    {
        return ['first_name' => 'Ada', 'last_name' => 'Okafor', 'company' => 'Acme Ltd (demo)', 'kind' => 'lead'];
    }

    /** A block with the person's details in its words */
    private static function filled(array $block, array $person): array
    {
        foreach (['text', 'source', 'label', 'alt'] as $field) {
            if (isset($block[$field]) && is_string($block[$field])) {
                $block[$field] = self::fill($block[$field], $person);
            }
        }
        if (isset($block['html'])) {
            $block['html'] = RichText::forEmail(self::fill($block['html'], $person, html: true));
        }

        return $block;
    }
}
