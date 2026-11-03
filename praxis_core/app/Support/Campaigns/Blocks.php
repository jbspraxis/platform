<?php

namespace App\Support\Campaigns;

use Illuminate\Support\Str;

/**
 * The blocks of a broadcast version (docs/CAMPAIGNS.md 4.4). Each block is an array with a type and an id:
 *
 *   heading  text                    text     html (RichText)               image    src, alt, link
 *   button   label, url              quote    text, source                  figures  items: [{value, label}] (1–3)
 *   details  rows: [{label, value}]  divider  —
 *
 * clean() is the only way blocks are stored: unknown types and fields go, strings are trimmed and capped, the text
 * is sanitised, addresses must be http(s) or mailto, images must be our own uploads. problems() lists what stops a
 * version from being sent. Placeholders {{ first_name }} and {{ company_name }} may appear in any text.
 */
final class Blocks
{
    public const TYPES = [
        'heading' => ['label' => 'Heading', 'icon' => 'heading-2'],
        'text' => ['label' => 'Text', 'icon' => 'pilcrow'],
        'image' => ['label' => 'Image', 'icon' => 'image'],
        'button' => ['label' => 'Button', 'icon' => 'mouse-pointer-click'],
        'quote' => ['label' => 'Quote', 'icon' => 'quote'],
        'figures' => ['label' => 'Key figures', 'icon' => 'chart-no-axes-column'],
        'details' => ['label' => 'Details', 'icon' => 'list'],
        'divider' => ['label' => 'Divider', 'icon' => 'minus'],
    ];

    public const MAX_BLOCKS = 30;

    public static function blank(string $type, string $locale = 'en'): array
    {
        $fr = $locale === 'fr';
        $block = ['type' => $type, 'id' => self::newId()];

        return $block + match ($type) {
            'heading' => ['text' => ''],
            'text' => ['html' => ''],
            'image' => ['src' => '', 'alt' => '', 'link' => ''],
            // The free audit and demo is the brand's one call to action (docs/BRAND.md section 14)
            'button' => ['label' => $fr ? 'Réserver un audit et une démonstration offerts' : 'Book a free audit and demo', 'url' => self::scheduleUrl()],
            'quote' => ['text' => '', 'source' => ''],
            'figures' => ['items' => [['value' => '', 'label' => ''], ['value' => '', 'label' => ''], ['value' => '', 'label' => '']]],
            'details' => ['rows' => $fr
                ? [['label' => 'Date', 'value' => ''], ['label' => 'Heure', 'value' => ''], ['label' => 'Lieu', 'value' => '']]
                : [['label' => 'Date', 'value' => ''], ['label' => 'Time', 'value' => ''], ['label' => 'Place', 'value' => '']]],
            default => [],
        };
    }

    /** The stored form of a version's blocks */
    public static function clean(array $blocks): array
    {
        $out = [];
        foreach (array_slice(array_values($blocks), 0, self::MAX_BLOCKS) as $block) {
            $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
            if (! isset(self::TYPES[$type])) {
                continue;
            }
            $id = is_string($block['id'] ?? null) && preg_match('/^[a-z0-9]{6,16}$/', $block['id']) ? $block['id'] : self::newId();
            $out[] = ['type' => $type, 'id' => $id] + match ($type) {
                'heading' => ['text' => self::line($block['text'] ?? '', 200)],
                'text' => ['html' => RichText::clean(is_string($block['html'] ?? null) ? mb_substr($block['html'], 0, 20000) : '')],
                'image' => [
                    'src' => Images::isOurs($block['src'] ?? null) ? (string) $block['src'] : '',
                    'alt' => self::line($block['alt'] ?? '', 200),
                    'link' => self::url($block['link'] ?? ''),
                ],
                'button' => ['label' => self::line($block['label'] ?? '', 60), 'url' => self::url($block['url'] ?? '', keepTyped: true)],
                'quote' => ['text' => self::para($block['text'] ?? '', 600), 'source' => self::line($block['source'] ?? '', 120)],
                'figures' => ['items' => self::pairs($block['items'] ?? [], 3, 'value', 'label', 24, 80)],
                'details' => ['rows' => self::pairs($block['rows'] ?? [], 6, 'label', 'value', 40, 160)],
                default => [],
            };
        }

        return $out;
    }

    /** What stops this version from going out, in plain words ("Button 2 needs a link") */
    public static function problems(array $version): array
    {
        $problems = [];
        if (trim((string) ($version['subject'] ?? '')) === '') {
            $problems[] = 'Add a subject.';
        }
        $blocks = $version['blocks'] ?? [];
        $hasContent = false;
        $count = [];
        foreach ($blocks as $block) {
            $type = $block['type'];
            $n = $count[$type] = ($count[$type] ?? 0) + 1;
            $name = self::TYPES[$type]['label'] . ($n > 1 || self::several($blocks, $type) ? ' ' . $n : '');
            match ($type) {
                'heading' => $block['text'] === '' ? $problems[] = "{$name} is empty: write it or remove it." : $hasContent = true,
                'text' => RichText::isBlank($block['html']) ? $problems[] = "{$name} is empty: write it or remove it." : $hasContent = true,
                'image' => $block['src'] === '' ? $problems[] = "{$name} has no picture: upload one or remove it."
                    : ($block['alt'] === '' ? $problems[] = "{$name} needs a description for people who can’t see images." : null),
                'button' => $block['label'] === '' || ! RichText::isSafeUrl($block['url'])
                    ? $problems[] = "{$name} needs a label and a link that starts with https://." : null,
                'quote' => $block['text'] === '' ? $problems[] = "{$name} is empty: write it or remove it." : $hasContent = true,
                'figures' => collect($block['items'])->contains(fn ($i) => $i['value'] !== '') ? $hasContent = true : $problems[] = "{$name} has no figures: fill them in or remove it.",
                'details' => collect($block['rows'])->contains(fn ($r) => $r['value'] !== '') ? $hasContent = true : $problems[] = "{$name} has no details: fill them in or remove it.",
                default => null,
            };
        }
        if (! $hasContent) {
            $problems[] = 'Write the email: it needs at least one heading or text.';
        }

        return $problems;
    }

    /** Every http(s) address the blocks link to, in order, once each (the links counted for clicks) */
    public static function links(array $blocks): array
    {
        $urls = [];
        foreach ($blocks as $block) {
            $urls = array_merge($urls, match ($block['type']) {
                'button' => [$block['url']],
                'image' => [$block['link']],
                'text' => RichText::links($block['html']),
                default => [],
            });
        }

        return array_values(array_unique(array_filter($urls, fn ($u) => (bool) preg_match('~^https?://~i', (string) $u))));
    }

    /** Blocks from an AI draft (config/ai.php broadcast_draft): text comes as plain text, the button keeps $buttonUrl */
    public static function fromAi(array $items, string $locale, ?string $buttonUrl = null): array
    {
        $blocks = [];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            if (! isset(self::TYPES[$type]) || $type === 'image') {
                continue;
            }
            $block = self::blank($type, $locale);
            $text = trim((string) ($item['text'] ?? ''));
            $block = match ($type) {
                'heading' => ['text' => $text] + $block,
                'text' => ['html' => RichText::fromPlain($text)] + $block,
                'quote' => ['text' => $text, 'source' => (string) ($item['source'] ?? '')] + $block,
                'button' => ['label' => (string) ($item['label'] ?? '') ?: $block['label'], 'url' => $buttonUrl ?: $block['url']] + $block,
                'figures' => ['items' => array_map(fn ($i) => ['value' => (string) ($i['value'] ?? ''), 'label' => (string) ($i['label'] ?? '')], (array) ($item['items'] ?? []))] + $block,
                'details' => ['rows' => array_map(fn ($i) => ['label' => (string) ($i['label'] ?? ''), 'value' => (string) ($i['value'] ?? '')], (array) ($item['items'] ?? []))] + $block,
                default => $block,
            };
            $blocks[] = $block;
        }

        return self::clean($blocks);
    }

    /**
     * A translation (config/ai.php broadcast_translate) laid over the blocks it came from: same blocks, same images
     * and links, the translated words. A block the answer leaves out keeps its original words.
     */
    public static function translated(array $source, array $items): array
    {
        $byIndex = collect($items)->keyBy(fn ($i) => (int) ($i['index'] ?? -1));
        $out = [];
        foreach (array_values($source) as $n => $block) {
            $t = $byIndex->get($n);
            $block['id'] = self::newId();
            if ($t) {
                $text = (string) ($t['text'] ?? '');
                $block = match ($block['type']) {
                    'heading' => ['text' => $text ?: $block['text']] + $block,
                    'text' => ['html' => $text !== '' ? $text : $block['html']] + $block,
                    'image' => ['alt' => $text ?: $block['alt']] + $block,
                    'button' => ['label' => (string) ($t['label'] ?? '') ?: $block['label']] + $block,
                    'quote' => ['text' => $text ?: $block['text'], 'source' => (string) ($t['source'] ?? '') ?: $block['source']] + $block,
                    'figures' => ['items' => self::overlay($block['items'], (array) ($t['items'] ?? []), 'value', 'label')] + $block,
                    'details' => ['rows' => self::overlay($block['rows'], (array) ($t['items'] ?? []), 'label', 'value')] + $block,
                    default => $block,
                };
            }
            $out[] = $block;
        }

        return self::clean($out);
    }

    /** The words of each block, numbered, for the AI to translate (with the block's own HTML for text) */
    public static function forTranslation(array $blocks): array
    {
        $items = [];
        foreach (array_values($blocks) as $n => $block) {
            $item = ['index' => $n, 'type' => $block['type']];
            $items[] = $item + match ($block['type']) {
                'heading' => ['text' => $block['text']],
                'text' => ['text' => $block['html']],
                'image' => ['text' => $block['alt']],
                'button' => ['label' => $block['label']],
                'quote' => ['text' => $block['text'], 'source' => $block['source']],
                'figures' => ['items' => $block['items']],
                'details' => ['items' => $block['rows']],
                default => [],
            };
        }

        return $items;
    }

    public static function newId(): string
    {
        return strtolower(Str::random(10));
    }

    public static function scheduleUrl(): string
    {
        return route('schedule');
    }

    private static function several(array $blocks, string $type): bool
    {
        return count(array_filter($blocks, fn ($b) => $b['type'] === $type)) > 1;
    }

    private static function line(mixed $value, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', is_string($value) ? $value : '')), 0, $max);
    }

    private static function para(mixed $value, int $max): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", is_string($value) ? $value : '');

        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/u', ' ', $value))), 0, $max);
    }

    /** A safe address, or '' ($keepTyped: a half-typed address stays so the person can finish it; problems() flags it) */
    private static function url(mixed $value, bool $keepTyped = false): string
    {
        $value = trim(is_string($value) ? $value : '');
        if ($value !== '' && ! preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) && preg_match('~^[a-z0-9-]+(\.[a-z0-9-]+)+(/|$)~i', $value)) {
            $value = 'https://' . $value; // jbspraxis.com/schedule
        }
        if (RichText::isSafeUrl($value)) {
            return $value;
        }

        return $keepTyped && ! preg_match('~^\s*(javascript|data|vbscript):~i', $value) ? mb_substr(strip_tags($value), 0, 2000) : '';
    }

    private static function pairs(mixed $rows, int $max, string $first, string $second, int $firstMax, int $secondMax): array
    {
        $out = [];
        foreach (array_slice(array_values(is_array($rows) ? $rows : []), 0, $max) as $row) {
            $row = is_array($row) ? $row : [];
            $out[] = [$first => self::line($row[$first] ?? '', $firstMax), $second => self::line($row[$second] ?? '', $secondMax)];
        }

        return $out ?: [[$first => '', $second => '']];
    }

    private static function overlay(array $rows, array $translated, string ...$fields): array
    {
        foreach ($rows as $n => $row) {
            foreach ($fields as $field) {
                $value = trim((string) ($translated[$n][$field] ?? ''));
                if ($value !== '') {
                    $rows[$n][$field] = $value;
                }
            }
        }

        return $rows;
    }
}
