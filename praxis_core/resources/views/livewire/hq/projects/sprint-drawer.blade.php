@use('App\Support\Projects\ProjectPlan')
@use('App\Support\Projects\Milestones')
<div>
    @if ($open)
        @include('livewire.hq.projects.partials.styles')
        <div class="pj-drawer-backdrop" wire:click="close" aria-hidden="true"></div>
        <aside class="pj-drawer" role="dialog" aria-modal="true" aria-labelledby="sprint-drawer-title" wire:key="sprint-drawer-{{ $sprintId ?? 'new' }}"
               x-data x-trap.noscroll="true" x-on:keydown.escape.window="$wire.close()">
            <header class="pj-drawer-head">
                <div style="min-width: 0">
                    <p class="pj-kicker">{{ $sprint ? __('projects.sprint.kicker') : __('projects.sprint.new_kicker') }}</p>
                    <h2 id="sprint-drawer-title">{{ $sprint ? $sprint->title : __('projects.sprint.new_title') }}</h2>
                </div>
                <button type="button" class="pj-icon-btn" wire:click="close" aria-label="{{ __('projects.drawer.close') }}"><i data-lucide="x"></i></button>
            </header>

            <div class="pj-drawer-body">
                @if ($notice)
                    <div class="alert alert-success" role="status" wire:key="sprint-notice"><i data-lucide="circle-check"></i><span>{{ $notice }}</span></div>
                @endif

                @if ($sprint)
                    <section class="pj-section" aria-labelledby="sp-delivers-title">
                        <h3 id="sp-delivers-title">{{ __('projects.sprint.delivers') }}</h3>
                        @if ($sprint->milestones->isEmpty())
                            <p class="help" style="margin: 0">{{ __('projects.sprints.no_milestone_text') }}</p>
                        @else
                            <ul class="pj-waits">
                                @foreach ($sprint->milestones as $m)
                                    <li wire:key="spm-{{ $m->id }}"><span>{{ $m->code }} · {{ $m->name }}</span> <span class="tag num">{{ ProjectPlan::percent($m->weight_percentage) }}</span></li>
                                @endforeach
                            </ul>
                            <p class="help" style="margin: 0">{{ __('projects.sprint.weight_text', ['value' => ProjectPlan::percent($sprint->weight), 'done' => $sprint->tasks_done, 'total' => $sprint->tasks_total]) }}</p>
                        @endif
                    </section>
                @endif

                <section class="pj-section" aria-labelledby="sp-details-title">
                    <h3 id="sp-details-title">{{ __('projects.sprint.details') }}</h3>
                    @if ($canManage)
                        <form wire:submit="save" class="pj-form" novalidate>
                            <div class="field">
                                <label for="sp-title" class="label">{{ __('projects.sprint.title') }}</label>
                                <input id="sp-title" type="text" class="input" wire:model="title" maxlength="100" @error('title') aria-invalid="true" @enderror>
                                @error('title') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="pj-two">
                                <div class="field">
                                    <label for="sp-start" class="label">{{ __('projects.sprint.start') }}</label>
                                    <input id="sp-start" type="date" class="input" wire:model="startDate" @error('startDate') aria-invalid="true" @enderror>
                                    @error('startDate') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="sp-end" class="label">{{ __('projects.sprint.end') }}</label>
                                    <input id="sp-end" type="date" class="input" wire:model="endDate" @error('endDate') aria-invalid="true" @enderror>
                                    @error('endDate') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="field">
                                <label for="sp-status" class="label">{{ __('projects.sprint.status') }}</label>
                                <select id="sp-status" class="select" wire:model="status">
                                    @foreach (Milestones::SPRINT_STATUSES as $s)
                                        <option value="{{ $s }}">{{ __('projects.sprint_statuses.' . $s) }}</option>
                                    @endforeach
                                </select>
                                @error('status') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="sp-goal" class="label">{{ __('projects.sprint.goal') }}</label>
                                <textarea id="sp-goal" class="textarea" rows="2" wire:model="goal"></textarea>
                                @error('goal') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="sp-notes" class="label">{{ __('projects.sprint.notes') }}</label>
                                <textarea id="sp-notes" class="textarea" rows="3" wire:model="notes"></textarea>
                                @error('notes') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="pj-actions">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i data-lucide="save"></i> {{ $sprint ? __('projects.drawer.save') : __('projects.sprint.create') }}</button>
                                <button type="button" class="btn btn-secondary" wire:click="close">{{ __('projects.drawer.cancel') }}</button>
                            </div>
                        </form>
                    @elseif ($sprint)
                        <dl class="pj-dl">
                            <dt>{{ __('projects.sprint.dates') }}</dt><dd>{{ __('projects.dates', ['start' => ProjectPlan::day($sprint->start_date), 'end' => ProjectPlan::day($sprint->end_date)]) }}</dd>
                            <dt>{{ __('projects.sprint.status') }}</dt><dd>{{ __('projects.sprint_statuses.' . $sprint->status) }}</dd>
                            <dt>{{ __('projects.sprint.goal') }}</dt><dd>{{ $sprint->goal ?: '—' }}</dd>
                            <dt>{{ __('projects.sprint.notes') }}</dt><dd>{{ $sprint->notes ?: '—' }}</dd>
                        </dl>
                    @endif
                </section>

                @if ($sprint)
                    <section class="pj-section" aria-labelledby="sp-history-title">
                        <h3 id="sp-history-title">{{ __('projects.drawer.history') }}</h3>
                        @include('livewire.hq.projects.partials.history', ['history' => $history])
                    </section>
                @endif
            </div>
        </aside>
    @endif
</div>
