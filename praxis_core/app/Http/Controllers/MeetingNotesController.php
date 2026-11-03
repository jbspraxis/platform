<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadMeetings;
use App\Support\Opportunities\MeetingNotesFile;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * A meeting's notes as a Markdown file (docs/OPPORTUNITIES.md 6.1), from the Meetings tab and the list of every
 * transcript. The notes with the meeting's details go to anyone who can open the opportunity, as on screen; with the
 * full transcript only to those who may read it (OpportunityAccess::canReadTranscripts). Every download is in the history.
 * A lead's meeting (docs/LEADS.md section 9) downloads from the lead's page: the notes for Leads access (crm.view), the
 * full transcript for LeadMeetings::canReadTranscripts; a meeting with typed notes and no transcript yet downloads too.
 */
class MeetingNotesController extends Controller
{
    public function download(string $opportunity, string $transcript, string $what): Response
    {
        $o = Opportunity::findOrFail($opportunity);
        abort_unless(OpportunityAccess::canView(Auth::user(), $o), 403);
        $withTranscript = $what === 'transcript';
        abort_if($withTranscript && ! OpportunityAccess::canReadTranscripts(Auth::user(), $o), 403);
        $t = MeetingTranscript::whereHas('meeting', fn ($q) => $q->where('opportunity_id', $o->id))->with('meeting.opportunity')->findOrFail($transcript);

        OpportunityAccess::log($withTranscript ? 'TRANSCRIPT_DOWNLOADED' : 'NOTES_DOWNLOADED',
            sprintf('Downloaded the notes%s of “%s”.', $withTranscript ? ' and full transcript' : '', $t->meeting->title),
            'opportunities', $o->id, [], ['meeting_id' => $t->meeting_id, 'transcript_id' => $t->id]);

        return $this->file($t, $withTranscript);
    }

    /** /intelligence/leads/{organization}/meetings/{meeting}/{what}.md[?transcript=<id>]: the latest transcript unless one is named */
    public function lead(Request $request, string $organization, string $meeting, string $what): Response
    {
        abort_unless(LeadAccess::canView(Auth::user()), 403);
        $lead = LeadAccess::find($organization);
        abort_unless($lead, 404);
        $m = LeadMeetings::find($lead->id, $meeting);
        abort_unless($m, 404);
        $withTranscript = $what === 'transcript';
        abort_if($withTranscript && ! LeadMeetings::canReadTranscripts(Auth::user(), $lead), 403);
        $named = (string) $request->query('transcript', '');
        $t = $named !== '' ? $m->transcripts()->findOrFail($named) : $m->transcripts()->get()->last();
        abort_if(! $t && ($withTranscript || blank($m->typed_notes)), 404);

        Meetings::log($m, $withTranscript ? 'TRANSCRIPT_DOWNLOADED' : 'NOTES_DOWNLOADED',
            sprintf('Downloaded the notes%s of “%s”.', $withTranscript ? ' and full transcript' : '', $m->title),
            [], array_filter(['meeting_id' => $m->id, 'transcript_id' => $t?->id]));

        return $this->file($t ?? $m, $withTranscript);
    }

    private function file(MeetingTranscript|Meeting $of, bool $withTranscript): Response
    {
        return response(MeetingNotesFile::markdown($of, $withTranscript), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . MeetingNotesFile::filename($of, $withTranscript) . '"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
