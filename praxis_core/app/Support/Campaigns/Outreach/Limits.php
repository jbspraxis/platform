<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The sending limits the owner edits (HQ › Campaigns › Outreach › Sending limits; owner's decision 2, 3 Nov 2026):
 * 15 emails a day per sender for that sender's first 2 weeks, then 30; weekdays 08:30–17:30 WAT only; at least 4
 * minutes between two emails of one sender; the follow-up about 3 working days after the first; a sender pauses by
 * themselves when more than 5% of that day's emails bounced. Follow-ups count toward the daily limit.
 *
 * Stored in outreach_settings (one row per key that differs from the default).
 */
final class Limits
{
    /** Lagos time is WAT (UTC+1, no summer time): the window and "today" are read in it */
    public const TIMEZONE = 'Africa/Lagos';

    public const DEFAULTS = [
        'first_weeks_limit' => 15,
        'first_weeks_days' => 14,
        'daily_limit' => 30,
        'window_start' => '08:30',
        'window_end' => '17:30',
        'gap_minutes' => 4,
        'follow_up_days' => 3,
        'bounce_pause_percent' => 5,
    ];

    public const LABELS = [
        'first_weeks_limit' => 'Emails a day in a sender’s first weeks',
        'first_weeks_days' => 'How long the first weeks last (days)',
        'daily_limit' => 'Emails a day after that',
        'window_start' => 'Send from (WAT)',
        'window_end' => 'Send until (WAT)',
        'gap_minutes' => 'Minutes between two emails of one sender',
        'follow_up_days' => 'Follow-up after (working days)',
        'bounce_pause_percent' => 'Pause a sender when more of the day’s emails bounce than (%)',
    ];

    public static function all(): array
    {
        // Read once per request or queue job (a scoped instance: Laravel drops it between jobs and tests)
        if (! app()->bound(self::class . '.values')) {
            app()->scoped(self::class . '.values', function () {
                $stored = DB::table('outreach_settings')->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all();
                $values = [];
                foreach (self::DEFAULTS as $key => $default) {
                    $values[$key] = isset($stored[$key]) ? (is_int($default) ? (int) $stored[$key] : (string) $stored[$key]) : $default;
                }

                return $values;
            });
        }

        return app(self::class . '.values');
    }

    public static function get(string $key): int|string
    {
        return self::all()[$key];
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::class . '.values');
    }

    public static function rules(): array
    {
        return [
            'first_weeks_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'first_weeks_days' => ['required', 'integer', 'min:0', 'max:90'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'window_start' => ['required', 'date_format:H:i'],
            'window_end' => ['required', 'date_format:H:i', 'after:window_start'],
            'gap_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'follow_up_days' => ['required', 'integer', 'min:1', 'max:15'],
            'bounce_pause_percent' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public static function messages(): array
    {
        return [
            '*.required' => 'Fill this in.',
            '*.integer' => 'Write a whole number.',
            '*.min' => 'Use :min or more.',
            '*.max' => 'Use :max or less.',
            '*.date_format' => 'Write a time like 08:30.',
            'window_end.after' => 'Choose a time after the start.',
        ];
    }

    /**
     * Saves the owner’s limits (validated with rules(); keys not given keep their value); returns the keys that changed.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function set(array $values, User $by): array
    {
        $values = array_intersect_key($values, self::DEFAULTS) + self::all();
        $clean = Validator::make($values, self::rules(), self::messages())->validate();
        $before = self::all();
        $changed = [];
        foreach ($clean as $key => $value) {
            $value = is_int(self::DEFAULTS[$key]) ? (int) $value : (string) $value;
            if ($before[$key] === $value) {
                continue;
            }
            DB::table('outreach_settings')->updateOrInsert(['key' => $key], ['value' => (string) $value, 'updated_by' => $by->id, 'updated_at' => now()]);
            $changed[] = $key;
        }
        self::flush();

        return $changed;
    }

    /** $at in Lagos time */
    public static function local(?CarbonInterface $at = null): Carbon
    {
        return Carbon::instance($at ?? now())->timezone(self::TIMEZONE);
    }

    /** Monday to Friday, between the window's start (included) and end (excluded), Lagos time */
    public static function inWindow(?CarbonInterface $at = null): bool
    {
        $local = self::local($at);
        if ($local->isWeekend()) {
            return false;
        }
        $time = $local->format('H:i');

        return $time >= self::get('window_start') && $time < self::get('window_end');
    }

    /** The first moment at or after $from when emails may go */
    public static function nextOpening(?CarbonInterface $from = null): Carbon
    {
        $local = self::local($from);
        for ($i = 0; $i < 8; $i++) {
            if (! $local->isWeekend()) {
                $start = $local->copy()->setTimeFromTimeString((string) self::get('window_start'));
                $end = $local->copy()->setTimeFromTimeString((string) self::get('window_end'));
                if ($local->lt($start)) {
                    return $start->utc();
                }
                if ($local->lt($end)) {
                    return $local->copy()->utc();
                }
            }
            $local = $local->copy()->addDay()->startOfDay();
        }

        return $local->utc();
    }

    /** The start of $at's day in Lagos, as a UTC time to compare with stored times */
    public static function dayStart(?CarbonInterface $at = null): Carbon
    {
        return self::local($at)->startOfDay()->utc();
    }

    /** $days working days (Monday to Friday) after $from, at the same time of day */
    public static function addWorkingDays(CarbonInterface $from, int $days): Carbon
    {
        $local = self::local($from);
        while ($days > 0) {
            $local->addDay();
            if (! $local->isWeekend()) {
                $days--;
            }
        }

        return $local->utc();
    }

    /** How many emails a sender may send in a day: fewer during their first weeks ($since: their first outreach email) */
    public static function dailyLimit(?CarbonInterface $since, ?CarbonInterface $at = null): int
    {
        $at ??= now();
        if ($since === null || Carbon::instance($since)->addDays((int) self::get('first_weeks_days'))->gt($at)) {
            return (int) self::get('first_weeks_limit');
        }

        return (int) self::get('daily_limit');
    }

    /** "weekdays 08:30–17:30 WAT" (it reads inside a sentence) */
    public static function windowText(): string
    {
        return 'weekdays ' . self::get('window_start') . '–' . self::get('window_end') . ' WAT';
    }
}
