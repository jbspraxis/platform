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
            'previous' => $month->gt($thisMonth) ? $month->subMonth()->format('Y-m') : null,
            'next' => $month->lt($lastMonth) ? $month->addMonth()->format('Y-m') : null,
            'weeks' => $weeks,
            'hasFreeDays' => $free !== [],
            'selected' => $selected,
            'selectedLabel' => $selected !== '' ? Format::date($selected . 'T12:00:00', $tz) : null,
            'times' => $free[$selected] ?? [],
        ]);
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
