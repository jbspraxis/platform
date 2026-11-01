@use('App\Support\Projects\ChangeReports')
@use('App\Support\Projects\ProjectPlan')
<div>
    @if ($open)
        @include('livewire.hq.projects.partials.styles')
        @php
            $day = fn ($d) => ProjectPlan::day($d);
            $editable = $canManage && (! $report || ChangeReports::isOpen($report));
            $currency = $report->currency ?? ($project->currency ?? 'NGN');
            $tone = ['approved' => 'pill-success', 'declined' => 'pill-neutral', 'sent' => 'pill-info', 'draft' => 'pill-warning'];
            $noticeLook = ['success' => ['alert-success', 'circle-check', 'status'], 'error' => ['alert-danger', 'circle-alert', 'alert']];
        @endphp
        <div class="pj-drawer-backdrop" wire:click="close" aria-hidden="true"></div>
        <aside class="pj-drawer" role="dialog" aria-modal="true" aria-labelledby="cr-drawer-title" wire:key="cr-drawer-{{ $reportId ?? 'new' }}"
               x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.close()">
            <header class="pj-drawer-head">
                <div style="min-width: 0">
                    <p class="pj-kicker">{{ $report ? ChangeReports::label($report) . ' · ' . __('projects.cr.kicker') : __('projects.cr.new_kicker') }}</p>
                    <h2 id="cr-drawer-title">{{ $report ? $report->title : __('projects.cr.new_title') }}</h2>
                    @if ($report)
                        <div class="pj-pills mt-2"><span class="pill {{ $tone[$report->status] }}">{{ __('projects.cr_statuses.' . $report->status) }}</span></div>
                    @endif
                </div>
                <button type="button" class="pj-icon-btn" wire:click="close" aria-label="{{ __('projects.drawer.close') }}"><i data-lucide="x"></i></button>
            </header>

            <div class="pj-drawer-body">
                @if ($notice)
                    @php [$nClass, $nIcon, $nRole] = $noticeLook[$noticeTone] ?? $noticeLook['success']; @endphp
                    <div class="alert {{ $nClass }}" role="{{ $nRole }}" wire:key="cr-notice"><i data-lucide="{{ $nIcon }}"></i><span>{{ $notice }}</span></div>
                @endif

                <p class="help" style="margin: 0">{{ __('projects.cr.intro') }}</p>

                @if ($report && ! ChangeReports::isOpen($report))
                    {{-- Decided: read only --}}
                    <section class="pj-section" aria-labelledby="cr-decision-title">
                        <h3 id="cr-decision-title">{{ __('projects.cr.decision') }}</h3>
                        <dl class="pj-dl">
                            <dt>{{ __('projects.cr.status') }}</dt><dd>{{ __('projects.cr_statuses.' . $report->status) }}</dd>
                            <dt>{{ __('projects.cr.decided_on') }}</dt><dd>{{ $report->decided_on ? $day($report->decided_on) : '—' }}</dd>
                            <dt>{{ __('projects.cr.decided_by') }}</dt><dd>{{ trim(($report->decided_by_name ?? '') . ($report->decided_by_title ? ', ' . $report->decided_by_title : '')) ?: '—' }}</dd>
                            @if ($phase)
                                <dt>{{ __('projects.cr.added_milestone') }}</dt>
                                <dd><button type="button" class="pj-link" wire:click="openMilestone('{{ $phase->id }}')">M{{ $phase->phase_number }} · {{ ProjectPlan::title($phase) }}</button> ({{ ProjectPlan::percent($phase->weight_percentage) }})</dd>
                            @endif
                        </dl>
                    </section>
                @endif

                <section class="pj-section" aria-labelledby="cr-details-title">
                    <h3 id="cr-details-title">{{ __('projects.cr.details') }}</h3>
                    @if ($editable)
                        <form wire:submit="save" class="pj-form" novalidate>
                            <div class="field">
                                <label for="cr-title" class="label">{{ __('projects.cr.title') }}</label>
                                <input id="cr-title" type="text" class="input" wire:model="title" maxlength="200" @error('title') aria-invalid="true" @enderror>
                                @error('title') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="cr-description" class="label">{{ __('projects.cr.description') }}</label>
                                <textarea id="cr-description" class="textarea" rows="3" wire:model="description"></textarea>
                                @error('description') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="pj-two">
                                <div class="field">
                                    <label for="cr-price" class="label">{{ __('projects.cr.price', ['currency' => $currency]) }}</label>
                                    <input id="cr-price" type="text" inputmode="decimal" class="input" wire:model="price" @error('price') aria-invalid="true" @enderror>
                                    @error('price') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="cr-days" class="label">{{ __('projects.cr.days') }}</label>
                                    <input id="cr-days" type="text" inputmode="numeric" class="input" wire:model="timelineDays" @error('timelineDays') aria-invalid="true" @enderror>
                                    <span class="help">{{ __('projects.cr.days_help') }}</span>
                                    @error('timelineDays') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <fieldset class="field" style="border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 12px 14px; margin: 0">
                                <legend class="label" style="padding: 0 4px">{{ __('projects.cr.plan') }}</legend>
                                <p class="help" style="margin: 0 0 10px">{{ __('projects.cr.plan_help') }}</p>
                                <div class="pj-form">
                                    <div class="field">
                                        <label for="cr-m-title" class="label">{{ __('projects.cr.milestone_title') }}</label>
                                        <input id="cr-m-title" type="text" class="input" wire:model="milestoneTitle" maxlength="150" placeholder="{{ $title }}">
                                        @error('milestoneTitle') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="cr-m-description" class="label">{{ __('projects.milestone.description') }}</label>
                                        <textarea id="cr-m-description" class="textarea" rows="2" wire:model="milestoneDescription"></textarea>
                                    </div>
                                    <div class="field">
                                        <label for="cr-m-due" class="label">{{ __('projects.milestone.due') }}</label>
                                        <input id="cr-m-due" type="date" class="input" wire:model="milestoneDue">
                                        @error('milestoneDue') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <span class="label">{{ __('projects.cr.tasks') }}</span>
                                        @if ($planTasks)
                                            <ul class="pj-waits">
                                                @foreach ($planTasks as $i => $t)
                                                    <li wire:key="pt-{{ $i }}-{{ md5($t) }}"><span>{{ $t }}</span>
                                                        <button type="button" class="pj-icon-btn is-small" wire:click="removePlanTask({{ $i }})" aria-label="{{ __('projects.cr.remove_task', ['task' => $t]) }}"><i data-lucide="x"></i></button></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        <div class="pj-inline">
                                            <label for="cr-new-task" class="sr-only">{{ __('projects.cr.new_task') }}</label>
                                            <input id="cr-new-task" type="text" class="input" wire:model="newPlanTask" maxlength="252" placeholder="{{ __('projects.cr.new_task') }}" x-on:keydown.enter.prevent="$wire.addPlanTask()">
                                            <button type="button" class="btn btn-secondary" wire:click="addPlanTask"><i data-lucide="plus"></i> {{ __('projects.cr.add_task') }}</button>
                                        </div>
                                        @error('planTasks') <span class="field-error">{{ $message }}</span> @enderror
                                        @error('planTasks.*') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </fieldset>

                            <div class="pj-actions">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i data-lucide="save"></i> {{ $report ? __('projects.drawer.save') : __('projects.cr.create') }}</button>
                                @if ($report && $report->status === 'draft')
                                    <button type="button" class="btn btn-secondary" wire:click="markSent"><i data-lucide="send"></i> {{ __('projects.cr.mark_sent') }}</button>
                                @endif
                                <button type="button" class="btn btn-secondary" wire:click="close">{{ __('projects.drawer.cancel') }}</button>
                            </div>
                        </form>
                    @elseif ($report)
                        @php $plan = json_decode((string) $report->plan, true) ?: []; @endphp
                        <dl class="pj-dl">
                            @if ($report->description)
                                <dt>{{ __('projects.cr.description') }}</dt><dd>{{ $report->description }}</dd>
                            @endif
                            @if ($canSeeMoney)
                                <dt>{{ __('projects.cr.price_short') }}</dt><dd>{{ ProjectPlan::money($report->price, $report->currency) }}</dd>
                            @endif
                            <dt>{{ __('projects.cr.days_short') }}</dt><dd>{{ trans_choice('projects.cr.days_value', (int) $report->timeline_days, ['count' => (int) $report->timeline_days]) }}</dd>
                            <dt>{{ __('projects.cr.plan') }}</dt>
                            <dd>{{ ($plan['milestone']['title'] ?? '') ?: $report->title }}@if (! empty($plan['tasks'])) · {{ trans_choice('projects.cr.task_count', count($plan['tasks']), ['count' => count($plan['tasks'])]) }}@endif</dd>
                        </dl>
                    @endif
                </section>

                @if ($report && ChangeReports::isOpen($report) && $canManage)
                    <section class="pj-section" aria-labelledby="cr-decide-title">
                        <h3 id="cr-decide-title">{{ __('projects.cr.decision') }}</h3>
                        @if ($preview)
                            <p class="help" style="margin: 0 0 12px">{{ __('projects.cr.preview', [
                                'weight' => ProjectPlan::percent($preview['weight']),
                                'total' => ProjectPlan::money($preview['total'], $currency),
                                'days' => (int) $report->timeline_days,
                            ]) }}</p>
                        @endif
                        @if (! $confirming)
                            <form wire:submit="reviewDecision" class="pj-form" novalidate>
                                <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                                    <legend class="label" style="margin-bottom: 6px">{{ __('projects.cr.client_decided') }}</legend>
                                    <div class="pj-actions">
                                        <label class="pj-check"><input type="radio" wire:model="decision" value="approved"> {{ __('projects.cr.approve') }}</label>
                                        <label class="pj-check"><input type="radio" wire:model="decision" value="declined"> {{ __('projects.cr.decline') }}</label>
                                    </div>
                                    @error('decision') <span class="field-error">{{ $message }}</span> @enderror
                                </fieldset>
                                <div class="pj-two">
                                    <div class="field">
                                        <label for="cr-on" class="label">{{ __('projects.cr.decided_on') }}</label>
                                        <input id="cr-on" type="date" class="input" wire:model="decidedOn">
                                        @error('decidedOn') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="cr-by" class="label">{{ __('projects.cr.decided_by') }}</label>
                                        <input id="cr-by" type="text" class="input" wire:model="decidedBy" maxlength="150">
                                        @error('decidedBy') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="cr-by-title" class="label">{{ __('projects.cr.decided_by_title') }}</label>
                                    <input id="cr-by-title" type="text" class="input" wire:model="decidedByTitle" maxlength="150">
                                </div>
                                <div class="pj-actions"><button type="submit" class="btn btn-secondary">{{ __('projects.cr.review') }}</button></div>
                            </form>
                        @else
                            <div class="alert {{ $decision === 'approved' ? 'alert-warning' : 'alert-info' }}" role="alert" style="flex-wrap: wrap">
                                <i data-lucide="{{ $decision === 'approved' ? 'triangle-alert' : 'info' }}"></i>
                                <span style="flex: 1">{{ $decision === 'approved'
                                    ? __('projects.cr.confirm_approve', ['label' => ChangeReports::label($report), 'name' => $decidedBy])
                                    : __('projects.cr.confirm_decline', ['label' => ChangeReports::label($report), 'name' => $decidedBy]) }}</span>
                                <span class="pj-actions" style="width: 100%">
                                    <button type="button" class="btn {{ $decision === 'approved' ? 'btn-primary' : 'btn-secondary' }} btn-sm" wire:click="recordDecision">
                                        {{ $decision === 'approved' ? __('projects.cr.record_approval') : __('projects.cr.record_decline') }}
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelDecision">{{ __('projects.drawer.cancel') }}</button>
                                </span>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($report)
                    <section class="pj-section" aria-labelledby="cr-history-title">
                        <h3 id="cr-history-title">{{ __('projects.drawer.history') }}</h3>
                        @include('livewire.hq.projects.partials.history', ['history' => $history])
                    </section>
                @endif
            </div>
        </aside>
    @endif
</div>
