<?php

namespace App\Http\Controllers;

use App\Support\HqApi;
use Illuminate\Http\Request;

/** /careers: an application with a CV, sent to HQ as multipart (POST /careers/applications). */
class CareersController extends Controller
{
    private const FIELDS = [
        'full_name', 'email', 'country_code', 'phone', 'city', 'primary_skill', 'other_skill', 'secondary_skillset',
        'linkedin_url', 'portfolio_url', 'praxis_answer',
    ];

    /** The CV's own checks; "mimes" reads the file's first bytes (finfo, or App\Support\Polyfill\Finfo without it) */
    public const CV_RULES = ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'];

    public function show()
    {
        return view('pages.careers');
    }

    public function store(Request $request, HqApi $api)
    {
        // The file is checked here before it travels to HQ (HQ checks it again); everything else is HQ's to validate
        $request->validate(
            ['cv' => self::CV_RULES],
            [
                'cv.required' => __('site.careers.cv_required'),
                'cv.file' => __('site.careers.cv_failed'),
                'cv.uploaded' => __('site.careers.cv_size'),
                'cv.mimes' => __('site.careers.cv_type'),
                'cv.max' => __('site.careers.cv_size'),
            ]
        );

        $fields = $this->fields($request, self::FIELDS);
        if (($fields['primary_skill'] ?? null) !== 'other') {
            unset($fields['other_skill']);
        }

        $api->applyForJob($fields, $request->file('cv'));

        return $this->sent('careers', 'careers');
    }
}
