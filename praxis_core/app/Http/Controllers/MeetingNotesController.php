<?php

namespace App\Http\Controllers;

use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Support\Opportunities\MeetingNotesFile;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * A meeting's notes as a Markdown file (docs/OPPORTUNITIES.md 6.1), from the Meetings tab and the list of every
 * transcript. The notes with the meeting's details go to anyone who can open the opportunity, as on screen; with the
 * full transcript only to those who may read it (OpportunityAccess::canReadTranscripts). Every download is in the history.
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

        return response(MeetingNotesFile::markdown($t, $withTranscript), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . MeetingNotesFile::filename($t, $withTranscript) . '"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
