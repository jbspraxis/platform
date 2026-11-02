<div class="pp">
    @include('livewire.hq.opportunities.partials.document-styles')

    @php
        $locale = app()->getLocale();
        $money = fn ($amount, $currency) => $locale === 'fr'
            ? number_format((float) $amount, 2, ',', "\u{202F}") . "\u{00A0}" . $currency
            : $currency . "\u{00A0}" . number_format((float) $amount, 2);
        $percent = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, $locale === 'fr' ? ',' : '.', ''), '0'), '.,') . ($locale === 'fr' ? "\u{202F}%" : '%');
        $currency = (string) ($payload['currency'] ?? '');
        $milestones = (array) ($payload['milestones'] ?? []);
        $roles = (array) ($payload['roles'] ?? []);
        $weightSum = \App\Support\Opportunities\Sows::weightTotal($milestones);
        $amountSum = round(array_sum(array_map(fn ($m) => (float) ($m['amount'] ?? 0), $milestones)), 2);
    @endphp

    @if ($drafting)
        <div wire:poll.3s="check" hidden></div>
    @endif

    @if ($draft)
        <section class="card pp-bar" aria-labelledby="pd-title">
            <div class="pp-bar-text">
                <h2 class="card-title pp-title" id="pd-title">
                    {{ __('project_draft.title') }}
                    <span class="pill {{ $draft->status === 'created' ? 'pill-success' : ($drafting ? 'pill-info' : 'pill-neutral') }}">{{ __('project_draft.statuses.' . $draft->status) }}</span>
                </h2>
                <p class="pp-sub">{{ __('project_draft.from', ['ref' => $draft->sow?->reference, 'n' => $draft->sow?->version, 'total' => $money($payload['total'] ?? 0, $currency)]) }}</p>
                <p class="pp-sub">{{ __('project_draft.lead', ['name' => $lead ? trim($lead->first_name . ' ' . $lead->last_name) : __('opportunities.no_lead')]) }}</p>
            </div>
            @if ($canEdit && ! $redrafting)
                <div class="pp-buttons">
                    <button type="button" class="btn btn-secondary" wire:click="openRedraft"><i data-lucide="sparkles"></i> {{ __('project_draft.redraft.button') }}</button>
                </div>
            @endif
        </section>

        @error('draft')
            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
        @enderror

        @if ($drafting)
            <section class="card" aria-labelledby="pd-drafting-title" aria-busy="true">
                <h2 class="card-title pp-title" id="pd-drafting-title"><i data-lucide="loader-circle" class="pp-spin"></i> {{ __('project_draft.drafting.title') }}</h2>
                <p class="pp-intro" style="margin-bottom: 0">{{ __('project_draft.drafting.text') }}</p>
                @if ($draft->aiRun)
                    <div class="pp-progress" role="progressbar" aria-label="{{ __('project_draft.drafting.progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $draft->aiRun->progress }}">
                        <span style="width: {{ max(4, (int) $draft->aiRun->progress) }}%"></span>
                    </div>
                @endif
            </section>
        @elseif ($draftError)
            <div class="alert alert-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <span>{{ __('project_draft.drafting.failed', ['message' => $draftError]) }}
                    @if ($canEdit) <button type="button" class="btn btn-ghost btn-sm" wire:click="dismissFailure">{{ __('proposals.dismiss') }}</button> @endif
                </span>
            </div>
        @elseif (! empty($payload['unavailable']))
            <div class="callout pp-callout" role="note"><i data-lucide="info"></i><span>{{ __('project_draft.unavailable') }}</span></div>
        @endif

        @if ($redrafting && $canEdit)
            <section class="card" aria-labelledby="pd-redraft-title">
                <h2 class="card-title" id="pd-redraft-title">{{ __('project_draft.redraft.title') }}</h2>
                <p class="pp-intro">{{ __('project_draft.redraft.intro') }}</p>
                <div class="pp-form">
                    <div class="field">
                        <label for="pd-instructions" class="label">{{ __('proposals.start.instructions') }}</label>
                        <textarea id="pd-instructions" class="textarea" rows="3" maxlength="2000" wire:model="instructions" aria-describedby="pd-instructions-help" x-init="$nextTick(() => $el.focus())"></textarea>
                        <span class="help" id="pd-instructions-help">{{ __('project_draft.redraft.help') }}</span>
                        @error('instructions') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="pp-buttons">
                        <button type="button" class="btn btn-primary" wire:click="redraft" wire:confirm="{{ __('project_draft.redraft.confirm') }}" wire:loading.attr="disabled" wire:target="redraft"><i data-lucide="sparkles"></i> {{ __('project_draft.redraft.submit') }}</button>
                        <button type="button" class="btn btn-secondary" wire:click="cancelRedraft">{{ __('opportunities.cancel') }}</button>
                    </div>
                </div>
            </section>
        @endif

        @unless ($drafting)
            {{-- The project --}}
            <section class="card" aria-labelledby="pd-project-title">
                <h2 class="card-title" id="pd-project-title">{{ __('project_draft.project.title') }}</h2>
                <p class="pp-intro">{{ $canEdit ? __('project_draft.project.intro') : __('project_draft.read_only') }}</p>
                <div class="pp-form">
                    <div class="field">
                        <label for="pd-name" class="label">{{ __('project_draft.project.name') }}</label>
                        <input id="pd-name" type="text" class="input" maxlength="255" wire:model.live.blur="payload.project.title" @disabled(! $canEdit)>
                    </div>
                    <div class="field">
                        <label for="pd-description" class="label">{{ __('project_draft.project.description') }}</label>
                        <textarea id="pd-description" class="textarea" rows="3" maxlength="5000" wire:model.live.blur="payload.project.description" @disabled(! $canEdit)></textarea>
                    </div>
                    <div class="field" style="max-width: 240px">
                        <label for="pd-start" class="label">{{ __('project_draft.project.start') }}</label>
                        <input id="pd-start" type="date" class="input" wire:model.live.blur="payload.project.start_date" aria-describedby="pd-start-help" @disabled(! $canEdit)>
                        <span class="help" id="pd-start-help">{{ __('project_draft.project.start_help') }}</span>
                    </div>
                </div>
            </section>

            {{-- Roles and who takes each --}}
            <section class="card" aria-labelledby="pd-roles-title">
                <h2 class="card-title" id="pd-roles-title">{{ __('project_draft.roles.title') }}</h2>
                <p class="pp-intro">{{ __('project_draft.roles.intro') }}</p>
                <div class="pp-lines">
                    @foreach ($roles as $r => $role)
                        <div class="pd-task" wire:key="pr-{{ $role['key'] }}">
                            <div class="field">
                                <label for="pd-r-{{ $r }}-title" class="label">{{ __('project_draft.roles.role') }}</label>
                                <input id="pd-r-{{ $r }}-title" type="text" class="input" maxlength="100" wire:model.live.blur="payload.roles.{{ $r }}.title" @disabled(! $canEdit)>
                            </div>
                            <div class="field" style="grid-column: span 3">
                                <label for="pd-r-{{ $r }}-user" class="label">{{ __('project_draft.roles.person') }}</label>
                                <select id="pd-r-{{ $r }}-user" class="select" wire:model.live="payload.roles.{{ $r }}.user_id" @disabled(! $canEdit)>
                                    <option value="">{{ __('project_draft.roles.nobody') }}</option>
                                    @foreach ($staff as $u)
                                        <option value="{{ $u->id }}">{{ trim($u->first_name . ' ' . $u->last_name) }}</option>
                                    @endforeach
                                    @if (! $canEdit && ($role['user_id'] ?? null))
                                        <option value="{{ $role['user_id'] }}" selected>{{ __('project_draft.roles.picked') }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="pp-tools">
                                @if ($canEdit)
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="removeRole({{ $r }})" aria-label="{{ __('project_draft.roles.remove', ['role' => $role['title']]) }}" title="{{ __('project_draft.roles.remove', ['role' => $role['title']]) }}"><i data-lucide="trash-2"></i></button>
                                @endif
                            </div>
                            @if (filled($role['responsibilities'] ?? ''))
                                <p class="help pp-wide" style="margin: 0">{{ $role['responsibilities'] }}</p>
                            @endif
                        </div>
                    @endforeach
                    @if (! $roles)
                        <p class="help" style="margin: 0">{{ __('project_draft.roles.none') }}</p>
                    @endif
                </div>
                @if ($canEdit && count($roles) < \App\Support\Opportunities\ProjectDrafts::MAX_ROLES)
                    <div class="pp-buttons" style="margin-top: 12px"><button type="button" class="btn btn-secondary btn-sm" wire:click="addRole"><i data-lucide="plus"></i> {{ __('project_draft.roles.add') }}</button></div>
                @endif
            </section>

            {{-- Milestones, each with its tasks --}}
            <ol class="pp-sections" aria-label="{{ __('project_draft.milestones.label') }}">
                @foreach ($milestones as $m => $ms)
                    <li class="card" wire:key="pm-{{ $ms['key'] }}">
                        <div class="pp-head">
                            <span>{{ __('sow.milestones.code', ['n' => $m + 1]) }} · {{ $percent($ms['weight'] ?? 0) }} · {{ $money($ms['amount'] ?? 0, $currency) }}@if (empty($ms['sow_key'])) · {{ __('project_draft.milestones.own') }}@endif</span>
                            @if ($canEdit && empty($ms['sow_key']))
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="removeMilestone({{ $m }})" wire:confirm="{{ __('project_draft.milestones.remove_confirm') }}"><i data-lucide="trash-2"></i> {{ __('project_draft.milestones.remove') }}</button>
                            @endif
                        </div>
                        <div class="pp-form">
                            <div class="field">
                                <label for="pd-m-{{ $m }}-title" class="label">{{ __('project_draft.milestones.name') }}</label>
                                <input id="pd-m-{{ $m }}-title" type="text" class="input" maxlength="140" wire:model.live.blur="payload.milestones.{{ $m }}.title" @disabled(! $canEdit)>
                            </div>
                            <div class="pd-dates">
                                <div class="field">
                                    <label for="pd-m-{{ $m }}-start" class="label">{{ __('project_draft.milestones.start') }}</label>
                                    <input id="pd-m-{{ $m }}-start" type="date" class="input" wire:model.live.blur="payload.milestones.{{ $m }}.start_date" @disabled(! $canEdit)>
                                </div>
                                <div class="field">
                                    <label for="pd-m-{{ $m }}-due" class="label">{{ __('project_draft.milestones.due') }}</label>
                                    <input id="pd-m-{{ $m }}-due" type="date" class="input" wire:model.live.blur="payload.milestones.{{ $m }}.due_date" @disabled(! $canEdit)>
                                </div>
                            </div>
                            <div class="field">
                                <label for="pd-m-{{ $m }}-goal" class="label">{{ __('project_draft.milestones.goal') }}</label>
                                <input id="pd-m-{{ $m }}-goal" type="text" class="input" maxlength="500" wire:model.live.blur="payload.milestones.{{ $m }}.goal" @disabled(! $canEdit)>
                            </div>
                            @if (filled($ms['description'] ?? ''))
                                <div class="pp-text"><strong>{{ __('sow.milestones.deliverables') }}</strong>@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $ms['description']])</div>
                            @endif
                        </div>

                        <h3 class="pp-h" style="margin-top: 16px">{{ trans_choice('project_draft.tasks.count', count($ms['tasks'] ?? []), ['count' => count($ms['tasks'] ?? [])]) }}</h3>
                        @foreach ((array) ($ms['tasks'] ?? []) as $t => $task)
                            <div class="pd-task" wire:key="pt-{{ $ms['key'] }}-{{ $t }}">
                                <div class="field">
                                    <label for="pd-t-{{ $m }}-{{ $t }}-title" class="label">{{ __('project_draft.tasks.task', ['n' => $t + 1]) }}</label>
                                    <input id="pd-t-{{ $m }}-{{ $t }}-title" type="text" class="input" maxlength="255" wire:model.live.blur="payload.milestones.{{ $m }}.tasks.{{ $t }}.title" @disabled(! $canEdit)>
                                </div>
                                <div class="field">
                                    <label for="pd-t-{{ $m }}-{{ $t }}-role" class="label">{{ __('project_draft.tasks.role') }}</label>
                                    <select id="pd-t-{{ $m }}-{{ $t }}-role" class="select" wire:model.live="payload.milestones.{{ $m }}.tasks.{{ $t }}.role_key" @disabled(! $canEdit)>
                                        <option value="">{{ __('project_draft.tasks.no_role') }}</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role['key'] }}">{{ $role['title'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="pd-t-{{ $m }}-{{ $t }}-hours" class="label">{{ __('project_draft.tasks.hours') }}</label>
                                    <input id="pd-t-{{ $m }}-{{ $t }}-hours" type="number" inputmode="decimal" min="0" max="999" step="0.5" class="input" wire:model.live.blur="payload.milestones.{{ $m }}.tasks.{{ $t }}.estimate_hours" @disabled(! $canEdit)>
                                </div>
                                <div class="field">
                                    <label for="pd-t-{{ $m }}-{{ $t }}-move" class="label">{{ __('project_draft.tasks.milestone') }}</label>
                                    <select id="pd-t-{{ $m }}-{{ $t }}-move" class="select" wire:change="moveTask({{ $m }}, {{ $t }}, $event.target.value)" @disabled(! $canEdit)>
                                        @foreach ($milestones as $to => $target)
                                            <option value="{{ $to }}" @selected($to === $m)>{{ __('sow.milestones.code', ['n' => $to + 1]) }} · {{ \Illuminate\Support\Str::limit($target['title'], 40) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="pp-tools">
                                    @if ($canEdit)
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="removeTask({{ $m }}, {{ $t }})" aria-label="{{ __('project_draft.tasks.remove', ['n' => $t + 1]) }}" title="{{ __('project_draft.tasks.remove', ['n' => $t + 1]) }}"><i data-lucide="trash-2"></i></button>
                                    @endif
                                </div>
                                <div class="field pp-wide">
                                    <label for="pd-t-{{ $m }}-{{ $t }}-desc" class="label">{{ __('project_draft.tasks.description') }}</label>
                                    <textarea id="pd-t-{{ $m }}-{{ $t }}-desc" class="textarea" rows="2" maxlength="5000" wire:model.live.blur="payload.milestones.{{ $m }}.tasks.{{ $t }}.description" @disabled(! $canEdit)></textarea>
                                </div>
                            </div>
                        @endforeach
                        @if (! ($ms['tasks'] ?? []))
                            <p class="help" style="margin: 0">{{ __('project_draft.tasks.none') }}</p>
                        @endif
                        @if ($canEdit)
                            <div class="pp-buttons" style="margin-top: 12px"><button type="button" class="btn btn-secondary btn-sm" wire:click="addTask({{ $m }})"><i data-lucide="plus"></i> {{ __('project_draft.tasks.add') }}</button></div>
                        @endif
                    </li>
                @endforeach
            </ol>

            <section class="card" aria-labelledby="pd-create-title">
                <h2 class="card-title" id="pd-create-title">{{ __('project_draft.create.title') }}</h2>
                <div class="pp-sum" role="status" style="margin: 0 0 12px">
                    <span @class(['is-off' => abs($weightSum - 100) > 0.001])>{{ __('sow.milestones.weight_total', ['total' => $percent($weightSum)]) }}</span>
                    <span>{{ __('sow.milestones.amount_total', ['amount' => $money($amountSum, $currency), 'total' => $money($payload['total'] ?? 0, $currency)]) }}</span>
                    <span>{{ trans_choice('project_draft.create.summary', $taskCount, ['count' => $taskCount, 'milestones' => count($milestones)]) }}</span>
                </div>
                @if ($canEdit)
                    @if (count($milestones) < count(array_filter($milestones, fn ($m) => ! empty($m['sow_key']))) + \App\Support\Opportunities\ProjectDrafts::MAX_EXTRA)
                        <div class="pp-buttons" style="margin-bottom: 12px"><button type="button" class="btn btn-secondary btn-sm" wire:click="addMilestone"><i data-lucide="plus"></i> {{ __('project_draft.milestones.add') }}</button></div>
                    @endif
                    <p class="pp-intro">{{ __('project_draft.create.intro') }}</p>
                    @error('create')
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                    @enderror
                    @if ($problem && $problem !== 'running')
                        <div class="callout pp-callout" role="note" style="margin-bottom: 12px"><i data-lucide="info"></i><span>{{ __('project_draft.create.errors.' . $problem) }}</span></div>
                    @endif
                    <div class="pp-buttons">
                        <button type="button" class="btn btn-primary" wire:click="create" wire:confirm="{{ __('project_draft.create.confirm') }}" wire:loading.attr="disabled" wire:target="create" @disabled($problem !== null)>
                            <i data-lucide="folder-plus"></i> {{ __('project_draft.create.button') }}
                        </button>
                    </div>
                @elseif ($draft->status !== 'created')
                    <p class="help" style="margin: 0">{{ __('project_draft.create.who', ['name' => $o->lead ? trim($o->lead->first_name . ' ' . $o->lead->last_name) : __('opportunities.no_lead')]) }}</p>
                @endif
            </section>
        @endunless
    @endif
</div>
