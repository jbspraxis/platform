<?php

namespace App\Support;

/**
 * The "links" of a Praxis AI reply (docs/SITE_API.md 6.4): shown as buttons under the reply. Only
 * same-site paths are kept (a single leading slash, plain path segments, no scheme, host, query or
 * "//"), at most site.chat_links of them, each with a short plain-text label.
 */
final class ChatLinks
{
    /** @return list<array{label: string, path: string}> */
    public static function clean(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $out = [];
        foreach ($links as $link) {
            if (! is_array($link) || ! is_string($link['label'] ?? null) || ! is_string($link['path'] ?? null)) {
                continue;
            }
            $path = trim($link['path']);
            $label = trim(preg_replace('/\s+/u', ' ', strip_tags($link['label'])) ?? '');
            if (! self::isSitePath($path) || $label === '' || mb_strlen($label) > 80) {
                continue;
            }
            $out[$path] = ['label' => $label, 'path' => $path];
            if (count($out) >= (int) config('site.chat_links', 3)) {
                break;
            }
        }

        return array_values($out);
    }

    /** "/", "/schedule", "/kaizen/write", "/fr/contact"… never "//host", "/\host", a scheme, a query or "..". */
    public static function isSitePath(string $path): bool
    {
        return strlen($path) <= 200
            && preg_match('~^/(?:[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*)?$~', $path) === 1;
    }
}
