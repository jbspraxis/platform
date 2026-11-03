<?php

namespace App\Services\Site;

use App\Mail\AuditRequestAlert;
use App\Mail\AuditRequested;
use App\Support\Access;
use App\Support\Leads\LeadMeetings;
use App\Support\Leads\WebsiteLeads;
use App\Support\MailLocale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Free audit bookings on the site API (GET /booking/slots, POST /bookings), as the old /schedule page
 * took them. Slots are shown and booked in Lagos time (WAT);
 * the app itself runs in UTC.
 * Rules (docs/SITE_API.md sections 6.2 and 6.4): weekdays from today up to 92 days ahead; one open
 * (Requested, still ahead) request per email and per organisation; 2 a day per visitor and 20 an
 * hour for everyone, on the old page's limiter key.
 */
final class BookingService
{
    public const TIMEZONE = 'Africa/Lagos';
    public const SLOTS = ['09:00', '10:30', '13:00', '14:30', '16:00'];

    /** The site API's fields */
    public const FIELDS = [
        'full_name', 'title', 'email', 'phone',
        'enterprise_name', 'focus', 'custom_focus',
        'date', 'time',
    ];

    public static function rules(): array
    {
        return [
            'full_name' => 'required|string|max:100',
            'title' => 'nullable|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:30',
            'enterprise_name' => 'required|string|max:150',
            'focus' => 'required|string|max:100',
            'custom_focus' => 'required_if:focus,Other|max:40',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|in:' . implode(',', self::SLOTS),
        ];
    }

    /** The last day that can be booked (Lagos time) */
    public static function lastBookableDay(): Carbon
    {
        return Carbon::today(self::TIMEZONE)->addDays((int) config('site_api.booking_days_ahead', 92));
    }

    /** Weekdays from today up to 92 days ahead (Lagos time) */
    public function isBookableDate($dateString): bool
    {
        $date = Carbon::createFromFormat('Y-m-d', (string) $dateString, self::TIMEZONE);
        return $date && $date->format('Y-m-d') === $dateString
            && !$date->startOfDay()->lt(Carbon::today(self::TIMEZONE))
            && !$date->startOfDay()->gt(self::lastBookableDay())
            && !$date->isWeekend();
    }

    /** Free slots for a date: not booked, and for today only times still ahead (Lagos time) */
    public function freeSlots($dateString): array
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

    /** Bookable days of a month with their free times (the site API's calendar) */
    public function month(int $year, int $month): array
    {
        $first = Carbon::createFromDate($year, $month, 1, self::TIMEZONE);
        $days = [];
        for ($d = 1; $d <= $first->daysInMonth; $d++) {
            $date = Carbon::createFromDate($year, $month, $d, self::TIMEZONE)->format('Y-m-d');
            if ($this->isBookableDate($date)) {
                $days[] = ['date' => $date, 'slots' => $this->freeSlots($date)];
            }
        }

        return $days;
    }

    /** True when this email or this organisation already has a request waiting for confirmation that is still ahead */
    public function hasOpenRequest(string $email, string $organisation): bool
    {
        $now = Carbon::now(self::TIMEZONE)->format('Y-m-d H:i:s'); // start_time holds Lagos wall-clock time
        $open = fn () => DB::table('appointments')->where('appointments.status', 'Requested')->where('appointments.start_time', '>=', $now);

        return $open()->join('client_users', 'client_users.id', '=', 'appointments.created_by')
                ->whereRaw('LOWER(client_users.email) = ?', [strtolower(trim($email))])->exists()
            || $open()->join('organizations', 'organizations.id', '=', 'appointments.organization_id')
                ->whereRaw('LOWER(organizations.name) = ?', [strtolower(trim($organisation))])->exists();
    }

    /**
     * Books a free audit (lead organisation, contact, requested appointment, HQ notification) and sends
     * the two emails. Returns the start (Lagos wall-clock) and the focus, or null when the time was taken
     * (or the day can't be booked).
     *
     * @param  string|null  $ip  the visitor's IP (limits)
     * @param  bool  $afterResponse  send the emails after the response (site API)
     * @return array{start: Carbon, focus: string}|null
     *
     * @throws LimitReached
     * @throws AlreadyBooked
     */
    public function book(array $in, ?string $ip = null, bool $afterResponse = false): ?array
    {
        $in = array_merge(array_fill_keys(array_values(self::FIELDS), null), $in);
        SiteLimits::check('booking', $ip);

        $finalFocus = $in['focus'] === 'Other' ? trim($in['custom_focus']) : $in['focus'];
        $startDateTime = null;

        $booked = DB::transaction(function () use ($in, $finalFocus, &$startDateTime) {
            // Re-check the slot on the server, locking that day's appointments
            DB::table('appointments')->whereDate('start_time', $in['date'])->lockForUpdate()->get();
            if (!in_array($in['time'], $this->freeSlots($in['date']), true)) {
                return false;
            }

            $emailStr = strtolower(trim($in['email']));
            $cleanOrgName = trim($in['enterprise_name']);

            // One open request per email and per organisation
            if ($this->hasOpenRequest($emailStr, $cleanOrgName)) {
                throw new AlreadyBooked();
            }

            // 1A. Find or Create Organization (Case-Insensitive). Only merge into an existing
            // lead: a match on a client organisation gets its own lead record, flagged for review.
            $existingOrg = DB::table('organizations')->whereRaw('LOWER(name) = ?', [strtolower($cleanOrgName)])->first();

            $isNew = ! ($existingOrg && $existingOrg->status === 'Lead');
            if (! $isNew) {
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
                    'preferred_language' => strtoupper(MailLocale::forVisitor()), // later emails from HQ follow the page language
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 1B. Find or create the contact (a lead: client account with no password)
            $existingUser = DB::table('client_users')->where('email', $emailStr)->first();

            if (!$existingUser) {
                $userId = (string) Str::uuid();
                $nameParts = Fields::nameParts($in['full_name']);

                DB::table('client_users')->insert([
                    'id' => $userId,
                    'organization_id' => $orgId, // Attaches to either the new or existing Org
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1],
                    'email' => $emailStr,
                    'phone' => $in['phone'],
                    'job_title' => $in['title'],
                    'status' => 'Active',
                    'lifecycle_stage' => 'Lead',
                    'source' => 'booking',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $bookerId = $existingUser->id ?? $userId;

            // 2. Combine Date and Time (stored as the Lagos wall-clock time shown to the visitor)
            $startDateTime = Carbon::parse($in['date'] . ' ' . $in['time']);
            $endDateTime = $startDateTime->copy()->addMinutes(60); // 1 hour block

            // 3. Create the Appointment mapped straight to the Pending Lobby
            $appointmentId = (string) Str::uuid();
            DB::table('appointments')->insert([
                'id' => $appointmentId,
                'organization_id' => $orgId,
                'title' => 'Free audit: ' . $finalFocus,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'status' => 'Requested',
                'created_by' => $bookerId, // the contact who booked (HQ-created appointments hold a staff id)
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // The lead's source, default owner (with a bell) and timeline (docs/LEADS.md); the booking waits on the lead's
            // Meetings tab as a requested lead meeting (not an opportunity) until Free audits approves or declines it
            WebsiteLeads::received($orgId, $isNew, 'website_booking', $bookerId,
                "Free audit requested on the website for {$startDateTime->format('D j M, H:i')} (WAT): {$finalFocus}.", null,
                ['appointment_id' => $appointmentId, 'focus' => $finalFocus, 'start' => $startDateTime->format('Y-m-d H:i')]);
            LeadMeetings::fromBooking($orgId, $appointmentId, $bookerId, 'Free audit: ' . $finalFocus, $startDateTime, $endDateTime);

            // 4. Notify the staff who handle leads
            // Notices are for staff; with no CRM staff account yet, skip it rather than fail the booking
            $notifications = [];
            foreach (Access::staffWith('crm.edit') as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminId,
                    'title' => 'New free audit request',
                    'message' => trim($in['enterprise_name']) . " asked for a free audit on " . $startDateTime->format('j M, H:i') . " (WAT). Approve or move it in Free audits.",
                    'link_url' => route('hq.crm.consult', [], false),
                    'type' => 'System',
                    'is_read' => 0,
                    'created_at' => now()
                ];
            }
            if (!empty($notifications)) DB::table('notifications')->insert($notifications);

            return true;
        });

        if (!$booked) {
            return null;
        }

        SiteLimits::hit('booking', $ip);
        $start = $startDateTime;
        Deferred::run($afterResponse, fn () => $this->sendEmails($in, $finalFocus, $start));

        return ['start' => $startDateTime, 'focus' => $finalFocus];
    }

    // Confirmation to the visitor and an alert to the team; failures are logged (the request is saved)
    private function sendEmails(array $in, string $focus, Carbon $start): void
    {
        $visitor = strtolower(trim($in['email']));

        // Of what the visitor typed, their copy carries only the first name: a topic that isn't one of the form's
        // own choices is shown as "the topic you described" (the team's copy has it in full)
        $listed = (array) __('emails.audit_request.topics', [], 'en');
        $visitorTopic = $in['focus'] !== 'Other' && array_key_exists((string) $in['focus'], $listed) ? $in['focus'] : 'Other';
        if (SiteLimits::allowVisitorEmail($visitor)) {
            try {
                // In the language of the page the visitor booked from
                Mail::to($visitor)
                    ->locale(MailLocale::forVisitor())
                    ->send(new AuditRequested(SiteLimits::firstName($in['full_name']), $visitorTopic, $start));
            } catch (\Throwable $e) {
                Log::error('Booking: confirmation email failed', ['message' => $e->getMessage()]);
            }
        }

        $team = config('services.leads.alert_email');
        if (!$team) {
            return;
        }

        try {
            // Team alert: English, replies go to the visitor
            Mail::to($team)
                ->locale('en')
                ->send(new AuditRequestAlert($in['full_name'], $in['title'], $visitor, $in['phone'], trim($in['enterprise_name']), $focus, $start));
        } catch (\Throwable $e) {
            Log::error('Booking: team alert email failed', ['message' => $e->getMessage()]);
        }
    }
}
