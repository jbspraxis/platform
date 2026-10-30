<?php

namespace App\Http\Controllers\SiteApi;

use App\Services\Site\KaizenService;
use App\Services\Site\SiteText;
use Illuminate\Http\Request;

/** JBS Praxis Insights (Kaizen): the published articles and contributor submissions */
class KaizenController extends SiteApiController
{
    public function index(Request $request, KaizenService $kaizen)
    {
        $in = $this->input($request, [
            'page' => 'nullable|integer|min:1|max:100000',
            'category' => 'nullable|string|in:' . implode(',', KaizenService::CATEGORIES),
        ], ['page', 'category']);

        return $this->data($kaizen->articles((int) ($in['page'] ?? 1) ?: 1, $in['category'] ?? null), 200, 600);
    }

    public function show(string $slug, KaizenService $kaizen)
    {
        $article = $kaizen->article($slug);
        if (!$article) {
            return $this->error(404, ['code' => 'not_found']);
        }

        return $this->data($article, 200, 600);
    }

    public function store(Request $request, KaizenService $kaizen)
    {
        $in = $this->input($request, KaizenService::rules(SiteText::get('summary_words')), array_values(KaizenService::FIELDS));

        $kaizen->submit($in, $this->visitorIp($request));

        return $this->data(['received' => true], 201);
    }
}
