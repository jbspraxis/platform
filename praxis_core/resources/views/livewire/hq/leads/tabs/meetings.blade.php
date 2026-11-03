{{-- The lead's Meetings tab (Leads PR 2, docs/LEADS.md section 9): App\Livewire\Hq\Leads\MeetingsTab --}}
<livewire:hq.leads.meetings-tab :lead-id="$lead->id" :key="'lead-meetings-' . $lead->id" />
