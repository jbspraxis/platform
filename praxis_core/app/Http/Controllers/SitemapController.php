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
        $sitemapData = Cache::remember('sitemap_data', 60 * 12, function () {
            
            // Generate a deterministic 'lastmod' for static pages (e.g., start of current week)
            // This forces Google to re-evaluate static sales pages regularly.
            $staticLastMod = Carbon::now()->startOfWeek()->tz('UTC')->toAtomString();

            // 2. Define Core Architecture (Flat & Fast)
            $staticPages = [
                ['url' => '/', 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => $staticLastMod],
                ['url' => '/about', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $staticLastMod],
                ['url' => '/services', 'priority' => '0.9', 'changefreq' => 'monthly', 'lastmod' => $staticLastMod],
                ['url' => '/portfolio', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $staticLastMod],
                ['url' => '/kaizen', 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => $staticLastMod],
                ['url' => '/schedule', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $staticLastMod],
                ['url' => '/careers', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $staticLastMod],
                // The Gatekeeper: Indexed so executives can search "JBS Praxis Login"
                ['url' => '/hq/login', 'priority' => '0.5', 'changefreq' => 'yearly', 'lastmod' => $staticLastMod], 
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