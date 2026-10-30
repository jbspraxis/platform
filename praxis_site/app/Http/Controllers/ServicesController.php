<?php

namespace App\Http\Controllers;

use App\Support\HqApi;
use Illuminate\Http\Request;

/** /services: the Services page, and the Praxis Method guide by email (POST /whitepaper) from Services or How we work. */
class ServicesController extends Controller
{
    public function show()
    {
        return view('pages.services');
    }

    public function store(Request $request, HqApi $api)
    {
        $api->requestWhitepaper($this->fields($request, ['email'])['email'] ?? '');

        // Back to the page the form was on, at the form (only these two pages carry it)
        $back = $request->input('back') === 'how-we-work' ? 'how-we-work' : 'services';

        return redirect()->to(route($back) . '#guide')->with('sent', 'services');
    }
}
