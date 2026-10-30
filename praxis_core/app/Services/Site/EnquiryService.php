<?php

namespace App\Services\Site;

use App\Mail\EnquiryAlert;
use App\Mail\EnquiryReceived;
use App\Support\Access;
use App\Support\MailLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The contact form: the old /contact page (ContactHub) and POST /enquiries on the site API.
 * Fields use the API names; FIELDS maps ContactHub's properties onto them.
 * Limits (docs/SITE_API.md section 6.2): 3 an hour and 6 a day per visitor, 2 a day per email,
 * 60 an hour for everyone, on the old page's limiter key.
 */
final class EnquiryService
{
    /** ContactHub property => API field */
    public const FIELDS = [
        'fullName' => 'full_name', 'email' => 'email', 'companyName' => 'company_name', 'message' => 'message',
        'title' => 'title', 'countryCode' => 'country_code', 'phone' => 'phone', 'city' => 'city',
        'language' => 'language', 'budget' => 'budget',
        'stage' => 'stage', 'bottleneck' => 'bottleneck', 'otherBottleneck' => 'other_bottleneck',
        'techReality' => 'tech_reality', 'govStatus' => 'gov_status',
    ];

    /** "When would you like to start?" (new site only; replaces the budget): stored value => label in HQ */
    public const START_TIMINGS = [
        'this_quarter' => 'This quarter',
        'next_quarter' => 'Next quarter',
        'later' => 'Later this year',
        'exploring' => 'Just exploring',
    ];

    public static function rules(bool $diagnostic, $bottleneck): array
    {
        $rules = [
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'company_name' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'title' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|max:6',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'budget' => 'nullable|string|max:100',
        ];

        if ($diagnostic) {
            $rules['stage'] = 'required|string|max:100';
            $rules['bottleneck'] = 'required|string|max:100';
            $rules['tech_reality'] = 'nullable|string|max:100';
            $rules['gov_status'] = 'nullable|string|max:100';
            if ($bottleneck === 'other') {
                $rules['other_bottleneck'] = 'required|string|max:255';
            }
        }

        return $rules;
    }

    /** The site API's rules: the form's, plus the optional start question the old page doesn't ask */
    public static function apiRules(bool $diagnostic, $bottleneck): array
    {
        return self::rules($diagnostic, $bottleneck) + ['start_timing' => 'nullable|string|in:' . implode(',', array_keys(self::START_TIMINGS))];
    }

    /** What the form starts with on the old page: the Nigerian code, and the language of the page */
    public static function defaults(): array
    {
        return [
            'country_code' => '+234',
            'language' => app()->getLocale() === 'fr' ? 'Français (FR)' : 'English (EN)',
        ];
    }

    /**
     * Saves the enquiry (organisation, contact, first CRM note, HQ notification), then emails the visitor
     * and the team. Input is already validated with rules() / apiRules().
     *
     * @param  string|null  $ip  the visitor's IP (limits)
     * @param  bool  $afterResponse  send the emails after the response (site API)
     *
     * @throws LimitReached
     */
    public function submit(array $in, bool $diagnostic, ?string $ip = null, bool $afterResponse = false): void
    {
        $in = array_merge(array_fill_keys(array_values(self::FIELDS), null), ['start_timing' => null], $in);
        $limitIds = ['email' => strtolower(trim((string) $in['email']))];
        SiteLimits::check('enquiry', $ip, $limitIds);

        $startTiming = array_key_exists((string) $in['start_timing'], self::START_TIMINGS) ? $in['start_timing'] : null;
        $hasStartColumn = $startTiming !== null && Schema::hasColumn('organizations', 'start_timing');

        DB::transaction(function () use ($in, $diagnostic, $startTiming, $hasStartColumn) {

            // Extract & Clean Data
            [$firstName, $lastName] = Fields::nameParts($in['full_name']);
            $cleanOrgName = trim($in['company_name']);
            $emailStr = strtolower(trim($in['email']));

            $finalBottleneck = $in['bottleneck'] === 'other' ? $in['other_bottleneck'] : $in['bottleneck'];

            // A. Find or Create Organization (Case-Insensitive Lock)
            $existingOrg = DB::table('organizations')->whereRaw('LOWER(name) = ?', [strtolower($cleanOrgName)])->first();

            if ($existingOrg) {
                $orgId = $existingOrg->id;
                DB::table('organizations')->where('id', $orgId)->update(array_merge([
                    'is_duplicate_suspect' => 1,
                    'updated_at' => now(),
                ], $hasStartColumn ? ['start_timing' => $startTiming] : []));
            } else {
                $orgId = (string) Str::uuid();
                DB::table('organizations')->insert(array_merge([
                    'id' => $orgId,
                    'name' => $cleanOrgName,
                    'status' => 'Lead',
                    'pipeline_stage' => 'Lead Captured',
                    'country' => $in['country_code'],
                    'city' => $in['city'],
                    'preferred_language' => $in['language'] !== null ? mb_substr((string) $in['language'], 0, 20) : null, // the column's size: a long value is cut, never a 500
                    'budget_estimation' => $in['budget'] !== null ? mb_substr((string) $in['budget'], 0, 50) : null,
                    'enterprise_stage' => $diagnostic ? $in['stage'] : 'Not Provided',
                    'tech_reality' => $diagnostic ? $in['tech_reality'] : 'Not Provided',
                    'governance_status' => $diagnostic ? $in['gov_status'] : 'Not Provided',
                    'created_at' => now(),
                    'updated_at' => now()
                ], $hasStartColumn ? ['start_timing' => $startTiming] : []));
            }

            // B. Find or create the contact (a lead: client account with no password, so no portal access)
            $existingUser = DB::table('client_users')->where('email', $emailStr)->first();

            if ($existingUser) {
                $userId = $existingUser->id; // Grab existing user ID for the CRM Note
            } else {
                $userId = (string) Str::uuid();
                DB::table('client_users')->insert([
                    'id' => $userId,
                    'organization_id' => $orgId, // Attaches correctly to new or existing Org
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $emailStr,
                    'phone' => $in['country_code'] . ' ' . $in['phone'],
                    'job_title' => $in['title'],
                    'status' => 'Active',
                    'lifecycle_stage' => 'Lead',
                    'source' => 'contact_form',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // C. Log the exact message and bottleneck as the first CRM Note
            $noteContent = ">> INTAKE MESSAGE:\n" . $in['message'];
            if ($diagnostic) {
                $noteContent .= "\n\n>> IDENTIFIED BOTTLENECK:\n" . $finalBottleneck;
            }
            if ($startTiming !== null) {
                $noteContent .= "\n\n>> WHEN THEY WOULD LIKE TO START:\n" . self::START_TIMINGS[$startTiming];
            }

            DB::table('crm_notes')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'author_id' => $userId, // Attributing the note securely to the correct user
                'author_type' => 'client',
                'note_text' => $noteContent,
                'interaction_type' => 'Internal Note',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // D. HQ notification, as the booking form does
            $notifications = [];
            foreach (Access::staffWith('crm.edit') as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $adminId,
                    'title' => 'New website enquiry',
                    'message' => "{$in['full_name']} ({$cleanOrgName}) sent a message through the contact form.",
                    'type' => 'System', 'created_at' => now(),
                ];
            }
            if (!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        SiteLimits::hit('enquiry', $ip, $limitIds);

        // Emails: confirmation to the visitor, alert to the team (failures are logged, the enquiry is already saved)
        Deferred::run($afterResponse, fn () => $this->sendEmails($in, $diagnostic, $startTiming));
    }

    private function sendEmails(array $in, bool $diagnostic, ?string $startTiming = null): void
    {
        $visitor = strtolower(trim($in['email']));
        $team = config('services.leads.alert_email');

        // Only the first name of what the visitor typed goes into their email; each address gets a few emails a day at most
        if (SiteLimits::allowVisitorEmail($visitor)) {
            try {
                // In the language the visitor chose on the form, else the language of the page
                Mail::to($visitor)
                    ->locale(MailLocale::forVisitor($in['language']))
                    ->send(new EnquiryReceived(SiteLimits::firstName($in['full_name'])));
            } catch (\Throwable $e) {
                Log::error('Contact form: confirmation email failed', ['message' => $e->getMessage()]);
            }
        }

        if (!$team) {
            return;
        }

        $finalBottleneck = $in['bottleneck'] === 'other' ? $in['other_bottleneck'] : $in['bottleneck'];
        $details = [
            'job_title' => $in['title'],
            'phone' => filled($in['phone']) ? trim(($in['country_code'] ?? '') . ' ' . $in['phone']) : null,
            'city' => $in['city'],
            'language' => $in['language'],
            'budget' => $in['budget'],
            'start_timing' => $startTiming !== null ? self::START_TIMINGS[$startTiming] : null,
            'stage' => $diagnostic ? $in['stage'] : null,
            'bottleneck' => $diagnostic ? $finalBottleneck : null,
            'systems' => $diagnostic ? $in['tech_reality'] : null,
            'governance' => $diagnostic ? $in['gov_status'] : null,
        ];

        try {
            // Team alert: English, replies go to the visitor
            Mail::to($team)
                ->locale('en')
                ->send(new EnquiryAlert($in['full_name'], $visitor, trim($in['company_name']), $in['message'], $details));
        } catch (\Throwable $e) {
            Log::error('Contact form: team alert email failed', ['message' => $e->getMessage()]);
        }
    }
}
