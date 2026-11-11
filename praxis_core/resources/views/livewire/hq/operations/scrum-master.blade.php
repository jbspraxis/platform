<div class="plan">
    {{-- Sent once per page, not with every update (each poll while the AI drafts) --}}
    @assets
    <style>
        /* Plan milestones (PR 5b): only what the shared classes don't cover. Brand tokens only. */
        .plan-grid { display: grid; gap: 24px; align-items: start; }
        @media (min-width: 1280px) { .plan-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); } }
        .plan-stack { display: flex; flex-direction: column; gap: 16px; }
        .plan-sources { margin: 0 0 12px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .plan-scope { min-height: 320px; font-size: 0.8125rem; line-height: 1.25rem; }
        .plan-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .plan-progress { height: 8px; border-radius: 999px; background: var(--color-mist); overflow: hidden; margin-top: 12px; }
        .plan-progress > span { display: block; height: 100%; border-radius: 999px; background: var(--color-deep-teal); transition: width 300ms ease; }
        .plan-spin { width: 18px; height: 18px; animation: plan-spin 1s linear infinite; }
        @keyframes plan-spin { to { transform: rotate(360deg); } }
        .plan-ms { border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); }
        .plan-ms + .plan-ms { margin-top: 8px; }
        .plan-ms > summary { list-style: none; cursor: pointer; display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 4px 10px; align-items: center; padding: 10px 12px; font-size: 0.875rem; line-height: 1.375rem; }
        .plan-ms > summary::-webkit-details-marker { display: none; }
        .plan-ms > summary svg { width: 16px; height: 16px; transition: transform 150ms ease; color: var(--color-muted); }
        .plan-ms[open] > summary svg.plan-chevron { transform: rotate(90deg); }
        .plan-ms > summary:focus-visible { outline: 2px solid var(--color-deep-teal); outline-offset: 2px; border-radius: var(--radius-control); }
        .plan-name { min-width: 0; overflow-wrap: anywhere; color: var(--color-ink); font-weight: 600; }
        .plan-meta { grid-column: 2 / -1; display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 0.75rem; color: var(--color-muted); font-weight: 400; }
        .plan-count { font-size: 0.75rem; font-weight: 600; color: var(--color-deep-teal); white-space: nowrap; }
        .plan-body { border-top: 1px solid var(--color-line); padding: 8px 12px 12px; }
        .plan-tasks { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
        .plan-task { padding: 8px 0; border-bottom: 1px solid var(--color-line); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-ink); }
        .plan-task:last-child { border-bottom: 0; }
        .plan-task-head { display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: baseline; }
        .plan-task-title { flex: 1 1 200px; min-width: 0; overflow-wrap: anywhere; }
        .plan-task-meta { font-size: 0.75rem; color: var(--color-muted); }
        .plan-sugg { margin-top: 8px; padding: 10px 12px; border-radius: var(--radius-control); background: var(--color-teal-tint); border: 1px solid var(--color-line); }
        .plan-sugg.is-note { background: var(--color-mist); }
        .plan-sugg label { display: flex; gap: 8px; align-items: flex-start; cursor: pointer; }
        .plan-sugg input { margin-top: 3px; flex-shrink: 0; }
        .plan-sugg-text { min-width: 0; overflow-wrap: anywhere; }
        .plan-sugg-text strong { display: block; color: var(--color-ink); }
        .plan-detail { display: block; margin: 4px 0 0; white-space: pre-line; color: var(--color-slate); }
        .plan-why { display: block; margin-top: 4px; font-size: 0.75rem; color: var(--color-muted); }
        .plan-sugg.is-stale { background: var(--color-mist); }
        .plan-sugg.is-stale label { cursor: default; }
        .plan-stale { color: var(--color-ink); }
        .plan-diff { display: flex; flex-direction: column; gap: 8px; margin: 8px 0 0 24px; }
        .plan-diff-label { display: block; margin-bottom: 4px; font-size: 0.75rem; font-weight: 600; color: var(--color-slate); }
        .plan-diff-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 8px; }
        .plan-diff-old, .plan-diff-new { padding: 6px 8px; border-radius: var(--radius-control); background: var(--color-paper); white-space: pre-line; overflow-wrap: anywhere; }
        .plan-diff-old { border: 1px solid var(--color-line); color: var(--color-slate); }
        .plan-diff-new { border: 1px solid var(--color-deep-teal); color: var(--color-ink); }
        .plan-diff-head { display: block; font-size: 0.6875rem; font-weight: 600; color: var(--color-muted); }
        .plan-tag { display: inline-block; margin-right: 6px; padding: 0 6px; border-radius: 999px; font-size: 0.6875rem; line-height: 1.125rem; font-weight: 600; background: var(--color-paper); color: var(--color-deep-teal); border: 1px solid var(--color-deep-teal); }
        .plan-save { position: sticky; bottom: 0; margin-top: 8px; padding: 12px 0; border-top: 1px solid var(--color-line); background: var(--color-paper); display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; }
        @media (prefers-reduced-motion: reduce) { .plan-spin { animation: none; } .plan-ms > summary svg { transition: none; } }
        @media (max-width: 600px) { .plan-save .btn, .plan-stack > .btn { width: 100%; } .plan-diff { margin-left: 0; } .plan-diff-row { grid-template-columns: minmax(0, 1fr); } }
    </style>
    @endassets

    @php
        $locale = app()->getLocale();
        $short = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale($locale)->translatedFormat('j M') : '?';
        $hours = fn ($h) => $h !== null ? __('planner.review.hours', ['hours' => rtrim(rtrim(number_format((float) $h, 1, $locale === 'fr' ? ',' : '.', ''), '0'), '.,')]) : __('planner.review.no_estimate');
    @endphp

    <div class="page-header">
        <div style="min-width: 0">
            <h1 class="page-title">{{ __('planner.title') }}</h1>
            <p class="page-subtitle" style="overflow-wrap: anywhere">{{ $project->title }}</p>
        </div>
        <a href="{{ route('hq.ops.project', ['project' => $projectId]) }}" class="btn btn-secondary" wire:navigate><i data-lucide="arrow-left"></i> {{ __('projects.open_page') }}</a>
    </div>


    @if ($notice)
        <div class="alert alert-success mb-4" role="status"><i data-lucide="circle-check"></i><span>{{ $notice }}</span></div>
    @endif
    @if ($warning)
        <div class="alert alert-warning mb-4" role="status"><i data-lucide="triangle-alert"></i><span>{{ $warning }}</span></div>
    @endif

    @if ($mode === 'draft_pending')
        {{-- A new project: its plan comes from the project draft on its opportunity (PR 5) --}}
        <div class="card" role="note">
            <p style="margin: 0 0 12px">{{ __('planner.draft_pending') }}</p>
            @if ($draftOpportunityId)
                <a href="{{ route('hq.crm.opportunity', ['opportunity' => $draftOpportunityId, 'tab' => 'project']) }}" class="btn btn-primary" wire:navigate><i data-lucide="folder-kanban"></i> {{ __('planner.open_draft') }}</a>
            @endif
        </div>
    @else
        @if ($drafting)
            {{-- check() draws the page again only when the AI has moved on (Planner::draftingState) --}}
            <div wire:poll.5s="check" hidden></div>
        @endif

        <div class="plan-grid">
            {{-- The scope, and what to plan --}}
            <section class="card plan-stack" aria-labelledby="plan-scope-title">
                <div>
                    <h2 class="card-title" id="plan-scope-title"><label for="plan-scope">{{ __('planner.scope.label') }}</label></h2>
                    <p class="plan-sources">{{ $sources ? __('planner.scope.sources', ['list' => implode(' · ', $sources)]) : __('planner.scope.no_sources') }}</p>
                    <textarea id="plan-scope" class="textarea plan-scope" wire:model="scope" aria-describedby="plan-scope-help" @disabled($drafting) @error('scope') aria-invalid="true" @enderror></textarea>
                    <span class="help" id="plan-scope-help">{{ __('planner.scope.help') }}</span>
                    @error('scope') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                @error('plan')
                    <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                @enderror

                @if ($mode === 'milestone')
                    <div class="field" style="margin: 0">
                        <label for="plan-milestone" class="label">{{ __('planner.milestone.label') }}</label>
                        <select id="plan-milestone" class="select" wire:model="phaseId" @disabled($drafting)>
                            @foreach ($milestones as $m)
                                <option value="{{ $m->id }}">{{ $m->code }} · {{ $m->name }}</option>
                            @endforeach
                        </select>
                        @error('phaseId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" class="btn btn-primary" wire:click="suggest" wire:loading.attr="disabled" wire:target="suggest" @disabled($drafting)>
                        <i data-lucide="sparkles"></i> {{ __('planner.milestone.suggest') }}
                    </button>
                    <p class="help" style="margin: 0">{{ __('planner.milestone.help') }}</p>
                @else
                    <button type="button" class="btn btn-primary" wire:click="draftPlan" wire:loading.attr="disabled" wire:target="draftPlan" @disabled($drafting)>
                        <i data-lucide="sparkles"></i> {{ __('planner.whole.draft') }}
                    </button>
                    <p class="help" style="margin: 0">{{ __('planner.whole.help') }}</p>
                @endif

                @if ($drafting)
                    <div class="callout" role="status" aria-busy="true">
                        <strong style="display: flex; gap: 8px; align-items: center"><i data-lucide="loader-circle" class="plan-spin"></i> {{ __('planner.progress.title') }}</strong>
                        <p style="margin: 6px 0 0">{{ $plan->pass === 2 && $plan->transcriptMeeting ? __('planner.progress.transcript', ['meeting' => $plan->transcriptMeeting->title]) : __('planner.progress.text') }}</p>
                        @if ($plan->pass === 2 && filled($plan->transcript_reason))
                            <p class="help" style="margin: 4px 0 0">{{ __('planner.progress.why', ['why' => $plan->transcript_reason]) }}</p>
                        @endif
                        @if ($plan->aiRun)
                            <div class="plan-progress" role="progressbar" aria-label="{{ __('planner.progress.title') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $plan->aiRun->progress }}"><span style="width: {{ max(4, (int) $plan->aiRun->progress) }}%"></span></div>
                        @endif
                    </div>
                @elseif ($plan?->status === 'failed')
                    <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ __('planner.failed', ['message' => $plan->aiRun?->errorMessage() ?? __('ai.errors.failed')]) }}</span></div>
                @endif
            </section>

            {{-- The adopted plan, or the drafted one --}}
            <section class="card" aria-labelledby="plan-review-title" x-data="{ all(open) { $root.querySelectorAll('details.plan-ms').forEach(d => d.open = open) } }">
                @if ($mode === 'milestone')
                    <div class="plan-tools">
                        <h2 class="card-title" id="plan-review-title" style="margin: 0">{{ __('planner.review.title') }}</h2>
                        <span style="display: flex; gap: 4px">
                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="all(true)"><i data-lucide="chevrons-down"></i> {{ __('planner.review.expand_all') }}</button>
                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="all(false)"><i data-lucide="chevrons-up"></i> {{ __('planner.review.collapse_all') }}</button>
                        </span>
                    </div>
                    @if ($plan?->status === 'ready')
                        <p class="help" style="margin: 0 0 12px">{{ $countByPhase->sum() ? __('planner.review.intro', ['milestone' => optional($milestones->firstWhere('id', $plan->phase_id))->code]) : __('planner.review.none') }}</p>
                    @endif
                    @error('save')
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                    @enderror

                    @foreach ($milestones as $m)
                        @php $list = $tasks->get($m->id, collect()); @endphp
                        <details class="plan-ms" wire:ignore.self wire:key="pm-{{ $m->id }}">
                            <summary>
                                <i data-lucide="chevron-right" class="plan-chevron"></i>
                                <span class="plan-name">{{ $m->code }} · {{ $m->name }}</span>
                                @php
                                    $badge = array_filter([
                                        ($n = $countByPhase->get($m->id)) ? trans_choice('planner.review.suggestions', $n, ['count' => $n]) : null,
                                        ($n = $notesByPhase->get($m->id)) ? trans_choice('planner.review.notes', $n, ['count' => $n]) : null,
                                    ]);
                                @endphp
                                <span class="plan-count">{{ implode(' · ', $badge) }}</span>
                                <span class="plan-meta">
                                    <span>{{ $short($m->start_date) }} – {{ $short($m->due_date) }}</span>
                                    <span>{{ __('projects.columns.' . $m->column) }}</span>
                                    <span>{{ __('planner.review.done', ['done' => $m->tasks_done, 'total' => $m->tasks_total]) }}</span>
                                </span>
                            </summary>
                            <div class="plan-body">
                                @if ($list->isEmpty() && ! $newByPhase->has($m->id))
                                    <p class="help" style="margin: 0">{{ __('planner.review.no_tasks') }}</p>
                                @endif
                                <ul class="plan-tasks">
                                    @foreach ($list as $task)
                                        <li class="plan-task" wire:key="pt-{{ $task->id }}">
                                            <div class="plan-task-head">
                                                <span class="plan-task-title">{{ $task->title }}</span>
                                                <span class="plan-task-meta">{{ __('projects.task_statuses.' . $task->status) }} · {{ $hours($task->estimated_hours) }} · {{ $task->first_name ? trim($task->first_name . ' ' . $task->last_name) : __('planner.review.no_lead') }}</span>
                                            </div>
                                            @if ($edit = $editByTask->get($task->id))
                                                {{-- Only the fields the AI changes, the task as it is now beside the suggestion --}}
                                                @php
                                                    $asRead = \App\Support\Projects\Planner::stillAsRead($task, $edit->base);
                                                    $fields = array_filter([
                                                        'title' => $edit->title !== null ? [$task->title, $edit->title] : null,
                                                        'detail' => $edit->detail !== null ? [filled($task->description) ? $task->description : __('planner.review.empty'), $edit->detail] : null,
                                                        'estimate' => $edit->estimate_hours !== null ? [$hours($task->estimated_hours), $hours($edit->estimate_hours)] : null,
                                                    ]);
                                                @endphp
                                                <div class="plan-sugg {{ $asRead ? '' : 'is-stale' }}" wire:key="ps-{{ $edit->id }}">
                                                    <label>
                                                        <input type="checkbox" class="checkbox" value="{{ $edit->id }}" wire:model.live="ticked" @disabled(! $asRead)>
                                                        <span class="plan-sugg-text">
                                                            <span class="plan-tag">{{ __('planner.review.edit') }}</span>
                                                            @if (filled($edit->why))
                                                                <span class="plan-why">{{ __('planner.review.why', ['why' => $edit->why]) }}</span>
                                                            @endif
                                                            @unless ($asRead)
                                                                <span class="plan-why plan-stale">{{ __('planner.review.stale') }}</span>
                                                            @endunless
                                                        </span>
                                                    </label>
                                                    <div class="plan-diff">
                                                        @foreach ($fields as $field => [$before, $after])
                                                            <div>
                                                                <span class="plan-diff-label">{{ __('planner.review.fields.' . $field) }}</span>
                                                                <div class="plan-diff-row">
                                                                    <div class="plan-diff-old"><span class="plan-diff-head">{{ __('planner.review.now') }}</span>{{ $before }}</div>
                                                                    <div class="plan-diff-new"><span class="plan-diff-head">{{ __('planner.review.suggested') }}</span>{{ $after }}</div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                            @foreach ($notesByTask->get($task->id, collect()) as $note)
                                                <div class="plan-sugg is-note" wire:key="pn-{{ $note->id }}">
                                                    <span class="plan-sugg-text"><span class="plan-tag">{{ __('planner.review.note') }}</span>{{ $note->why }}</span>
                                                </div>
                                            @endforeach
                                        </li>
                                    @endforeach
                                    @foreach ($newByPhase->get($m->id, collect()) as $new)
                                        <li class="plan-task" wire:key="pnew-{{ $new->id }}">
                                            <div class="plan-sugg">
                                                <label>
                                                    <input type="checkbox" class="checkbox" value="{{ $new->id }}" wire:model.live="ticked">
                                                    <span class="plan-sugg-text">
                                                        <span class="plan-tag">{{ __('planner.review.new') }}</span>
                                                        <strong>{{ $new->title }}</strong>
                                                        <span class="plan-task-meta">{{ $hours($new->estimate_hours) }}</span>
                                                        @if (filled($new->detail))
                                                            <span class="plan-detail">{{ $new->detail }}</span>
                                                        @endif
                                                    </span>
                                                </label>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    @endforeach

                    @if ($plan?->status === 'ready' && $countByPhase->sum())
                        <p class="help" style="margin: 16px 0 0">{{ __('planner.save_help') }}</p>
                        <div class="plan-save">
                            <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save"><i data-lucide="save"></i> {{ trans_choice('planner.save', count($ticked), ['count' => count($ticked)]) }}</button>
                        </div>
                    @endif

                @else
                    <h2 class="card-title" id="plan-review-title" style="margin-bottom: 12px">{{ __('planner.whole.title') }}</h2>
                    @error('save')
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                    @enderror
                    @if ($plan?->status === 'ready' && $plan->phase_id === null)
                        <p class="help" style="margin: 0 0 12px">{{ __('planner.whole.intro') }}</p>
                        @foreach ((array) $plan->output as $mi => $dm)
                            <details class="plan-ms" open wire:key="pd-{{ $mi }}-{{ $plan->revision }}">
                                <summary>
                                    <i data-lucide="chevron-right" class="plan-chevron"></i>
                                    <span class="plan-name">{{ __('planner.whole.code', ['n' => $mi + 1]) }} · {{ $dm['title'] }}</span>
                                    <span class="plan-count">{{ trans_choice('planner.whole.tasks', count($dm['tasks']), ['count' => count($dm['tasks'])]) }}</span>
                                    @if (filled($dm['goal']))<span class="plan-meta">{{ $dm['goal'] }}</span>@endif
                                </summary>
                                <div class="plan-body">
                                    <ul class="plan-tasks">
                                        @foreach ($dm['tasks'] as $ti => $dt)
                                            <li class="plan-task" wire:key="pdt-{{ $mi }}-{{ $ti }}-{{ $plan->revision }}">
                                                <div class="plan-task-head">
                                                    <span class="plan-task-title">{{ $dt['title'] }}</span>
                                                    <span class="plan-task-meta">{{ $hours($dt['estimate_hours']) }}</span>
                                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="removeDrafted({{ $mi }}, {{ $ti }})" aria-label="{{ __('planner.whole.remove_task', ['task' => $dt['title']]) }}" title="{{ __('planner.whole.remove_task', ['task' => $dt['title']]) }}"><i data-lucide="trash-2"></i></button>
                                                </div>
                                                @if (filled($dt['detail']))<span class="plan-detail">{{ $dt['detail'] }}</span>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn btn-ghost btn-sm" style="margin-top: 8px" wire:click="removeDrafted({{ $mi }})" wire:confirm="{{ __('planner.whole.remove_confirm') }}"><i data-lucide="trash-2"></i> {{ __('planner.whole.remove_milestone') }}</button>
                                </div>
                            </details>
                        @endforeach
                        <p class="help" style="margin: 16px 0 0">{{ __('planner.whole.save_help') }}</p>
                        <div class="plan-save">
                            <button type="button" class="btn btn-primary" wire:click="saveWhole" wire:loading.attr="disabled" wire:target="saveWhole"><i data-lucide="save"></i> {{ __('planner.whole.save') }}</button>
                        </div>
                    @else
                        <div class="empty-state rounded-lg border border-dashed border-line">
                            <i data-lucide="layout-template" class="mx-auto mb-3 h-8 w-8 text-muted"></i>
                            <strong>{{ __('planner.whole.empty_title') }}</strong>
                            {{ __('planner.whole.empty') }}
                        </div>
                    @endif
                @endif
            </section>
        </div>
    @endif
</div>
