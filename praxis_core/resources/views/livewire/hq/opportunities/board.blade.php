<div class="opb">
    <style>
        /* Opportunities board: only what the shared component classes don't cover. Brand tokens only. */
        .opb-tools { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; margin-bottom: 20px; }
        .opb-tools .field { min-width: 0; }
        .opb-search { flex: 1 1 240px; max-width: 360px; }
        .opb-lead { flex: 0 1 220px; }
        .opb-teamonly { display: flex; align-items: center; gap: 8px; margin: 0 0 16px; }
        .opb-teamonly svg { width: 16px; height: 16px; flex-shrink: 0; }
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
        .opb-modal { max-width: 600px; }
        .opb-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
        .opb-neworg { display: flex; flex-direction: column; gap: 14px; padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist); }
        .opb-neworg-title { margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .opb-neworg-actions { display: flex; justify-content: flex-end; gap: 12px; }
        .opb-more { display: inline-flex; align-items: center; gap: 6px; align-self: flex-start; padding: 0; border: 0; background: none; color: var(--color-deep-teal); font: inherit; font-size: 0.875rem; font-weight: 600; cursor: pointer; }
        .opb-more svg { width: 16px; height: 16px; }
        .opb-link { padding: 0; border: 0; background: none; color: var(--color-deep-teal); font: inherit; font-weight: 600; text-decoration: underline; cursor: pointer; }
        .opb-radios { display: flex; flex-wrap: wrap; gap: 16px; }
        .opb-radios label { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .opb-table td a { font-weight: 600; text-decoration: none; }
        .opb-table td a:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            .opb-search, .opb-lead { flex: 1 1 100%; max-width: none; }
            .opb-board { grid-auto-columns: minmax(78vw, 1fr); }
            .opb-two { grid-template-columns: 1fr; }
            .opb .modal-actions, .opb-neworg-actions { flex-direction: column-reverse; }
            .opb .modal-actions .btn, .opb-neworg-actions .btn { width: 100%; }
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

    @if ($teamOnly)
        <p class="help opb-teamonly"><i data-lucide="users"></i> {{ __('opportunities.team_only') }}</p>
    @endif

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
                            <th scope="col">{{ __('opportunities.fields.team') }}</th>
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
                                <td style="white-space: nowrap">{{ trans_choice('opportunities.team_count', $o->members_count + ($o->lead_user_id ? 1 : 0)) }}</td>
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
                                        @if ($o->members_count)
                                            <span><i data-lucide="users"></i> {{ trans_choice('opportunities.team_count', $o->members_count + ($o->lead_user_id ? 1 : 0)) }}</span>
                                        @endif
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
            <div class="modal opb-modal" role="dialog" aria-modal="true" aria-labelledby="opb-create-title">
                <h2 class="modal-title" id="opb-create-title">{{ __('opportunities.create.title') }}</h2>
                <p class="help" style="margin: 0 0 16px">{{ __('opportunities.create.intro') }}</p>
                <form wire:submit="create" class="opb-form" novalidate>
                    @if ($newOrg)
                        <section class="opb-neworg" aria-labelledby="opb-neworg-title" wire:key="new-org">
                            <div>
                                <h3 class="opb-neworg-title" id="opb-neworg-title">{{ __('opportunities.create.new_org.title') }}</h3>
                                <p class="help" style="margin: 2px 0 0">{{ __('opportunities.create.new_org.intro') }}</p>
                            </div>
                            <div class="field">
                                <label for="opb-no-name" class="label">{{ __('opportunities.create.new_org.name') }}</label>
                                <input id="opb-no-name" type="text" class="input" wire:model="newOrgName" maxlength="150" autofocus
                                       @error('newOrgName') aria-invalid="true" aria-describedby="opb-no-name-error" @enderror>
                                @error('newOrgName')
                                    <span class="field-error" id="opb-no-name-error">{{ $message }}
                                        @if (! empty($existingOrg['usable']))
                                            <button type="button" class="opb-link" wire:click="useExistingOrganisation">{{ __('opportunities.create.new_org.use_existing') }}</button>
                                        @endif
                                    </span>
                                @enderror
                            </div>
                            <div class="opb-two">
                                <div class="field">
                                    <label for="opb-no-country" class="label">{{ __('opportunities.create.new_org.country') }}</label>
                                    <input id="opb-no-country" type="text" class="input" wire:model="newOrgCountry" maxlength="100" autocomplete="off"
                                           @error('newOrgCountry') aria-invalid="true" aria-describedby="opb-no-country-error" @enderror>
                                    @error('newOrgCountry') <span class="field-error" id="opb-no-country-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="opb-no-city" class="label">{{ __('opportunities.create.new_org.city') }}</label>
                                    <input id="opb-no-city" type="text" class="input" wire:model="newOrgCity" maxlength="100" autocomplete="off"
                                           @error('newOrgCity') aria-invalid="true" aria-describedby="opb-no-city-error" @enderror>
                                    @error('newOrgCity') <span class="field-error" id="opb-no-city-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="opb-two">
                                <div class="field">
                                    <label for="opb-no-industry" class="label">{{ __('opportunities.create.new_org.industry') }}</label>
                                    <select id="opb-no-industry" class="select" wire:model="newOrgIndustry">
                                        <option value="">{{ __('opportunities.create.new_org.choose_industry') }}</option>
                                        @foreach ($industries as $industry)
                                            <option value="{{ $industry }}">{{ $industry }}</option>
                                        @endforeach
                                    </select>
                                    @error('newOrgIndustry') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                                    <legend class="label" style="margin-bottom: 6px">{{ __('opportunities.create.new_org.language') }}</legend>
                                    <div class="opb-radios" style="min-height: 40px; align-items: center">
                                        <label><input type="radio" wire:model="newOrgLanguage" value="EN"> {{ __('opportunities.languages.en') }}</label>
                                        <label><input type="radio" wire:model="newOrgLanguage" value="FR"> {{ __('opportunities.languages.fr') }}</label>
                                    </div>
                                    @error('newOrgLanguage') <span class="field-error">{{ $message }}</span> @enderror
                                </fieldset>
                            </div>

                            <button type="button" class="opb-more" wire:click="toggleNewOrgMore" aria-expanded="{{ $newOrgMore ? 'true' : 'false' }}" aria-controls="opb-no-more">
                                <i data-lucide="{{ $newOrgMore ? 'chevron-up' : 'chevron-down' }}"></i>
                                {{ $newOrgMore ? __('opportunities.create.new_org.less') : __('opportunities.create.new_org.more') }}
                            </button>
                            @if ($newOrgMore)
                                <div class="opb-form" id="opb-no-more" wire:key="new-org-more">
                                    <div class="opb-two">
                                        <div class="field">
                                            <label for="opb-no-stage" class="label">{{ __('opportunities.create.new_org.stage') }}</label>
                                            <input id="opb-no-stage" type="text" class="input" wire:model="newOrgStage" maxlength="100" placeholder="{{ __('opportunities.create.new_org.stage_hint') }}">
                                            @error('newOrgStage') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="field">
                                            <label for="opb-no-systems" class="label">{{ __('opportunities.create.new_org.systems') }}</label>
                                            <select id="opb-no-systems" class="select" wire:model="newOrgSystems">
                                                <option value="">{{ __('opportunities.create.new_org.choose_systems') }}</option>
                                                @foreach ($systems as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('newOrgSystems') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="opb-two">
                                        <div class="field">
                                            <label for="opb-no-gov" class="label">{{ __('opportunities.create.new_org.governance') }}</label>
                                            <input id="opb-no-gov" type="text" class="input" wire:model="newOrgGovernance" maxlength="100" placeholder="{{ __('opportunities.create.new_org.governance_hint') }}">
                                            @error('newOrgGovernance') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="field">
                                            <label for="opb-no-turnover" class="label">{{ __('opportunities.create.new_org.turnover') }}</label>
                                            <input id="opb-no-turnover" type="text" inputmode="numeric" class="input" wire:model="newOrgTurnover" maxlength="15"
                                                   @error('newOrgTurnover') aria-invalid="true" aria-describedby="opb-no-turnover-error" @enderror>
                                            @error('newOrgTurnover') <span class="field-error" id="opb-no-turnover-error">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="opb-two">
                                        <div class="field">
                                            <label for="opb-no-budget" class="label">{{ __('opportunities.create.new_org.budget') }}</label>
                                            <input id="opb-no-budget" type="text" class="input" wire:model="newOrgBudget" maxlength="50">
                                            @error('newOrgBudget') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="field">
                                            <label for="opb-no-tz" class="label">{{ __('opportunities.create.new_org.timezone') }}</label>
                                            <select id="opb-no-tz" class="select" wire:model="newOrgTimezone" @error('newOrgTimezone') aria-invalid="true" @enderror>
                                                @foreach ($timeZones as $zone)
                                                    <option value="{{ $zone }}">{{ str_replace('_', ' ', $zone) }}</option>
                                                @endforeach
                                                @if ($newOrgTimezone !== '' && ! in_array($newOrgTimezone, $timeZones, true))
                                                    <option value="{{ $newOrgTimezone }}">{{ $newOrgTimezone }}</option>
                                                @endif
                                            </select>
                                            @error('newOrgTimezone') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label for="opb-no-address" class="label">{{ __('opportunities.create.new_org.billing_address') }}</label>
                                        <textarea id="opb-no-address" class="textarea" rows="2" wire:model="newOrgBillingAddress" maxlength="2000"></textarea>
                                        @error('newOrgBillingAddress') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="opb-no-tax" class="label">{{ __('opportunities.create.new_org.tax_id') }}</label>
                                        <input id="opb-no-tax" type="text" class="input" wire:model="newOrgTaxId" maxlength="100">
                                        @error('newOrgTaxId') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            @endif

                            <div class="opb-neworg-actions">
                                <button type="button" class="btn btn-secondary" wire:click="cancelNewOrganisation">{{ __('opportunities.create.new_org.back') }}</button>
                                <button type="button" class="btn btn-primary" wire:click="saveNewOrganisation" wire:loading.attr="disabled" wire:target="saveNewOrganisation">
                                    <span wire:loading.remove wire:target="saveNewOrganisation">{{ __('opportunities.create.new_org.save') }}</span>
                                    <span wire:loading wire:target="saveNewOrganisation">{{ __('opportunities.create.new_org.saving') }}</span>
                                </button>
                            </div>
                        </section>
                    @else
                        <x-hq.picker id="opb-org" :label="__('opportunities.fields.organisation')" model="orgSearch" :options="$organisations" :selected="$organisationSelected"
                                     choose="chooseOrganisation" remove="clearOrganisation" :placeholder="__('opportunities.create.find_hint')"
                                     :help="$orgAdded !== '' ? $orgAdded : __('opportunities.create.org_help')" :error="$errors->first('organizationId')"
                                     :empty="__('opportunities.create.no_org')" :term="$orgSearch" :change="__('opportunities.create.change')" :avatars="false" :autofocus="true">
                            <x-slot:footer>
                                @if ($canAddOrganisation)
                                    <button type="button" class="pk-opt pk-add" role="option" id="opb-org-opt-{{ count($organisations) }}" tabindex="-1"
                                            :class="{ 'is-active': active === {{ count($organisations) }} }" :aria-selected="active === {{ count($organisations) }} ? 'true' : 'false'"
                                            x-on:mouseenter="active = {{ count($organisations) }}" wire:click="startNewOrganisation" x-on:click="close()">
                                        <i data-lucide="plus"></i> {{ __('opportunities.create.add_org', ['name' => trim($orgSearch)]) }}
                                    </button>
                                @endif
                            </x-slot:footer>
                        </x-hq.picker>
                    @endif

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

                    <x-hq.picker id="opb-lead-user" :label="__('opportunities.fields.lead')" model="leadSearch" :options="$leadOptions" :selected="$leadSelected"
                                 choose="chooseLead" remove="clearLead" :placeholder="__('opportunities.create.team_hint')" :help="__('opportunities.create.lead_help')"
                                 :error="$errors->first('leadUserId')" :empty="__('opportunities.create.no_staff')" :term="$leadSearch" :change="__('opportunities.create.change')" />

                    <x-hq.picker id="opb-team" :label="__('opportunities.fields.team')" model="teamSearch" :options="$teamOptions" :selected="$teamSelected" :multiple="true"
                                 choose="addMember" remove="removeMember" :placeholder="__('opportunities.create.team_hint')" :help="__('opportunities.create.team_help')"
                                 :error="$errors->first('teamUserIds') ?: $errors->first('teamUserIds.*')" :empty="__('opportunities.create.no_staff')" :term="$teamSearch"
                                 :removeLabel="__('opportunities.create.remove_member')" />

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
                    @include('livewire.hq.opportunities.partials.billing-company', ['prefix' => 'opb'])
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeCreate">{{ __('opportunities.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="create" @if ($newOrg) disabled @endif>
                            <span wire:loading.remove wire:target="create">{{ __('opportunities.create.submit') }}</span>
                            <span wire:loading wire:target="create">{{ __('opportunities.create.submitting') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
