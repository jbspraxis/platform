<?php

namespace App\Http\Controllers;

use App\Support\HqApi;
use Illuminate\Http\Request;

/** /contact: the enquiry form, with optional questions about the organisation (POST /enquiries). */
class ContactController extends Controller
{
    private const FIELDS = [
        'full_name', 'email', 'company_name', 'message', 'title', 'country_code', 'phone', 'city', 'language',
        'stage', 'bottleneck', 'other_bottleneck', 'tech_reality', 'gov_status',
        // "When would you like to start?" (docs/SITE_API.md 6.4): replaces the old budget question, which is never sent
        'start_timing',
    ];

    public function show()
    {
        // The reply language starts on the page language, so a French visitor gets a French reply
        return view('pages.contact', [
            'replyLanguage' => app()->getLocale() === 'fr' ? 'Français (FR)' : 'English (EN)',
        ]);
    }

    public function store(Request $request, HqApi $api)
    {
        $fields = $this->fields($request, self::FIELDS);
        if (($fields['bottleneck'] ?? null) !== 'other') {
            unset($fields['other_bottleneck']);
        }
        // A phone number without a number needs no country code
        if (! isset($fields['phone'])) {
            unset($fields['country_code']);
        }

        $api->createEnquiry($fields);

        return $this->sent('contact', 'contact');
    }
}
