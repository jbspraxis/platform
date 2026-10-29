<?php

namespace App\Livewire;

use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ScheduleConsultation extends Component
{
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
        $now = Carbon::now();
        $this->currentYear = $now->year;
        $this->currentMonth = $now->month;
        $this->generateCalendar();
    }

    public function generateCalendar()
    {
        $this->daysInMonth = [];
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $daysInMonthCount = $date->daysInMonth;
        $firstDayOfWeek = $date->dayOfWeek; // 0 (Sun) to 6 (Sat)

        // Pad the beginning of the grid with empty slots
        for ($i = 0; $i < $firstDayOfWeek; $i++) {
            $this->daysInMonth[] = ['day' => null, 'is_past' => true];
        }

        // Populate actual days
        $today = Carbon::today();
        for ($i = 1; $i <= $daysInMonthCount; $i++) {
            $currentLoopDate = Carbon::createFromDate($this->currentYear, $this->currentMonth, $i);
            
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
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonths($step);
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
        // Define standard operating hours
        $standardSlots = ['09:00', '10:30', '13:00', '14:30', '16:00'];
        
        // Query database to block slots that are already Scheduled OR Requested (Pending in Lobby)
        $bookedTimes = DB::table('appointments')
            ->whereDate('start_time', $dateString)
            ->whereIn('status', ['Scheduled', 'Requested'])
            ->pluck('start_time')
            ->map(function ($datetime) {
                return Carbon::parse($datetime)->format('H:i');
            })
            ->toArray();

        // Filter out booked slots
        $this->availableSlots = array_values(array_diff($standardSlots, $bookedTimes));
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
            'phone' => 'required|string|max:50',
            'enterpriseName' => 'required|string|max:150',
            'focus' => 'required|string',
            'customFocus' => 'required_if:focus,Other|max:40',
            'selectedDate' => 'required',
            'selectedTime' => 'required',
        ]);

        $finalFocus = $this->focus === 'Other' ? trim($this->customFocus) : $this->focus;

        DB::transaction(function () use ($finalFocus) {
            $emailStr = strtolower(trim($this->email));
            $cleanOrgName = trim($this->enterpriseName);
            
            // 1A. Find or Create Organization (Case-Insensitive)
            $existingOrg = DB::table('organizations')->whereRaw('LOWER(name) = ?', [strtolower($cleanOrgName)])->first();

            if ($existingOrg) {
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

            // 2. Combine Date and Time
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
        });

        $this->isSuccess = true;
    }

    public function render()
    {
        return view('livewire.schedule-consultation')->layout('layouts.app');
    }
}