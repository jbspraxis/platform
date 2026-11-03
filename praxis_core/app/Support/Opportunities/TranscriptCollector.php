<?php

namespace App\Support\Opportunities;

use App\Events\Opportunities\MeetingEnded;
use App\Models\Meeting;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Google\Transcript;
use Carbon\CarbonImmutable;

/**
 * meetings:collect-transcripts, every 5 minutes (docs/OPPORTUNITIES.md 6.1). Scheduled meetings whose end has
 * passed become held. From 20 minutes after the end, a held meeting without a transcript is looked up: (1) Meet's
 * own transcript by the meeting's code, then (2) the Google Docs Gemini attached to the calendar event. Nothing
 * yet: again every 10 minutes until 6 hours after the end, then Not found and the lead is told to add it by hand.
 * A meeting HQ can't look up (no Meet code, no event) waits to be added by hand. When Google itself can't be
 * reached (not connected, revoked, quota) the run stops; the 6-hour limit still counts.
 */
final class TranscriptCollector
{
    public const RETRY_MINUTES = 10;

    public const GIVE_UP_AFTER_HOURS = 6;

    /** Meetings looked up per run, oldest first */
    public const BATCH = 25;

    /** Reasons that would fail every other lookup of this run the same way */
    private const STOP = ['not_connected', 'revoked', 'scopes', 'client', 'quota'];

    /** @return array{ended: int, tried: int, filed: int, not_found: int, manual: int, google: ?string} */
    public static function run(): array
    {
        $now = CarbonImmutable::now('UTC');
        $out = ['ended' => 0, 'tried' => 0, 'filed' => 0, 'not_found' => 0, 'manual' => 0, 'google' => null];

        foreach (Meeting::where('status', 'scheduled')->where('ends_at', '<=', $now)->orderBy('ends_at')->limit(100)->get() as $meeting) {
            $meeting->forceFill(['status' => 'held'])->save();
            MeetingEnded::dispatch($meeting);
            $out['ended']++;
        }

        $due = Meeting::where('status', 'held')->whereIn('transcript_status', ['waiting', 'collecting'])
            ->where('ends_at', '<=', $now->subMinutes(Meetings::FIRST_TRY_AFTER_MINUTES))
            ->where(fn ($q) => $q->whereNull('transcript_next_try_at')->orWhere('transcript_next_try_at', '<=', $now))
            ->whereDoesntHave('transcripts')
            ->with('opportunity')->orderBy('ends_at')->limit(self::BATCH)->get();

        $google = app(GoogleWorkspace::class);
        foreach ($due as $meeting) {
            if (! $meeting->meet_code && ! $meeting->google_event_id) {
                $meeting->forceFill(['transcript_status' => 'manual', 'transcript_next_try_at' => null])->save();
                $out['manual']++;

                continue;
            }

            try {
                $found = self::lookUp($google, $meeting);
            } catch (GoogleUnavailable $e) {
                $out['google'] = $e->reason;
                $out['not_found'] += self::notYet($meeting, $now) === 'not_found' ? 1 : 0;
                if (in_array($e->reason, self::STOP, true)) {
                    break;
                }

                continue;
            }

            $out['tried']++;
            if ($found) {
                Transcripts::fromGoogle($meeting, $found);
                $out['filed']++;
            } elseif (self::notYet($meeting, $now) === 'not_found') {
                $out['not_found']++;
            }
        }

        return $out;
    }

    /** Route 1, then route 2; an event or recording that is gone counts as nothing found */
    private static function lookUp(GoogleWorkspace $google, Meeting $meeting): ?Transcript
    {
        $routes = array_filter([
            // A conference counts if it started from an hour before the meeting to an hour after its end
            $meeting->meet_code ? fn () => $google->conferenceTranscript($meeting->meet_code, $meeting->starts_at->copy()->subHour(), $meeting->ends_at->copy()->addHour()) : null,
            $meeting->google_event_id ? fn () => $google->eventAttachmentText($meeting->google_event_id) : null,
        ]);
        foreach ($routes as $route) {
            try {
                if ($found = $route()) {
                    return $found;
                }
            } catch (GoogleUnavailable $e) {
                if ($e->reason !== 'not_found') {
                    throw $e;
                }
            }
        }

        return null;
    }

    /** Try again in 10 minutes, or give up 6 hours after the end and tell the lead */
    private static function notYet(Meeting $meeting, CarbonImmutable $now): string
    {
        $tries = $meeting->transcript_tries + 1;
        if ($now->greaterThanOrEqualTo($meeting->ends_at->copy()->addHours(self::GIVE_UP_AFTER_HOURS))) {
            $meeting->forceFill(['transcript_status' => 'not_found', 'transcript_next_try_at' => null, 'transcript_tries' => $tries])->save();
            Meetings::log($meeting, 'TRANSCRIPT_NOT_FOUND', sprintf('No transcript of “%s” was found in Google after %d tries.', $meeting->title, $tries),
                [], ['meeting_id' => $meeting->id]);
            // The opportunity's lead, or for a lead meeting (no opportunity) the lead's owner
            Meetings::tellOwner($meeting, __('meetings.notify.not_found_title'), __('meetings.notify.not_found', ['title' => $meeting->title]));

            return 'not_found';
        }
        $meeting->forceFill([
            'transcript_status' => 'collecting',
            'transcript_next_try_at' => $now->addMinutes(self::RETRY_MINUTES),
            'transcript_tries' => $tries,
        ])->save();

        return 'collecting';
    }
}
