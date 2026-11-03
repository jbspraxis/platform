<?php

namespace App\Listeners\Opportunities;

use App\Events\Opportunities\TranscriptCollected;
use App\Support\Ai\AiUnavailable;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\Transcripts;
use Illuminate\Support\Facades\Log;

/**
 * A transcript was filed: the AI drafts the internal notes and, for a client meeting of an opportunity, the minutes
 * (docs/OPPORTUNITIES.md 2 step 3). A lead's meeting gets the notes only: it has no minutes to send (docs/LEADS.md
 * section 12, PR 2); they can be drafted once an opportunity is opened from it. Without a key or over the budget
 * nothing is drafted; the meeting shows why and offers to try again. A transcript HQ collected by itself is announced
 * to the opportunity's lead (a lead meeting: the lead's owner).
 */
class DraftMeetingNotes
{
    public function handle(TranscriptCollected $event): void
    {
        $transcript = $event->transcript;
        $meeting = $transcript->meeting()->with('opportunity')->first();

        foreach ($meeting->kind === 'client' && ! $meeting->isLeadMeeting() ? ['notes', 'minutes'] : ['notes'] as $what) {
            try {
                $what === 'notes' ? Transcripts::draftNotes($transcript) : Transcripts::draftMinutes($transcript);
            } catch (AiUnavailable $e) {
                Log::info('Meetings: the AI did not draft ' . $what, ['transcript' => $transcript->id, 'reason' => $e->reason]);
            }
        }

        if ($event->automatic) {
            Meetings::tellOwner($meeting, __('meetings.notify.filed_title'), __('meetings.notify.filed', ['title' => $meeting->title]));
        }
    }
}
