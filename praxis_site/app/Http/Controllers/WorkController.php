<?php

namespace App\Http\Controllers;

use App\Support\Work;

/** The proof pages: /work (the index), /work/{slug} (one case study) and /industries (text in lang/{en,fr}/work.php). */
class WorkController extends Controller
{
    public function index()
    {
        return view('pages.work.index', [
            'clients' => Work::group('clients'),
            'products' => Work::group('products'),
        ]);
    }

    public function show(string $slug)
    {
        abort_unless(array_key_exists($slug, Work::CASES), 404);

        return view('pages.work.show', ['slug' => $slug, 'case' => Work::CASES[$slug]]);
    }

    public function industries()
    {
        return view('pages.work.industries', ['sectors' => Work::sectors()]);
    }
}
