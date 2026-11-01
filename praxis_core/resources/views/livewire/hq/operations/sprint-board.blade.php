<div>
    @php
        // Stored task statuses (database values) and what people read; QA_Review is the client’s 5-hour review, as named in the Client Portal
        $columnLabels = collect(['To-Do', 'In-Progress', 'QA_Review', 'Failed', 'Done'])->mapWithKeys(fn ($s) => [$s => __('projects.task_statuses.' . $s)])->all();
    @endphp

    {{-- On the project page's Milestone board tab (docs/PROJECTS.md); the page holds the header and the drawers --}}
    <div class="page-header" style="margin-bottom: 16px">
        <div style="min-width: 0">
            <p class="page-subtitle" style="margin-top: 0">{{ __('projects.board.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            @if(!empty($milestones))
                <div class="field" style="min-width: 0; max-width: 100%">
                    <label for="milestone-select" class="label">{{ __('projects.board.milestone') }}</label>
                    <select id="milestone-select" wire:model.live="activeMilestoneId" class="select" style="min-width: min(280px, 100%); max-width: 100%">
                        @foreach($milestones as $m)
                            <option value="{{ $m->id }}">{{ $m->code }} · {{ $m->name }}@if($m->start_date || $m->due_date) ({{ \App\Support\Projects\ProjectPlan::day($m->start_date, 'short') ?: '…' }} – {{ \App\Support\Projects\ProjectPlan::day($m->due_date, 'short') ?: '…' }})@endif</option>
                        @endforeach
                    </select>
                </div>
                @if($activeMilestoneId)
                    <button type="button" class="btn btn-secondary" wire:click="$dispatch('open-milestone', { id: '{{ $activeMilestoneId }}' })">
                        <i data-lucide="pencil"></i> {{ __('projects.board.details') }}
                    </button>
                @endif
            @endif
        </div>
    </div>

    <x-flash class="mb-4" />

    @if(empty($milestones))
        <div class="alert alert-info mb-4"><i data-lucide="info"></i> <span>{{ __('projects.board.no_milestones') }}</span></div>
    @endif

    {{-- The handover meeting with the client of the milestone this board shows (docs/OPPORTUNITIES.md section 12, PR 6) --}}
    @if($handover && $handover['milestone'])
        <section class="card mb-4 flex items-start gap-3 py-4" aria-labelledby="handover-title" wire:key="handover-{{ $handover['milestone']->id }}">
            <i data-lucide="target" class="mt-0.5 h-5 w-5 shrink-0 text-deep-teal"></i>
            <div class="min-w-0 flex-1">
                <h2 id="handover-title" class="text-ui font-semibold text-ink">{{ trans_choice('handover.board.title', 1) }}</h2>
                <div class="mt-3">
                    @include('livewire.hq.operations.partials.milestone-handover', ['h' => $handover])
                </div>
            </div>
        </section>
    @endif

    @if(!empty($teamCapacity))
        <div class="card mb-4 flex flex-wrap items-center gap-3 py-4">
            <span class="stat-label">{{ __('projects.board.capacity') }}</span>
            @foreach($teamCapacity as $userId => $data)
                @php
                    $capClass = 'pill-success';
                    if ($data['hours_assigned'] > 45) $capClass = 'pill-danger';
                    elseif ($data['hours_assigned'] >= 35) $capClass = 'pill-warning';
                @endphp
                <span class="pill {{ $capClass }}">
                    {{ $data['name'] }} · <span class="num">{{ $data['hours_assigned'] }} h</span>
                </span>
            @endforeach
        </div>
    @endif

    {{-- The 45-minute presence check is on every HQ page now (livewire:hq.timer-presence in the HQ layout) --}}
    <div class="flex items-stretch gap-4 overflow-x-auto pb-4" x-data="sprintBoard()">

        @foreach(['To-Do', 'In-Progress', 'QA_Review', 'Failed', 'Done'] as $column)
            <div class="flex w-[280px] min-w-[280px] flex-col rounded-lg border border-line bg-paper"
                 @dragover.prevent="dragover = true"
                 @dragleave.prevent="dragover = false"
                 @drop.prevent="dropTask($event, '{{ $column }}')"
                 x-bind:style="dragover && dragStatus === '{{ $column }}' ? 'background: var(--color-teal-tint); border-color: var(--color-deep-teal);' : ''"
                 @dragenter="dragStatus = '{{ $column }}'">

                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <span class="text-ui font-semibold text-ink">{{ $columnLabels[$column] }}</span>
                    <span class="rounded-full bg-mist px-2.5 text-caption font-semibold text-slate num" aria-label="{{ trans_choice('projects.backlog.count', count($tasks[$column]), ['count' => count($tasks[$column])]) }}">{{ count($tasks[$column]) }}</span>
                </div>

                <div class="flex min-h-[300px] flex-1 flex-col gap-3 p-3">
                    @foreach($tasks[$column] as $task)
                        @php
                            // The lead or someone sharing it, the project's lead or a manager (App\Support\Projects\ProjectRules)
                            $isDraggable = ($task['can_move'] ?? false) ? 'true' : 'false';
                        @endphp

                        <div class="rounded-lg border border-line bg-paper p-3 transition-colors hover:border-deep-teal {{ $isDraggable === 'true' ? 'cursor-grab' : '' }}"
                             draggable="{{ $isDraggable }}" wire:key="card-{{ $task['id'] }}"
                             @dragstart="dragStart($event, '{{ $task['id'] }}', '{{ $column }}')"
                             style="{{ $isDraggable === 'false' && $task['assigned_to'] ? 'opacity: 0.6; cursor: not-allowed;' : '' }}">

                            {{-- Opens the task drawer (docs/PROJECTS.md section 3); every card is on the milestone in the menu --}}
                            <button type="button" class="mb-3 block w-full cursor-pointer border-0 bg-transparent p-0 text-left text-small font-semibold text-ink hover:text-deep-teal"
                                    wire:click="$dispatch('open-task', { id: '{{ $task['id'] }}' })">{{ $task['title'] }}</button>

                            @if(!empty($task['due_date']) || !empty($task['waits_on']))
                                <div class="mb-3 flex flex-wrap gap-1.5">
                                    @if(!empty($task['due_date']))
                                        <span class="pill {{ ($task['is_overdue'] ?? false) ? 'pill-danger' : 'pill-neutral' }}">
                                            {{ ($task['is_overdue'] ?? false) ? __('projects.overdue') . ' · ' : '' }}{{ __('projects.due', ['date' => \App\Support\Projects\ProjectPlan::day($task['due_date'], 'short')]) }}
                                        </span>
                                    @endif
                                    @if(!empty($task['waits_on']))
                                        <span class="pill pill-warning" title="{{ implode(' · ', $task['waits_on']) }}">{{ trans_choice('projects.waits.card', count($task['waits_on']), ['count' => count($task['waits_on'])]) }}</span>
                                    @endif
                                </div>
                            @endif

                            @if($column === 'QA_Review' && $task['qa_sla_expires_at'])
                                @php
                                    $expires = \Carbon\Carbon::parse($task['qa_sla_expires_at']);
                                    $isExpired = $expires->isPast();
                                @endphp
                                <span class="pill {{ $isExpired ? 'pill-danger' : 'pill-warning' }} mb-3">
                                    {{ $isExpired ? __('projects.board.review_passed') : __('projects.board.review_due', ['when' => $expires->locale(app()->getLocale())->diffForHumans()]) }}
                                </span>
                            @endif

                            @if(!$task['assigned_to'])
                                @if($canMoveOwn)
                                    <button type="button" wire:click="assignToMe('{{ $task['id'] }}')" class="btn btn-secondary btn-sm w-full">
                                        <i data-lucide="user-plus"></i> {{ __('projects.task.assign_to_me') }}
                                    </button>
                                @endif
                            @else
                                {{-- Story points stay in the database for the timers but are no longer shown (decision 3) --}}
                                <div class="flex items-center justify-between gap-2 border-t border-line pt-2">
                                    <span class="inline-flex min-w-0 items-center gap-1 text-caption text-slate">
                                        <i data-lucide="user" class="h-3.5 w-3.5"></i>
                                        {{ substr($task['first_name'], 0, 1) }}. {{ $task['last_name'] }}
                                    </span>
                                    @if(($task['sharing_count'] ?? 0) > 0)
                                        <span class="tag" title="{{ __('projects.task.sharing') }}">+{{ $task['sharing_count'] }}</span>
                                    @endif
                                </div>
                            @endif

                            @if($task['has_active_qa'] ?? false)
                                <div class="group relative mt-3">
                                    <a href="{{ route('hq.ops.qa', ['ticket' => $task['qa_ticket_id']]) }}" class="btn btn-sm w-full border-danger bg-danger-tint text-danger no-underline" wire:navigate>
                                        <i data-lucide="circle-alert"></i> {{ trans_choice('handover.board.issues', $task['qa_ticket_count'] ?? 1, ['count' => $task['qa_ticket_count'] ?? 1]) }}
                                    </a>

                                    <div class="pointer-events-none invisible absolute bottom-full left-1/2 z-10 mb-2 w-[240px] -translate-x-1/2 rounded-lg border border-line bg-paper p-3 text-left opacity-0 transition-opacity group-hover:visible group-hover:opacity-100" style="box-shadow: var(--shadow-float);">
                                        <div class="text-caption font-semibold text-danger">{{ isset($task['qa_ticket_title']) ? preg_replace('/^\[(DIRECT COMM|BUG REPORT)\]\s*/', '', $task['qa_ticket_title']) : __('projects.board.issue_reported') }}</div>
                                        <div class="mt-1 text-caption text-slate">{{ $task['qa_ticket_description'] ?? __('projects.board.no_description') }}</div>
                                        <div class="mt-2 text-caption text-muted">{{ __('projects.board.issue_hint') }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if(count($tasks[$column]) === 0)
                        <div class="rounded-lg border border-dashed border-line p-4 text-center text-caption text-muted">
                            {{ __('projects.board.empty_column') }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <script>
        function sprintBoard() {
            return {
                dragover: false,
                dragStatus: null,
                draggedTaskId: null,
                sourceColumn: null,

                dragStart(event, taskId, sourceColumn) {
                    this.draggedTaskId = taskId;
                    this.sourceColumn = sourceColumn;
                    event.dataTransfer.effectAllowed = 'move';
                    setTimeout(() => { event.target.style.opacity = '0.5'; }, 0);
                },

                dropTask(event, targetColumn) {
                    this.dragover = false;
                    this.dragStatus = null;

                    let draggedElement = document.querySelector(`[draggable="true"][style*="0.5"]`);
                    if(draggedElement) draggedElement.style.opacity = '1';

                    if (this.draggedTaskId && this.sourceColumn !== targetColumn) {
                        @this.call('updateTaskStatus', this.draggedTaskId, targetColumn);
                    }
                }
            }
        }
    </script>
</div>
