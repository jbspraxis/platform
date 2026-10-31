<?php

namespace App\Livewire\Hq\Opportunities;

use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Every transcript of an opportunity together, in meeting order, with the AI's notes and the minutes sent
 * (docs/OPPORTUNITIES.md section 4, row 2). Read only: the Meetings tab is where they are added and sent.
 * Embed it anywhere the opportunity's meetings matter, e.g. the project page (PR 5):
 *   <livewire:hq.opportunities.transcript-list :opportunity-id="$opportunityId" />
 */
class TranscriptList extends Component
{
    #[Locked]
    public string $opportunityId;

    #[Locked]
    public bool $canReadTranscripts = false;

    public function mount(string $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        abort_unless(OpportunityAccess::canView(Auth::user()), 403);
        $this->opportunityId = $opportunity->id;
        $this->canReadTranscripts = OpportunityAccess::canReadTranscripts(Auth::user(), $opportunity);
    }

    public function render()
    {
        $transcripts = MeetingTranscript::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_transcripts.meeting_id')
            ->where('meetings.opportunity_id', $this->opportunityId)->whereNull('meetings.deleted_at')
            ->orderBy('meetings.starts_at')->orderBy('meeting_transcripts.collected_at')
            ->select('meeting_transcripts.*')->with('meeting')->get();

        return view('livewire.hq.opportunities.transcript-list', ['transcripts' => $transcripts]);
    }
}
