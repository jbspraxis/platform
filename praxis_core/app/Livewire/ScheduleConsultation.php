<?php

namespace App\Livewire;

use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ScheduleConsultation extends Component
{
    // Slots are shown and booked in Lagos time (WAT); the app itself runs in UTC.
    private const TIMEZONE = 'Africa/Lagos';
    private const SLOTS = ['09:00', '10:30', '13:00', '14:30', '16:00'];

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

        // Populate actual days
        $today = Carbon::today(self::TIMEZONE);
        for ($i = 1; $i <= $daysInMonthCount; $i++) {
            $currentLoopDate = Carbon::createFromDate($this->currentYear, $this->currentMonth, $i, self::TIMEZONE);

            // Disable past dates and weekends (Saturday=6, Sunday=0)
            $isPast = $currentLoopDate->startOfDay()->lt($today);
            $isWeekend = $currentLoopDate->isWeekend();

            $this->daysInMonth[] = [
                'day' => $i,
                'full_date' => $currentLoopDate->format('Y-m-d'),
                'is_past' => $isPast || $isWeekend,
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

    // Free slots for a date: not booked, and for today only times still ahead (Lagos time)
    private function freeSlots($dateString): array
    {
        if (!$this->isBookableDate($dateString)) {
            return [];
        }

        // Block slots that are already Scheduled OR Requested (Pending in Lobby)
        $bookedTimes = DB::table('appointments')
            ->whereDate('start_time', $dateString)
            ->whereIn('status', ['Scheduled', 'Requested'])
            ->pluck('start_time')
            ->map(fn ($datetime) => Carbon::parse($datetime)->format('H:i'))
            ->toArray();

        $now = Carbon::now(self::TIMEZONE);
        $isToday = $dateString === $now->format('Y-m-d');

        return array_values(array_filter(self::SLOTS, function ($slot) use ($bookedTimes, $isToday, $now) {
            return !in_array($slot, $bookedTimes, true) && (!$isToday || $slot > $now->format('H:i'));
        }));
    }

    private function isBookableDate($dateString): bool
    {
        $date = Carbon::createFromFormat('Y-m-d', (string) $dateString, self::TIMEZONE);
        return $date && $date->format('Y-m-d') === $dateString
            && !$date->startOfDay()->lt(Carbon::today(self::TIMEZONE))
            && !$date->isWeekend();
    }

    public function loadTimeSlots($dateString)
    {
        try {
            $this->availableSlots = $this->freeSlots($dateString);
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
        $this->validate([
            'fullName' => 'required|string|max:100',
            'title' => 'nullable|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:30',
            'enterpriseName' => 'required|string|max:150',
            'focus' => 'required|string|max:100',
            'customFocus' => 'required_if:focus,Other|max:40',
            'selectedDate' => 'required|date_format:Y-m-d',
            'selectedTime' => 'required|in:' . implode(',', self::SLOTS),
        ]);

        // 5 booking requests per IP per hour
        $rateKey = 'booking:' . request()->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('selectedTime', 'Too many booking requests from this connection. Please try again later.');
            return;
        }

        $finalFocus = $this->focus === 'Other' ? trim($this->customFocus) : $this->focus;
        $startDateTime = null;

        $booked = DB::transaction(function () use ($finalFocus, &$startDateTime) {
            // Re-check the slot on the server, locking that day's appointments
            DB::table('appointments')->whereDate('start_time', $this->selectedDate)->lockForUpdate()->get();
            if (!in_array($this->selectedTime, $this->freeSlots($this->selectedDate), true)) {
                return false;
            }

            $emailStr = strtolower(trim($this->email));
            $cleanOrgName = trim($this->enterpriseName);

            // 1A. Find or Create Organization (Case-Insensitive). Only merge into an existing
            // lead: a match on a client organisation gets its own lead record, flagged for review.
            $existingOrg = DB::table('organizations')->whereRaw('LOWER(name) = ?', [strtolower($cleanOrgName)])->first();

            if ($existingOrg && $existingOrg->status === 'Lead') {
                $orgId = $existingOrg->id;
                // Update timestamp and flag as suspect so command sees a new interaction
                DB::table('organizations')->where('id', $orgId)->update([
                    'updated_at' => now(),
                    'is_duplicate_suspect' => 1
                ]);
            } else {
                $orgId = (string) Str::uuid();
                DB::table('organizations')->insert([
                    'id' => $orgId,
                    'name' => $cleanOrgName,
                    'status' => 'Lead',
                    'pipeline_stage' => 'Lead Captured',
                    'is_duplicate_suspect' => $existingOrg ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 1B. Find or Create User
            $existingUser = DB::table('users')->where('email', $emailStr)->first();

            if (!$existingUser) {
                $userId = (string) Str::uuid();
                $nameParts = explode(' ', trim($this->fullName), 2);

                DB::table('users')->insert([
                    'id' => $userId,
                    'organization_id' => $orgId, // Attaches to either the new or existing Org
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? 'Unknown',
                    'email' => $emailStr,
                    'phone' => $this->phone,
                    'job_title' => $this->title,
                    'password' => Hash::make(Str::random(16)),
                    'status' => 'Active',
                    'lifecycle_stage' => 'Lead',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Combine Date and Time (stored as the Lagos wall-clock time shown to the visitor)
            $startDateTime = Carbon::parse($this->selectedDate . ' ' . $this->selectedTime);
            $endDateTime = $startDateTime->copy()->addMinutes(60); // 1 hour block

            // 3. Create the Appointment mapped straight to the Pending Lobby
            $appointmentId = (string) Str::uuid();
            DB::table('appointments')->insert([
                'id' => $appointmentId,
                'organization_id' => $orgId,
                'title' => 'Executive Consultation: ' . $finalFocus,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'status' => 'Requested',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 4. System Telemetry (Notify Admins)
            $admin = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')
                       ->where('roles.clearance_level', 5)->select('users.id')->first();
            $adminId = $admin ? $admin->id : ($existingUser ? $existingUser->id : (string) Str::uuid());

            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $adminId,
                'title' => 'NEW CONSULTATION REQUEST',
                'message' => strtoupper(trim($this->enterpriseName)) . " requested a meeting for " . $startDateTime->format('M d @ H:i') . ". Awaiting approval in the Lobby.",
                'link_url' => '/hq/intelligence/consultation',
                'type' => 'System',
                'is_read' => 0,
                'created_at' => now()
            ]);

            return true;
        });

        if (!$booked) {
            $this->loadTimeSlots($this->selectedDate);
            $this->selectedTime = null;
            $this->addError('selectedTime', 'That time is no longer available. Please choose another slot.');
            return;
        }

        RateLimiter::hit($rateKey, 3600);
        $this->sendBookingEmails($finalFocus, $startDateTime);
        $this->isSuccess = true;
    }

    // Confirmation to the visitor and an alert to the team; failures are logged (the request is saved)
    private function sendBookingEmails(string $focus, Carbon $start): void
    {
        $firstName = explode(' ', trim($this->fullName), 2)[0];
        $visitor = strtolower(trim($this->email));
        $when = $start->format('l j F Y') . ' at ' . $start->format('H:i') . ' (Lagos time, WAT)';

        try {
            Mail::raw(
                "Hello {$firstName},\n\n"
                . "Thank you for booking a free audit & demo with JBS Praxis.\n\n"
                . "Requested time: {$when}\nTopic: {$focus}\n\n"
                . "Our team will confirm the meeting and send you the call details by email shortly.\n\n"
                . "JBS Praxis\ninfo@jbspraxis.com\n" . url('/'),
                function ($m) use ($visitor) {
                    $m->to($visitor)->subject('Your audit & demo request | JBS Praxis');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Booking: confirmation email failed', ['message' => $e->getMessage()]);
        }

        $team = config('services.leads.alert_email');
        if (!$team) {
            return;
        }

        try {
            Mail::raw(
                "New audit & demo request from jbspraxis.com.\n\n"
                . "Name: {$this->fullName}\nJob title: {$this->title}\nEmail: {$visitor}\nPhone: {$this->phone}\n"
                . "Company: {$this->enterpriseName}\nTopic: {$focus}\nRequested: {$when}\n\n"
                . "Approve it in HQ: " . url('/hq/intelligence/consultation'),
                function ($m) use ($team, $visitor) {
                    $m->to($team)->replyTo($visitor)->subject('New demo request: ' . trim($this->enterpriseName));
                }
            );
        } catch (\Throwable $e) {
            Log::error('Booking: team alert email failed', ['message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.schedule-consultation')->layout('layouts.app');
    }
}
