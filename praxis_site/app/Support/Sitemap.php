<?php

namespace App\Support;

use Throwable;

/** What /sitemap.xml lists (PageController::sitemap), which is also what `php artisan site:indexnow` submits. */
final class Sitemap
{
    /** Pages listed in the sitemap, each in English and French (route names without the "fr." prefix). */
    public const PAGES = ['home', 'services', 'how-we-work', 'work', 'industries', 'company', 'security', 'schedule', 'contact', 'calculator', 'kaizen', 'kaizen.write', 'careers', 'feedback', 'privacy'];

    /**
     * Every page and case study (App\Support\Work) in both languages with its hreflang pair, then the published Insights articles (English), on $root
     * (default: this site's own address). It never fails because HQ is slow: then it lists the pages only, and calls
     * $whenHqFails (site:indexnow says so).
     *
     * @return list<array{loc: string, en?: string, fr?: string}>
     */
    public static function entries(HqApi $api, ?string $root = null, ?callable $whenHqFails = null): array
    {
        $root = rtrim($root ?? url('/'), '/');
        $urls = [];
        $paths = array_map(fn (string $name) => route($name, [], false), self::PAGES);
        foreach (Work::slugs() as $slug) {
            $paths[] = route('work.show', ['slug' => $slug], false);
        }
        foreach ($paths as $path) {
            $en = $root . $path;
            $fr = $root . '/fr' . ($path === '/' ? '' : $path);
            $urls[] = ['loc' => $en, 'en' => $en, 'fr' => $fr];
            $urls[] = ['loc' => $fr, 'en' => $en, 'fr' => $fr];
        }

        // Articles: the first pages of the list
        try {
            for ($page = 1; $page <= 5; $page++) {
                $list = Articles::page($api->kaizenArticles($page), $page);
                foreach ($list['items'] as $article) {
                    $urls[] = ['loc' => $root . '/kaizen/' . $article['slug']];
                }
                if (! $list['has_more']) {
                    break;
                }
            }
        } catch (Throwable $e) {
            // pages only (and the articles of the pages read before the failure)
            if ($whenHqFails) {
                $whenHqFails($e);
            }
        }

        return $urls;
    }
}
