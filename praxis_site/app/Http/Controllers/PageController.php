<?php

namespace App\Http\Controllers;

use App\Support\HqApi;
use App\Support\Sitemap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The content pages (text in lang/{en,fr}/site.php and pages-extra.php), and the files for crawlers. */
class PageController extends Controller
{
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

    /** Every page and case study in both languages with its hreflang pair, then the published Insights articles (App\Support\Sitemap). */
    public function sitemap(HqApi $api): Response
    {
        return response()->view('seo.sitemap', ['urls' => Sitemap::entries($api)], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** IndexNow's key file (config site.indexnow): the engines read the key here; any other key is not found. */
    public function indexNowKey(string $key): Response
    {
        $ours = (string) config('site.indexnow.key');
        abort_unless($ours !== '' && $key === $ours, 404);

        return response($ours, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
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
