<?php

namespace App\Http\Controllers;

use App\Exceptions\Hq\HqConflict;
use App\Http\Middleware\FormGuard;
use App\Support\Format;
use App\Support\HqApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * /schedule: book a free audit and demo. A month view from GET /booking/slots (Lagos time, WAT), pick a
 * day (a link, so it works without JavaScript), pick a time, then the form (POST /bookings).
 */
class ScheduleController extends Controller
{
    private const FIELDS = ['full_name', 'title', 'email', 'phone', 'enterprise_name', 'focus', 'custom_focus', 'date', 'time'];

    public function show(Request $request, HqApi $api)
    {
        ['month' => $month, 'free' => $free, 'selected' => $selected, 'previous' => $previous, 'next' => $next, 'tz' => $tz]
            = $this->month($request, $api);

        // Weeks start on Monday; empty cells pad the first and last week
        $weeks = [];
        $week = array_fill(0, $month->dayOfWeekIso - 1, null);
        for ($d = $month; $d->month === $month->month; $d = $d->addDay()) {
            $date = $d->format('Y-m-d');
            $week[] = [
                'date' => $date,
                'day' => $d->day,
                'label' => Format::date($date . 'T12:00:00', $tz),
                'slots' => count($free[$date] ?? []),
                'selected' => $date === $selected,
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }
        if ($week) {
            $weeks[] = array_pad($week, 7, null);
        }

        return view('pages.schedule', [
            'month' => $month,
            'monthLabel' => $month->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
            'previous' => $previous,
            'next' => $next,
            'weeks' => $weeks,
            'hasFreeDays' => $free !== [],
            'selected' => $selected,
            'selectedLabel' => $selected !== '' ? Format::date($selected . 'T12:00:00', $tz) : null,
            'times' => $free[$selected] ?? [],
        ]);
    }

    /**
     * GET /schedule/slots?month=YYYY-MM: the same free days this page reads, as JSON.
     *
     * The conversation (components/agent-dialog) draws its own calendar and lets people move between
     * months without leaving it, which the page does by reloading. Nothing is cached here and nothing new
     * is asked of HQ: it is the one read show() already makes, answered to the browser instead of a view.
     */
    public function slots(Request $request, HqApi $api)
    {
        ['month' => $month, 'free' => $free, 'previous' => $previous, 'next' => $next] = $this->month($request, $api);

        return response()->json([
            'month' => $month->format('Y-m'),
            'label' => $month->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
            'first_weekday' => $month->dayOfWeekIso,   // 1 is Monday, as the page's grid starts
            'days_in_month' => $month->daysInMonth,
            'previous' => $previous,
            'next' => $next,
            'days' => $free,
        ]);
    }

    /**
     * The month being looked at and the days in it that have free times, from GET /booking/slots.
     * Both the page and the JSON read it through here so they can never disagree about what is free.
     *
     * @return array{month: CarbonImmutable, free: array<string, list<string>>, selected: string, previous: ?string, next: ?string, tz: string}
     */
    private function month(Request $request, HqApi $api): array
    {
        $tz = (string) config('site.booking_timezone', 'Africa/Lagos');
        $thisMonth = CarbonImmutable::now($tz)->startOfMonth();
        // A booking can be at most 92 days ahead (docs/SITE_API.md 6.2): later days are never offered
        $lastDay = CarbonImmutable::now($tz)->startOfDay()->addDays((int) config('site.booking_days_ahead', 92));
        $lastMonth = $lastDay->startOfMonth();

        $month = $thisMonth;
        $asked = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $asked)) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $asked, $tz)->startOfMonth();
        }
        $selected = (string) ($request->query('date') ?: old('date', ''));
        if ($selected !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected) && ! $request->filled('month')) {
            $month = CarbonImmutable::createFromFormat('!Y-m-d', $selected, $tz)->startOfMonth();
        }
        if ($month->lt($thisMonth)) {
            $month = $thisMonth;
        }
        if ($month->gt($lastMonth)) {
            $month = $lastMonth;
        }

        $data = $api->bookingSlots($month->format('Y-m'));

        $free = [];
        foreach ((array) ($data['days'] ?? []) as $day) {
            $date = is_array($day) ? (string) ($day['date'] ?? '') : '';
            $slots = is_array($day) ? array_values(array_filter((array) ($day['slots'] ?? []), fn ($s) => is_string($s) && preg_match('/^\d{2}:\d{2}$/', $s))) : [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $slots && $date <= $lastDay->format('Y-m-d')) {
                $free[$date] = $slots;
            }
        }
        if (! isset($free[$selected])) {
            $selected = '';
        }

        return [
            'month' => $month,
            'free' => $free,
            'selected' => $selected,
            'previous' => $month->gt($thisMonth) ? $month->subMonth()->format('Y-m') : null,
            'next' => $month->lt($lastMonth) ? $month->addMonth()->format('Y-m') : null,
            'tz' => $tz,
        ];
    }

    public function store(Request $request, HqApi $api)
    {
        $fields = $this->fields($request, self::FIELDS);
        if (($fields['focus'] ?? null) !== 'Other') {
            unset($fields['custom_focus']);
        }

        try {
            $booking = $api->createBooking($fields);
        } catch (HqConflict $e) {
            if ($e->errorCode === 'slot_taken') {
                // The time went while the visitor was filling in the form
                return redirect()->to(url()->previous())
                    ->withInput($request->except([...FormGuard::NOT_KEPT, 'time']))
                    ->withErrors(['time' => __('site.schedule.slot_taken')]);
            }
            if ($e->errorCode === 'already_booked') {
                // This email or organisation already has an open request: HQ's calm message above the form
                return redirect()->to(url()->previous())
                    ->withInput($request->except(FormGuard::NOT_KEPT))
                    ->with('hq_error', $e->hqMessage ?: __('site.schedule.already_booked', ['email' => $api->contactEmail()]));
            }
            throw $e;
        }

        $start = is_string($booking['start'] ?? null) ? $booking['start'] : (($fields['date'] ?? '') . ' ' . ($fields['time'] ?? ''));

        return $this->sent('schedule', 'schedule', [
            'booking_when' => Format::bookingTime($start),
        ]);
    }
}
