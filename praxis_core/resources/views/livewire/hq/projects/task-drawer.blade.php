@use('App\Support\Projects\ProjectPlan')
<div>
    @if ($open)
        @include('livewire.hq.projects.partials.styles')
        @php
            $day = fn ($d, $f = 'date') => ProjectPlan::day($d, $f);
            $name = fn ($id) => $id ? ($names[$id] ?? '—') : __('projects.no_lead');
            $taskTone = ['To-Do' => 'pill-neutral', 'In-Progress' => 'pill-info', 'QA_Review' => 'pill-warning', 'Failed' => 'pill-danger', 'Done' => 'pill-success'];
            $late = $task && ProjectPlan::taskOverdue($task);
            $noticeLook = ['success' => ['alert-success', 'circle-check', 'status'], 'info' => ['alert-info', 'info', 'status'], 'warning' => ['alert-warning', 'triangle-alert', 'alert'], 'error' => ['alert-danger', 'circle-alert', 'alert']];
        @endphp
        <div class="pj-drawer-backdrop" wire:click="close" aria-hidden="true"></div>
        <aside class="pj-drawer" role="dialog" aria-modal="true" aria-labelledby="task-drawer-title" wire:key="task-drawer-{{ $taskId ?? 'new' }}"
               x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.close()">
            <header class="pj-drawer-head">
                <div style="min-width: 0">
                    <p class="pj-kicker">{{ $task ? ($milestone ? $milestone->code . ' · ' . $milestone->name : __('projects.task.kicker')) : __('projects.task.new_kicker') }}</p>
                    <h2 id="task-drawer-title">{{ $task ? $task->title : __('projects.task.new_title') }}</h2>
                    @if ($task)
                        <div class="pj-pills mt-2">
                            <span class="pill {{ $taskTone[$task->status] ?? 'pill-neutral' }}">{{ __('projects.task_statuses.' . $task->status) }}</span>
                            @if ($task->due_date)
                                <span @class(['pill', 'pill-danger' => $late, 'pill-neutral' => ! $late])>{{ $late ? __('projects.overdue') . ' · ' : '' }}{{ __('projects.due', ['date' => $day($task->due_date)]) }}</span>
                            @endif
                            @if (! $task->is_client_visible)
                                <span class="tag">{{ __('projects.task.internal') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
                <button type="button" class="pj-icon-btn" wire:click="close" aria-label="{{ __('projects.drawer.close') }}"><i data-lucide="x"></i></button>
            </header>

            <div class="pj-drawer-body">
                @if ($notice)
                    @php [$nClass, $nIcon, $nRole] = $noticeLook[$noticeTone] ?? $noticeLook['success']; @endphp
                    <div class="alert {{ $nClass }}" role="{{ $nRole }}" wire:key="task-notice"><i data-lucide="{{ $nIcon }}"></i><span>{{ $notice }}</span></div>
                @endif

                @if ($task)
                    {{-- Work on it: status for whoever may move it, watching for everyone --}}
                    <section class="pj-section" aria-labelledby="task-work-title">
                        <h3 id="task-work-title" class="sr-only">{{ __('projects.task.work') }}</h3>
                        <div class="pj-two">
                            <div class="field">
                                <label for="task-status" class="label">{{ __('projects.task.status') }}</label>
                                <select id="task-status" class="select" wire:model.live="status" @disabled(! $canMove)>
                                    @foreach (ProjectPlan::TASK_STATUSES as $s)
                                        <option value="{{ $s }}">{{ __('projects.task_statuses.' . $s) }}</option>
                                    @endforeach
                                </select>
                                <span class="help">{{ $canMove ? __('projects.task.status_help') : __('projects.task.move_own') }}</span>
                            </div>
                            <div class="field">
                                <span class="label">{{ __('projects.task.you') }}</span>
                                <div class="pj-actions" style="align-items: center; min-height: 40px">
                                    @if ($myRole === 'lead')
                                        <span class="tag">{{ __('projects.task.you_lead') }}</span>
                                    @elseif ($myRole === 'sharing')
                                        <span class="tag">{{ __('projects.task.you_share') }}</span>
                                    @elseif ($myRole === 'watcher')
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="watch(false)"><i data-lucide="eye-off"></i> {{ __('projects.task.stop_watching') }}</button>
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="watch(true)"><i data-lucide="eye"></i> {{ __('projects.task.watch') }}</button>
                                    @endif
                                    @if ($canCheckout)
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="assignToMe"><i data-lucide="user-plus"></i> {{ __('projects.task.assign_to_me') }}</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>

                    @if ($task->proposed_due_date)
                        <div class="callout" role="note" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between">
                            <span>{{ __('projects.task.proposal', ['name' => $name($task->proposed_due_by), 'date' => $day($task->proposed_due_date)]) }}</span>
                            @if ($canManage)
                                <span class="pj-actions">
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="decideProposal(true)">{{ __('projects.task.accept_proposal') }}</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="decideProposal(false)">{{ __('projects.task.decline_proposal') }}</button>
                                </span>
                            @endif
                        </div>
                    @endif
                @endif

                {{-- Details: a form for the project's lead and managers, read only for everyone else --}}
                <section class="pj-section" aria-labelledby="task-details-title">
                    <h3 id="task-details-title">{{ __('projects.task.details') }}</h3>
                    @if ($canManage)
                        <form wire:submit="save" class="pj-form" novalidate>
                            <div class="field">
                                <label for="task-title" class="label">{{ __('projects.task.title') }}</label>
                                <input id="task-title" type="text" class="input" wire:model="title" maxlength="255" @error('title') aria-invalid="true" aria-describedby="task-title-error" @enderror>
                                @error('title') <span class="field-error" id="task-title-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="task-description" class="label">{{ __('projects.task.description') }}</label>
                                <textarea id="task-description" class="textarea" rows="3" wire:model="description"></textarea>
                                @error('description') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="task-phase" class="label">{{ __('projects.task.milestone') }}</label>
                                <select id="task-phase" class="select" wire:model="phaseId" @error('phaseId') aria-invalid="true" aria-describedby="task-phase-error" @else aria-describedby="task-phase-help" @enderror>
                                    <option value="">{{ __('projects.task.choose_milestone') }}</option>
                                    @foreach ($milestones as $m)
                                        <option value="{{ $m->id }}" wire:key="tp-{{ $m->id }}">{{ $m->code }} · {{ $m->name }}</option>
                                    @endforeach
                                </select>
                                @error('phaseId')
                                    <span class="field-error" id="task-phase-error">{{ $message }}</span>
                                @else
                                    <span class="help" id="task-phase-help">{{ __('projects.task.milestone_help') }}</span>
                                @enderror
                            </div>
                            {{-- People: PR #14's picker over the project's team (type part of a name, Enter or click to choose) --}}
                            <div>
                                <x-hq.picker id="task-lead" :label="__('projects.task.lead')" model="leadSearch" :options="$leadOptions" :selected="$leadSelected"
                                             choose="chooseLead" remove="clearLead" :placeholder="__('projects.task.find_person')" :help="__('projects.task.lead_help')"
                                             :error="$errors->first('leadId')" :empty="__('projects.task.no_person')" :term="$leadSearch" :change="__('projects.task.change')" />
                                @if ($leadId !== auth()->id())
                                    <button type="button" class="btn btn-ghost btn-sm mt-1" wire:click="chooseLead('{{ auth()->id() }}')"><i data-lucide="user-plus"></i> {{ __('projects.task.me') }}</button>
                                @endif
                            </div>
                            <x-hq.picker id="task-sharing" :label="__('projects.task.sharing')" model="sharingSearch" :options="$sharingOptions" :selected="$sharingSelected" :multiple="true"
                                         choose="addSharing" remove="removeSharing" :placeholder="__('projects.task.find_person')" :help="__('projects.task.sharing_help')"
                                         :error="$errors->first('sharing') ?: $errors->first('sharing.*')" :empty="__('projects.task.no_person')" :term="$sharingSearch"
                                         :removeLabel="__('projects.task.remove_person')" />
                            <x-hq.picker id="task-watchers" :label="__('projects.task.watchers')" model="watcherSearch" :options="$watcherOptions" :selected="$watcherSelected" :multiple="true"
                                         choose="addWatcher" remove="removeWatcher" :placeholder="__('projects.task.find_person')" :help="__('projects.task.watchers_help')"
                                         :error="$errors->first('watchers') ?: $errors->first('watchers.*')" :empty="__('projects.task.no_person')" :term="$watcherSearch"
                                         :removeLabel="__('projects.task.remove_person')" />
                            <div class="pj-two">
                                <div class="field">
                                    <label for="task-due" class="label">{{ __('projects.task.due_date') }}</label>
                                    <input id="task-due" type="date" class="input" wire:model="dueDate" @error('dueDate') aria-invalid="true" @enderror>
                                    @error('dueDate') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="task-hours" class="label">{{ __('projects.task.hours') }}</label>
                                    <input id="task-hours" type="text" inputmode="decimal" class="input" wire:model="hours" @error('hours') aria-invalid="true" @enderror>
                                    @error('hours') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <label class="pj-check"><input type="checkbox" wire:model="clientVisible"> {{ __('projects.task.client_visible') }}</label>
                            <div class="pj-actions">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i data-lucide="save"></i> {{ $task ? __('projects.drawer.save') : __('projects.task.create') }}</button>
                                <button type="button" class="btn btn-secondary" wire:click="close">{{ __('projects.drawer.cancel') }}</button>
                            </div>
                        </form>
                    @elseif ($task)
                        <dl class="pj-dl">
                            @if ($task->description)
                                <dt>{{ __('projects.task.description') }}</dt><dd>{{ $task->description }}</dd>
                            @endif
                            <dt>{{ __('projects.task.milestone') }}</dt><dd>{{ $milestone ? $milestone->code . ' · ' . $milestone->name : '—' }}</dd>
                            <dt>{{ __('projects.task.lead') }}</dt><dd>{{ $name($task->assigned_to) }}</dd>
                            <dt>{{ __('projects.task.sharing') }}</dt><dd>{{ collect($people['sharing'])->map(fn ($id) => $name($id))->implode(', ') ?: '—' }}</dd>
                            <dt>{{ __('projects.task.watchers') }}</dt><dd>{{ collect($people['watcher'])->map(fn ($id) => $name($id))->implode(', ') ?: '—' }}</dd>
                            <dt>{{ __('projects.task.due_date') }}</dt><dd>{{ $task->due_date ? $day($task->due_date) : '—' }}</dd>
                            <dt>{{ __('projects.task.hours') }}</dt><dd>{{ $task->estimated_hours !== null ? rtrim(rtrim((string) $task->estimated_hours, '0'), '.') : '—' }}</dd>
                            <dt>{{ __('projects.task.client_visible_short') }}</dt><dd>{{ $task->is_client_visible ? __('projects.yes') : __('projects.no') }}</dd>
                        </dl>
                        @if ($canWork)
                            <form wire:submit="proposeDue" class="pj-form mt-4" novalidate>
                                <div class="field">
                                    <label for="task-propose" class="label">{{ __('projects.task.propose') }}</label>
                                    <div class="pj-inline">
                                        <input id="task-propose" type="date" class="input" wire:model="proposedDate" @error('proposedDate') aria-invalid="true" @enderror>
                                        <button type="submit" class="btn btn-secondary">{{ __('projects.task.propose_button') }}</button>
                                    </div>
                                    <span class="help">{{ __('projects.task.propose_help') }}</span>
                                    @error('proposedDate') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </form>
                        @endif
                    @endif
                </section>

                @if ($task)
                    {{-- Steps --}}
                    @php $doneSteps = $steps->whereNotNull('done_at')->count(); @endphp
                    <section class="pj-section" aria-labelledby="task-steps-title">
                        <h3 id="task-steps-title">{{ __('projects.task.steps', ['done' => $doneSteps, 'total' => $steps->count()]) }}</h3>
                        @if ($steps->isNotEmpty())
                            <ul class="pj-steps">
                                @foreach ($steps as $step)
                                    <li @class(['is-done' => $step->done_at]) wire:key="step-{{ $step->id }}">
                                        <input type="checkbox" id="step-{{ $step->id }}" @checked($step->done_at) @disabled(! $canWork) wire:click="toggleStep({{ $step->id }})">
                                        <span><label for="step-{{ $step->id }}">{{ $step->text }}</label>@if ($step->done_at)<small class="help" style="display: block">{{ __('projects.task.step_done', ['name' => $name($step->done_by), 'date' => $day($step->done_at, 'short')]) }}</small>@endif</span>
                                        @if ($canWork)
                                            <button type="button" class="pj-icon-btn is-small" wire:click="removeStep({{ $step->id }})" aria-label="{{ __('projects.task.remove_step', ['step' => $step->text]) }}"><i data-lucide="trash-2"></i></button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="help" style="margin: 0 0 10px">{{ __('projects.task.no_steps') }}</p>
                        @endif
                        @if ($canWork)
                            <form wire:submit="addStep" class="field" novalidate>
                                <label for="task-new-step" class="sr-only">{{ __('projects.task.new_step') }}</label>
                                <div class="pj-inline">
                                    <input id="task-new-step" type="text" class="input" wire:model="newStep" maxlength="500" placeholder="{{ __('projects.task.new_step') }}" @error('newStep') aria-invalid="true" @enderror>
                                    <button type="submit" class="btn btn-secondary"><i data-lucide="plus"></i> {{ __('projects.task.add_step') }}</button>
                                </div>
                                @error('newStep') <span class="field-error">{{ $message }}</span> @enderror
                            </form>
                        @endif
                    </section>

                    {{-- Waits on (a warning only, decision 5) --}}
                    <section class="pj-section" aria-labelledby="task-waits-title">
                        <h3 id="task-waits-title">{{ __('projects.waits.title') }}</h3>
                        @if ($waits->where('status', '!=', 'Done')->isNotEmpty())
                            <div class="alert alert-warning mb-3" role="note"><i data-lucide="triangle-alert"></i><span>{{ __('projects.waits.warning') }}</span></div>
                        @endif
                        @if ($waits->isNotEmpty())
                            <ul class="pj-waits">
                                @foreach ($waits as $w)
                                    <li wire:key="wait-{{ $w->id }}">
                                        <button type="button" class="pj-link" wire:click="openTask('{{ $w->id }}')">{{ $w->title }}</button>
                                        <span class="pill {{ $taskTone[$w->status] ?? 'pill-neutral' }}">{{ __('projects.task_statuses.' . $w->status) }}</span>
                                        @if ($canWork)
                                            <button type="button" class="pj-icon-btn is-small" wire:click="removeWait('{{ $w->id }}')" aria-label="{{ __('projects.waits.remove', ['what' => $w->title]) }}"><i data-lucide="x"></i></button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="help" style="margin: 0 0 10px">{{ __('projects.waits.none') }}</p>
                        @endif
                        @if ($canWork && $candidates->isNotEmpty())
                            <form wire:submit="addWait" class="field" novalidate>
                                <label for="task-wait-on" class="sr-only">{{ __('projects.waits.add_label') }}</label>
                                <div class="pj-inline">
                                    <select id="task-wait-on" class="select" wire:model="waitOn" @error('waitOn') aria-invalid="true" @enderror>
                                        <option value="">{{ __('projects.waits.choose_task') }}</option>
                                        @foreach ($candidates as $c)
                                            <option value="{{ $c->id }}" wire:key="cand-{{ $c->id }}">M{{ $c->phase_number }} · {{ \Illuminate\Support\Str::limit($c->title, 80) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary"><i data-lucide="link"></i> {{ __('projects.waits.add') }}</button>
                                </div>
                                @error('waitOn') <span class="field-error">{{ $message }}</span> @enderror
                            </form>
                        @endif
                        @if ($holding->isNotEmpty())
                            <p class="help" style="margin: 12px 0 6px">{{ __('projects.waits.holding') }}</p>
                            <ul class="pj-waits">
                                @foreach ($holding as $h)
                                    <li wire:key="hold-{{ $h->id }}"><button type="button" class="pj-link" wire:click="openTask('{{ $h->id }}')">{{ $h->title }}</button> <span class="pill {{ $taskTone[$h->status] ?? 'pill-neutral' }}">{{ __('projects.task_statuses.' . $h->status) }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- Comments --}}
                    <section class="pj-section" aria-labelledby="task-comments-title">
                        <h3 id="task-comments-title">{{ __('projects.task.comments', ['count' => $comments->count()]) }}</h3>
                        @if ($canWork)
                            <form wire:submit="addComment" class="pj-form mb-4" novalidate>
                                <div class="field">
                                    <label for="task-comment" class="sr-only">{{ __('projects.task.new_comment') }}</label>
                                    <textarea id="task-comment" class="textarea" rows="3" wire:model="comment" placeholder="{{ __('projects.task.new_comment') }}" @error('comment') aria-invalid="true" @enderror></textarea>
                                    @error('comment') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="task-file" class="label">{{ __('projects.task.attach') }}</label>
                                    <input id="task-file" type="file" class="input" wire:model="attachment">
                                    <span class="help" wire:loading wire:target="attachment">{{ __('projects.task.uploading') }}</span>
                                    @error('attachment') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="pj-actions">
                                    <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="addComment, attachment"><i data-lucide="send"></i> {{ __('projects.task.add_comment') }}</button>
                                </div>
                                <span class="help">{{ __('projects.task.comment_help') }}</span>
                            </form>
                        @endif
                        @forelse ($comments as $c)
                            <div class="pj-comment mb-2" wire:key="comment-{{ $c->id }}">
                                <div class="pj-comment-meta"><strong style="color: var(--color-ink)">{{ trim($c->first_name . ' ' . $c->last_name) ?: '—' }}</strong><span>{{ $day($c->created_at, 'datetime') }}</span></div>
                                {{ $c->comment_text }}
                                @if ($c->attachment_path)
                                    <div class="mt-2"><a href="{{ route('hq.file', ['path' => $c->attachment_path]) }}" target="_blank" rel="noopener"><i data-lucide="paperclip" style="width: 14px; height: 14px; display: inline"></i> {{ __('projects.task.attachment') }}</a></div>
                                @endif
                            </div>
                        @empty
                            <p class="help" style="margin: 0">{{ __('projects.task.no_comments') }}</p>
                        @endforelse
                    </section>

                    {{-- History (the audit log) --}}
                    <section class="pj-section" aria-labelledby="task-history-title">
                        <h3 id="task-history-title">{{ __('projects.drawer.history') }}</h3>
                        @include('livewire.hq.projects.partials.history', ['history' => $history])
                    </section>
                @endif
            </div>
        </aside>
    @endif
</div>
