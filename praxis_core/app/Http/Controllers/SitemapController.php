<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KaizenArticle;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SitemapController extends Controller
{
    public function index()
    {
        // 1. Cache the entire sitemap matrix for 12 hours (720 minutes)
        $sitemapData = Cache::remember('sitemap_data_v2', 60 * 12, function () {

            // Real 'lastmod' for static pages: when the page's view file last changed
            $lastMod = function (string $view) {
                $file = resource_path('views/livewire/' . $view . '.blade.php');
                return Carbon::createFromTimestamp(is_file($file) ? filemtime($file) : time())->tz('UTC')->toAtomString();
            };

            // 2. Define Core Architecture (Flat & Fast). Logins and private areas stay out of the sitemap.
            $staticPages = [
                ['url' => '/', 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => $lastMod('conversion-hub')],
                ['url' => '/about', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $lastMod('the-firm')],
                ['url' => '/services', 'priority' => '0.9', 'changefreq' => 'monthly', 'lastmod' => $lastMod('services')],
                ['url' => '/portfolio', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $lastMod('portfolio')],
                ['url' => '/kaizen', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $lastMod('kaizen')],
                ['url' => '/schedule', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $lastMod('schedule-consultation')],
                ['url' => '/contact', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $lastMod('contact-hub')],
                ['url' => '/careers', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $lastMod('careers')],
                ['url' => '/trust-center', 'priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $lastMod('trust-center')],
                ['url' => '/privacy', 'priority' => '0.4', 'changefreq' => 'yearly', 'lastmod' => $lastMod('privacy')],
            ];

            // 3. Fetch Dynamic Intelligence (Kaizen Hub)
            // CRITICAL: We only pull the slug and updated_at to minimize memory consumption.
            $articles = KaizenArticle::where('status', 'Published')
            ->select('slug', 'updated_at') // PRAXIS OVERRIDE: Switched back to slug
            ->get();

            return [
                'staticPages' => $staticPages,
                'articles'    => $articles,
            ];
        });

        // 4. Render the XML Payload
        return response()->view('sitemap', [
            'staticPages' => $sitemapData['staticPages'],
            'articles'    => $sitemapData['articles'],
        ])->header('Content-Type', 'text/xml');
    }
}