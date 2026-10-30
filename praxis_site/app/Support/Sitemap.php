<?php

namespace App\Support;

use Throwable;

/** What /sitemap.xml lists (PageController::sitemap), which is also what `php artisan site:indexnow` submits. */
final class Sitemap
{
    /** Pages listed in the sitemap, each in English and French (route names without the "fr." prefix). */
    public const PAGES = ['home', 'services', 'how-we-work', 'company', 'security', 'schedule', 'contact', 'calculator', 'kaizen', 'careers', 'privacy'];

    /**
     * Every page in both languages with its hreflang pair, then the published Insights articles (English), on $root
     * (default: this site's own address). It never fails because HQ is slow: then it lists the pages only.
     *
     * @return list<array{loc: string, en?: string, fr?: string}>
     */
    public static function entries(HqApi $api, ?string $root = null): array
    {
        $root = rtrim($root ?? url('/'), '/');
        $urls = [];
        foreach (self::PAGES as $name) {
            $path = route($name, [], false);
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
        } catch (Throwable) {
            // pages only
        }

        return $urls;
    }
}
