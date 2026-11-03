<?php

namespace App\Livewire\Hq\Leads;

use App\Models\Meeting;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Support\Leads\LeadAccess;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A lead's Opportunities tab (docs/LEADS.md section 9), inside LeadPage: every opportunity with the organisation
 * (decision 5: a lead can have many, some won and some lost), open ones first: stage, who leads it, when it opened or
 * closed, its latest proposal and next meeting, and Open an opportunity. Everyone with Leads access sees the titles
 * and stages; the link, the proposal's value and the meetings follow OpportunityAccess::canView.
 */
class OpportunitiesTab extends Component
{
    #[Locked]
    public string $leadId;

    public function mount(string $leadId): void
    {
        abort_unless(LeadAccess::canView(Auth::user()), 403);
        $lead = LeadAccess::find($leadId);
        abort_unless($lead, 404);
        $this->leadId = $lead->id;
    }

    public function render()
    {
        $user = Auth::user();
        $lead = LeadAccess::find($this->leadId);
        $opportunities = Opportunity::where('organization_id', $this->leadId)->with('lead:id,first_name,last_name')
            ->orderByRaw("CASE WHEN stage IN ('won', 'lost') THEN 1 ELSE 0 END")->orderByDesc('updated_at')->get();
        $ids = $opportunities->pluck('id')->all();

        // The latest version of each opportunity's proposal that still stands (v2; legacy ones have no opportunity)
        $proposals = $ids ? Proposal::whereIn('opportunity_id', $ids)->where('status', '!=', Proposal::SUPERSEDED)
            ->orderByDesc('version')->orderByDesc('created_at')->get(['id', 'opportunity_id', 'proposal_ref', 'status', 'total_value', 'currency', 'version', 'approved_at'])
            ->unique('opportunity_id')->keyBy('opportunity_id') : collect();
        $meetings = $ids ? Meeting::whereIn('opportunity_id', $ids)->where('status', '!=', 'cancelled')->get(['id', 'opportunity_id', 'kind', 'status', 'starts_at', 'title'])->groupBy('opportunity_id') : collect();
        $visible = $opportunities->mapWithKeys(fn ($o) => [$o->id => OpportunityAccess::canView($user, $o)]);

        // The value of open opportunities' proposals, per currency, among those this person may open
        $openValue = $opportunities->reject(fn ($o) => in_array($o->stage, Opportunity::CLOSED, true))
            ->filter(fn ($o) => $visible[$o->id] && isset($proposals[$o->id]) && $proposals[$o->id]->total_value !== null)
            ->groupBy(fn ($o) => $proposals[$o->id]->currency)
            ->map(fn ($group, $currency) => $group->sum(fn ($o) => (float) $proposals[$o->id]->total_value));

        return view('livewire.hq.leads.opportunities-tab', [
            'lead' => $lead,
            'groups' => [
                'Open' => $opportunities->reject(fn ($o) => in_array($o->stage, Opportunity::CLOSED, true))->values(),
                'Won' => $opportunities->where('stage', 'won')->values(),
                'Lost' => $opportunities->where('stage', 'lost')->values(),
            ],
            'count' => $opportunities->count(),
            'proposals' => $proposals,
            'meetings' => $meetings,
            'visible' => $visible,
            'openValue' => $openValue,
            'canOpen' => OpportunityAccess::canCreate($user),
        ]);
    }
}
