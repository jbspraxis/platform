<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * JBS Praxis Insights articles from GET /kaizen/articles(/{slug}), shaped for the views. Tolerant of
 * field names (the API uses snake_case; older names from the Kaizen tables are accepted too). The byline
 * is always "JBS Praxis": contributors are never named on the site.
 * Article text is shown as plain text: tags are stripped, then escaped; **bold** and paragraphs are kept.
 */
final class Articles
{
    /**
     * @param  array{data?: mixed, meta?: mixed}  $envelope  data plus meta, as HqApi::kaizenArticles returns it
     * @return array{items: list<array<string, mixed>>, page: int, pages: ?int, has_more: bool}
     */
    public static function page(array $envelope, int $requestedPage): array
    {
        $data = $envelope['data'] ?? [];
        $meta = is_array($envelope['meta'] ?? null) ? $envelope['meta'] : [];

        if (is_array($data) && ! array_is_list($data)) {
            $meta = array_merge($meta, is_array($data['meta'] ?? null) ? $data['meta'] : [], $data);
            $data = $data['articles'] ?? $data['items'] ?? $data['data'] ?? [];
        }
        $items = [];
        foreach (is_array($data) ? $data : [] as $row) {
            if (is_array($row) && ($article = self::article($row)) !== null) {
                $items[] = $article;
            }
        }

        $page = (int) ($meta['current_page'] ?? $meta['page'] ?? $requestedPage) ?: $requestedPage;
        $pages = $meta['last_page'] ?? $meta['pages'] ?? $meta['total_pages'] ?? null;
        $pages = is_numeric($pages) ? max(1, (int) $pages) : null;
        $hasMore = $pages !== null ? $page < $pages : (bool) ($meta['has_more'] ?? $meta['next_page'] ?? false);

        return ['items' => $items, 'page' => max(1, $page), 'pages' => $pages, 'has_more' => $hasMore];
    }

    /** @return array<string, mixed>|null */
    public static function article(array $row): ?array
    {
        $slug = self::text($row['slug'] ?? null);
        $title = self::text($row['title'] ?? $row['article_title'] ?? null);
        if ($slug === '' || $title === '' || ! preg_match('/^[A-Za-z0-9-]+$/', $slug)) {
            return null;
        }

        // Every article carries the byline "JBS Praxis" (docs/BRAND.md section 1, credentials without
        // names). The API sends no author or designation (docs/SITE_API.md 6.4), and nothing here reads them.
        $minutes = $row['read_time_minutes'] ?? $row['read_time'] ?? $row['minutes'] ?? null;

        return [
            'slug' => $slug,
            'title' => $title,
            'category' => self::text($row['category'] ?? null),
            'minutes' => is_numeric($minutes) && (int) $minutes > 0 ? (int) $minutes : null,
            'summary' => self::text($row['summary'] ?? $row['executive_summary'] ?? $row['exec_summary'] ?? null),
            'problem' => self::text($row['problem'] ?? $row['problem_statement'] ?? null),
            'solution' => self::text($row['solution'] ?? $row['engineered_solution'] ?? null),
            'result' => self::text($row['result'] ?? $row['roi'] ?? $row['final_roi'] ?? null),
            'date' => Format::date(is_string($d = $row['published_at'] ?? $row['created_at'] ?? null) ? $d : null),
            'byline' => __('site.name'),
        ];
    }

    /** Paragraphs of escaped text, with **bold** kept. */
    public static function html(?string $text): HtmlString
    {
        $text = trim((string) $text);
        if ($text === '') {
            return new HtmlString('');
        }
        $paragraphs = preg_split('/\R{2,}/', $text) ?: [];
        $out = '';
        foreach ($paragraphs as $paragraph) {
            $p = e(trim($paragraph));
            $p = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $p) ?? $p;
            $out .= '<p>' . nl2br($p, false) . '</p>';
        }

        return new HtmlString($out);
    }

    private static function text(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
