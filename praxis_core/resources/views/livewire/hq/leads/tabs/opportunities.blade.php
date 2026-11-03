{{-- The lead's Opportunities tab (Leads PR 2, docs/LEADS.md section 9): App\Livewire\Hq\Leads\OpportunitiesTab --}}
<livewire:hq.leads.opportunities-tab :lead-id="$lead->id" :key="'lead-opportunities-' . $lead->id" />
