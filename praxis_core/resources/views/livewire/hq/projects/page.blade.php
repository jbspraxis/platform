@use('App\Support\Projects\ChangeReports')
@use('App\Support\Projects\ProjectPlan')
<div class="pj">
    @include('livewire.hq.projects.partials.styles')
    @php
        $day = fn ($d, $f = 'date') => ProjectPlan::day($d, $f);
        $pct = fn ($v) => ProjectPlan::percent($v);
        $money = fn ($a, $c) => ProjectPlan::money($a, $c);
        $person = fn ($first, $last) => trim($first . ' ' . $last);
        $leadName = $person($project->lead_first_name, $project->lead_last_name) ?: __('projects.no_lead');
        $columnTone = ['not_started' => 'pill-neutral', 'in_progress' => 'pill-info', 'client_review' => 'pill-warning', 'accepted' => 'pill-success', 'invoiced' => 'pill-info', 'paid' => 'pill-success'];
        $taskTone = ['To-Do' => 'pill-neutral', 'In-Progress' => 'pill-info', 'QA_Review' => 'pill-warning', 'Failed' => 'pill-danger', 'Done' => 'pill-success'];
        $tabUrl = fn ($key, $extra = []) => route('hq.ops.project', ['project' => $projectId] + ($key === 'overview' ? [] : ['tab' => $key]) + $extra);
        // Opening a milestone shows its task board (sprints were folded into milestones, docs/PROJECTS.md)
        $boardUrl = fn ($m) => $tabUrl('board', ['board' => $m->id]);
        $tabs = [
            'overview' => __('projects.tabs.overview'),
            'milestones' => __('projects.tabs.milestones'),
            'board' => __('projects.tabs.board'),
            'backlog' => __('projects.tabs.backlog'),
            'qa' => __('projects.tabs.qa') . ($openQa ? ' (' . $openQa . ')' : ''),
            'money' => __('projects.tabs.money'),
        ];
    @endphp

    <a href="{{ route('hq.ops.deployments') }}" class="pj-back" wire:navigate><i data-lucide="arrow-left"></i> {{ __('projects.back') }}</a>

    <div class="page-header">
        <div style="min-width: 0">
            <h1 class="page-title" style="overflow-wrap: anywhere">{{ $project->title }}</h1>
            <div class="pj-meta">
                <span class="pill {{ $project->status === 'Active' ? 'pill-info' : ($project->status === 'Completed' ? 'pill-success' : 'pill-neutral') }}">{{ __('projects.project_statuses.' . $project->status) }}</span>
                <span><i data-lucide="building-2"></i> {{ $project->client_name }}</span>
                <span><i data-lucide="user-round"></i> {{ __('projects.led_by', ['name' => $leadName]) }}</span>
                @if ($project->start_date || $project->target_end_date)
                    <span><i data-lucide="calendar"></i> {{ __('projects.dates', ['start' => $day($project->start_date) ?: '—', 'end' => $day($project->target_end_date) ?: '—']) }}</span>
                @endif
            </div>
        </div>
        @if ($canManage)
            <div class="pj-actions">
                <button type="button" class="btn btn-primary" wire:click="$dispatch('new-task')"><i data-lucide="plus"></i> {{ __('projects.backlog.new_task') }}</button>
            </div>
        @endif
    </div>

    <x-flash class="mb-6" />

    <section class="pj-stats" aria-label="{{ __('projects.stats.label') }}">
        <div class="pj-stat">
            <div class="stat-label">{{ __('projects.stats.progress') }}</div>
            <div class="stat-value num" style="text-align: left">{{ $pct($progress) }}</div>
            <div class="pj-bar mt-2 {{ $progress >= 100 ? 'is-done' : '' }}" role="img" aria-label="{{ __('projects.stats.progress_label', ['value' => $pct($progress)]) }}"><span style="width: {{ min(100, $progress) }}%"></span></div>
        </div>
        @if ($canSeeMoney)
            <div class="pj-stat">
                <div class="stat-label">{{ __('projects.stats.contract') }}</div>
                <div class="stat-value num" style="text-align: left">{{ $money($project->total_budget, $project->currency) }}</div>
            </div>
        @endif
        <div class="pj-stat">
            <div class="stat-label">{{ __('projects.stats.next_due') }}</div>
            @if ($nextDue)
                <div class="stat-value" style="font-size: 1rem; line-height: 1.5rem">
                    <button type="button" class="pj-link" wire:click="$dispatch('open-milestone', { id: '{{ $nextDue->id }}' })">{{ $nextDue->code }} · {{ $nextDue->name }}</button>
                </div>
                <div class="pj-stat-note">
                    {{ __('projects.due', ['date' => $day($nextDue->due_date)]) }}
                    @if ($nextDue->overdue) <span class="pill pill-danger ml-1">{{ __('projects.overdue') }}</span> @endif
                </div>
            @else
                <div class="stat-value">—</div>
            @endif
        </div>
        <div class="pj-stat">
            <div class="stat-label">{{ __('projects.stats.overdue') }}</div>
            <div class="stat-value num {{ $overdueTasks->count() ? 'text-danger' : '' }}" style="text-align: left">
                <a href="{{ $tabUrl('backlog', ['overdue' => 1]) }}" wire:navigate style="color: inherit">{{ $overdueTasks->count() }}</a>
            </div>
            <div class="pj-stat-note">{{ __('projects.stats.overdue_note') }}</div>
        </div>
        <div class="pj-stat">
            <div class="stat-label">{{ __('projects.stats.open_qa') }}</div>
            <div class="stat-value num" style="text-align: left"><a href="{{ $tabUrl('qa') }}" wire:navigate style="color: inherit">{{ $openQa }}</a></div>
        </div>
    </section>

    {{-- Each tab is its own address (links, not buttons), so the milestone board's own script runs when its tab opens --}}
    <nav class="tabs" aria-label="{{ __('projects.tabs.label') }}">
        @foreach ($tabs as $key => $label)
            <a href="{{ $tabUrl($key) }}" @class(['tab', 'is-active' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif wire:navigate wire:key="tab-{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div>
        @if ($tab === 'overview')
            <section class="mb-6" aria-labelledby="pj-strip-title">
                <div class="pj-toolbar" style="margin-bottom: 10px">
                    <h2 class="section-title" id="pj-strip-title" style="margin: 0">{{ __('projects.overview.milestones') }}</h2>
                    <a href="{{ $tabUrl('milestones') }}" class="btn btn-ghost btn-sm" wire:navigate>{{ __('projects.overview.open_kanban') }} <i data-lucide="arrow-right"></i></a>
                </div>
                @if ($milestones->isEmpty())
                    <div class="card empty-state"><strong>{{ __('projects.milestones.empty_title') }}</strong>{{ __('projects.milestones.empty_text') }}</div>
                @else
                    <div class="pj-strip">
                        @foreach ($milestones as $m)
                            <a href="{{ $boardUrl($m) }}" class="pj-mini" wire:key="strip-{{ $m->id }}" wire:navigate aria-label="{{ __('projects.milestones.open_board', ['code' => $m->code, 'name' => $m->name]) }}">
                                <span class="pj-mini-top"><span class="tag">{{ $m->code }}</span> <span class="num">{{ $pct($m->weight_percentage) }}</span></span>
                                <span class="pj-mini-title">{{ $m->name }}</span>
                                <span class="pj-pills">
                                    <span class="pill {{ $columnTone[$m->column] }}">{{ __('projects.columns.' . $m->column) }}</span>
                                    @if ($m->overdue) <span class="pill pill-danger">{{ __('projects.overdue') }}</span> @endif
                                </span>
                                <span class="pj-bar {{ $m->progress >= 100 ? 'is-done' : '' }}" role="img" aria-label="{{ __('projects.milestones.progress', ['done' => $m->tasks_done, 'total' => $m->tasks_total]) }}"><span style="width: {{ $m->progress }}%"></span></span>
                                <span class="pj-mini-top"><span>{{ $m->due_date ? __('projects.due', ['date' => $day($m->due_date, 'short')]) : __('projects.no_due') }}</span><span class="num">{{ $pct($m->progress) }}</span></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="pj-grid">
                <section class="card" aria-labelledby="pj-now-title">
                    <h2 class="card-title" id="pj-now-title" style="margin-bottom: 12px">{{ __('projects.overview.now') }}</h2>
                    @if ($current->isNotEmpty())
                        <ul class="pj-list">
                            @foreach ($current as $m)
                                <li wire:key="now-{{ $m->id }}">
                                    <a href="{{ $boardUrl($m) }}" class="pj-link" wire:navigate>{{ $m->code }} · {{ $m->name }}</a>
                                    <span class="pill {{ $columnTone[$m->column] }} ml-1">{{ __('projects.columns.' . $m->column) }}</span>
                                    @if ($m->overdue) <span class="pill pill-danger">{{ __('projects.overdue') }}</span> @endif
                                    <span class="pj-sub">
                                        {{ ($m->start_date || $m->due_date) ? __('projects.dates', ['start' => $day($m->start_date) ?: '…', 'end' => $day($m->due_date) ?: '…']) . ' · ' : '' }}{{ __('projects.milestones.progress', ['done' => $m->tasks_done, 'total' => $m->tasks_total]) }}
                                    </span>
                                    @if ($m->goal) <span class="pj-sub">{{ __('projects.overview.goal', ['goal' => \Illuminate\Support\Str::limit($m->goal, 140)]) }}</span> @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="help" style="margin: 0">{{ __('projects.overview.nothing_now') }}</p>
                    @endif
                </section>

                <section class="card" aria-labelledby="pj-overdue-title">
                    <h2 class="card-title" id="pj-overdue-title" style="margin-bottom: 12px">{{ __('projects.overview.overdue') }}</h2>
                    @php $lateMilestones = $milestones->where('overdue', true); @endphp
                    @if ($overdueTasks->isEmpty() && $lateMilestones->isEmpty())
                        <p class="help" style="margin: 0">{{ __('projects.overview.nothing_overdue') }}</p>
                    @else
                        <ul class="pj-list">
                            @foreach ($lateMilestones as $m)
                                <li wire:key="late-m-{{ $m->id }}">
                                    <button type="button" class="pj-link" wire:click="$dispatch('open-milestone', { id: '{{ $m->id }}' })">{{ $m->code }} · {{ $m->name }}</button>
                                    <span class="pj-sub">{{ __('projects.overview.milestone_due', ['date' => $day($m->due_date)]) }}</span>
                                </li>
                            @endforeach
                            @foreach ($overdueTasks->take(8) as $t)
                                <li wire:key="late-t-{{ $t->id }}">
                                    <button type="button" class="pj-link" wire:click="$dispatch('open-task', { id: '{{ $t->id }}' })">{{ $t->title }}</button>
                                    <span class="pj-sub">M{{ $t->phase_number }} · {{ __('projects.due', ['date' => $day($t->due_date)]) }} · {{ $person($t->first_name, $t->last_name) ?: __('projects.no_lead') }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($overdueTasks->count() > 8)
                            <a href="{{ $tabUrl('backlog', ['overdue' => 1]) }}" class="btn btn-ghost btn-sm mt-2" wire:navigate>{{ __('projects.overview.all_overdue', ['count' => $overdueTasks->count()]) }}</a>
                        @endif
                    @endif
                </section>

                @if ($canSetLead)
                    <section class="card" aria-labelledby="pj-lead-title">
                        <h2 class="card-title" id="pj-lead-title" style="margin-bottom: 12px">{{ __('projects.lead.title') }}</h2>
                        <form wire:submit="saveLead" class="pj-form" novalidate>
                            <div class="field">
                                <label for="pj-lead" class="sr-only">{{ __('projects.lead.title') }}</label>
                                <select id="pj-lead" class="select" wire:model="leadUserId" @error('leadUserId') aria-invalid="true" aria-describedby="pj-lead-error" @enderror>
                                    <option value="">{{ __('projects.no_lead') }}</option>
                                    @foreach ($team as $p)
                                        <option value="{{ $p->id }}" wire:key="pl-{{ $p->id }}">{{ $person($p->first_name, $p->last_name) }}</option>
                                    @endforeach
                                </select>
                                <span class="help">{{ __('projects.lead.help') }}</span>
                                @error('leadUserId') <span class="field-error" id="pj-lead-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="pj-actions"><button type="submit" class="btn btn-secondary btn-sm">{{ __('projects.lead.save') }}</button></div>
                        </form>
                    </section>
                @endif

                <section class="card" aria-labelledby="pj-activity-title">
                    <h2 class="card-title" id="pj-activity-title" style="margin-bottom: 12px">{{ __('projects.overview.activity') }}</h2>
                    @if ($activity->isEmpty())
                        <p class="help" style="margin: 0">{{ __('projects.overview.no_activity') }}</p>
                    @else
                        <ol class="pj-list">
                            @foreach ($activity as $line)
                                <li>
                                    @if ($line->subject) <strong style="color: var(--color-ink); font-weight: 600">{{ $line->subject }}:</strong> @endif
                                    {{ $line->text }}
                                    <time datetime="{{ \Illuminate\Support\Carbon::parse($line->when)->toIso8601String() }}">{{ $line->who }} · {{ $day($line->when, 'datetime') }}</time>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>

        @elseif ($tab === 'milestones')
            <div class="pj-toolbar">
                <div class="pj-toolbar-note">
                    <span>{{ __('projects.milestones.weight_total', ['value' => $pct($weightTotal)]) }}</span>
                    @if ($milestones->isNotEmpty() && abs($weightTotal - 100) >= 0.01)
                        <span class="pill pill-warning">{{ __('projects.milestones.weight_warning') }}</span>
                    @endif
                    <span class="help">{{ $canManage ? __('projects.milestones.help_manage') : __('projects.milestones.help_read') }}</span>
                </div>
                @if ($canManage)
                    <button type="button" class="btn btn-secondary" wire:click="$dispatch('new-milestone')"><i data-lucide="plus"></i> {{ __('projects.milestones.new') }}</button>
                @endif
            </div>

            <div class="pj-kanban" x-data="{ dragId: null, over: null }">
                @foreach (ProjectPlan::COLUMNS as $column)
                    @php $cards = $milestones->where('column', $column); @endphp
                    <section class="pj-col" wire:key="col-{{ $column }}" aria-labelledby="col-{{ $column }}-title"
                             :class="{ 'is-over': over === '{{ $column }}' }"
                             @if ($canManage)
                                 x-on:dragover.prevent="over = '{{ $column }}'" x-on:dragleave="over = null"
                                 x-on:drop.prevent="over = null; if (dragId) { $wire.moveMilestone(dragId, '{{ $column }}'); dragId = null }"
                             @endif>
                        <h2 class="pj-col-head" id="col-{{ $column }}-title" style="margin: 0">
                            <span>{{ __('projects.columns.' . $column) }}</span>
                            <small class="num">{{ $cards->count() }} · {{ $pct($cards->sum(fn ($m) => (float) $m->weight_percentage)) }}</small>
                        </h2>
                        <div class="pj-col-body">
                            @forelse ($cards as $m)
                                <article class="pj-card" wire:key="card-{{ $m->id }}" @if ($canManage) draggable="true" x-on:dragstart="dragId = '{{ $m->id }}'" x-on:dragend="dragId = null; over = null" @endif>
                                    <div class="pj-card-top">
                                        <span class="tag">{{ $m->code }}</span>
                                        <span class="pj-pills" style="align-items: center">
                                            <span class="text-caption num" style="color: var(--color-slate)" title="{{ __('projects.milestone.weight') }}">{{ $pct($m->weight_percentage) }}</span>
                                            <button type="button" class="pj-icon-btn is-small" wire:click="$dispatch('open-milestone', { id: '{{ $m->id }}' })"
                                                    aria-label="{{ __('projects.milestones.details', ['code' => $m->code]) }}" title="{{ __('projects.milestones.details', ['code' => $m->code]) }}"><i data-lucide="pencil"></i></button>
                                        </span>
                                    </div>
                                    {{-- Opening a milestone shows its task board --}}
                                    <a href="{{ $boardUrl($m) }}" class="pj-link" wire:navigate>{{ $m->name }}</a>
                                    <div class="pj-pills">
                                        @if ($m->due_date)
                                            <span class="pill {{ $m->overdue ? 'pill-danger' : 'pill-neutral' }}">{{ $m->overdue ? __('projects.overdue') . ' · ' : '' }}{{ __('projects.due', ['date' => $day($m->due_date, 'short')]) }}</span>
                                        @endif
                                        @if ($m->invoice)
                                            <span class="pill {{ $m->invoice->status === 'Paid' ? 'pill-success' : ($m->invoice->status === 'Overdue' ? 'pill-danger' : 'pill-info') }}">{{ __('projects.invoice_badge.' . $m->invoice->status, ['number' => \Illuminate\Support\Str::afterLast($m->invoice->invoice_number, '-')]) }}</span>
                                        @endif
                                        @foreach ($m->waits_on->where('done', false) as $w)
                                            <span class="pill pill-warning" title="{{ $w->code }} · {{ $w->name }}">{{ __('projects.waits.on', ['what' => $w->code]) }}</span>
                                        @endforeach
                                    </div>
                                    <div class="pj-bar {{ $m->progress >= 100 ? 'is-done' : '' }}" role="img" aria-label="{{ __('projects.milestones.progress', ['done' => $m->tasks_done, 'total' => $m->tasks_total]) }}"><span style="width: {{ $m->progress }}%"></span></div>
                                    <div class="pj-card-foot">
                                        <span>{{ __('projects.milestones.progress', ['done' => $m->tasks_done, 'total' => $m->tasks_total]) }}</span>
                                        @if ($canManage)
                                            <label class="sr-only" for="move-{{ $m->id }}">{{ __('projects.milestones.move_label', ['code' => $m->code]) }}</label>
                                            <select id="move-{{ $m->id }}" class="select pj-move" style="width: auto" x-on:change="$wire.moveMilestone('{{ $m->id }}', $event.target.value); $event.target.value = ''">
                                                <option value="">{{ __('projects.milestones.move') }}</option>
                                                @foreach (ProjectPlan::COLUMNS as $to)
                                                    @if ($to !== $column)
                                                        <option value="{{ $to }}">{{ __('projects.columns.' . $to) }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="pj-empty-col">{{ __('projects.milestones.empty_column') }}</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>

        @elseif ($tab === 'board')
            {{-- One milestone's tasks by status (the sprint board, now taking a milestone) --}}
            <livewire:hq.operations.sprint-board :id="$projectId" :milestone="$boardMilestoneId" :key="'board-' . $projectId . '-' . ($boardMilestoneId ?? 'now')" />

        @elseif ($tab === 'backlog')
            {{-- Every task of the project, under its milestone (every task has one; ideas go into a 0% milestone such as "Later") --}}
            <div class="pj-filters">
                <div class="field">
                    <label for="f-lead" class="label">{{ __('projects.backlog.lead') }}</label>
                    <select id="f-lead" class="select" wire:model.live="filterLead">
                        <option value="">{{ __('projects.backlog.anyone') }}</option>
                        <option value="none">{{ __('projects.no_lead') }}</option>
                        @foreach ($leads as $l)
                            <option value="{{ $l->id }}" wire:key="fl-{{ $l->id }}">{{ $person($l->first_name, $l->last_name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="f-status" class="label">{{ __('projects.backlog.status') }}</label>
                    <select id="f-status" class="select" wire:model.live="filterStatus">
                        <option value="">{{ __('projects.backlog.any_status') }}</option>
                        @foreach (ProjectPlan::TASK_STATUSES as $s)
                            <option value="{{ $s }}">{{ __('projects.task_statuses.' . $s) }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="pj-check"><input type="checkbox" wire:model.live="filterOverdue"> {{ __('projects.backlog.overdue_only') }}</label>
            </div>

            <div class="pj-toolbar">
                <p class="help" style="margin: 0" role="status">{{ trans_choice('projects.backlog.count', $taskCount, ['count' => $taskCount]) }}</p>
                @if ($filtered)
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters">{{ __('projects.backlog.clear') }}</button>
                @endif
            </div>

            @if ($milestones->isEmpty())
                <div class="card empty-state"><strong>{{ __('projects.milestones.empty_title') }}</strong>{{ __('projects.milestones.empty_text') }}</div>
            @elseif ($taskCount === 0 && $filtered)
                <div class="card empty-state">
                    <strong>{{ __('projects.backlog.filtered_title') }}</strong>{{ __('projects.backlog.filtered_text') }}
                    <div class="mt-4"><button type="button" class="btn btn-secondary btn-sm" wire:click="clearFilters">{{ __('projects.backlog.clear') }}</button></div>
                </div>
            @endif

            @foreach ($milestones as $m)
                @php $group = $tasks->get($m->id, collect()); @endphp
                @if ($group->isNotEmpty() || (! $filtered && $canManage))
                    <section class="pj-group" wire:key="group-{{ $m->id }}" aria-labelledby="group-{{ $m->id }}-title">
                        <div class="pj-group-head">
                            <h3 id="group-{{ $m->id }}-title"><span class="tag mr-1">{{ $m->code }}</span> <a href="{{ $boardUrl($m) }}" wire:navigate style="color: inherit">{{ $m->name }}</a></h3>
                            <div class="pj-pills" style="align-items: center">
                                <span class="help">{{ $pct($m->weight_percentage) }} · {{ $m->due_date ? __('projects.due', ['date' => $day($m->due_date, 'short')]) . ' · ' : '' }}{{ __('projects.milestones.progress', ['done' => $m->tasks_done, 'total' => $m->tasks_total]) }}</span>
                                @if ($canManage)
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="$dispatch('new-task', { phase: '{{ $m->id }}' })"><i data-lucide="plus"></i> {{ __('projects.backlog.new_task') }}</button>
                                @endif
                            </div>
                        </div>
                        @if ($group->isEmpty())
                            <p class="help" style="margin: 10px 0 0">{{ __('projects.backlog.group_empty') }}</p>
                        @else
                            <ul class="pj-rows">
                                @foreach ($group as $t)
                                    @php $late = ProjectPlan::taskOverdue($t); $w = $waits->get($t->id); @endphp
                                    <li class="pj-row" wire:key="row-{{ $t->id }}">
                                        <div class="pj-row-cell">
                                            <button type="button" class="pj-link" wire:click="$dispatch('open-task', { id: '{{ $t->id }}' })">{{ $t->title }}</button>
                                            @if ($w)
                                                <span class="pill pill-warning ml-1" title="{{ $w->pluck('title')->implode(' · ') }}">{{ trans_choice('projects.waits.card', $w->count(), ['count' => $w->count()]) }}</span>
                                            @endif
                                        </div>
                                        <div class="pj-row-cell"><span class="pj-row-label">{{ __('projects.backlog.status') }}</span><span class="pill {{ $taskTone[$t->status] ?? 'pill-neutral' }}">{{ __('projects.task_statuses.' . $t->status) }}</span></div>
                                        <div class="pj-row-cell"><span class="pj-row-label">{{ __('projects.backlog.lead') }}</span>{{ $person($t->first_name, $t->last_name) ?: __('projects.no_lead') }}@if (($sharing[$t->id] ?? 0) > 0) <span class="tag ml-1">+{{ $sharing[$t->id] }}</span>@endif</div>
                                        <div class="pj-row-cell"><span class="pj-row-label">{{ __('projects.task.due_date') }}</span>
                                            @if ($t->due_date)
                                                <span @class(['pill', 'pill-danger' => $late, 'pill-neutral' => ! $late])>{{ $late ? __('projects.overdue') . ' · ' : '' }}{{ $day($t->due_date, 'short') }}</span>
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
            @endforeach

        @elseif ($tab === 'qa')
            <div class="pj-toolbar">
                <p class="help" style="margin: 0">{{ __('projects.qa.intro') }}</p>
                @can('qa.view')
                    <a href="{{ route('hq.ops.qa', ['project' => $projectId]) }}" class="btn btn-secondary" wire:navigate><i data-lucide="external-link"></i> {{ __('projects.qa.open_hub') }}</a>
                @endcan
            </div>
            @if ($tickets->isEmpty())
                <div class="card empty-state"><strong>{{ __('projects.qa.empty_title') }}</strong>{{ __('projects.qa.empty_text') }}</div>
            @else
                <div class="card" style="padding: 0">
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>{{ __('projects.qa.ticket') }}</th><th>{{ __('projects.qa.severity') }}</th><th>{{ __('projects.qa.status') }}</th><th>{{ __('projects.qa.task') }}</th><th>{{ __('projects.qa.raised') }}</th></tr></thead>
                            <tbody>
                                @foreach ($tickets as $t)
                                    <tr wire:key="qa-{{ $t->id }}">
                                        <td style="min-width: 220px">
                                            @can('qa.view')
                                                <a href="{{ route('hq.ops.qa', ['ticket' => $t->id]) }}" wire:navigate>{{ $t->title }}</a>
                                            @else
                                                {{ $t->title }}
                                            @endcan
                                        </td>
                                        <td><span class="pill {{ $t->severity === 'Critical' ? 'pill-danger' : ($t->severity === 'Medium' ? 'pill-warning' : 'pill-neutral') }}">{{ __('projects.qa.severities.' . $t->severity) }}</span></td>
                                        <td>{{ __('projects.qa.statuses.' . $t->status) }}</td>
                                        <td>{{ $t->task_title ?: '—' }}</td>
                                        <td style="white-space: nowrap">{{ $day($t->created_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        @elseif ($tab === 'money')
            <div class="pj-grid">
                <section class="card" aria-labelledby="pj-meetings-title">
                    <h2 class="card-title" id="pj-meetings-title" style="margin-bottom: 12px">{{ __('projects.money.meetings') }}</h2>
                    @if ($opportunity)
                        <p class="help" style="margin: 0 0 12px">{{ __('projects.money.meetings_text', ['title' => $opportunity->title]) }}</p>
                        @if ($canOpenOpportunity)
                            <div class="pj-actions">
                                <a href="{{ route('hq.crm.opportunity', ['opportunity' => $opportunity->id, 'tab' => 'meetings']) }}" class="btn btn-secondary btn-sm" wire:navigate><i data-lucide="calendar"></i> {{ __('projects.money.open_meetings') }}</a>
                                <a href="{{ route('hq.crm.opportunity', ['opportunity' => $opportunity->id, 'tab' => 'sow']) }}" class="btn btn-secondary btn-sm" wire:navigate><i data-lucide="file-signature"></i> {{ __('projects.money.open_sow') }}</a>
                            </div>
                        @endif
                        @if ($sow)
                            <p class="help" style="margin: 12px 0 0">{{ __('projects.money.sow', ['ref' => $sow->reference ?: __('projects.money.sow_no_ref'), 'version' => $sow->version, 'date' => $sow->signed_at ? $day($sow->signed_at) : '—']) }}</p>
                        @endif
                    @else
                        <p class="help" style="margin: 0">{{ __('projects.money.no_opportunity') }}</p>
                    @endif
                </section>

                <section class="card" aria-labelledby="pj-invoices-title">
                    <div class="card-header" style="margin-bottom: 12px">
                        <h2 class="card-title" id="pj-invoices-title">{{ __('projects.money.invoices') }}</h2>
                        @can('finance.view')
                            <a href="{{ route('hq.finance.invoices') }}" class="btn btn-ghost btn-sm" wire:navigate>{{ __('projects.money.open_finance') }} <i data-lucide="arrow-right"></i></a>
                        @endcan
                    </div>
                    @if ($invoices->isEmpty())
                        <p class="help" style="margin: 0">{{ __('projects.money.no_invoices') }}</p>
                    @else
                        <ul class="pj-list">
                            @foreach ($invoices as $inv)
                                <li wire:key="inv-{{ $inv->id }}" style="display: flex; justify-content: space-between; gap: 12px">
                                    <span style="min-width: 0; overflow-wrap: anywhere">
                                        <strong style="color: var(--color-ink); font-weight: 600">{{ $inv->invoice_number }}</strong>
                                        <span class="pj-sub">{{ $inv->phase_number !== null ? 'M' . $inv->phase_number : __('projects.money.no_milestone') }} · {{ __('projects.invoice_statuses.' . $inv->status) }}{{ $inv->paid_at ? ' · ' . $day($inv->paid_at) : '' }}</span>
                                    </span>
                                    @if ($canSeeMoney)
                                        <span class="num" style="color: var(--color-ink)">{{ $money($inv->total_amount, $inv->currency) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="card" aria-labelledby="pj-reports-title">
                    <div class="card-header" style="margin-bottom: 12px">
                        <h2 class="card-title" id="pj-reports-title">{{ __('projects.money.reports') }}</h2>
                        @if ($canManage)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="$dispatch('new-report')"><i data-lucide="plus"></i> {{ __('projects.money.new_report') }}</button>
                        @endif
                    </div>
                    <p class="help" style="margin: 0 0 12px">{{ __('projects.money.reports_text') }}</p>
                    @if ($reports->isEmpty())
                        <p class="help" style="margin: 0">{{ __('projects.money.no_reports') }}</p>
                    @else
                        <ul class="pj-list">
                            @foreach ($reports as $r)
                                <li wire:key="cr-{{ $r->id }}" style="display: flex; justify-content: space-between; gap: 12px">
                                    <span style="min-width: 0">
                                        <button type="button" class="pj-link" wire:click="$dispatch('open-report', { id: '{{ $r->id }}' })">{{ ChangeReports::label($r) }} · {{ $r->title }}</button>
                                        <span class="pj-sub">
                                            <span class="pill {{ ['approved' => 'pill-success', 'declined' => 'pill-neutral', 'sent' => 'pill-info'][$r->status] ?? 'pill-warning' }}" style="margin-top: 4px">{{ __('projects.cr_statuses.' . $r->status) }}</span>
                                            {{ $r->decided_on ? $day($r->decided_on) : '' }}
                                        </span>
                                    </span>
                                    @if ($canSeeMoney)
                                        <span class="num" style="color: var(--color-ink)">{{ $money($r->price, $r->currency) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif
    </div>

    <livewire:hq.projects.task-drawer :project-id="$projectId" :key="'task-drawer-' . $projectId" />
    <livewire:hq.projects.milestone-drawer :project-id="$projectId" :key="'milestone-drawer-' . $projectId" />
    <livewire:hq.projects.change-report-drawer :project-id="$projectId" :key="'report-drawer-' . $projectId" />
</div>
