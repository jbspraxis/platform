<div class="opb">
    <style>
        /* Opportunities board: only what the shared component classes don't cover. Brand tokens only. */
        .opb-tools { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; margin-bottom: 20px; }
        .opb-tools .field { min-width: 0; }
        .opb-search { flex: 1 1 240px; max-width: 360px; }
        .opb-lead { flex: 0 1 220px; }
        .opb-check { display: flex; align-items: center; gap: 8px; min-height: 40px; font-size: 0.875rem; color: var(--color-ink); }
        .opb-board-wrap { overflow-x: auto; padding-bottom: 8px; margin: 0 -4px; }
        .opb-board { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(240px, 1fr); gap: 12px; padding: 0 4px; min-width: min-content; }
        .opb-col { background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); display: flex; flex-direction: column; min-height: 120px; }
        .opb-col-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 14px; border-bottom: 1px solid var(--color-line); font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .opb-col-body { display: flex; flex-direction: column; gap: 8px; padding: 10px; }
        .opb-card { display: block; padding: 12px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); color: var(--color-slate); text-decoration: none; transition: border-color var(--motion-instant), background var(--motion-instant); }
        .opb-card:hover { border-color: var(--color-deep-teal); background: var(--color-mist); }
        .opb-card-title { display: block; color: var(--color-ink); font-weight: 600; font-size: 0.875rem; line-height: 1.25rem; overflow-wrap: anywhere; }
        .opb-card-org { display: block; margin-top: 2px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); overflow-wrap: anywhere; }
        .opb-card-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin-top: 10px; font-size: 0.75rem; line-height: 1rem; color: var(--color-muted); }
        .opb-card-meta > span { display: inline-flex; align-items: center; gap: 4px; }
        .opb-card-meta svg { width: 14px; height: 14px; flex-shrink: 0; }
        .opb-none { padding: 12px 4px; font-size: 0.8125rem; color: var(--color-muted); text-align: center; }
        .opb-form { display: flex; flex-direction: column; gap: 16px; }
        .opb-radios { display: flex; flex-wrap: wrap; gap: 16px; }
        .opb-radios label { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .opb-table td a { font-weight: 600; text-decoration: none; }
        .opb-table td a:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            .opb-search, .opb-lead { flex: 1 1 100%; max-width: none; }
            .opb-board { grid-auto-columns: minmax(78vw, 1fr); }
            .opb .modal-actions { flex-direction: column-reverse; }
            .opb .modal-actions .btn { width: 100%; }
        }
    </style>

    @php
        $tone = fn (string $stage) => match ($stage) { 'won' => 'pill-success', 'lost' => 'pill-danger', 'on_hold' => 'pill-warning', default => 'pill-info' };
        $day = fn ($at) => $at?->copy()->setTimezone('Africa/Lagos')->locale(app()->getLocale())->translatedFormat('j M Y');
        $name = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : __('opportunities.no_lead');
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('opportunities.title') }}</h1>
            <p class="page-subtitle">{{ __('opportunities.subtitle') }}</p>
        </div>
        @if ($canCreate)
            <button type="button" class="btn btn-primary" wire:click="openCreate">
                <i data-lucide="plus"></i> {{ __('opportunities.create.button') }}
            </button>
        @endif
    </div>

    <x-flash class="mb-6" />

    <div class="opb-tools" role="search">
        <div class="field opb-search">
            <label for="opb-search" class="label">{{ __('opportunities.filters.search') }}</label>
            <input id="opb-search" type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="{{ __('opportunities.filters.search_hint') }}">
        </div>
        <div class="field opb-lead">
            <label for="opb-lead" class="label">{{ __('opportunities.filters.lead') }}</label>
            <select id="opb-lead" class="select" wire:model.live="lead">
                <option value="">{{ __('opportunities.filters.everyone') }}</option>
                <option value="mine">{{ __('opportunities.filters.mine') }}</option>
                @foreach ($staff as $person)
                    <option value="{{ $person->id }}" wire:key="lead-{{ $person->id }}">{{ $name($person) }}</option>
                @endforeach
            </select>
        </div>
        <label class="opb-check"><input type="checkbox" class="checkbox" wire:model.live="closed"> {{ __('opportunities.filters.closed') }}</label>
        <div class="segmented" role="group" aria-label="{{ __('opportunities.filters.view') }}" style="margin-left: auto">
            <button type="button" class="segmented-item" aria-pressed="{{ $view === 'board' ? 'true' : 'false' }}" wire:click="$set('view', 'board')">
                <i data-lucide="columns-3"></i> {{ __('opportunities.filters.board') }}
            </button>
            <button type="button" class="segmented-item" aria-pressed="{{ $view === 'table' ? 'true' : 'false' }}" wire:click="$set('view', 'table')">
                <i data-lucide="list"></i> {{ __('opportunities.filters.table') }}
            </button>
        </div>
    </div>

    @if ($opportunities->isEmpty() && $search === '' && $lead === '')
        <div class="card empty-state">
            <strong>{{ __('opportunities.empty.title') }}</strong>
            {{ __('opportunities.empty.text') }}
            @if ($canCreate)
                <div style="margin-top: 16px">
                    <button type="button" class="btn btn-primary" wire:click="openCreate"><i data-lucide="plus"></i> {{ __('opportunities.create.button') }}</button>
                </div>
            @endif
        </div>
    @elseif ($opportunities->isEmpty())
        <div class="card empty-state">
            <strong>{{ __('opportunities.empty.filtered_title') }}</strong>
            {{ __('opportunities.empty.filtered_text') }}
            <div style="margin-top: 16px">
                <button type="button" class="btn btn-secondary" wire:click="$set('search', ''); $set('lead', '')">{{ __('opportunities.empty.clear') }}</button>
            </div>
        </div>
    @elseif ($view === 'table')
        <div class="card" style="padding: 0">
            <div class="table-wrap">
                <table class="table opb-table">
                    <caption class="sr-only">{{ __('opportunities.title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('opportunities.fields.title') }}</th>
                            <th scope="col">{{ __('opportunities.fields.organisation') }}</th>
                            <th scope="col">{{ __('opportunities.fields.stage') }}</th>
                            <th scope="col">{{ __('opportunities.fields.lead') }}</th>
                            <th scope="col">{{ __('opportunities.fields.language') }}</th>
                            <th scope="col">{{ __('opportunities.fields.updated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($opportunities as $o)
                            <tr wire:key="row-{{ $o->id }}">
                                <td><a href="{{ route('hq.crm.opportunity', $o) }}" wire:navigate>{{ $o->title }}</a></td>
                                <td>{{ $o->organization?->name }}</td>
                                <td><span class="pill {{ $tone($o->stage) }}">{{ __('opportunities.stages.' . $o->stage) }}</span></td>
                                <td>{{ $name($o->lead) }}</td>
                                <td><span class="tag">{{ __('opportunities.languages.' . $o->language) }}</span></td>
                                <td style="white-space: nowrap">{{ $day($o->updated_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="opb-board-wrap" tabindex="0" aria-label="{{ __('opportunities.filters.board') }}">
            <div class="opb-board">
                @foreach ($stages as $stage)
                    @php $items = $byStage->get($stage, collect()); @endphp
                    <section class="opb-col" wire:key="col-{{ $stage }}" aria-labelledby="col-{{ $stage }}">
                        <h2 class="opb-col-head" id="col-{{ $stage }}" style="margin: 0">
                            <span>{{ __('opportunities.stages.' . $stage) }}</span>
                            <span class="tag" aria-label="{{ trans_choice('opportunities.count', $items->count()) }}">{{ $items->count() }}</span>
                        </h2>
                        <div class="opb-col-body">
                            @forelse ($items as $o)
                                <a href="{{ route('hq.crm.opportunity', $o) }}" class="opb-card" wire:navigate wire:key="card-{{ $o->id }}">
                                    <span class="opb-card-title">{{ $o->title }}</span>
                                    <span class="opb-card-org">{{ $o->organization?->name }}</span>
                                    <span class="opb-card-meta">
                                        <span><i data-lucide="user-round"></i> {{ $name($o->lead) }}</span>
                                        <span class="tag">{{ strtoupper($o->language) }}</span>
                                        @if ($o->meetings_count)
                                            <span><i data-lucide="calendar"></i> {{ trans_choice('opportunities.meetings_count', $o->meetings_count) }}</span>
                                        @endif
                                        @if ($o->proposals_count)
                                            <span><i data-lucide="file-text"></i> {{ trans_choice('opportunities.proposals_count', $o->proposals_count) }}</span>
                                        @endif
                                    </span>
                                </a>
                            @empty
                                <p class="opb-none">{{ __('opportunities.none_here') }}</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif

    @if ($creating)
        <div class="modal-backdrop" wire:key="create-modal" x-data x-on:keydown.escape.window="$wire.closeCreate()">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="opb-create-title">
                <h2 class="modal-title" id="opb-create-title">{{ __('opportunities.create.title') }}</h2>
                <p class="help" style="margin: 0 0 16px">{{ __('opportunities.create.intro') }}</p>
                <form wire:submit="create" class="opb-form" novalidate>
                    <div class="field">
                        <label for="opb-org-search" class="label">{{ __('opportunities.create.find_organisation') }}</label>
                        <input id="opb-org-search" type="search" class="input" wire:model.live.debounce.300ms="orgSearch" autofocus
                               placeholder="{{ __('opportunities.create.find_hint') }}">
                    </div>
                    <div class="field">
                        <label for="opb-org" class="label">{{ __('opportunities.fields.organisation') }}</label>
                        <select id="opb-org" class="select" wire:model.live="organizationId"
                                @error('organizationId') aria-invalid="true" aria-describedby="opb-org-error" @enderror>
                            <option value="">{{ __('opportunities.create.choose_organisation') }}</option>
                            @foreach ($organisations as $org)
                                <option value="{{ $org->id }}" wire:key="org-{{ $org->id }}">{{ $org->name }}</option>
                            @endforeach
                        </select>
                        @error('organizationId') <span class="field-error" id="opb-org-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="opb-title" class="label">{{ __('opportunities.fields.title') }}</label>
                        <input id="opb-title" type="text" class="input" wire:model="title" maxlength="200"
                               @error('title') aria-invalid="true" aria-describedby="opb-title-error" @else aria-describedby="opb-title-help" @enderror>
                        @error('title')
                            <span class="field-error" id="opb-title-error">{{ $message }}</span>
                        @else
                            <span class="help" id="opb-title-help">{{ __('opportunities.create.title_help') }}</span>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="opb-lead-user" class="label">{{ __('opportunities.fields.lead') }}</label>
                        <select id="opb-lead-user" class="select" wire:model="leadUserId"
                                @error('leadUserId') aria-invalid="true" aria-describedby="opb-lead-error" @else aria-describedby="opb-lead-help" @enderror>
                            <option value="">{{ __('opportunities.create.choose_lead') }}</option>
                            @foreach ($staff as $person)
                                <option value="{{ $person->id }}" wire:key="lead-opt-{{ $person->id }}">{{ $name($person) }}</option>
                            @endforeach
                        </select>
                        @error('leadUserId')
                            <span class="field-error" id="opb-lead-error">{{ $message }}</span>
                        @else
                            <span class="help" id="opb-lead-help">{{ __('opportunities.create.lead_help') }}</span>
                        @enderror
                    </div>
                    <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                        <legend class="label" style="margin-bottom: 6px">{{ __('opportunities.fields.language') }}</legend>
                        <div class="opb-radios">
                            @foreach (\App\Models\Opportunity::LANGUAGES as $code)
                                <label><input type="radio" wire:model="language" value="{{ $code }}"> {{ __('opportunities.languages.' . $code) }}</label>
                            @endforeach
                        </div>
                        <span class="help">{{ __('opportunities.create.language_help') }}</span>
                        @error('language') <span class="field-error">{{ $message }}</span> @enderror
                    </fieldset>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeCreate">{{ __('opportunities.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="create">
                            <span wire:loading.remove wire:target="create">{{ __('opportunities.create.submit') }}</span>
                            <span wire:loading wire:target="create">{{ __('opportunities.create.submitting') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
