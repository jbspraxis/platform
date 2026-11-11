<?php

namespace App\Support\Leads;

use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Support\Access;
use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\MailLocale;
use App\Support\Opportunities\MeetingChange;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\OpportunityContext;
use App\Support\Opportunities\Team;
use App\Support\Opportunities\Transcripts;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * A lead's meetings (docs/LEADS.md section 9, owner's decisions 11 and 13–16): meetings with the organisation before
 * any opportunity (meetings.opportunity_id null). They reuse the opportunity meeting code (Meetings, Transcripts, the
 * collector and the AI's notes) and add the lead's timeline lines (type meeting, with meeting_id).
 *
 *   LeadMeetings::schedule($lead, $data, $by)          a Google invite with a Meet link, or in person at an address
 *   LeadMeetings::logPast($lead, $data, $by)           a meeting that already happened: typed notes, a transcript if any
 *   LeadMeetings::update / cancel / saveTypedNotes     as on an opportunity, with the timeline
 *   LeadMeetings::fromBooking(...)                     a website free-audit booking becomes a requested lead meeting
 *   LeadMeetings::approveBooking / approveBookingWithLink / closeBooking   Free audits (LiveConsultation)
 *   LeadMeetings::suggestTitle($meeting)               the AI's title for the opportunity opened from it
 *   LeadMeetings::openOpportunity($meeting, $data, $by) the meeting, its notes and its people move to a new opportunity
 *
 * Who: crm.view sees them; crm.edit (canManage) schedules, logs and changes them; full transcripts need
 * canReadTranscripts. Client minutes are not sent from a lead meeting (no portal yet): once an opportunity is opened
 * from it, its Meetings tab drafts and sends them.
 */
final class LeadMeetings
{
    /** Minutes a meeting can last, as offered on the forms */
    public const DURATIONS = Meetings::DURATIONS;

    /** Longest typed notes */
    public const MAX_NOTES = 20000;

    /** The transcript text the AI reads to suggest a title (the start of the conversation names the work) */
    private const TITLE_MATERIAL_CHARS = 40000;

    public static function canManage(?User $user): bool
    {
        return LeadAccess::canEdit($user);
    }

    /** Full transcripts (personal data): those who read opportunities' transcripts, or the lead's own owner */
    public static function canReadTranscripts(?User $user, Organization $lead): bool
    {
        return Access::allows($user, 'opportunities.transcripts')
            || ($user !== null && $user->status === 'Active' && $lead->assigned_to === $user->id && LeadAccess::canEdit($user));
    }

    /** A transcript's columns but its text and lines (up to 500,000 characters each): what a list of meetings needs */
    public const TRANSCRIPT_LIST = MeetingTranscript::WITHOUT_TEXT;

    /**
     * The lead's meetings, by start: its own (no opportunity) and the client meetings of its opportunities, which are
     * shown here and managed on the opportunity. Cancelled ones of an opportunity are left out. Without $transcriptText,
     * the transcripts come without their text and lines (TRANSCRIPT_LIST).
     */
    public static function of(string $organizationId, bool $transcriptText = true): Collection
    {
        return Meeting::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereNull('opportunity_id')->orWhere(fn ($o) => $o->where('kind', 'client')->where('status', '!=', 'cancelled')))
            ->with(['attendees' => fn ($q) => $q->orderByRaw("FIELD(role, 'staff', 'client', 'guest')")->orderBy('name'),
                'transcripts' => fn ($q) => $transcriptText ? $q : $q->select(self::TRANSCRIPT_LIST), 'opportunity:id,title,stage,lead_user_id'])
            ->orderBy('starts_at')->get();
    }

    /** What the Meetings tab counts: the lead's own meetings and its opportunities' client meetings, not cancelled */
    public static function count(string $organizationId): int
    {
        return Meeting::where('organization_id', $organizationId)->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('opportunity_id')->orWhere('kind', 'client'))->count();
    }

    /** A lead meeting of this lead, else null (an opportunity's meeting is managed there) */
    public static function find(string $organizationId, string $meetingId): ?Meeting
    {
        return Meeting::where('organization_id', $organizationId)->whereNull('opportunity_id')->with('attendees')->find($meetingId);
    }

    // ---- Scheduling, logging, changing ------------------------------------------------------------------------

    /**
     * A meeting through Google, as on an opportunity: $data as Meetings::schedule (title, agenda, starts_at, ends_at,
     * staff, contacts, guests), plus location for one in person (the invite then has the address and no Meet link).
     * The contacts and guests get the branded invitation in the lead's language. Throws GoogleUnavailable.
     */
    public static function schedule(Organization $lead, array $data, User $by): MeetingChange
    {
        $change = Meetings::schedule($lead, ['kind' => 'client'] + $data);
        $m = $change->meeting;
        LeadTimeline::record($lead->id, 'meeting', sprintf('Meeting scheduled for %s (WAT)%s: %s', self::when($m), $m->location ? ', in person' : ' on Google Meet', $m->title), [
            'actor' => $by, 'meeting_id' => $m->id, 'client_user_id' => self::firstContact($m),
            'body' => $m->agenda, 'payload' => ['status' => 'scheduled', 'location' => $m->location],
        ]);

        return $change;
    }

    /**
     * A meeting that already happened, written in by hand: $data title, starts_at, ends_at (in the past), staff,
     * contacts, guests, location (an address) or meet_link (a call), typed_notes, and optionally transcript +
     * transcript_source (upload | paste), which the AI then writes the notes from. Nobody is emailed.
     */
    public static function logPast(Organization $lead, array $data, User $by): Meeting
    {
        $starts = CarbonImmutable::instance($data['starts_at'])->utc();
        $ends = CarbonImmutable::instance($data['ends_at'])->utc();
        if (! $ends->greaterThan($starts) || $starts->isFuture()) {
            throw new LogicException('A logged meeting started in the past and ends after it starts');
        }
        $attendees = Meetings::attendees($lead, 'client', $data['staff'] ?? [], $data['contacts'] ?? [], $data['guests'] ?? []);
        $link = self::link($data['meet_link'] ?? null);

        $meeting = DB::transaction(function () use ($lead, $data, $starts, $ends, $attendees, $link, $by) {
            $meeting = Meeting::create([
                'opportunity_id' => null,
                'organization_id' => $lead->id,
                'kind' => 'client',
                'title' => Str::limit(trim((string) $data['title']), 200, ''),
                'agenda' => null,
                'typed_notes' => self::notes($data['typed_notes'] ?? null),
                'starts_at' => $starts,
                'ends_at' => $ends,
                'time_zone' => Meetings::TIME_ZONE,
                'status' => 'held',
                'meet_link' => $link,
                'meet_code' => Meetings::meetCodeFrom($link),
                'location' => filled($data['location'] ?? null) ? Str::limit(trim($data['location']), 255, '') : null,
                // Held already: there is nothing to look for in Google, a transcript is added by hand
                'transcript_status' => 'manual',
                'transcript_tries' => 0,
                'created_by' => $by->id,
            ]);
            $meeting->attendees()->createMany($attendees);

            return $meeting;
        });

        Meetings::log($meeting, 'MEETING_LOGGED', sprintf('Logged the meeting “%s” held on %s WAT.', $meeting->title, Meetings::when($meeting)),
            [], ['meeting_id' => $meeting->id, 'attendees' => count($attendees)]);
        LeadTimeline::record($lead->id, 'meeting', sprintf('Meeting held on %s: %s', self::when($meeting), $meeting->title), [
            'actor' => $by, 'meeting_id' => $meeting->id, 'client_user_id' => self::firstContact($meeting->load('attendees')),
            'body' => $meeting->typed_notes, 'at' => $starts, 'payload' => ['status' => 'held', 'location' => $meeting->location],
        ]);
        if (filled($data['transcript'] ?? null)) {
            Transcripts::fromText($meeting, (string) $data['transcript'], ($data['transcript_source'] ?? 'paste') === 'upload' ? 'upload' : 'paste');
        }

        return $meeting->fresh(['attendees', 'transcripts']);
    }

    /** Move or change an upcoming lead meeting (Google tells everyone; new guests get the invitation) */
    public static function update(Meeting $meeting, array $data, User $by): MeetingChange
    {
        self::own($meeting);
        $before = $meeting->starts_at->copy();
        $change = Meetings::update($meeting, $data);
        $m = $change->meeting;
        if (! $before->equalTo($m->starts_at)) {
            LeadTimeline::record($m->organization_id, 'meeting', sprintf('Meeting moved to %s (WAT): %s', self::when($m), $m->title), [
                'actor' => $by, 'meeting_id' => $m->id, 'client_user_id' => self::firstContact($m), 'payload' => ['status' => 'moved'],
            ]);
        }

        return $change;
    }

    /** Cancel an upcoming lead meeting: Google tells the attendees, the contacts get the branded email */
    public static function cancel(Meeting $meeting, User $by): MeetingChange
    {
        self::own($meeting);
        $change = Meetings::cancel($meeting);
        LeadTimeline::record($meeting->organization_id, 'meeting', sprintf('Meeting of %s cancelled: %s', self::when($meeting), $meeting->title), [
            'actor' => $by, 'meeting_id' => $meeting->id, 'client_user_id' => self::firstContact($meeting), 'payload' => ['status' => 'cancelled'],
        ]);

        return $change;
    }

    /** The team's typed notes of a meeting (any meeting of the lead, before or after it is held). False when unchanged. */
    public static function saveTypedNotes(Meeting $meeting, ?string $text, User $by): bool
    {
        $text = self::notes($text);
        if ($text === $meeting->typed_notes) {
            return false;
        }
        $had = filled($meeting->typed_notes);
        $meeting->forceFill(['typed_notes' => $text, 'updated_by' => $by->id])->save();
        Meetings::log($meeting, 'MEETING_NOTES_TYPED', sprintf('%s the typed notes of “%s”.', $text === null ? 'Removed' : ($had ? 'Changed' : 'Added'), $meeting->title),
            [], ['meeting_id' => $meeting->id, 'characters' => mb_strlen((string) $text)]);

        return true;
    }

    // ---- Website free-audit bookings (owner's decision 11) ---------------------------------------------------------

    /**
     * BookingService, inside its transaction: the booking waits as a requested lead meeting (no Google event yet) with
     * the person who booked and the lead's owner, until Free audits approves it (approveBooking) or declines it.
     * $starts / $ends are Lagos wall-clock times, as the appointment stores them.
     */
    public static function fromBooking(string $organizationId, string $appointmentId, ?string $contactId, string $title, CarbonInterface $starts, CarbonInterface $ends): Meeting
    {
        $existing = Meeting::where('appointment_id', $appointmentId)->first();
        if ($existing) {
            return $existing;
        }
        $lead = Organization::findOrFail($organizationId);
        $owner = LeadOwner::activeStaff($lead->assigned_to);
        $starts = CarbonImmutable::parse($starts->format('Y-m-d H:i:s'), Meetings::TIME_ZONE)->utc();
        $ends = CarbonImmutable::parse($ends->format('Y-m-d H:i:s'), Meetings::TIME_ZONE)->utc();
        // The person who booked: a contact of this lead, or (an address already someone else's contact) a guest
        $booker = $contactId ? DB::table('client_users')->where('id', $contactId)->whereNull('deleted_at')->first(['organization_id', 'email', 'first_name', 'last_name']) : null;
        $ours = $booker && $booker->organization_id === $lead->id;
        $guests = $booker && ! $ours && $booker->email ? [['email' => $booker->email, 'name' => trim($booker->first_name . ' ' . $booker->last_name) ?: null]] : [];
        $attendees = Meetings::attendees($lead, 'client', $owner ? [$owner->id] : [], $ours ? [$contactId] : [], $guests);

        $meeting = Meeting::create([
            'opportunity_id' => null,
            'organization_id' => $lead->id,
            'kind' => 'client',
            'title' => Str::limit(trim($title) ?: 'Free audit', 200, ''),
            'starts_at' => $starts,
            'ends_at' => $ends->greaterThan($starts) ? $ends : $starts->addHour(),
            'time_zone' => Meetings::TIME_ZONE,
            'status' => 'requested',
            'appointment_id' => $appointmentId,
            'transcript_status' => 'waiting',
            'transcript_tries' => 0,
        ]);
        $meeting->attendees()->createMany($attendees);

        return $meeting;
    }

    /**
     * The booking's lead meeting: the one made when it was booked, or (a booking from before this release) one made
     * now from the appointment. Null for an appointment that isn't a lead's (no organisation).
     */
    public static function forAppointment(object $appointment): ?Meeting
    {
        $meeting = Meeting::where('appointment_id', $appointment->id)->with('attendees')->first();
        if ($meeting || ! $appointment->organization_id || ! DB::table('organizations')->where('id', $appointment->organization_id)->exists()) {
            return $meeting;
        }
        $booker = $appointment->created_by && DB::table('client_users')->where('id', $appointment->created_by)
            ->where('organization_id', $appointment->organization_id)->whereNull('deleted_at')->exists() ? $appointment->created_by : null;
        $starts = CarbonImmutable::parse($appointment->start_time, Meetings::TIME_ZONE);
        $ends = $appointment->end_time ? CarbonImmutable::parse($appointment->end_time, Meetings::TIME_ZONE) : $starts->addHour();

        return self::fromBooking($appointment->organization_id, $appointment->id, $booker, (string) $appointment->title, $starts, $ends)->load('attendees');
    }

    /**
     * Free audits approves a booking with Google connected: the requested meeting gets its Google event and Meet link
     * (Google invites the client); Free audits sends its own branded email, so Meetings sends none. $staff joins the
     * people already on it; $guestEmail invites the client when the booker is no contact. Throws GoogleUnavailable.
     */
    public static function approveBooking(Meeting $meeting, array $staff, ?string $guestEmail = null): MeetingChange
    {
        $contacts = $meeting->attendees->where('role', 'client')->pluck('client_user_id')->filter()->values()->all();
        $guests = $meeting->attendees->where('role', 'guest')->map(fn ($a) => ['email' => $a->email, 'name' => $a->name])->values()->all();
        if (! $contacts && ! $guests && $guestEmail) {
            $guests = [['email' => $guestEmail, 'name' => null]];
        }
        $change = Meetings::update($meeting, [
            'title' => $meeting->title,
            'agenda' => $meeting->agenda,
            'starts_at' => $meeting->starts_at,
            'ends_at' => $meeting->ends_at,
            'staff' => array_values(array_unique([...$meeting->attendees->where('role', 'staff')->pluck('user_id')->filter()->all(), ...$staff])),
            'contacts' => $contacts,
            'guests' => $guests,
        ], brandedEmail: false);
        self::bookingLine($change->meeting, sprintf('Free audit scheduled for %s (WAT) on Google Meet.', self::when($change->meeting)), 'scheduled');

        return $change;
    }

    /** Free audits approves a booking without Google: the link pasted there becomes the meeting's (Meet's transcript can still be looked for) */
    public static function approveBookingWithLink(Meeting $meeting, string $link): Meeting
    {
        $code = Meetings::meetCodeFrom($link);
        $meeting->forceFill([
            'status' => 'scheduled',
            'meet_link' => self::link($link),
            'meet_code' => $code,
            'transcript_status' => $code ? 'waiting' : 'manual',
            'transcript_next_try_at' => $code ? $meeting->ends_at->copy()->addMinutes(Meetings::FIRST_TRY_AFTER_MINUTES) : null,
            'updated_by' => Auth::guard('web')->id(),
        ])->save();
        Meetings::log($meeting, 'MEETING_CONFIRMED', sprintf('Scheduled the requested meeting “%s” for %s WAT with a pasted link.', $meeting->title, Meetings::when($meeting)),
            ['status' => 'requested'], ['meeting_id' => $meeting->id, 'status' => 'scheduled']);
        self::bookingLine($meeting, sprintf('Free audit scheduled for %s (WAT).', self::when($meeting)), 'scheduled');

        return $meeting;
    }

    /**
     * A booking that won't happen: declined in Free audits ($why declined, with the team's $reason) or closed because
     * no one approved it in time (expired). Its requested meeting is cancelled; there is no Google event to remove yet.
     */
    public static function closeBooking(string $appointmentId, string $why, ?string $reason = null): void
    {
        $meeting = Meeting::where('appointment_id', $appointmentId)->where('status', 'requested')->first();
        if (! $meeting) {
            return;
        }
        $meeting->forceFill(['status' => 'cancelled', 'transcript_next_try_at' => null, 'updated_by' => Auth::guard('web')->id()])->save();
        self::bookingLine($meeting, $why === 'expired'
            ? 'Free audit request closed: no one approved it before its time.'
            : 'Free audit request declined in Free audits.', 'cancelled', $why === 'expired' ? null : $reason);
    }

    // ---- Open an opportunity from a meeting (owner's decision 15) -------------------------------------------------

    /** Whether an opportunity can be opened from it: a lead meeting that is scheduled or held */
    public static function canOpenOpportunity(Meeting $meeting): bool
    {
        return $meeting->isLeadMeeting() && in_array($meeting->status, ['scheduled', 'held'], true);
    }

    /** The title to start from: the meeting's own, without "Free audit:" and the like */
    public static function defaultTitle(Meeting $meeting): string
    {
        $title = trim((string) preg_replace('/^(free audit|discovery|introduction|meeting)\s*[:\-–]\s*/iu', '', (string) $meeting->title));

        return Str::limit($title !== '' ? Str::ucfirst($title) : (string) $meeting->title, 200, '');
    }

    /**
     * The AI's title for the opportunity, from the transcript (the latest) and the team's typed notes: purpose
     * opportunity_title, while the person waits. Null when there is nothing to read or the answer is unusable.
     * Throws AiUnavailable (no key, over the budget) before anything is spent.
     */
    public static function suggestTitle(Meeting $meeting): ?string
    {
        $meeting->loadMissing('attendees', 'organization', 'transcripts');
        $transcript = $meeting->transcripts->last();
        if (! $transcript && blank($meeting->typed_notes)) {
            return null;
        }
        $material = $transcript
            ? Str::limit(Transcripts::material($meeting, $transcript), self::TITLE_MATERIAL_CHARS, '…')
            : implode("\n", array_filter([
                sprintf('Meeting: %s (%s), %s WAT', $meeting->title, $meeting->kind, Meetings::when($meeting)),
                'Notes typed by the team: ' . trim((string) $meeting->typed_notes),
            ]));
        $run = Ai::now('opportunity_title', $meeting, [
            'context' => OpportunityContext::forLead($meeting->organization),
            'material' => $material,
            'language' => Meetings::language($meeting),
        ]);
        if (! $run->succeeded()) {
            throw new AiUnavailable(Str::after((string) $run->error, 'ai.errors.') ?: 'failed');
        }
        $title = trim((string) ($run->output['title'] ?? ''), " \t\n\r\0\x0B.\"“”");

        return $title === '' ? null : Str::limit($title, 200, '');
    }

    /**
     * Open an opportunity from a lead meeting: $data title, lead_user_id, language (en|fr). The meeting moves to it
     * (opportunity_id), with its transcripts, the AI's notes and the typed notes; the staff on the meeting join its
     * team; its contacts stay the lead's contacts, which the opportunity reads (opportunities have no contact list of
     * their own). Timeline opportunity_opened with opportunity_id and meeting_id.
     */
    public static function openOpportunity(Meeting $meeting, array $data, User $by): Opportunity
    {
        if (! self::canOpenOpportunity($meeting)) {
            throw new LogicException('An opportunity is opened from a lead meeting that is scheduled or held');
        }
        $meeting->loadMissing('attendees');
        $language = in_array($data['language'] ?? null, Opportunity::LANGUAGES, true) ? $data['language'] : Meetings::language($meeting);
        $staff = $meeting->attendees->where('role', 'staff')->pluck('user_id')->filter()->values()->all();
        $contacts = $meeting->attendees->where('role', 'client')->pluck('client_user_id')->filter()->values()->all();

        $opportunity = DB::transaction(function () use ($meeting, $data, $language, $by) {
            // Claimed under a lock: two people opening one from the same meeting at once make one opportunity
            $locked = Meeting::whereKey($meeting->id)->lockForUpdate()->first();
            if (! $locked || $locked->opportunity_id !== null) {
                throw new LogicException('This meeting is already part of an opportunity');
            }
            $opportunity = Opportunity::create([
                'organization_id' => $meeting->organization_id,
                'title' => Str::limit(trim((string) $data['title']), 200, ''),
                'stage' => 'discovery',
                'lead_user_id' => $data['lead_user_id'],
                'language' => $language,
                'source' => $meeting->appointment_id ? 'website_booking' : 'manual',
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);
            $locked->forceFill(['opportunity_id' => $opportunity->id, 'updated_by' => $by->id])->save();

            return $opportunity;
        });

        $org = (string) DB::table('organizations')->where('id', $meeting->organization_id)->value('name');
        OpportunityAccess::log('OPPORTUNITY_OPENED', "Opened the opportunity “{$opportunity->title}” for {$org} from the meeting “{$meeting->title}”.", 'opportunities', $opportunity->id,
            [], $opportunity->only(['organization_id', 'title', 'stage', 'lead_user_id', 'language', 'source']) + ['meeting_id' => $meeting->id]);
        Team::sync($opportunity, $staff, $by->id);
        LeadTimeline::record($meeting->organization_id, 'opportunity_opened', "Opportunity opened from the meeting “{$meeting->title}”: {$opportunity->title}", [
            'actor' => $by, 'opportunity_id' => $opportunity->id, 'meeting_id' => $meeting->id, 'client_user_id' => $contacts[0] ?? null,
            'payload' => ['contacts' => $contacts, 'team' => $staff],
        ]);

        return $opportunity;
    }

    // ---- Helpers ---------------------------------------------------------------------------------------------------

    /** "Thu 5 Nov, 10:30" in WAT (the timeline's lines) */
    public static function when(Meeting $meeting): string
    {
        $at = $meeting->starts_at->copy()->setTimezone($meeting->time_zone ?: Meetings::TIME_ZONE);

        return ($at->year === now(Meetings::TIME_ZONE)->year ? $at->format('D j M') : $at->format('j M Y')) . ', ' . $at->format('H:i');
    }

    /** The language of a lead's emails and documents: en or fr */
    public static function language(Organization $lead): string
    {
        return MailLocale::parse($lead->preferred_language) ?? 'en';
    }

    private static function own(Meeting $meeting): void
    {
        if (! $meeting->isLeadMeeting()) {
            throw new LogicException('An opportunity’s meeting is changed on the opportunity');
        }
    }

    private static function firstContact(Meeting $meeting): ?string
    {
        return $meeting->attendees->firstWhere('role', 'client')?->client_user_id;
    }

    private static function bookingLine(Meeting $meeting, string $summary, string $status, ?string $body = null): void
    {
        $user = Auth::guard('web')->user();
        LeadTimeline::record($meeting->organization_id, 'meeting', $summary, [
            'actor' => $user instanceof User ? $user : null, 'meeting_id' => $meeting->id, 'client_user_id' => self::firstContact($meeting->loadMissing('attendees')),
            'related_type' => 'appointment', 'related_id' => $meeting->appointment_id, 'body' => $body, 'payload' => ['status' => $status, 'booking' => true],
        ]);
    }

    private static function notes(?string $text): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));

        return $text === '' ? null : Str::limit($text, self::MAX_NOTES, '');
    }

    /** A link pasted without https:// still opens */
    private static function link(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }

        return Str::limit(preg_match('~^https?://~i', $link) ? $link : 'https://' . ltrim($link, '/'), 255, '');
    }
}
