<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What jbspraxis.com shows about the company: legal details, registrations, offices, contact, social links
 * and (only when switched on) leadership. Edited in HQ → Marketing → Website settings; read by the marketing
 * site through GET /api/site/v1/settings (docs/SITE_API.md section 4).
 *
 *   WebsiteSettings::all()                       every value, stored over the defaults (cached)
 *   WebsiteSettings::get('contact.email')        one value by dot key
 *   WebsiteSettings::set(['contact' => [...]], $userId)   validate, save, audit, clear the cache
 *   WebsiteSettings::publicPayload()             the "data" object of GET /settings (cached)
 *
 * Storage: table website_settings, one row per section (company, contact, social, leadership), JSON value.
 * A section without a row uses defaults(). Lists are stored whole; a saved list replaces the default list.
 */
class WebsiteSettings
{
    public const SECTIONS = ['company', 'contact', 'social', 'leadership'];

    /** Registration status => what HQ shows (the site shows "registration in progress" for pending, never a number) */
    public const STATUSES = ['pending' => 'Registration in progress', 'registered' => 'Registered'];

    /** Countries offered for registrations and offices (ISO 3166-1 alpha-2 => name) */
    public const COUNTRIES = [
        'NG' => 'Nigeria', 'CM' => 'Cameroon', 'BJ' => 'Benin', 'CF' => 'Central African Republic', 'TD' => 'Chad',
        'CG' => 'Congo', 'CI' => 'Côte d’Ivoire', 'GQ' => 'Equatorial Guinea', 'GA' => 'Gabon', 'GH' => 'Ghana',
        'KE' => 'Kenya', 'RW' => 'Rwanda', 'SN' => 'Senegal', 'ZA' => 'South Africa', 'TG' => 'Togo',
        'FR' => 'France', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
    ];

    /** The lists: dot path => [empty row, most rows allowed, label used in messages] */
    public const LISTS = [
        'company.registrations' => [['country' => 'NG', 'label' => null, 'number' => null, 'status' => 'pending'], 10, 'registration'],
        'company.offices' => [['city' => null, 'country' => 'NG', 'address' => null], 10, 'office'],
        'contact.phones' => [['label' => null, 'number' => null], 5, 'phone'],
        'contact.whatsapp' => [['label' => null, 'number' => null], 5, 'WhatsApp number'],
        'leadership.people' => [['name' => null, 'title' => null, 'bio_en' => null, 'bio_fr' => null, 'linkedin' => null, 'photo_url' => null], 12, 'person'],
    ];

    /** Fields chosen from a list: a row with only these filled is still an empty row */
    private const CHOICE_FIELDS = ['country', 'status'];

    /** Digits, spaces, brackets, dots and dashes, with an optional + in front: +234 800 000 0000 */
    private const PHONE = '/^\+?[0-9][0-9 ().\-]{4,22}[0-9]$/';

    private const CACHE_SECONDS = 600;

    public static function defaults(): array
    {
        return [
            'company' => [
                'legal_name' => 'JBS Praxis LLC',
                'trading_name' => 'JBS Praxis',
                'founded' => null,
                'registrations' => [
                    ['country' => 'NG', 'label' => 'CAC', 'number' => null, 'status' => 'pending'],
                    ['country' => 'CM', 'label' => 'RCCM', 'number' => null, 'status' => 'pending'],
                ],
                'offices' => [
                    ['city' => 'Lagos', 'country' => 'NG', 'address' => null],
                    ['city' => 'Douala', 'country' => 'CM', 'address' => null],
                ],
            ],
            'contact' => [
                'email' => 'info@jbspraxis.com',
                'phones' => [['label' => 'Nigeria', 'number' => null]],
                'whatsapp' => [['label' => 'Nigeria', 'number' => null]],
                'hours' => null,
            ],
            'social' => [
                'linkedin' => 'https://www.linkedin.com/company/jbspraxis',
                'x' => 'https://x.com/jbspraxis',
                'youtube' => 'https://www.youtube.com/@jbspraxis',
            ],
            'leadership' => [
                'show' => false,
                'people' => [],
            ],
        ];
    }

    /** Every setting: stored values over the defaults. */
    public static function all(): array
    {
        return self::state()['values'];
    }

    /** One setting by dot key ('contact.email', 'company.offices'); $default when it is empty. */
    public static function get(string $dotKey, $default = null)
    {
        return data_get(self::all(), $dotKey) ?? $default;
    }

    /** When the settings were last saved (ISO 8601 with offset), or null if they never were. */
    public static function updatedAt(): ?string
    {
        return self::state()['updated_at'];
    }

    /**
     * Save some or all sections, e.g. set(['contact' => ['email' => '...']], $userId). Fields left out keep
     * their current value; a list given replaces the whole list. Text is trimmed, empty text becomes null and
     * empty list rows are dropped. Throws ValidationException (keys like 'company.legal_name') if anything is
     * invalid; nothing is saved then. Unchanged sections are not written. Writes one audit row per save.
     */
    public static function set(array $values, ?string $userId): void
    {
        $unknown = array_values(array_diff(array_keys($values), self::SECTIONS));
        if ($unknown) {
            throw ValidationException::withMessages([(string) $unknown[0] => 'This setting does not exist.']);
        }
        foreach ($values as $section => $fields) {
            if (! is_array($fields)) {
                throw ValidationException::withMessages([$section => 'The settings for this section are missing. Reload the page and try again.']);
            }
        }

        $current = self::load()['values'];
        $merged = self::normalise(self::overlay($current, $values));
        Validator::make($merged, self::rules(), self::messages(), self::attributes())->validate();

        $changed = array_values(array_filter(self::SECTIONS, fn ($s) => array_key_exists($s, $values) && $merged[$s] !== $current[$s]));
        if (! $changed) {
            self::forget();
            return;
        }

        $labels = ['company' => 'company', 'contact' => 'contact', 'social' => 'social links', 'leadership' => 'leadership'];
        $description = 'Website settings saved: ' . implode(', ', array_map(fn ($s) => $labels[$s], $changed)) . '.';
        if ($merged['leadership']['show'] !== $current['leadership']['show']) {
            $description .= $merged['leadership']['show'] ? ' Leadership is now shown on the website.' : ' Leadership is now hidden on the website.';
        }

        DB::transaction(function () use ($changed, $merged, $current, $userId, $description) {
            $now = now();
            foreach ($changed as $section) {
                DB::table('website_settings')->upsert(
                    [['key' => $section, 'value' => self::json($merged[$section]), 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now]],
                    ['key'],
                    ['value', 'updated_by', 'updated_at'],
                );
            }

            DB::table('system_audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'WEBSITE_SETTINGS_UPDATED',
                'description' => $description,
                'target_table' => 'website_settings',
                'old_values' => self::json(array_intersect_key($current, array_flip($changed))),
                'new_values' => self::json(array_intersect_key($merged, array_flip($changed))),
                'ip_address' => request()?->ip(),
                'created_at' => $now,
            ]);
        });

        self::forget();
    }

    /**
     * The public subset for GET /api/site/v1/settings: the object inside {"data": ...} (docs/SITE_API.md
     * section 4). A pending registration never shows a number; leadership people are only listed when
     * leadership.show is on. updated_at is null until the settings are first saved.
     */
    public static function publicPayload(): array
    {
        $state = self::state();
        $v = $state['values'];
        $show = (bool) $v['leadership']['show'];

        return [
            'company' => [
                'legal_name' => $v['company']['legal_name'],
                'trading_name' => $v['company']['trading_name'],
                'founded' => $v['company']['founded'],
                'registrations' => array_map(fn ($r) => [
                    'country' => $r['country'],
                    'label' => $r['label'],
                    'number' => $r['status'] === 'registered' ? $r['number'] : null,
                    'status' => $r['status'],
                ], $v['company']['registrations']),
                'offices' => array_map(fn ($o) => ['city' => $o['city'], 'country' => $o['country'], 'address' => $o['address']], $v['company']['offices']),
            ],
            'contact' => [
                'email' => $v['contact']['email'],
                'phones' => array_map(fn ($p) => ['label' => $p['label'], 'number' => $p['number']], $v['contact']['phones']),
                'whatsapp' => array_map(fn ($p) => ['label' => $p['label'], 'number' => $p['number']], $v['contact']['whatsapp']),
                'hours' => $v['contact']['hours'],
            ],
            'social' => [
                'linkedin' => $v['social']['linkedin'],
                'x' => $v['social']['x'],
                'youtube' => $v['social']['youtube'],
            ],
            'leadership' => [
                'show' => $show,
                'people' => $show ? array_map(fn ($p) => [
                    'name' => $p['name'], 'title' => $p['title'], 'bio_en' => $p['bio_en'], 'bio_fr' => $p['bio_fr'],
                    'linkedin' => $p['linkedin'], 'photo_url' => $p['photo_url'],
                ], $v['leadership']['people']) : [],
            ],
            'updated_at' => $state['updated_at'],
        ];
    }

    /** Validation rules for the full settings array; $prefix for a form property ('settings.'). */
    public static function rules(string $prefix = ''): array
    {
        $p = $prefix;
        $country = ['required', 'string', Rule::in(array_keys(self::COUNTRIES))];
        $phone = ['nullable', 'string', 'max:24', 'regex:' . self::PHONE];
        $link = ['nullable', 'string', 'max:255', 'url:https'];

        return [
            "{$p}company" => ['required', 'array'],
            "{$p}company.legal_name" => ['required', 'string', 'max:120'],
            "{$p}company.trading_name" => ['required', 'string', 'max:80'],
            "{$p}company.founded" => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
            "{$p}company.registrations" => ['present', 'array', 'max:' . self::LISTS['company.registrations'][1]],
            "{$p}company.registrations.*.country" => $country,
            "{$p}company.registrations.*.label" => ['required', 'string', 'max:40'],
            "{$p}company.registrations.*.number" => ['nullable', "required_if:{$p}company.registrations.*.status,registered", 'string', 'max:60'],
            "{$p}company.registrations.*.status" => ['required', 'string', Rule::in(array_keys(self::STATUSES))],
            "{$p}company.offices" => ['present', 'array', 'max:' . self::LISTS['company.offices'][1]],
            "{$p}company.offices.*.city" => ['required', 'string', 'max:80'],
            "{$p}company.offices.*.country" => $country,
            "{$p}company.offices.*.address" => ['nullable', 'string', 'max:200'],

            "{$p}contact" => ['required', 'array'],
            "{$p}contact.email" => ['required', 'string', 'email', 'max:120'],
            "{$p}contact.phones" => ['present', 'array', 'max:' . self::LISTS['contact.phones'][1]],
            "{$p}contact.phones.*.label" => ['required', 'string', 'max:40'],
            "{$p}contact.phones.*.number" => $phone,
            "{$p}contact.whatsapp" => ['present', 'array', 'max:' . self::LISTS['contact.whatsapp'][1]],
            "{$p}contact.whatsapp.*.label" => ['required', 'string', 'max:40'],
            "{$p}contact.whatsapp.*.number" => $phone,
            "{$p}contact.hours" => ['nullable', 'string', 'max:120'],

            "{$p}social" => ['required', 'array'],
            "{$p}social.linkedin" => $link,
            "{$p}social.x" => $link,
            "{$p}social.youtube" => $link,

            "{$p}leadership" => ['required', 'array'],
            "{$p}leadership.show" => ['required', 'boolean'],
            "{$p}leadership.people" => ['present', 'array', 'max:' . self::LISTS['leadership.people'][1]],
            "{$p}leadership.people.*.name" => ['required', 'string', 'max:80'],
            "{$p}leadership.people.*.title" => ['required', 'string', 'max:80'],
            "{$p}leadership.people.*.bio_en" => ['nullable', 'string', 'max:400'],
            "{$p}leadership.people.*.bio_fr" => ['nullable', 'string', 'max:400'],
            "{$p}leadership.people.*.linkedin" => $link,
            "{$p}leadership.people.*.photo_url" => $link,
        ];
    }

    /** Field names as people read them in messages (:position = the row number). */
    public static function attributes(string $prefix = ''): array
    {
        $p = $prefix;

        return [
            "{$p}company.legal_name" => 'legal name',
            "{$p}company.trading_name" => 'trading name',
            "{$p}company.founded" => 'year founded',
            "{$p}company.registrations" => 'registrations',
            "{$p}company.registrations.*.country" => 'country of registration :position',
            "{$p}company.registrations.*.label" => 'registry of registration :position',
            "{$p}company.registrations.*.number" => 'registration number :position',
            "{$p}company.registrations.*.status" => 'status of registration :position',
            "{$p}company.offices" => 'offices',
            "{$p}company.offices.*.city" => 'city of office :position',
            "{$p}company.offices.*.country" => 'country of office :position',
            "{$p}company.offices.*.address" => 'address of office :position',
            "{$p}contact.email" => 'general email',
            "{$p}contact.phones" => 'phone numbers',
            "{$p}contact.phones.*.label" => 'label of phone number :position',
            "{$p}contact.phones.*.number" => 'phone number :position',
            "{$p}contact.whatsapp" => 'WhatsApp numbers',
            "{$p}contact.whatsapp.*.label" => 'label of WhatsApp number :position',
            "{$p}contact.whatsapp.*.number" => 'WhatsApp number :position',
            "{$p}contact.hours" => 'opening hours',
            "{$p}social.linkedin" => 'LinkedIn link',
            "{$p}social.x" => 'X link',
            "{$p}social.youtube" => 'YouTube link',
            "{$p}leadership.show" => 'show leadership on the website',
            "{$p}leadership.people" => 'people',
            "{$p}leadership.people.*.name" => 'name of person :position',
            "{$p}leadership.people.*.title" => 'job title of person :position',
            "{$p}leadership.people.*.bio_en" => 'English bio of person :position',
            "{$p}leadership.people.*.bio_fr" => 'French bio of person :position',
            "{$p}leadership.people.*.linkedin" => 'LinkedIn link of person :position',
            "{$p}leadership.people.*.photo_url" => 'photo link of person :position',
        ];
    }

    /** Calm messages that say how to fix the field (docs/BRAND.md section 4). */
    public static function messages(string $prefix = ''): array
    {
        $p = $prefix;
        $out = [
            "{$p}company.registrations.*.number.required_if" => 'Add the :attribute, or set its status to ‘Registration in progress’.',
            "{$p}company.founded.integer" => 'Enter the year founded as 4 digits, for example 2024.',
            "{$p}company.founded.min" => 'Enter the year founded as 4 digits, for example 2024.',
            "{$p}company.founded.max" => 'The year founded can’t be later than :max. Check the year and try again.',
            'required' => 'Add the :attribute.',
            'max' => 'Shorten the :attribute to :max characters or fewer.',
            'email' => 'Enter the :attribute as a full email address, for example info@jbspraxis.com.',
            'url' => 'Enter the :attribute as a full link that starts with https://.',
            'regex' => 'Enter the :attribute with digits and spaces only, starting with the country code, for example +234 800 000 0000.',
            'in' => 'Choose the :attribute from the list.',
            'string' => 'Enter the :attribute as text.',
            'boolean' => 'Choose whether to :attribute.',
            'present' => 'The :attribute list is missing. Reload the page and try again.',
            'array' => 'The :attribute list is missing. Reload the page and try again.',
        ];
        foreach (self::LISTS as $path => [, $max, $noun]) {
            $out = ["{$p}{$path}.max" => "Add up to {$max} {$noun}s. Remove one to add another."] + $out;
        }

        return $out;
    }

    /**
     * Form input in the stored shape: every section and field (missing ones from the defaults), then normalise().
     * The screen runs this before it validates, so what it checks is exactly what set() will save.
     */
    public static function shape(array $input): array
    {
        return self::normalise(self::overlay(self::defaults(), $input));
    }

    /**
     * Text trimmed, empty text as null, the year as a number, the leadership switch as true/false, list rows
     * with exactly the row's fields, and empty list rows removed. Expects the full shape (see overlay()).
     */
    public static function normalise(array $values): array
    {
        foreach ($values as $section => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            foreach ($fields as $field => $value) {
                $path = "{$section}.{$field}";
                if (isset(self::LISTS[$path])) {
                    $rows = [];
                    foreach (is_array($value) ? $value : [] as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $clean = [];
                        foreach (self::LISTS[$path][0] as $key => $empty) {
                            $clean[$key] = self::text($row[$key] ?? $empty);
                            if (in_array($key, self::CHOICE_FIELDS, true) && is_string($clean[$key])) {
                                $clean[$key] = $key === 'country' ? strtoupper($clean[$key]) : strtolower($clean[$key]);
                            }
                        }
                        $filled = array_filter(array_diff_key($clean, array_flip(self::CHOICE_FIELDS)), fn ($v) => $v !== null);
                        if ($filled) {
                            $rows[] = $clean;
                        }
                    }
                    $values[$section][$field] = $rows;
                } elseif ($path === 'company.founded') {
                    $text = self::text($value);
                    $values[$section][$field] = is_string($text) && ctype_digit($text) ? (int) $text : $text;
                } elseif ($path === 'leadership.show') {
                    $values[$section][$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } else {
                    $values[$section][$field] = self::text($value);
                }
            }
        }

        return $values;
    }

    /** An empty row for one of the lists (the screen's "Add" buttons). */
    public static function emptyRow(string $list): array
    {
        return self::LISTS[$list][0];
    }

    /** Clear the cached settings (after a save, or a change made outside set()). */
    public static function forget(): void
    {
        Cache::forget(self::cacheKey());
    }

    /** Read the table now (not the cache), refresh the cache and return ['values' => ..., 'updated_at' => ...]. */
    public static function load(): array
    {
        try {
            $rows = DB::table('website_settings')->get(['key', 'value', 'updated_at']);
        } catch (QueryException $e) {
            // The table is missing until the migration runs: the defaults still give the site a working footer
            report($e);

            return ['values' => self::defaults(), 'updated_at' => null];
        }

        $stored = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->value, true);
            if (is_array($decoded)) {
                $stored[$row->key] = $decoded;
            }
        }
        $latest = $rows->max('updated_at');

        $state = [
            'values' => self::normalise(self::overlay(self::defaults(), $stored)),
            'updated_at' => $latest ? Carbon::parse($latest, config('app.timezone'))->toIso8601String() : null,
        ];
        Cache::put(self::cacheKey(), $state, self::CACHE_SECONDS);

        return $state;
    }

    private static function state(): array
    {
        $cached = Cache::get(self::cacheKey());

        return is_array($cached) && isset($cached['values']) ? $cached : self::load();
    }

    /** Copy $input over $base, field by field, keeping only the sections and fields $base has. */
    private static function overlay(array $base, array $input): array
    {
        foreach ($base as $section => $fields) {
            if (! isset($input[$section]) || ! is_array($input[$section])) {
                continue;
            }
            foreach ($fields as $field => $value) {
                if (array_key_exists($field, $input[$section])) {
                    $base[$section][$field] = $input[$section][$field];
                }
            }
        }

        return $base;
    }

    private static function text($value)
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : $value;
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Per database, so local copies that share one cache folder never see each other's settings. */
    private static function cacheKey(): string
    {
        return 'website_settings:' . DB::connection()->getDatabaseName();
    }
}
