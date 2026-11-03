{{-- The segment builder (BuildsSegments, docs/CAMPAIGNS.md 4.2): who and the filters, with live numbers. $onSave: the method that saves. --}}
@if ($segmentOpen)
    @php
        $groupNames = ['clients' => 'clients', 'leads' => 'leads', 'subscribers' => 'subscribers'];
        $kindNames = ['client' => 'Clients', 'lead' => 'Leads', 'kaizen' => 'Kaizen writers', 'subscriber' => 'Subscribers'];
    @endphp
    <style>
        .sgb { max-width: 640px; }
        .sgb-form { display: flex; flex-direction: column; gap: 16px; }
        .sgb-who { display: flex; flex-wrap: wrap; gap: 8px 20px; }
        .sgb-who label { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .sgb-filters { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
        .sgb-filter { position: relative; }
        .sgb-trigger { width: 100%; display: flex; align-items: center; min-height: 40px; text-align: left; cursor: pointer; font: inherit; overflow: hidden; }
        .sgb-trigger span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sgb-trigger[aria-expanded="true"] { border-color: var(--color-deep-teal); }
        .sgb-options { position: absolute; z-index: 5; left: 0; right: 0; top: calc(100% + 4px); max-height: 240px; overflow-y: auto; padding: 6px; background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-control); box-shadow: var(--shadow-float); }
        .sgb-options label { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: var(--radius-control); font-size: 0.875rem; color: var(--color-ink); cursor: pointer; }
        .sgb-options label:hover { background: var(--color-mist); }
        .sgb-count { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 16px; padding: 12px 16px; border-radius: var(--radius-card); background: var(--color-mist); font-size: 0.875rem; color: var(--color-slate); }
        .sgb-count strong { font-size: 1.25rem; color: var(--color-ink); font-variant-numeric: tabular-nums; }
        @media (max-width: 600px) { .sgb-filters { grid-template-columns: 1fr; } .sgb .modal-actions { flex-direction: column-reverse; } .sgb .modal-actions .btn { width: 100%; } }
    </style>
    <div class="modal-backdrop" wire:click.self="closeSegment" @keydown.escape.window="$wire.closeSegment()">
        <div class="modal sgb" role="dialog" aria-modal="true" aria-labelledby="sgb-title">
            <h2 class="modal-title" id="sgb-title">{{ $segmentEditing ? 'Edit segment' : 'New segment' }}</h2>
            <p class="help mb-4">Only people who already know us: clients’, leads’ and subscribers’ addresses that are not on the do-not-email list. Outreach prospects are never in a segment.</p>
            <form class="sgb-form" wire:submit="{{ $onSave }}">
                <div class="field">
                    <label class="label" for="sgb-name">Name</label>
                    <input id="sgb-name" type="text" class="input" maxlength="120" wire:model="segmentName" placeholder="e.g. Clients in Cameroon" autofocus>
                    @error('segmentName') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <fieldset class="field">
                    <legend class="label mb-2">Who</legend>
                    <div class="sgb-who">
                        <label><input type="checkbox" class="checkbox" value="clients" wire:model.live="segmentWho"> Clients’ contacts</label>
                        <label><input type="checkbox" class="checkbox" value="leads" wire:model.live="segmentWho"> Leads’ contacts</label>
                        <label><input type="checkbox" class="checkbox" value="subscribers" wire:model.live="segmentWho"> Subscribers and Kaizen writers</label>
                    </div>
                    @error('segmentWho') <span class="field-error">{{ $message }}</span> @enderror
                </fieldset>
                <fieldset class="field">
                    <legend class="label mb-2">Only those who match <span class="help">(optional: leave a filter on Any)</span></legend>
                    <div class="sgb-filters">
                        @foreach ($segmentDefinitions as $key => $definition)
                            @php
                                $chosen = $segmentFilters[$key] ?? [];
                                $shown = $chosen ? implode(', ', array_map(fn ($v) => $definition['options'][$v] ?? $v, $chosen)) : 'Any';
                                $applies = array_intersect($definition['groups'], [...$segmentWho, ...(in_array('subscribers', $segmentWho, true) ? ['kaizen'] : [])]);
                            @endphp
                            <div class="field" wire:key="f-{{ $key }}">
                                <span class="label" id="sgb-l-{{ $key }}">{{ $definition['label'] }}</span>
                                {{-- Alpine keeps it open while Livewire redraws the page after each tick --}}
                                <div class="sgb-filter" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false">
                                    <button type="button" class="select sgb-trigger" @click="open = ! open" aria-expanded="false" :aria-expanded="open.toString()" aria-labelledby="sgb-l-{{ $key }} sgb-v-{{ $key }}"><span id="sgb-v-{{ $key }}">{{ $shown }}</span></button>
                                    <div class="sgb-options" role="group" aria-labelledby="sgb-l-{{ $key }}" x-show="open" x-cloak>
                                        @forelse ($definition['options'] as $value => $label)
                                            <label wire:key="o-{{ $key }}-{{ md5($value) }}"><input type="checkbox" class="checkbox" value="{{ $value }}" wire:model.live="segmentFilters.{{ $key }}"> {{ $label }}</label>
                                        @empty
                                            <p class="help m-0 p-2">Nothing to choose from yet.</p>
                                        @endforelse
                                        @if ($chosen)
                                            <button type="button" class="btn btn-ghost btn-sm" wire:click="clearSegmentFilter('{{ $key }}')">Any {{ mb_strtolower($definition['label']) }}</button>
                                        @endif
                                    </div>
                                </div>
                                @if ($chosen && ! $applies)
                                    <span class="help">Doesn’t apply to the people chosen above.</span>
                                @elseif ($chosen && in_array('subscribers', $segmentWho, true) && ! in_array('subscribers', $definition['groups'], true) && ! ($key === 'client_status' || str_starts_with($key, 'lead_')))
                                    <span class="help">Subscribers have no {{ mb_strtolower($definition['label']) }}, so they are left out while this is set.</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </fieldset>
                @if ($segmentSummary)
                    <div class="sgb-count" role="status" aria-live="polite">
                        <span><strong>{{ $segmentSummary['total'] }}</strong> {{ $segmentSummary['total'] === 1 ? 'person' : 'people' }} today</span>
                        @foreach ($segmentSummary['by_kind'] as $k => $n)
                            <span>{{ $kindNames[$k] }}: {{ $n }}</span>
                        @endforeach
                        <span>English: {{ $segmentSummary['by_language']['en'] }} · French: {{ $segmentSummary['by_language']['fr'] }}</span>
                        @if ($segmentSummary['left_out'] > 0)
                            <span>{{ $segmentSummary['left_out'] }} left out (do not email)</span>
                        @endif
                    </div>
                @endif
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="closeSegment">Cancel</button>
                    <button type="submit" class="btn btn-primary">{{ $segmentEditing ? 'Save segment' : 'Create segment' }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
