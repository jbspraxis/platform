<?php

namespace App\Http\Controllers;

use App\Support\Articles;
use App\Support\HqApi;
use Illuminate\Http\Request;

/** /kaizen: JBS Praxis Insights (list, article, and the form to submit an article for review). */
class KaizenController extends Controller
{
    /** The two sections (stored values; labels in lang/en/site.php). */
    public const CATEGORIES = ['The Boardroom', 'The Server Room'];

    private const FIELDS = [
        'full_name', 'title', 'email', 'linkedin_url', 'category', 'article_title', 'exec_summary', 'vulnerability', 'solution', 'roi',
    ];

    public function index(Request $request, HqApi $api)
    {
        $page = max(1, min(500, (int) $request->query('page', 1)));
        $category = in_array($request->query('category'), self::CATEGORIES, true) ? (string) $request->query('category') : null;

        $list = Articles::page($api->kaizenArticles($page, $category), $page);

        return view('kaizen.index', [
            'list' => $list,
            'category' => $category,
            'stale' => $api->lastReadWasStale(),
        ]);
    }

    public function show(string $slug, HqApi $api)
    {
        $article = Articles::article($api->kaizenArticle($slug));
        abort_if($article === null, 404);

        return view('kaizen.show', [
            'article' => $article,
            'stale' => $api->lastReadWasStale(),
        ]);
    }

    public function create()
    {
        return view('kaizen.write');
    }

    public function store(Request $request, HqApi $api)
    {
        $api->submitKaizenArticle($this->fields($request, self::FIELDS));

        return $this->sent('kaizen.write', 'kaizen');
    }
}
