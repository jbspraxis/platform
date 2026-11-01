<?php

namespace App\Support\Opportunities;

use App\Events\Opportunities\MeetingScheduled;
use App\Mail\MeetingInvitationMail;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Google\GoogleWorkspace;
use App\Support\Google\MeetingRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Client and internal meetings of an opportunity (docs/OPPORTUNITIES.md 2, 6.1). HQ creates every meeting in
 * praxisjbs@gmail.com's calendar with a Meet link and Google sends the invitations; clients and other guests also
 * get HQ's branded email in the opportunity's language. Internal meetings get the Google invite only and never
 * reach the client. Google is called first: when it fails (GoogleUnavailable, with a message fit for a toast)
 * nothing is saved. A meeting from a website booking keeps its appointment in step (time, link, cancellation).
 *
 *   Meetings::schedule($opportunity, $data)       create (status scheduled, Google event, invites)
 *   Meetings::request($opportunity, $data)        a client's proposed times from the portal (no Google event yet)
 *   Meetings::update($meeting, $data)             reschedule or change; a requested meeting gets its event here
 *   Meetings::cancel($meeting)                    Google tells the attendees; clients get the branded email
 *
 * $data: kind (client|internal), title, agenda, starts_at, ends_at (any time zone; stored in UTC), staff (user
 * ids), contacts (the organisation's client_users ids; client meetings only), guests ([['email' => …, 'name' => …]]),
 * appointment_id (the website booking it comes from), sprint_id (a client meeting that hands over that sprint of the
 * opportunity's project: App\Support\Opportunities\Handovers checks it before scheduling).
 */
final class Meetings
{
    /** Meetings are shown and booked in West Africa Time; Douala is the same offset */
    public const TIME_ZONE = 'Africa/Lagos';

    public const KINDS = ['client', 'internal'];

    /** Minutes a meeting can last, as offered on the form */
    public const DURATIONS = [30, 45, 60, 90, 120];

    /** Transcripts are looked for from 20 minutes after the end (Google takes 15–20, section 6.1) */
    public const FIRST_TRY_AFTER_MINUTES = 20;

    /** Who gets the branded email: everyone invited who is not staff */
    public const EMAILED_ROLES = ['client', 'guest'];

    public static function schedule(Opportunity $opportunity, array $data, bool $brandedEmail = true): MeetingChange
    {
        $kind = self::kind($data);
        [$starts, $ends] = self::times($data);
        $attendees = self::attendees($opportunity, $kind, $data['staff'] ?? [], $data['contacts'] ?? [], $data['guests'] ?? []);
        $google = app(GoogleWorkspace::class);
        $event = $google->createMeeting(self::googleRequest($opportunity, $kind, $data, $starts, $ends, $attendees));

        try {
            $meeting = DB::transaction(function () use ($opportunity, $data, $kind, $starts, $ends, $attendees, $event) {
                $meeting = Meeting::create([
                    'opportunity_id' => $opportunity->id,
                    'sprint_id' => $kind === 'client' ? ($data['sprint_id'] ?? null) : null,
                    'organization_id' => $opportunity->organization_id,
                    'kind' => $kind,
                    'title' => self::title($data),
                    'agenda' => self::agenda($data),
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                    'time_zone' => self::TIME_ZONE,
                    'status' => 'scheduled',
                    'appointment_id' => $data['appointment_id'] ?? null,
                    'google_event_id' => $event->eventId,
                    'meet_link' => $event->meetLink,
                    'meet_code' => $event->meetCode,
                    'transcript_status' => 'waiting',
                    'transcript_next_try_at' => $ends->addMinutes(self::FIRST_TRY_AFTER_MINUTES),
                    'transcript_tries' => 0,
                    'created_by' => Auth::guard('web')->id(),
                ]);
                $meeting->attendees()->createMany($attendees);

                return $meeting;
            });
        } catch (Throwable $e) {
            // Don't leave an event in the calendar that HQ knows nothing about
            try {
                $google->cancelMeeting($event->eventId);
            } catch (Throwable) {
            }
            throw $e;
        }

        OpportunityAccess::log('MEETING_SCHEDULED', sprintf('Scheduled the %s meeting “%s” for %s WAT.', $kind, $meeting->title, self::when($meeting)),
            'opportunities', $opportunity->id, [], ['meeting_id' => $meeting->id, 'starts_at' => $meeting->starts_at->toIso8601String(), 'attendees' => count($attendees)]);
        MeetingScheduled::dispatch($meeting);
        $meeting->load('attendees', 'opportunity.organization');

        return new MeetingChange($meeting, $brandedEmail && $kind === 'client' ? self::emailClients($meeting, 'invite') : []);
    }

    /**
     * A client asked for a follow-up at times they proposed (portal): the meeting waits as requested, with the
     * times in its agenda, until the lead schedules it (update()) or declines it (cancel()).
     *
     * @param  list<CarbonInterface>  $proposed
     */
    public static function request(Opportunity $opportunity, array $data, array $proposed): Meeting
    {
        $first = CarbonImmutable::instance($proposed[0])->utc();
        $attendees = self::attendees($opportunity, 'client', $data['staff'] ?? [], $data['contacts'] ?? [], $data['guests'] ?? []);

        $meeting = DB::transaction(function () use ($opportunity, $data, $first, $attendees) {
            $meeting = Meeting::create([
                'opportunity_id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'kind' => 'client',
                'title' => self::title($data),
                'agenda' => self::agenda($data),
                'starts_at' => $first,
                'ends_at' => $first->addMinutes((int) ($data['minutes'] ?? 60)),
                'time_zone' => self::TIME_ZONE,
                'status' => 'requested',
                'transcript_status' => 'waiting',
                'created_by' => Auth::guard('web')->id(),
            ]);
            $meeting->attendees()->createMany($attendees);

            return $meeting;
        });

        OpportunityAccess::log('MEETING_REQUESTED', sprintf('The client asked for a follow-up meeting (“%s”), first choice %s WAT.', $meeting->title, self::when($meeting)),
            'opportunities', $opportunity->id, [], ['meeting_id' => $meeting->id]);

        return $meeting->load('attendees');
    }

    /** Move or change a meeting that hasn't started; a requested one is created in Google here */
    public static function update(Meeting $meeting, array $data): MeetingChange
    {
        if (! self::canChange($meeting)) {
            throw new LogicException('Only a requested or upcoming meeting can be changed');
        }
        $opportunity = $meeting->opportunity;
        $data['kind'] = $meeting->kind;
        [$starts, $ends] = self::times($data);
        $attendees = self::attendees($opportunity, $meeting->kind, $data['staff'] ?? [], $data['contacts'] ?? [], $data['guests'] ?? []);
        $request = self::googleRequest($opportunity, $meeting->kind, $data, $starts, $ends, $attendees);
        $google = app(GoogleWorkspace::class);
        $wasRequested = $meeting->status === 'requested' || ! $meeting->google_event_id;
        $event = $wasRequested ? $google->createMeeting($request) : $google->updateMeeting($meeting->google_event_id, $request);

        $known = $meeting->attendees->pluck('email')->map(fn ($e) => mb_strtolower($e))->all();
        $moved = ! $meeting->starts_at->equalTo($starts) || ! $meeting->ends_at->equalTo($ends);
        $old = ['starts_at' => $meeting->starts_at->toIso8601String(), 'ends_at' => $meeting->ends_at->toIso8601String(), 'status' => $meeting->status, 'title' => $meeting->title];

        DB::transaction(function () use ($meeting, $data, $starts, $ends, $attendees, $event) {
            $meeting->fill([
                'title' => self::title($data),
                'agenda' => self::agenda($data),
                'starts_at' => $starts,
                'ends_at' => $ends,
                'status' => 'scheduled',
                'google_event_id' => $event->eventId,
                'meet_link' => $event->meetLink ?? $meeting->meet_link,
                'meet_code' => $event->meetCode ?? $meeting->meet_code,
                'transcript_status' => 'waiting',
                'transcript_next_try_at' => $ends->addMinutes(self::FIRST_TRY_AFTER_MINUTES),
                'transcript_tries' => 0,
                'updated_by' => Auth::guard('web')->id(),
            ])->save();

            $emails = array_column($attendees, 'email');
            $meeting->attendees()->whereNotIn('email', $emails)->delete();
            foreach ($attendees as $a) {
                MeetingAttendee::updateOrCreate(['meeting_id' => $meeting->id, 'email' => $a['email']], $a);
            }
            self::syncAppointment($meeting);
        });
        $meeting->load('attendees', 'opportunity.organization');

        OpportunityAccess::log($wasRequested ? 'MEETING_CONFIRMED' : 'MEETING_RESCHEDULED',
            sprintf('%s the meeting “%s” for %s WAT.', $wasRequested ? 'Scheduled the requested' : 'Changed', $meeting->title, self::when($meeting)),
            'opportunities', $opportunity->id, $old, ['meeting_id' => $meeting->id, 'starts_at' => $meeting->starts_at->toIso8601String(), 'ends_at' => $meeting->ends_at->toIso8601String()]);
        if ($wasRequested) {
            MeetingScheduled::dispatch($meeting);
        }
        if ($meeting->kind !== 'client') {
            return new MeetingChange($meeting);
        }

        // New guests get the invitation; those already invited hear only that the time moved
        $emailed = $meeting->attendees->whereIn('role', self::EMAILED_ROLES)->pluck('email')->all();
        $new = $wasRequested ? $emailed : array_values(array_diff($emailed, $known));
        $unsent = $new ? self::emailClients($meeting, 'invite', $new) : [];
        if ($moved && ! $wasRequested && ($stay = array_values(array_intersect($emailed, $known)))) {
            $unsent = [...$unsent, ...self::emailClients($meeting, 'moved', $stay)];
        }

        return new MeetingChange($meeting, $unsent);
    }

    /** Cancel an upcoming meeting (Google tells everyone invited), or decline a client's request */
    public static function cancel(Meeting $meeting): MeetingChange
    {
        if (! self::canChange($meeting)) {
            throw new LogicException('Only a requested or upcoming meeting can be cancelled');
        }
        $wasScheduled = $meeting->status === 'scheduled';
        if ($meeting->google_event_id) {
            app(GoogleWorkspace::class)->cancelMeeting($meeting->google_event_id);
        }
        $meeting->forceFill(['status' => 'cancelled', 'transcript_next_try_at' => null, 'updated_by' => Auth::guard('web')->id()])->save();
        if ($meeting->appointment_id) {
            DB::table('appointments')->where('id', $meeting->appointment_id)->whereIn('status', ['Scheduled', 'Requested'])
                ->update(['status' => 'Cancelled', 'updated_by' => Auth::guard('web')->id() ?? 'system', 'updated_at' => now()]);
        }

        OpportunityAccess::log($wasScheduled ? 'MEETING_CANCELLED' : 'MEETING_DECLINED',
            sprintf('%s the meeting “%s” of %s WAT.', $wasScheduled ? 'Cancelled' : 'Declined the client’s request for', $meeting->title, self::when($meeting)),
            'opportunities', $meeting->opportunity_id, ['status' => $wasScheduled ? 'scheduled' : 'requested'], ['meeting_id' => $meeting->id, 'status' => 'cancelled']);
        $meeting->load('attendees', 'opportunity.organization');

        return new MeetingChange($meeting, $wasScheduled && $meeting->kind === 'client' ? self::emailClients($meeting, 'cancelled') : []);
    }

    /** A requested meeting, or a scheduled one that hasn't started */
    public static function canChange(Meeting $meeting): bool
    {
        return $meeting->status === 'requested' || ($meeting->status === 'scheduled' && $meeting->starts_at->isFuture());
    }

    /**
     * Staff, the organisation's own contacts (client meetings only) and any other email, once each (by email).
     *
     * @return list<array{user_id: ?string, client_user_id: ?string, email: string, name: ?string, role: string}>
     */
    public static function attendees(Opportunity $opportunity, string $kind, array $staff, array $contacts, array $guests): array
    {
        $out = [];
        $add = function (?string $email, array $row) use (&$out) {
            $email = mb_strtolower(trim((string) $email));
            if ($email !== '' && ! isset($out[$email])) {
                $out[$email] = array_merge(['user_id' => null, 'client_user_id' => null], $row, ['email' => $email]);
            }
        };
        $name = fn ($p) => trim($p->first_name . ' ' . $p->last_name) ?: null;

        if ($staff) {
            foreach (User::whereIn('id', array_values($staff))->where('status', 'Active')->whereNull('deleted_at')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']) as $u) {
                $add($u->email, ['user_id' => $u->id, 'name' => $name($u), 'role' => 'staff']);
            }
        }
        if ($kind === 'client' && $contacts) {
            $people = DB::table('client_users')->whereIn('id', array_values($contacts))->where('organization_id', $opportunity->organization_id)
                ->whereNull('deleted_at')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']);
            foreach ($people as $c) {
                $add($c->email, ['client_user_id' => $c->id, 'name' => $name($c), 'role' => 'client']);
            }
        }
        foreach ($guests as $g) {
            $email = is_array($g) ? ($g['email'] ?? '') : (string) $g;
            if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                $add($email, ['name' => is_array($g) && filled($g['name'] ?? null) ? Str::limit(trim($g['name']), 150, '') : null, 'role' => 'guest']);
            }
        }

        return array_values($out);
    }

    /**
     * The branded email to everyone invited who isn't staff ($only: just these addresses), in the opportunity's
     * language. Returns the addresses it could not send to.
     *
     * @param  'invite'|'moved'|'cancelled'  $variant
     */
    public static function emailClients(Meeting $meeting, string $variant, ?array $only = null): array
    {
        $meeting->loadMissing('attendees', 'opportunity.organization');
        $unsent = [];
        foreach ($meeting->attendees->whereIn('role', self::EMAILED_ROLES) as $a) {
            if ($only !== null && ! in_array($a->email, $only, true)) {
                continue;
            }
            try {
                Mail::to($a->email, $a->name)->locale($meeting->opportunity->language)
                    ->send(new MeetingInvitationMail($meeting, $variant, self::firstName($a)));
            } catch (Throwable $e) {
                Log::warning('Meetings: the branded email could not be sent', ['meeting' => $meeting->id, 'variant' => $variant, 'error' => $e->getMessage()]);
                $unsent[] = $a->email;
            }
        }

        return $unsent;
    }

    /** A bell notification (and so a phone push) for the opportunity's lead, opening its Meetings tab */
    public static function tellLead(Opportunity $opportunity, string $title, string $message, ?string $meetingId = null): void
    {
        $lead = $opportunity->lead_user_id ? User::where('id', $opportunity->lead_user_id)->where('status', 'Active')->first() : null;
        if (! $lead) {
            return;
        }
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $lead->id,
            'type' => 'System',
            'title' => Str::limit($title, 150, '…'),
            'message' => $message,
            'link_url' => route('hq.crm.opportunity', array_filter(['opportunity' => $opportunity->id, 'tab' => 'meetings', 'meeting' => $meetingId])),
            'is_read' => 0,
            'created_at' => now(),
        ]);
    }

    /** "Thursday 5 November 2026, 10:30" in WAT, for the audit log */
    public static function when(Meeting $meeting): string
    {
        return $meeting->starts_at->copy()->setTimezone($meeting->time_zone ?: self::TIME_ZONE)->format('l j F Y, H:i');
    }

    /** The Meet code in a pasted link (meet.google.com/abc-defg-hij), so route 1 of the collector can still look */
    public static function meetCodeFrom(?string $link): ?string
    {
        return preg_match('~meet\.google\.com/([a-z]{3}-[a-z]{4}-[a-z]{3})~i', (string) $link, $m) ? strtolower($m[1]) : null;
    }

    private static function googleRequest(Opportunity $opportunity, string $kind, array $data, CarbonImmutable $starts, CarbonImmutable $ends, array $attendees): MeetingRequest
    {
        $description = array_filter([
            self::agenda($data),
            // Every client invite carries the transcription notice (section 1, defaults)
            $kind === 'client' ? __('meetings.calendar.notice', [], $opportunity->language) : null,
        ]);

        return new MeetingRequest(
            self::title($data),
            $starts,
            $ends,
            array_map(fn ($a) => ['email' => $a['email'], 'name' => $a['name']], $attendees),
            $description ? implode("\n\n", $description) : null,
            self::TIME_ZONE,
        );
    }

    /** The website booking follows its meeting: new time (Lagos wall-clock, as it stores it) and link */
    private static function syncAppointment(Meeting $meeting): void
    {
        if (! $meeting->appointment_id) {
            return;
        }
        DB::table('appointments')->where('id', $meeting->appointment_id)->update([
            'start_time' => $meeting->starts_at->copy()->setTimezone(self::TIME_ZONE)->format('Y-m-d H:i:s'),
            'end_time' => $meeting->ends_at->copy()->setTimezone(self::TIME_ZONE)->format('Y-m-d H:i:s'),
            'meeting_link' => $meeting->meet_link,
            'updated_by' => Auth::guard('web')->id() ?? 'system',
            'updated_at' => now(),
        ]);
    }

    private static function kind(array $data): string
    {
        $kind = $data['kind'] ?? 'client';
        if (! in_array($kind, self::KINDS, true)) {
            throw new LogicException("Unknown meeting kind '{$kind}'");
        }

        return $kind;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} in UTC */
    private static function times(array $data): array
    {
        $starts = CarbonImmutable::instance($data['starts_at'])->utc();
        $ends = CarbonImmutable::instance($data['ends_at'])->utc();
        if (! $ends->greaterThan($starts)) {
            throw new LogicException('A meeting must end after it starts');
        }

        return [$starts, $ends];
    }

    private static function title(array $data): string
    {
        return Str::limit(trim((string) ($data['title'] ?? '')), 200, '');
    }

    private static function agenda(array $data): ?string
    {
        $agenda = trim((string) ($data['agenda'] ?? ''));

        return $agenda === '' ? null : Str::limit($agenda, 5000, '');
    }

    /** The first name the email greets: the contact's own record, else the first word of the name given */
    public static function firstName(MeetingAttendee $a): ?string
    {
        if ($a->client_user_id && ($first = DB::table('client_users')->where('id', $a->client_user_id)->value('first_name'))) {
            return $first;
        }

        return $a->name ? Str::before(trim($a->name), ' ') : null;
    }
}
