<div>
    @php
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 0);
        $tierLabel = fn ($tier) => match ((int) $tier) { 1 => 'Tier 1', 2 => 'Tier 2', default => 'Tier 3' };
    @endphp

    <style>
        .lr-row { cursor: pointer; }
        .lr-row:hover td { background: var(--color-mist); }
        .lr-name { font-weight: 600; color: var(--color-ink); text-decoration: none; overflow-wrap: anywhere; }
        .lr-name:hover { text-decoration: underline; }
        .lr-overdue { color: var(--color-danger); }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Leads</h1>
            <p class="page-subtitle">Every lead and client, who follows each one up, and what happens next. Open a lead to see everything about it.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @can('crm.export')
            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="btn btn-secondary">
                <i data-lucide="download"></i>
                <span wire:loading.remove wire:target="exportCsv">Download CSV</span>
                <span wire:loading wire:target="exportCsv">Preparing file…</span>
            </button>
            @endcan
            @if ($canSetDefault)
                <livewire:hq.leads.lead-settings />
            @endif
            @can('crm.create')
            <a href="{{ route('hq.crm.leads.new') }}" class="btn btn-primary" wire:navigate>
                <i data-lucide="plus"></i>
                <span>Add lead</span>
            </a>
            @endcan
        </div>
    </div>

    <x-flash class="mb-6" />

    <div class="card" style="padding: 0;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Organisation</th>
                        <th style="min-width: 180px;">Main contact</th>
                        <th>Status</th>
                        <th style="min-width: 180px;">Next step</th>
                        <th style="min-width: 150px;">Owner</th>
                        <th class="num" style="text-align: right;">Cost of poor quality (a year)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                    @php
                        $url = route('hq.crm.lead', ['organization' => $lead->id]);
                        $overdue = \App\Support\Leads\NextStep::isOverdue($lead);
                    @endphp
                    <tr class="lr-row" wire:key="lead-{{ $lead->id }}" x-on:click="if (! $event.target.closest('a, button')) Livewire.navigate('{{ $url }}')">
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ $url }}" class="lr-name" wire:navigate>{{ $lead->name }}</a>
                                @if($lead->is_duplicate_suspect)
                                    <span class="pill pill-warning">Possible duplicate</span>
                                @endif
                            </div>
                            <div class="text-caption text-muted mt-1">{{ collect([$lead->industry, $lead->country])->filter()->implode(' · ') ?: 'Industry not given' }}</div>
                            @if($lead->status === 'Lead')
                                @can('crm.create')
                                <button type="button" wire:click="requestConsultation('{{ $lead->id }}')" class="btn btn-ghost btn-sm mt-1" style="padding-left: 0;" title="Book a free audit for this lead">
                                    <i data-lucide="calendar-plus"></i> Book free audit
                                </button>
                                @endcan
                            @endif
                        </td>
                        <td>
                            <div class="text-ink">{{ $lead->contact_name ?: 'No contact yet' }}</div>
                            <div class="text-caption text-muted" style="overflow-wrap: anywhere">{{ $lead->contact_email ?: $lead->contact_phone ?: $lead->email ?: $lead->phone }}</div>
                        </td>
                        <td><span class="pill {{ \App\Support\Leads\LeadStatus::tone($lead->lead_status) }}">{{ \App\Support\Leads\LeadStatus::label($lead->lead_status) }}</span></td>
                        <td>
                            @if ($lead->next_step && \App\Support\Leads\LeadStatus::isOpen($lead->lead_status))
                                <div @class(['text-ink' => ! $overdue, 'lr-overdue' => $overdue])>{{ \Illuminate\Support\Str::limit($lead->next_step, 60) }}</div>
                                <div @class(['text-caption', 'text-muted' => ! $overdue, 'lr-overdue' => $overdue])>By {{ \App\Support\Leads\NextStep::day($lead->next_step_due) }}@if ($overdue) · overdue @endif</div>
                            @elseif (\App\Support\Leads\LeadStatus::isOpen($lead->lead_status))
                                <span class="text-caption text-muted">No next step</span>
                            @else
                                <span class="text-caption text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($lead->owner_id)
                                <div class="flex items-center gap-2">
                                    <x-hq.avatar :user="['id' => $lead->owner_id, 'first_name' => $lead->owner_first, 'last_name' => $lead->owner_last, 'avatar_path' => $lead->owner_avatar ?? null]" :size="28" />
                                    <span class="text-ink">{{ $lead->owner_first }}</span>
                                </div>
                            @else
                                @can('crm.edit')
                                <button type="button" wire:click="claimLead('{{ $lead->id }}')" wire:loading.attr="disabled" wire:target="claimLead('{{ $lead->id }}')" class="btn btn-secondary btn-sm">
                                    <span wire:loading.remove wire:target="claimLead('{{ $lead->id }}')">Assign to me</span>
                                    <span wire:loading wire:target="claimLead('{{ $lead->id }}')">Assigning…</span>
                                </button>
                                @else
                                <span class="text-muted">Not assigned</span>
                                @endcan
                            @endif
                        </td>
                        <td class="num">
                            @if($lead->calculated_leakage)
                                <span class="font-semibold text-ink">{{ $money($lead->copq_currency, $lead->calculated_leakage) }}</span>
                                <div class="text-caption text-muted">{{ $tierLabel($lead->lead_tier) }}</div>
                            @else
                                <span class="text-muted">Not scored</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <strong>No leads yet</strong>
                                Leads appear here when someone writes through the website, books a free audit or runs the cost calculator. You can also add a lead yourself.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Book a free audit: date and time in Lagos time (WAT), like website bookings --}}
    @if($showBookingModal)
        <div class="modal-backdrop" style="z-index: 60;">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="booking-title" style="max-width: 440px;">
                <form wire:submit="bookConsultation">
                    <h2 id="booking-title" class="modal-title">Book a free audit</h2>
                    <p class="text-small text-muted" style="margin: 0 0 16px;">For <strong class="text-ink">{{ $bookingCompany }}</strong>. It appears in the Consultation hub as a request; approve it there with the meeting link to email the client.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="field">
                            <label class="label" for="booking-date">Date</label>
                            <input id="booking-date" type="date" wire:model="bookingDate" required class="input">
                            @error('bookingDate') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="booking-time">Time (WAT)</label>
                            <input id="booking-time" type="time" step="300" wire:model="bookingTime" required class="input">
                            @error('bookingTime') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="help" style="margin: 8px 0 0;">Lagos time (WAT), the same as website bookings. The free audit lasts an hour.</p>

                    <div class="modal-actions">
                        <button type="button" wire:click="closeBookingModal" class="btn btn-secondary">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="bookConsultation" class="btn btn-primary">
                            <span wire:loading.remove wire:target="bookConsultation">Book free audit</span>
                            <span wire:loading wire:target="bookConsultation">Booking…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
