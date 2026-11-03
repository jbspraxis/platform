{{-- The lead's Emails tab (Leads PR 2, docs/LEADS.md section 9): App\Livewire\Hq\Leads\EmailsTab --}}
<livewire:hq.leads.emails-tab :lead-id="$lead->id" :key="'lead-emails-' . $lead->id" />
