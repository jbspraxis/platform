@use('App\Support\Projects\ProjectPlan')
<div>
    @if ($open)
        @include('livewire.hq.projects.partials.styles')
        @php
            $day = fn ($d, $f = 'date') => ProjectPlan::day($d, $f);
            $pct = fn ($v) => ProjectPlan::percent($v);
            $taskTone = ['To-Do' => 'pill-neutral', 'In-Progress' => 'pill-info', 'QA_Review' => 'pill-warning', 'Failed' => 'pill-danger', 'Done' => 'pill-success'];
            $columnTone = ['not_started' => 'pill-neutral', 'in_progress' => 'pill-info', 'client_review' => 'pill-warning', 'accepted' => 'pill-success', 'invoiced' => 'pill-info', 'paid' => 'pill-success'];
            $invoiced = $milestone && in_array($milestone->column, ['invoiced', 'paid'], true);
            $noticeLook = ['success' => ['alert-success', 'circle-check', 'status'], 'info' => ['alert-info', 'info', 'status'], 'warning' => ['alert-warning', 'triangle-alert', 'alert'], 'error' => ['alert-danger', 'circle-alert', 'alert']];
            // The plan's total with this form's weight in place of the saved one
            $otherWeights = $weightTotal - ($milestone ? (float) $milestone->weight_percentage : 0);
            $formTotal = round($otherWeights + (is_numeric($weight) ? (float) $weight : 0), 2);
        @endphp
        <div class="pj-drawer-backdrop" wire:click="close" aria-hidden="true"></div>
        <aside class="pj-drawer" role="dialog" aria-modal="true" aria-labelledby="milestone-drawer-title" wire:key="milestone-drawer-{{ $phaseId ?? 'new' }}"
               x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.close()">
            <header class="pj-drawer-head">
                <div style="min-width: 0">
                    <p class="pj-kicker">{{ $milestone ? $milestone->code . ' · ' . __('projects.milestone.kicker') : __('projects.milestone.new_kicker') }}</p>
                    <h2 id="milestone-drawer-title">{{ $milestone ? $milestone->name : __('projects.milestone.new_title') }}</h2>
                    @if ($milestone)
                        <div class="pj-pills mt-2">
                            <span class="pill {{ $columnTone[$milestone->column] }}">{{ __('projects.columns.' . $milestone->column) }}</span>
                            <span class="tag">{{ __('projects.weight_value', ['value' => $pct($milestone->weight_percentage)]) }}</span>
                            @if ($milestone->overdue) <span class="pill pill-danger">{{ __('projects.overdue') }}</span> @endif
                        </div>
                    @endif
                </div>
                <button type="button" class="pj-icon-btn" wire:click="close" aria-label="{{ __('projects.drawer.close') }}"><i data-lucide="x"></i></button>
            </header>

            <div class="pj-drawer-body">
                @if ($notice)
                    @php [$nClass, $nIcon, $nRole] = $noticeLook[$noticeTone] ?? $noticeLook['success']; @endphp
                    <div class="alert {{ $nClass }}" role="{{ $nRole }}" wire:key="milestone-notice"><i data-lucide="{{ $nIcon }}"></i><span>{{ $notice }}</span></div>
                @endif

                @if ($milestone)
                    <section class="pj-section" aria-labelledby="ms-progress-title">
                        <h3 id="ms-progress-title">{{ __('projects.milestone.progress_title') }}</h3>
                        <div class="pj-bar {{ $milestone->progress >= 100 ? 'is-done' : '' }}" role="img" aria-label="{{ $pct($milestone->progress) }}"><span style="width: {{ $milestone->progress }}%"></span></div>
                        <p class="help" style="margin: 6px 0 0">{{ __('projects.milestone.progress_text', ['value' => $pct($milestone->progress), 'done' => $milestone->tasks_done, 'total' => $milestone->tasks_total]) }}</p>
                        <a href="{{ route('hq.ops.project', ['project' => $projectId, 'tab' => 'board', 'board' => $milestone->id]) }}" class="btn btn-secondary btn-sm mt-3" wire:navigate>
                            <i data-lucide="kanban"></i> {{ __('projects.milestone.open_board') }}
                        </a>
                    </section>
                @endif

                <section class="pj-section" aria-labelledby="ms-details-title">
                    <h3 id="ms-details-title">{{ __('projects.milestone.details') }}</h3>
                    @if ($canManage)
                        <form wire:submit="save" class="pj-form" novalidate>
                            <div class="pj-two" style="grid-template-columns: 110px minmax(0, 1fr)">
                                <div class="field">
                                    <label for="ms-number" class="label">{{ __('projects.milestone.number') }}</label>
                                    <input id="ms-number" type="text" inputmode="numeric" class="input" wire:model="number" @error('number') aria-invalid="true" @enderror>
                                    @error('number') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="ms-title" class="label">{{ __('projects.milestone.title') }}</label>
                                    <input id="ms-title" type="text" class="input" wire:model="title" maxlength="140" @error('title') aria-invalid="true" @enderror>
                                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="field">
                                <label for="ms-description" class="label">{{ __('projects.milestone.description') }}</label>
                                <textarea id="ms-description" class="textarea" rows="3" wire:model="description"></textarea>
                                @error('description') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="pj-two">
                                <div class="field">
                                    <label for="ms-weight" class="label">{{ __('projects.milestone.weight') }}</label>
                                    <input id="ms-weight" type="text" inputmode="decimal" class="input" wire:model.live.debounce.400ms="weight" @error('weight') aria-invalid="true" @enderror>
                                    <span class="help">{{ __('projects.milestone.weight_help', ['total' => $pct($formTotal)]) }}</span>
                                    @if (abs($formTotal - 100) >= 0.01)
                                        <span class="field-error" style="color: var(--color-warning)">{{ __('projects.milestones.weight_warning') }}</span>
                                    @endif
                                    @error('weight') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="ms-status" class="label">{{ __('projects.milestone.status') }}</label>
                                    @if ($invoiced)
                                        <input id="ms-status" type="text" class="input" value="{{ __('projects.columns.' . $milestone->column) }}" disabled>
                                        <span class="help">{{ __('projects.milestone.status_invoiced') }}</span>
                                    @else
                                        <select id="ms-status" class="select" wire:model.live="column">
                                            @foreach (array_keys(ProjectPlan::STATUS_FOR) as $c)
                                                <option value="{{ $c }}">{{ __('projects.columns.' . $c) }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @error('column') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="pj-two">
                                <div class="field">
                                    <label for="ms-start" class="label">{{ __('projects.milestone.start') }}</label>
                                    <input id="ms-start" type="date" class="input" wire:model="startDate">
                                    @error('startDate') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="ms-due" class="label">{{ __('projects.milestone.due') }}</label>
                                    <input id="ms-due" type="date" class="input" wire:model="dueDate" @error('dueDate') aria-invalid="true" @enderror>
                                    @error('dueDate') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            @if ($invoiced || $column === 'accepted')
                                <div class="pj-two">
                                    <div class="field">
                                        <label for="ms-accepted-at" class="label">{{ __('projects.milestone.accepted_at') }}</label>
                                        <input id="ms-accepted-at" type="date" class="input" wire:model="acceptedAt" @disabled($invoiced) @error('acceptedAt') aria-invalid="true" @enderror>
                                        @error('acceptedAt') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="ms-accepted-by" class="label">{{ __('projects.milestone.accepted_by') }}</label>
                                        <input id="ms-accepted-by" type="text" class="input" wire:model="acceptedBy" maxlength="150" placeholder="{{ __('projects.milestone.accepted_by_hint') }}" @disabled($invoiced) @error('acceptedBy') aria-invalid="true" @enderror>
                                        @error('acceptedBy') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            @endif
                            <div class="field">
                                <label for="ms-goal" class="label">{{ __('projects.milestone.goal') }}</label>
                                <textarea id="ms-goal" class="textarea" rows="2" wire:model="goal"></textarea>
                                @error('goal') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="ms-notes" class="label">{{ __('projects.milestone.notes') }}</label>
                                <textarea id="ms-notes" class="textarea" rows="3" wire:model="notes"></textarea>
                                @error('notes') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="pj-actions">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i data-lucide="save"></i> {{ $milestone ? __('projects.drawer.save') : __('projects.milestone.create') }}</button>
                                <button type="button" class="btn btn-secondary" wire:click="close">{{ __('projects.drawer.cancel') }}</button>
                            </div>
                        </form>
                    @elseif ($milestone)
                        <dl class="pj-dl">
                            @if ($milestone->description)
                                <dt>{{ __('projects.milestone.description') }}</dt><dd>{{ $milestone->description }}</dd>
                            @endif
                            <dt>{{ __('projects.milestone.weight') }}</dt><dd>{{ $pct($milestone->weight_percentage) }}</dd>
                            <dt>{{ __('projects.milestone.start') }}</dt><dd>{{ $milestone->start_date ? $day($milestone->start_date) : '—' }}</dd>
                            <dt>{{ __('projects.milestone.due') }}</dt><dd>{{ $milestone->due_date ? $day($milestone->due_date) : '—' }}</dd>
                            @if ($milestone->accepted_at)
                                <dt>{{ __('projects.milestone.accepted') }}</dt><dd>{{ __('projects.milestone.accepted_text', ['date' => $day($milestone->accepted_at), 'name' => $milestone->accepted_by_name ?: '—']) }}</dd>
                            @endif
                            @if ($milestone->goal)
                                <dt>{{ __('projects.milestone.goal') }}</dt><dd>{{ $milestone->goal }}</dd>
                            @endif
                            @if ($milestone->notes)
                                <dt>{{ __('projects.milestone.notes') }}</dt><dd>{{ $milestone->notes }}</dd>
                            @endif
                        </dl>
                    @endif
                </section>

                @if ($milestone)
                    <section class="pj-section" aria-labelledby="ms-invoice-title">
                        <h3 id="ms-invoice-title">{{ __('projects.milestone.invoice') }}</h3>
                        @if ($milestone->invoice)
                            <dl class="pj-dl">
                                <dt>{{ __('projects.milestone.invoice_number') }}</dt><dd>{{ $milestone->invoice->invoice_number }}</dd>
                                <dt>{{ __('projects.milestone.invoice_status') }}</dt><dd>{{ __('projects.invoice_statuses.' . $milestone->invoice->status) }}{{ $milestone->invoice->paid_at ? ' · ' . $day($milestone->invoice->paid_at) : '' }}</dd>
                                @if ($canSeeMoney)
                                    <dt>{{ __('projects.milestone.invoice_total') }}</dt><dd>{{ ProjectPlan::money($milestone->invoice->total_amount, $milestone->invoice->currency) }}</dd>
                                @endif
                            </dl>
                        @else
                            <p class="help" style="margin: 0">
                                {{ __('projects.milestone.no_invoice') }}
                                @if ($canSeeMoney && $project && (float) $project->total_budget > 0)
                                    {{ __('projects.milestone.invoice_amount', ['amount' => ProjectPlan::money(\App\Support\Projects\Milestones::amount($milestone, (float) $project->total_budget), $project->currency)]) }}
                                    {{-- "Less deposit, every milestone" (owner, 1 Nov 2026): what its invoice will be due --}}
                                    @php $due = \App\Support\Projects\Deposit::forMilestone($milestone); @endphp
                                    @if ($due['deposit'])
                                        {{ __('projects.milestone.invoice_less', ['code' => 'M' . $milestone->phase_number, 'amount' => ProjectPlan::money($due['amount'], $project->currency),
                                            'percent' => \App\Support\Projects\Deposit::percentLabel($due['deposit']['percent'], app()->getLocale()), 'due' => ProjectPlan::money($due['due'], $project->currency)]) }}
                                    @endif
                                    {{-- Whether Finance adds VAT to it today (App\Support\Finance\Vat) --}}
                                    {{ \App\Support\Finance\Vat::describe(\App\Support\Finance\Vat::for((string) $project->organization_id, null, \App\Support\Finance\Vat::projectEntity($projectId))) }}
                                @endif
                            </p>
                        @endif
                    </section>

                    <section class="pj-section" aria-labelledby="ms-waits-title">
                        <h3 id="ms-waits-title">{{ __('projects.waits.title') }}</h3>
                        @if ($milestone->waits_on->where('done', false)->isNotEmpty())
                            <div class="alert alert-warning mb-3" role="note"><i data-lucide="triangle-alert"></i><span>{{ __('projects.waits.milestone_warning') }}</span></div>
                        @endif
                        @if ($milestone->waits_on->isNotEmpty())
                            <ul class="pj-waits">
                                @foreach ($milestone->waits_on as $w)
                                    <li wire:key="mwait-{{ $w->id }}">
                                        <button type="button" class="pj-link" wire:click="openMilestone('{{ $w->id }}')">{{ $w->code }} · {{ $w->name }}</button>
                                        <span class="pill {{ $w->done ? 'pill-success' : 'pill-warning' }}">{{ $w->done ? __('projects.waits.done') : __('projects.waits.not_done') }}</span>
                                        @if ($canManage)
                                            <button type="button" class="pj-icon-btn is-small" wire:click="removeWait('{{ $w->id }}')" aria-label="{{ __('projects.waits.remove', ['what' => $w->code]) }}"><i data-lucide="x"></i></button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="help" style="margin: 0 0 10px">{{ __('projects.waits.none_milestone') }}</p>
                        @endif
                        @if ($canManage && $others->isNotEmpty())
                            <form wire:submit="addWait" class="field" novalidate>
                                <label for="ms-wait-on" class="sr-only">{{ __('projects.waits.add_label') }}</label>
                                <div class="pj-inline">
                                    <select id="ms-wait-on" class="select" wire:model="waitOn" @error('waitOn') aria-invalid="true" @enderror>
                                        <option value="">{{ __('projects.waits.choose_milestone') }}</option>
                                        @foreach ($others as $o)
                                            <option value="{{ $o->id }}" wire:key="mo-{{ $o->id }}">{{ $o->code }} · {{ $o->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary"><i data-lucide="link"></i> {{ __('projects.waits.add') }}</button>
                                </div>
                                @error('waitOn') <span class="field-error">{{ $message }}</span> @enderror
                            </form>
                        @endif
                        @if ($holding->isNotEmpty())
                            <p class="help" style="margin: 12px 0 0">{{ __('projects.waits.holding_milestones', ['list' => $holding->map(fn ($m) => $m->code)->implode(', ')]) }}</p>
                        @endif
                    </section>

                    <section class="pj-section" aria-labelledby="ms-tasks-title">
                        <div class="card-header" style="margin-bottom: 10px">
                            <h3 id="ms-tasks-title" style="margin: 0">{{ __('projects.milestone.tasks', ['count' => $tasks->count()]) }}</h3>
                            @if ($canManage)
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="newTask"><i data-lucide="plus"></i> {{ __('projects.backlog.new_task') }}</button>
                            @endif
                        </div>
                        @if ($tasks->isEmpty())
                            <p class="help" style="margin: 0">{{ __('projects.backlog.group_empty') }}</p>
                        @else
                            <ul class="pj-waits">
                                @foreach ($tasks as $t)
                                    <li wire:key="mtask-{{ $t->id }}">
                                        <button type="button" class="pj-link" style="font-weight: 500" wire:click="openTask('{{ $t->id }}')">{{ $t->title }}</button>
                                        @if (ProjectPlan::taskOverdue($t)) <span class="pill pill-danger">{{ __('projects.overdue') }}</span> @endif
                                        <span class="pill {{ $taskTone[$t->status] ?? 'pill-neutral' }}">{{ __('projects.task_statuses.' . $t->status) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="pj-section" aria-labelledby="ms-history-title">
                        <h3 id="ms-history-title">{{ __('projects.drawer.history') }}</h3>
                        @include('livewire.hq.projects.partials.history', ['history' => $history])
                    </section>

                    @if ($canManage)
                        <section class="pj-section" aria-labelledby="ms-delete-title">
                            <h3 id="ms-delete-title">{{ __('projects.milestone.delete_title') }}</h3>
                            @if (! $canDelete)
                                <p class="help" style="margin: 0">{{ __('projects.milestone.cannot_delete') }}</p>
                            @elseif ($confirmingDelete)
                                <div class="alert alert-danger" role="alert" style="flex-wrap: wrap">
                                    <i data-lucide="triangle-alert"></i>
                                    <span style="flex: 1">{{ __('projects.milestone.delete_confirm', ['code' => $milestone->code, 'name' => $milestone->name]) }}</span>
                                    <span class="pj-actions" style="width: 100%">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="delete">{{ __('projects.milestone.delete_yes', ['code' => $milestone->code]) }}</button>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="askDelete(false)">{{ __('projects.drawer.cancel') }}</button>
                                    </span>
                                </div>
                            @else
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="askDelete(true)"><i data-lucide="trash-2"></i> {{ __('projects.milestone.delete', ['code' => $milestone->code]) }}</button>
                            @endif
                        </section>
                    @endif
                @endif
            </div>
        </aside>
    @endif
</div>
