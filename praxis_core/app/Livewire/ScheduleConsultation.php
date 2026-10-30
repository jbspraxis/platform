<?php

namespace App\Livewire;

use App\Services\Site\AlreadyBooked;
use App\Services\Site\BookingService;
use App\Services\Site\LimitReached;
use App\Services\Site\Fields;
use Livewire\Component;
use Carbon\Carbon;

class ScheduleConsultation extends Component
{
    // Slots are shown and booked in Lagos time (WAT); the app itself runs in UTC.
    // Slots, booking rules and the booking itself: App\Services\Site\BookingService (also used by the site API).
    private const TIMEZONE = BookingService::TIMEZONE;

    // Calendar State
    public $currentYear;
    public $currentMonth;
    public $daysInMonth = [];
    public $selectedDate = null;
    public $availableSlots = [];
    public $selectedTime = null;

    // Form State
    public $fullName;
    public $title;
    public $email;
    public $phone;
    public $enterpriseName;
    public $focus;
    public $customFocus;

    // UI State
    public $isSuccess = false;

    public function mount()
    {
        $now = Carbon::now(self::TIMEZONE);
        $this->currentYear = $now->year;
        $this->currentMonth = $now->month;
        $this->generateCalendar();
    }

    public function generateCalendar()
    {
        $this->daysInMonth = [];
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1, self::TIMEZONE);
        $daysInMonthCount = $date->daysInMonth;
        $firstDayOfWeek = $date->dayOfWeek; // 0 (Sun) to 6 (Sat)

        // Pad the beginning of the grid with empty slots
        for ($i = 0; $i < $firstDayOfWeek; $i++) {
            $this->daysInMonth[] = ['day' => null, 'is_past' => true];
        }

        // Populate actual days: past days, weekends and days more than 92 days ahead can't be booked
        $bookings = app(BookingService::class);
        for ($i = 1; $i <= $daysInMonthCount; $i++) {
            $currentLoopDate = Carbon::createFromDate($this->currentYear, $this->currentMonth, $i, self::TIMEZONE);

            $this->daysInMonth[] = [
                'day' => $i,
                'full_date' => $currentLoopDate->format('Y-m-d'),
                'is_past' => !$bookings->isBookableDate($currentLoopDate->format('Y-m-d')),
            ];
        }
    }

    public function changeMonth($step)
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1, self::TIMEZONE)->addMonths((int) $step);
        $this->currentYear = $date->year;
        $this->currentMonth = $date->month;
        $this->selectedDate = null; // Reset selection
        $this->availableSlots = [];
        $this->generateCalendar();
    }

    public function selectDate($dateString)
    {
        $this->selectedDate = $dateString;
        $this->selectedTime = null;
        $this->loadTimeSlots($dateString);
    }

    public function loadTimeSlots($dateString)
    {
        try {
            $this->availableSlots = app(BookingService::class)->freeSlots($dateString);
        } catch (\Throwable $e) {
            $this->availableSlots = [];
        }
    }

    public function selectTime($time)
    {
        $this->selectedTime = $time;
    }

    public function processBooking()
    {
        $this->validate(Fields::rulesFor(BookingService::rules(), BookingService::FIELDS));

        // Saves the request and sends the confirmation and the team alert; null when the time was just taken.
        // The limits (per visitor, everyone, one open request per email and organisation) are the service's.
        try {
            $booking = app(BookingService::class)->book(Fields::fromComponent($this, BookingService::FIELDS), request()->ip());
        } catch (LimitReached $e) {
            $this->addError('selectedTime', 'Too many booking requests from this connection. Please try again later.');
            return;
        } catch (AlreadyBooked $e) {
            $this->addError('selectedTime', 'You already have a free audit request waiting for confirmation. To change it, reply to our confirmation email.');
            return;
        }

        if (!$booking) {
            $this->loadTimeSlots($this->selectedDate);
            $this->selectedTime = null;
            $this->addError('selectedTime', 'That time is no longer available. Please choose another slot.');
            return;
        }

        $this->isSuccess = true;
    }

    public function render()
    {
        return view('livewire.schedule-consultation')->layout('layouts.app');
    }
}
