<?php

namespace App\Services\Site;

use App\Models\KaizenTelemetry;

/**
 * The site feedback form: POST /feedback on the site API.
 * Limits (docs/SITE_API.md section 6.2): 3 an hour and 10 a day
 * per visitor, 60 an hour for everyone, on the old page's limiter key.
 */
final class FeedbackService
{
    /** The site API's fields */
    public const FIELDS = ['identity', 'email', 'category', 'severity', 'description'];

    public static function rules(): array
    {
        return [
            'identity'    => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'category'    => 'required|string|max:255',
            'severity'    => 'required|integer|in:1,2,3',
            'description' => 'required|string|min:10|max:5000',
        ];
    }

    /**
     * Stores a report (validated with rules())
     *
     * @param  string|null  $ip  the visitor's IP (limits)
     *
     * @throws LimitReached
     */
    public function submit(array $in, ?string $ip = null): void
    {
        SiteLimits::check('feedback', $ip);

        KaizenTelemetry::create([
            'identity'    => $in['identity'] ?? null,
            'email'       => $in['email'] ?? null,
            'category'    => $in['category'],
            'severity'    => $in['severity'],
            'description' => $in['description'],
        ]);

        SiteLimits::hit('feedback', $ip);
    }
}
