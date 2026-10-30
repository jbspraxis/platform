<?php

namespace App\Http\Controllers;

use App\Support\Articles;
use App\Support\HqApi;
use App\Support\Work;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** The content pages (text in lang/{en,fr}/site.php and pages-extra.php), and the files for crawlers. */
class PageController extends Controller
{
    /** Pages listed in the sitemap, each in English and French (route names without the "fr." prefix). */
    public const PAGES = ['home', 'services', 'how-we-work', 'work', 'industries', 'company', 'security', 'schedule', 'contact', 'calculator', 'kaizen', 'careers', 'privacy'];

    public function home()
    {
        return view('pages.home');
    }

    public function howWeWork()
    {
        return view('pages.how-we-work');
    }

    public function company()
    {
        return view('pages.company');
    }

    public function security()
    {
        return view('pages.security');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    /** While the site is a preview (SITE_NOINDEX) nothing may be crawled. */
    public function robots(): Response
    {
        $body = config('site.noindex')
            ? "User-agent: *\nDisallow: /\n"
            : "User-agent: *\nDisallow:\n\nSitemap: " . url('/sitemap.xml') . "\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Every page and case study in both languages with its hreflang pair, then the published Insights articles (English). */
    public function sitemap(HqApi $api): Response
    {
        $root = rtrim(url('/'), '/');
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

        // Articles: the first pages of the list; the sitemap never fails because HQ is slow
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

        return response()->view('seo.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** A plain-text summary for AI crawlers (llmstxt.org). */
    public function llms(): Response
    {
        return response()->view('seo.llms', [], 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** /hq/…, /client/…, /portal/… from before HQ and the portal had their own hosts (praxis_core App\Support\Hosts). */
    public function legacy(Request $request): RedirectResponse
    {
        $path = trim($request->path(), '/');
        $map = [
            '#^hq(?:/(.*))?$#' => ['hq', '$1'],
            '#^taskforce(?:/(.*))?$#' => ['hq', 'taskforce/$1'],
            '#^files/(.+)$#' => ['hq', 'files/$1'],
            '#^client-portal$#' => ['portal', 'login'],
            '#^client/login$#' => ['portal', 'login'],
            '#^client/dashboard$#' => ['portal', ''],
            '#^client/setup/(.+)$#' => ['portal', 'setup/$1'],
            '#^portal/proposal/(.+)$#' => ['portal', 'proposal/$1'],
            '#^portal/tracker/(.+)$#' => ['portal', 'tracker/$1'],
            '#^signoff/gateway/(.+)$#' => ['portal', 'signoff/$1'],
            '#^verify/contract/(.+)$#' => ['portal', 'verify/$1'],
        ];

        foreach ($map as $pattern => [$host, $target]) {
            if (preg_match($pattern, $path)) {
                $base = rtrim((string) config('site.' . $host . '_url'), '/');
                $newPath = rtrim((string) preg_replace($pattern, $target, $path), '/');
                $query = $request->getQueryString();

                return redirect()->away($base . ($newPath !== '' ? '/' . $newPath : '') . ($query ? '?' . $query : ''), 301);
            }
        }

        abort(404);
    }
}
