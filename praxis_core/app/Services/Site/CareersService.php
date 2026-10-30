<?php

namespace App\Services\Site;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Job applications: POST /careers/applications on the site API. The CV is stored
 * on the private disk (storage/app/private/vault/dossiers), read in HQ through the checked file route.
 * Limits (docs/SITE_API.md section 6.2): 2 a day per visitor, 30 an hour for everyone; one application
 * per email: a repeat updates the existing application and adds the new CV (never an error).
 */
final class CareersService
{
    /** The site API's fields */
    public const FIELDS = [
        'full_name', 'email', 'country_code', 'phone',
        'city', 'primary_skill', 'other_skill',
        'secondary_skillset', 'linkedin_url',
        'portfolio_url', 'praxis_answer', 'cv',
    ];

    public static function rules(): array
    {
        return [
            'full_name'          => 'required|string|max:100',
            'email'              => 'required|email|max:100',
            'country_code'       => 'required|string|max:6', // with phone it must fit talent_pool.phone (30 chars)
            'phone'              => 'required|string|max:20',
            'city'               => 'required|string|max:255',
            'primary_skill'      => 'required|string',
            'other_skill'        => 'required_if:primary_skill,other|max:100',
            'secondary_skillset' => 'required|string|max:255',
            'linkedin_url'       => 'required|url|max:255',
            'portfolio_url'      => 'nullable|url|max:255',
            'praxis_answer'      => 'required|string|max:10000', // DB is TEXT; cap it to stop abuse
            'cv'                 => 'required|file|mimes:pdf,doc,docx|max:5120',
        ];
    }

    /** The site API's messages for these fields, in plain words and in the request's language */
    public static function apiMessages(): array
    {
        return [
            'cv.required' => SiteText::get('cv_required'),
            'phone.required' => SiteText::get('phone_required'),
            'city.required' => SiteText::get('city_required'),
            'other_skill.required_if' => SiteText::get('other_skill_required'),
        ];
    }

    /**
     * Saves the application and its CV (validated with rules()). A second application with the same email
     * updates the first and adds the new CV; after careers_repeat_days (30) it counts as a new application
     * again (status back to Pending, restored if it was removed).
     *
     * @param  string|null  $ip  the visitor's IP (limits)
     *
     * @throws LimitReached
     */
    public function submit(array $in, ?UploadedFile $cv, ?string $ip = null): void
    {
        $in = array_merge(array_fill_keys(array_values(self::FIELDS), null), $in);
        SiteLimits::check('careers', $ip);

        // Data Normalization
        $finalSkill = $in['primary_skill'] === 'other' ? $in['other_skill'] : $in['primary_skill'];
        $formattedPhone = $in['country_code'] . ' ' . ltrim($in['phone'], '0');

        $details = [
            'full_name'          => $in['full_name'],
            'phone'              => $formattedPhone,
            'city'               => $in['city'],
            'primary_weapon'     => $finalSkill !== null ? mb_substr((string) $finalSkill, 0, 100) : null, // the column's size: a long value is cut, never a 500
            'secondary_skillset' => $in['secondary_skillset'],
            'linkedin_url'       => $in['linkedin_url'],
            'portfolio_url'      => $in['portfolio_url'],
            'praxis_answer'      => $in['praxis_answer'],
        ];

        $save = function () use ($in, $cv, $details) {
            DB::transaction(function () use ($in, $cv, $details) {
                // talent_pool.email is unique: one application per email, updated by a repeat
                $existing = DB::table('talent_pool')->where('email', trim($in['email']))->lockForUpdate()->first();

                if ($existing) {
                    $talentId = $existing->id;
                    $fresh = $existing->deleted_at !== null
                        || Carbon::parse($existing->updated_at ?? $existing->created_at ?? now())->lt(now()->subDays((int) config('site_api.careers_repeat_days', 30)));
                    DB::table('talent_pool')->where('id', $talentId)->update($details + ['updated_at' => now()]
                        + ($fresh ? ['status' => 'Pending', 'deleted_at' => null, 'deleted_by' => null] : []));
                } else {
                    $talentId = (string) Str::uuid();
                    DB::table('talent_pool')->insert($details + [
                        'id'         => $talentId,
                        'email'      => $in['email'],
                        'status'     => 'Pending', // Matches your exact ENUM
                        // created_by, updated_by, deleted_by remain NULL as this is a public form
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // The CV (a repeat adds its CV; the earlier ones stay with the application)
                if ($cv) {
                    // Read the details first (before the file is moved)
                    $originalName = $cv->getClientOriginalName();
                    $mimeType = $cv->getMimeType();
                    $fileSize = $cv->getSize();

                    // Then store it on the private disk
                    $path = $cv->store('vault/dossiers', 'local');

                    DB::table('media_assets')->insert([
                        'id'          => Str::uuid(),
                        'talent_id'   => $talentId,
                        'file_name'   => $originalName,
                        'file_path'   => $path,
                        'mime_type'   => $mimeType,
                        'size_bytes'  => $fileSize,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            });
        };

        try {
            $save();
        } catch (UniqueConstraintViolationException $e) {
            // The same applicant sent twice at the same moment: the other one is saved now, so this one updates it
            $save();
        }

        SiteLimits::hit('careers', $ip);
    }
}
