<?php

namespace App\Support\Opportunities;

use App\Models\Meeting;
use App\Models\Opportunity;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Times a client can book a follow-up with the opportunity's lead in the portal (docs/OPPORTUNITIES.md 6.1,
 * decision 12): the lead's working hours (calendar_availability, else weekdays 9:00–17:00 WAT) from tomorrow for
 * two weeks, starting every half hour, minus busy times in the company calendar and in the lead's own (when it is
 * shared with praxisjbs@gmail.com), HQ's meetings and the website's free audit bookings. Null when Google can't be
 * asked: the portal then offers "propose times" only.
 */
final class FreeTimes
{
    public const MINUTES = 60;

    public const STEP_MINUTES = 30;

    /** Calendar days looked at, from tomorrow (Lagos time) */
    public const DAYS = 14;

    public const DEFAULT_HOURS = [['09:00', '17:00']];

    public const DEFAULT_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /** @return array<string, list<CarbonImmutable>>|null free starts in UTC, by Lagos date (Y-m-d) */
    public static function for(Opportunity $opportunity, int $minutes = self::MINUTES, ?CarbonInterface $now = null): ?array
    {
        $google = app(GoogleWorkspace::class);
        if (! $google->isConnected()) {
            return null;
        }
        $tz = Meetings::TIME_ZONE;
        $now = CarbonImmutable::instance($now ?? now())->utc();
        $from = $now->setTimezone($tz)->addDay()->startOfDay();
        $to = $from->addDays(self::DAYS);

        $lead = $opportunity->lead_user_id ? DB::table('users')->where('id', $opportunity->lead_user_id)->first(['id', 'email']) : null;
        $calendars = array_values(array_filter(['primary', $lead?->email]));
        try {
            $busy = collect($google->freeBusy($calendars, $from, $to))->filter()->flatten(1)
                ->map(fn ($b) => [CarbonImmutable::instance($b['start'])->utc(), CarbonImmutable::instance($b['end'])->utc()])->all();
        } catch (GoogleUnavailable) {
            return null;
        }
        $busy = [...$busy, ...self::busyInHq($from, $to)];

        $hours = self::workingHours($lead?->id);
        $out = [];
        for ($day = $from; $day->lessThan($to); $day = $day->addDay()) {
            foreach ($hours[$day->format('l')] ?? [] as [$open, $close]) {
                $start = $day->setTimeFromTimeString($open);
                $end = $day->setTimeFromTimeString($close);
                for ($s = $start; $s->addMinutes($minutes)->lessThanOrEqualTo($end); $s = $s->addMinutes(self::STEP_MINUTES)) {
                    $slotStart = $s->utc();
                    $slotEnd = $slotStart->addMinutes($minutes);
                    $clash = collect($busy)->contains(fn ($b) => $slotStart->lessThan($b[1]) && $slotEnd->greaterThan($b[0]));
                    if (! $clash) {
                        $out[$day->format('Y-m-d')][] = $slotStart;
                    }
                }
            }
        }

        return $out;
    }

    /** Whether $start (any time zone) is one of the free times right now */
    public static function isFree(Opportunity $opportunity, CarbonInterface $start, int $minutes = self::MINUTES): bool
    {
        $start = CarbonImmutable::instance($start)->utc();
        $free = self::for($opportunity, $minutes) ?? [];

        return collect($free)->flatten()->contains(fn ($s) => $s->equalTo($start));
    }

    /** day name => [[open, close], …] in Lagos time */
    private static function workingHours(?string $userId): array
    {
        $rows = $userId ? DB::table('calendar_availability')->where('user_id', $userId)->where('is_active', 1)->orderBy('start_time')->get() : collect();
        if ($rows->isEmpty()) {
            return array_fill_keys(self::DEFAULT_DAYS, self::DEFAULT_HOURS);
        }
        $hours = [];
        foreach ($rows as $row) {
            $hours[$row->day_of_week][] = [substr($row->start_time, 0, 5), substr($row->end_time, 0, 5)];
        }

        return $hours;
    }

    /** HQ's own meetings (requested ones aren't in Google yet) and the website's bookings, as UTC periods */
    private static function busyInHq(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $meetings = Meeting::whereIn('status', ['requested', 'scheduled'])->where('starts_at', '<', $to->utc())->where('ends_at', '>', $from->utc())
            ->get(['starts_at', 'ends_at'])->map(fn ($m) => [CarbonImmutable::instance($m->starts_at)->utc(), CarbonImmutable::instance($m->ends_at)->utc()]);
        $tz = Meetings::TIME_ZONE;
        $bookings = DB::table('appointments')->whereIn('status', ['Requested', 'Scheduled'])
            ->whereBetween('start_time', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])->get(['start_time', 'end_time'])
            ->map(function ($a) use ($tz) {
                $start = CarbonImmutable::parse($a->start_time, $tz);
                $end = $a->end_time ? CarbonImmutable::parse($a->end_time, $tz) : $start->addHour();

                return [$start->utc(), ($end->greaterThan($start) ? $end : $start->addHour())->utc()];
            });

        return [...$meetings->all(), ...$bookings->all()];
    }
}
