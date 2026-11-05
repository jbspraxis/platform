<div class="fu">
    <style>
        /* My follow-ups: only what the shared component classes don't cover. Brand tokens only. */
        .fu { max-width: 1040px; }
        .fu-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 0.875rem; text-decoration: none; }
        .fu-back svg { width: 16px; height: 16px; }
        .fu-owner { min-width: 200px; }
        .fu-counts { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; padding: 0; list-style: none; }
        .fu-counts a { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border: 1px solid var(--color-line); border-radius: 999px; background: var(--color-paper); font-size: 0.8125rem; color: var(--color-ink); text-decoration: none; }
        .fu-counts a:hover { background: var(--color-mist); }
        .fu-counts a.is-danger { border-color: var(--color-danger); color: var(--color-danger); background: var(--color-danger-tint); }
        .fu-counts a.is-warning { border-color: var(--color-warning); color: var(--color-warning); background: var(--color-warning-tint); }
        .fu-counts strong { font-variant-numeric: tabular-nums; }
        .fu-group { padding: 0; margin-bottom: 16px; overflow: hidden; scroll-margin-top: 80px; }
        .fu-group-head { display: flex; align-items: center; gap: 8px; padding: 14px 20px; border-bottom: 1px solid var(--color-line); }
        .fu-group-head h2 { margin: 0; font-size: 1rem; font-weight: 600; color: var(--color-ink); }
        .fu-group-head p { margin: 0 0 0 auto; font-size: 0.8125rem; color: var(--color-muted); }
        .fu-group-head svg { width: 18px; height: 18px; }
        .fu-group.is-overdue { border-color: var(--color-danger); }
        .fu-group.is-overdue .fu-group-head { background: var(--color-danger-tint); }
        .fu-group.is-overdue .fu-group-head h2, .fu-group.is-overdue .fu-group-head svg { color: var(--color-danger); }
        .fu-group.is-none .fu-group-head { background: var(--color-warning-tint); }
        .fu-group.is-none .fu-group-head svg { color: var(--color-warning); }
        .fu-list { list-style: none; margin: 0; padding: 0; }
        .fu-row { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) auto; gap: 8px 20px; align-items: start; padding: 14px 20px; border-bottom: 1px solid var(--color-line); }
        .fu-row:last-child { border-bottom: 0; }
        .fu-name { font-weight: 600; color: var(--color-ink); text-decoration: none; overflow-wrap: anywhere; }
        .fu-name:hover { text-decoration: underline; }
        .fu-meta { font-size: 0.8125rem; color: var(--color-muted); margin-top: 2px; }
        .fu-step { margin-top: 6px; color: var(--color-ink); overflow-wrap: anywhere; }
        .fu-due { font-size: 0.8125rem; color: var(--color-muted); }
        .fu-due.is-overdue, .fu-step.is-overdue { color: var(--color-danger); }
        .fu-due.is-overdue { font-weight: 600; }
        .fu-flag { display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 0.8125rem; font-weight: 600; color: var(--color-warning); }
        .fu-flag svg { width: 14px; height: 14px; }
        .fu-contact { font-size: 0.875rem; color: var(--color-ink); min-width: 0; }
        .fu-contact a { display: inline-flex; align-items: center; gap: 4px; overflow-wrap: anywhere; }
        .fu-contact svg { width: 14px; height: 14px; flex-shrink: 0; }
        .fu-ways { display: flex; flex-direction: column; gap: 2px; margin-top: 2px; font-size: 0.8125rem; }
        .fu-edit { grid-column: 1 / -1; display: grid; gap: 12px; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto; align-items: end; padding: 12px; border-radius: var(--radius-card); background: var(--color-mist); }
        .fu-edit .field { margin: 0; }
        .fu-edit-actions { display: flex; gap: 8px; }
        @media (max-width: 720px) {
            .fu-row { grid-template-columns: minmax(0, 1fr); padding: 14px 16px; }
            .fu-group-head { padding: 12px 16px; flex-wrap: wrap; }
            .fu-group-head p { margin-left: 0; width: 100%; }
            .fu-edit { grid-template-columns: minmax(0, 1fr); }
            .fu-edit-actions .btn, .fu-row > div:last-child .btn { flex: 1 1 auto; }
            .fu-owner { width: 100%; }
        }
    </style>

    <a href="{{ route('hq.crm.leads') }}" class="fu-back" wire:navigate><i data-lucide="arrow-left"></i> All leads</a>

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="page-subtitle">Every open lead’s next step, overdue ones first. Leads with no next step are at the end: give each one.</p>
        </div>
        <div class="field fu-owner" style="margin: 0">
            <label class="label" for="fu-owner">Owner</label>
            <select id="fu-owner" class="select" wire:model.live="owner">
                <option value="mine">Mine</option>
                <option value="all">Everyone</option>
                <option value="none">No owner</option>
                @foreach ($owners as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
        </div>
    </div>


    @php
        // The sections name their icons in PHP. scripts/icons.mjs bundles only icons named in the views, so they are
        // listed here: data-lucide="alarm-clock" data-lucide="clock" data-lucide="calendar-clock" data-lucide="calendar" data-lucide="flag"
        $sections = [
            'overdue' => ['Overdue', 'alarm-clock', 'is-overdue', 'The oldest first.'],
            'today' => ['Due today', 'clock', '', ''],
            'week' => ['In the next 7 days', 'calendar-clock', '', ''],
            'later' => ['Later', 'calendar', '', ''],
            'none' => ['No next step', 'flag', 'is-none', 'Every open lead needs one: what happens next, and by when.'],
        ];
        $total = collect($groups)->sum(fn ($g) => $g->count());
    @endphp

    @if ($total === 0)
        <div class="card">
            <div class="empty-state">
                <strong>Nothing to follow up</strong>
                {{ $owner === 'mine' ? 'You have no open leads. Leads you add or take, and website leads given to you, show here with their next step.' : 'No open leads here.' }}
                <div class="mt-2"><a href="{{ route('hq.crm.leads', ['owner' => 'all']) }}" class="btn btn-secondary btn-sm" wire:navigate>See all leads</a></div>
            </div>
        </div>
    @else
        <ul class="fu-counts" aria-label="Summary">
            @foreach ($sections as $key => [$label])
                @if ($groups[$key]->isNotEmpty())
                    <li><a href="#fu-{{ $key }}" @class(['is-danger' => $key === 'overdue', 'is-warning' => $key === 'none'])>{{ $label }} <strong>{{ $groups[$key]->count() }}</strong></a></li>
                @endif
            @endforeach
        </ul>

        @foreach ($sections as $key => [$label, $icon, $tone, $note])
            @continue($groups[$key]->isEmpty())
            <section class="card fu-group {{ $tone }}" id="fu-{{ $key }}" aria-labelledby="fu-{{ $key }}-title" wire:key="fu-group-{{ $key }}">
                <div class="fu-group-head">
                    <i data-lucide="{{ $icon }}" aria-hidden="true"></i>
                    <h2 id="fu-{{ $key }}-title">{{ $label }} ({{ $groups[$key]->count() }})</h2>
                    @if ($note) <p>{{ $note }}</p> @endif
                </div>
                <ul class="fu-list">
                    @foreach ($groups[$key] as $lead)
                        @php
                            $url = route('hq.crm.lead', ['organization' => $lead->id]);
                            $late = $key === 'overdue' ? \Illuminate\Support\Carbon::parse($lead->next_step_due, \App\Support\Leads\NextStep::TZ)->diffInDays(\Illuminate\Support\Carbon::parse($today, \App\Support\Leads\NextStep::TZ)) : 0;
                        @endphp
                        <li class="fu-row" wire:key="fu-{{ $lead->id }}">
                            <div>
                                <a href="{{ $url }}" class="fu-name" wire:navigate>{{ $lead->name }}</a>
                                <span class="pill {{ \App\Support\Leads\LeadStatus::tone($lead->lead_status) }}" style="margin-left: 6px">{{ \App\Support\Leads\LeadStatus::label($lead->lead_status) }}</span>
                                <div class="fu-meta">
                                    {{ collect([$lead->industry, $lead->country])->filter()->implode(' · ') }}
                                    @if ($owner !== 'mine') · {{ $lead->owner_first ? trim($lead->owner_first . ' ' . $lead->owner_last) : 'No owner' }} @endif
                                </div>
                                @if ($key === 'none')
                                    <div class="fu-flag"><i data-lucide="flag" aria-hidden="true"></i> No next step</div>
                                @else
                                    <div @class(['fu-step', 'is-overdue' => $key === 'overdue'])>{{ $lead->next_step }}</div>
                                    <div @class(['fu-due', 'is-overdue' => $key === 'overdue'])>
                                        By {{ \App\Support\Leads\NextStep::day($lead->next_step_due) }}@if ($key === 'overdue') · {{ (int) $late }} {{ (int) $late === 1 ? 'day' : 'days' }} overdue @elseif ($key === 'today') · today @endif
                                    </div>
                                @endif
                            </div>
                            <div class="fu-contact">
                                @if ($lead->contact_name)
                                    <div>{{ $lead->contact_name }}@if ($lead->contact_title)<span class="text-muted">, {{ $lead->contact_title }}</span>@endif</div>
                                @endif
                                <div class="fu-ways">
                                    @if ($lead->contact_phone)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->contact_phone) }}"><i data-lucide="phone" aria-hidden="true"></i> {{ $lead->contact_phone }}</a>
                                    @endif
                                    @if ($lead->contact_email)
                                        <a href="mailto:{{ $lead->contact_email }}"><i data-lucide="mail" aria-hidden="true"></i> {{ $lead->contact_email }}</a>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($canEdit && $editing !== $lead->id)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="edit('{{ $lead->id }}')">{{ $key === 'none' ? 'Set a next step' : 'Change next step' }}</button>
                                @endif
                                <a href="{{ $url }}" class="btn btn-ghost btn-sm" wire:navigate>Open</a>
                            </div>
                            @if ($editing === $lead->id)
                                <form class="fu-edit" wire:submit="save">
                                    <div class="field">
                                        <label class="label" for="fu-what">Next step</label>
                                        <input id="fu-what" type="text" class="input" wire:model="stepWhat" maxlength="255" placeholder="e.g. Call Ada about the proposal" autofocus
                                               @error('stepWhat') aria-invalid="true" aria-describedby="fu-what-error" @enderror>
                                        @error('stepWhat') <span class="field-error" id="fu-what-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label class="label" for="fu-due">By</label>
                                        <input id="fu-due" type="date" class="input" wire:model="stepDue" @error('stepDue') aria-invalid="true" aria-describedby="fu-due-error" @enderror>
                                        @error('stepDue') <span class="field-error" id="fu-due-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="fu-edit-actions">
                                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="save">Save</button>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="cancel">Cancel</button>
                                    </div>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
