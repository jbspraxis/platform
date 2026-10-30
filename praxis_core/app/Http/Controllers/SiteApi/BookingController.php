<?php

namespace App\Http\Controllers\SiteApi;

use App\Services\Site\AlreadyBooked;
use App\Services\Site\BookingService;
use App\Services\Site\SiteText;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** GET /booking/slots and POST /bookings: the free audit calendar (ScheduleConsultation on the old site) */
class BookingController extends SiteApiController
{
    public function slots(Request $request, BookingService $bookings)
    {
        $in = $this->input($request, ['month' => 'nullable|date_format:Y-m'], ['month']);
        $month = filled($in['month'] ?? null)
            ? Carbon::createFromFormat('!Y-m', $in['month'], BookingService::TIMEZONE)
            : Carbon::now(BookingService::TIMEZONE)->startOfMonth();

        return $this->data([
            'month' => $month->format('Y-m'),
            'days' => $bookings->month($month->year, $month->month),
            'timezone' => BookingService::TIMEZONE,
        ]);
    }

    public function store(Request $request, BookingService $bookings)
    {
        $in = $this->input($request, BookingService::rules(), array_values(BookingService::FIELDS));

        // A weekday from today up to 92 days ahead (Lagos time)
        if (!$bookings->isBookableDate($in['date'])) {
            throw ValidationException::withMessages(['date' => [SiteText::get('date_unavailable')]]);
        }

        // Limits in the service (429 too_many); the emails go after the response
        try {
            $booking = $bookings->book($in, $this->visitorIp($request), afterResponse: true);
        } catch (AlreadyBooked $e) {
            return $this->error(409, ['code' => 'already_booked', 'message' => SiteText::get('already_booked')]);
        }
        if (!$booking) {
            return $this->error(409, ['code' => 'slot_taken', 'message' => SiteText::get('slot_taken')]);
        }

        // Stored as Lagos wall-clock time: give it the Lagos offset
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $booking['start']->format('Y-m-d H:i:s'), BookingService::TIMEZONE);

        return $this->data(['start' => $start->toIso8601String(), 'focus' => $booking['focus']], 201);
    }
}
