<?php

namespace App\Http\Controllers;

use App\Support\HqApi;
use Illuminate\Http\Request;

/** /feedback: feedback on the site (POST /feedback). */
class FeedbackController extends Controller
{
    public function show()
    {
        return view('pages.feedback');
    }

    public function store(Request $request, HqApi $api)
    {
        $api->sendFeedback($this->fields($request, ['identity', 'email', 'category', 'severity', 'description']));

        return $this->sent('feedback', 'feedback');
    }
}
