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
        /* The summary strip (owner's decision 18) and the filters */
        .lr-strip { display: flex; flex-direction: column; gap: 16px; margin-bottom: 16px; }
        .lr-strip-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .lr-strip-head h2 { margin: 0; font-size: 1rem; font-weight: 600; color: var(--color-ink); }
        .lr-strip-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .lr-strip-tools .select { width: auto; min-width: 150px; }
        .lr-tiles { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(130px, 100%), 1fr)); }
        .lr-tile { padding: 14px 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); min-width: 0; }
        .lr-tile .stat-value { overflow-wrap: anywhere; }
        .lr-tile .stat-value.is-danger { color: var(--color-danger); }
        .lr-tile-note { margin-top: 2px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
        .lr-tile-link { padding: 0; border: 0; background: none; font: inherit; color: var(--color-fg-link); text-decoration: underline; cursor: pointer; }
        .lr-money { display: block; }
        .lr-money + .lr-money { font-size: 1rem; line-height: 1.5rem; }
        .lr-statuses { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-slate); }
        .lr-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border: 1px solid var(--color-line); border-radius: 999px; background: var(--color-paper); font: inherit; font-size: 0.8125rem; color: var(--color-ink); cursor: pointer; }
        .lr-chip:hover { background: var(--color-mist); }
        .lr-chip strong { font-variant-numeric: tabular-nums; }
        .lr-chip.is-active { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .lr-filters { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(170px, 100%), 1fr)); align-items: end; }
        .lr-filters .field { margin: 0; }
        .lr-filter-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-top: 12px; font-size: 0.875rem; color: var(--color-slate); }
        .lr-owner-row { cursor: pointer; }
        .lr-badge { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--color-danger); color: #fff; font-size: 0.75rem; font-weight: 600; }
        @media (max-width: 600px) {
            .lr-actions { width: 100%; }
            .lr-actions > * { flex: 1 1 calc(50% - 6px); }
            .lr-actions > * > .btn, .lr-actions > .btn { width: 100%; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Leads</h1>
            <p class="page-subtitle">Every lead and client, who follows each one up, and what happens next. Open a lead to see everything about it.</p>
        </div>
        <div class="flex flex-wrap gap-3 lr-actions">
            <a href="{{ route('hq.crm.leads.follow-ups') }}" class="btn btn-secondary" wire:navigate>
                <i data-lucide="list-checks"></i> My follow-ups
                @if ($myOverdue) <span class="lr-badge" title="{{ $myOverdue }} overdue">{{ $myOverdue }}</span> @endif
            </a>
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
            @if ($canImport)
                <livewire:hq.import.import-dialog schema="leads" />
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

    {{-- The summary strip: the owner filter below applies to it too --}}
    @php
        $periodText = \App\Support\Leads\LeadNumbers::periodText($period);
        $conv = $numbers['conversion'];
        $value = $numbers['value'];
    @endphp
    <section class="card lr-strip" aria-labelledby="lr-strip-title">
        <div class="lr-strip-head">
            <h2 id="lr-strip-title">Numbers for {{ $ownerName === 'you' ? 'your leads' : ($ownerName === 'everyone' ? 'everyone’s leads' : ($ownerName === 'no one' ? 'leads nobody owns' : $ownerName . '’s leads')) }}</h2>
            <div class="lr-strip-tools">
                <label class="sr-only" for="lr-period">Period</label>
                <select id="lr-period" class="select" wire:model.live="period" aria-label="Period">
                    @foreach (\App\Support\Leads\LeadNumbers::PERIODS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" class="segmented-item" aria-pressed="{{ $byOwner ? 'true' : 'false' }}" wire:click="$toggle('byOwner')">
                    <i data-lucide="users"></i> By owner
                </button>
            </div>
        </div>

        <div class="lr-tiles">
            <div class="lr-tile">
                <div class="stat-label">New leads, {{ $periodText }}</div>
                <div class="stat-value">{{ number_format($numbers['new']['period']) }}</div>
                <div class="lr-tile-note">{{ number_format($numbers['new']['week']) }} this week · {{ number_format($numbers['new']['month']) }} this month</div>
            </div>
            <div class="lr-tile">
                <div class="stat-label">Conversion</div>
                <div class="stat-value">{{ $conv['rate'] === null ? '—' : rtrim(rtrim(number_format($conv['rate'], 1), '0'), '.') . '%' }}</div>
                <div class="lr-tile-note">
                    @if ($conv['leads'])
                        {{ number_format($conv['clients']) }} of {{ number_format($conv['leads']) }} {{ $conv['leads'] === 1 ? 'lead' : 'leads' }} added {{ $periodText === 'all time' ? 'so far' : $periodText }} {{ $conv['clients'] === 1 ? 'is a client' : 'are clients' }} now
                    @else
                        No leads added {{ $periodText === 'all time' ? 'yet' : $periodText }}
                    @endif
                </div>
            </div>
            <div class="lr-tile">
                <div class="stat-label">Open opportunity value</div>
                <div class="stat-value">
                    @forelse ($value['totals'] as $currency => $amount)
                        <span class="lr-money">{{ \App\Livewire\Hq\HqMoney::format($currency, $amount, 0) }}</span>
                    @empty
                        —
                    @endforelse
                </div>
                <div class="lr-tile-note">
                    @if ($value['open'])
                        {{ number_format($value['open']) }} open {{ $value['open'] === 1 ? 'opportunity' : 'opportunities' }}, {{ number_format($value['priced']) }} with a proposal (before VAT)
                    @else
                        No open opportunities
                    @endif
                </div>
            </div>
            <div class="lr-tile">
                <div class="stat-label">Overdue follow-ups</div>
                <div @class(['stat-value', 'is-danger' => $numbers['overdue'] > 0])>{{ number_format($numbers['overdue']) }}</div>
                <div class="lr-tile-note">
                    @if ($numbers['overdue'])
                        <button type="button" class="lr-tile-link" wire:click="filterBy('step', 'overdue')">Show them</button> ·
                    @endif
                    @if ($numbers['noStep'])
                        <button type="button" class="lr-tile-link" wire:click="filterBy('step', 'none')">{{ number_format($numbers['noStep']) }} with no next step</button>
                    @else
                        Every open lead has a next step
                    @endif
                </div>
            </div>
        </div>

        <div class="lr-statuses">
            <span>{{ $periodText === 'all time' ? 'All leads' : 'Added ' . $periodText }}, by status now:</span>
            @foreach (\App\Support\Leads\LeadStatus::LABELS as $key => $label)
                <button type="button" @class(['lr-chip', 'is-active' => $status === $key]) wire:click="filterBy('status', '{{ $key }}')" aria-pressed="{{ $status === $key ? 'true' : 'false' }}">
                    {{ $label }} <strong>{{ number_format($numbers['status'][$key] ?? 0) }}</strong>
                </button>
            @endforeach
        </div>

        @if ($byOwner)
            <div class="table-wrap" style="border: 1px solid var(--color-line); border-radius: var(--radius-card)">
                <table class="table">
                    <caption class="sr-only">Numbers by owner, {{ $periodText }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Owner</th>
                            <th scope="col" class="num">New, {{ $periodText }}</th>
                            <th scope="col" class="num">Open</th>
                            <th scope="col" class="num">Overdue</th>
                            <th scope="col" class="num">No next step</th>
                            <th scope="col" class="num">Conversion</th>
                            <th scope="col" class="num">Open opportunity value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($perOwner as $row)
                            <tr class="lr-owner-row" wire:key="owner-{{ $row->scope }}" wire:click="filterBy('owner', '{{ $row->scope }}')" title="Show {{ $row->name }}’s leads">
                                <td>
                                    <div class="flex items-center gap-2">
                                        @if ($row->id)
                                            <x-hq.avatar :user="['id' => $row->id, 'first_name' => $row->first_name, 'last_name' => $row->last_name, 'avatar_path' => $row->avatar_path]" :size="24" />
                                        @endif
                                        <span class="text-ink">{{ $row->name }}</span>
                                    </div>
                                </td>
                                <td class="num">{{ number_format($row->new) }}</td>
                                <td class="num">{{ number_format($row->open) }}</td>
                                <td class="num" @if ($row->overdue) style="color: var(--color-danger); font-weight: 600" @endif>{{ number_format($row->overdue) }}</td>
                                <td class="num">{{ number_format($row->noStep) }}</td>
                                <td class="num">{{ $row->conversion['rate'] === null ? '—' : rtrim(rtrim(number_format($row->conversion['rate'], 1), '0'), '.') . '%' }}</td>
                                <td class="num">{{ collect($row->value['totals'])->map(fn ($a, $c) => \App\Livewire\Hq\HqMoney::format($c, $a, 0))->implode(' · ') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">Nobody owns a lead yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Filters (Mine on by default: owner's decision 8) --}}
    <section class="card" style="margin-bottom: 16px" aria-label="Filters">
        <div class="lr-filters">
            <div class="field">
                <label class="label" for="lr-owner">Owner</label>
                <select id="lr-owner" class="select" wire:model.live="owner">
                    <option value="mine">Mine</option>
                    <option value="all">Everyone</option>
                    <option value="none">No owner</option>
                    @foreach ($owners as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="lr-status">Status</label>
                <select id="lr-status" class="select" wire:model.live="status">
                    <option value="">Any status</option>
                    @foreach (\App\Livewire\Hq\Intelligence\LeadsRegister::STATUS_FILTERS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="lr-step">Next step</label>
                <select id="lr-step" class="select" wire:model.live="step">
                    <option value="">Any</option>
                    @foreach (\App\Livewire\Hq\Intelligence\LeadsRegister::STEPS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="lr-source">Source</label>
                <select id="lr-source" class="select" wire:model.live="source">
                    <option value="">Any source</option>
                    @foreach (\App\Support\Leads\Sources::LABELS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="lr-country">Country</label>
                <select id="lr-country" class="select" wire:model.live="country">
                    <option value="">Any country</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="lr-search">Search</label>
                <input id="lr-search" type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="Name, contact, email or phone" maxlength="100">
            </div>
        </div>
        <div class="lr-filter-foot">
            <span aria-live="polite">{{ number_format($total) }} {{ $total === 1 ? 'lead' : 'leads' }}{{ $owner === 'mine' ? ' of yours' : '' }}{{ $filtered ? ' match the filters' : '' }}.</span>
            @if ($filtered)
                <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters"><i data-lucide="x"></i> Clear filters</button>
            @endif
        </div>
    </section>

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
                                @if ($filtered)
                                    <strong>No leads match these filters</strong>
                                    <button type="button" class="btn btn-secondary btn-sm mt-2" wire:click="clearFilters">Clear filters</button>
                                @elseif ($owner === 'mine')
                                    <strong>You don’t own any leads yet</strong>
                                    Leads you add or take, and website leads given to you, show here.
                                    <button type="button" class="btn btn-secondary btn-sm mt-2" wire:click="filterBy('owner', 'all')">Show everyone’s leads</button>
                                @elseif ($owner !== 'all')
                                    <strong>No leads here</strong>
                                    <button type="button" class="btn btn-secondary btn-sm mt-2" wire:click="filterBy('owner', 'all')">Show everyone’s leads</button>
                                @else
                                    <strong>No leads yet</strong>
                                    Leads appear here when someone writes through the website, books a free audit or runs the cost calculator. You can also add a lead yourself, or import a list.
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($total > $leads->count())
        <div class="flex justify-center" style="margin-top: 12px">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="showMore">Show {{ number_format(min($total - $leads->count(), 100)) }} more of {{ number_format($total) }}</button>
        </div>
    @endif

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
