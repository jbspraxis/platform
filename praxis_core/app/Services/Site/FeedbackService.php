<?php

namespace App\Services\Site;

use App\Models\KaizenTelemetry;

/**
 * The site feedback form: the old /feedback page (TelemetryNode) and POST /feedback.
 * The field names are the same on both. Limits (docs/SITE_API.md section 6.2): 3 an hour and 10 a day
 * per visitor, 60 an hour for everyone, on the old page's limiter key.
 */
final class FeedbackService
{
    public const FIELDS = ['identity' => 'identity', 'email' => 'email', 'category' => 'category', 'severity' => 'severity', 'description' => 'description'];

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
