<div>
    @php
        // Stored task statuses (database values) and what people read
        $columnLabels = ['To-Do' => 'To do', 'In-Progress' => 'In progress', 'QA_Review' => 'QA review', 'Failed' => 'Failed QA', 'Done' => 'Done'];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Sprint boards</h1>
            <p class="page-subtitle">{{ $project->title }}. Drag a task to change its status; moving it into In progress starts your timer.</p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            @if(!empty($sprints))
                <div class="field">
                    <label for="sprint-select" class="label">Sprint</label>
                    <select id="sprint-select" wire:model.live="activeSprintId" class="select min-w-[240px]">
                        @foreach($sprints as $sprint)
                            <option value="{{ $sprint->id }}">{{ $sprint->title }} ({{ \Carbon\Carbon::parse($sprint->start_date)->format('j M') }} to {{ \Carbon\Carbon::parse($sprint->end_date)->format('j M') }})</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <a href="{{ route('hq.ops.deployments') }}" class="btn btn-secondary" wire:navigate>
                <i data-lucide="arrow-left"></i> All projects
            </a>
        </div>
    </div>

    @if (session()->has('error'))
        <div class="alert alert-danger mb-4" role="alert"><i data-lucide="circle-alert"></i> <span>{{ session('error') }}</span></div>
    @endif

    @if(empty($sprints))
        <div class="alert alert-info mb-4"><i data-lucide="info"></i> <span>No sprints planned for this project yet. Once sprints are planned, their tasks appear here.</span></div>
    @endif

    @if(!empty($teamCapacity))
        <div class="card mb-4 flex flex-wrap items-center gap-3 py-4">
            <span class="stat-label">Open hours assigned in this sprint</span>
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

    @php
        // Detect if the logged-in user has an active task to trigger the Alpine monitor
        $hasActiveTasks = collect($tasks['In-Progress'])->where('assigned_to', auth()->id())->count() > 0;
    @endphp

    <div class="flex items-stretch gap-4 overflow-x-auto pb-4" x-data="sprintBoard({{ $hasActiveTasks ? 'true' : 'false' }})">

        <!-- Presence check -->
        <div class="modal-backdrop" x-show="showPresenceModal" style="display: none;" x-transition.opacity>
            <div class="modal text-center" role="dialog" aria-modal="true" aria-labelledby="presence-title">
                <i data-lucide="clock" class="mx-auto mb-3 h-8 w-8 text-deep-teal"></i>
                <h2 id="presence-title" class="modal-title">Are you still working on this?</h2>
                <p class="text-small text-slate">Your timer has run for 45 minutes. Confirm within 5 minutes, or we’ll pause your timer and move the task back to To do.</p>
                <div class="modal-actions justify-center">
                    <button type="button" @click="confirmPresence()" class="btn btn-primary">Keep timer running</button>
                </div>
            </div>
        </div>

        @foreach(['To-Do', 'In-Progress', 'QA_Review', 'Failed', 'Done'] as $column)
            <div class="flex w-[280px] min-w-[280px] flex-col rounded-lg border border-line bg-paper"
                 @dragover.prevent="dragover = true"
                 @dragleave.prevent="dragover = false"
                 @drop.prevent="dropTask($event, '{{ $column }}')"
                 x-bind:style="dragover && dragStatus === '{{ $column }}' ? 'background: var(--color-teal-tint); border-color: var(--color-deep-teal);' : ''"
                 @dragenter="dragStatus = '{{ $column }}'">

                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <span class="text-ui font-semibold text-ink">{{ $columnLabels[$column] }}</span>
                    <span class="rounded-full bg-mist px-2.5 text-caption font-semibold text-slate num" aria-label="{{ count($tasks[$column]) }} tasks">{{ count($tasks[$column]) }}</span>
                </div>

                <div class="flex min-h-[300px] flex-1 flex-col gap-3 p-3">
                    @foreach($tasks[$column] as $task)
                        @php
                            $isDraggable = (($task['assigned_to'] === auth()->id() && $canMoveOwn) || $canMoveAny) ? 'true' : 'false';
                        @endphp

                        <div class="rounded-lg border border-line bg-paper p-3 transition-colors hover:border-deep-teal {{ $isDraggable === 'true' ? 'cursor-grab' : '' }}"
                             draggable="{{ $isDraggable }}"
                             @dragstart="dragStart($event, '{{ $task['id'] }}', '{{ $column }}')"
                             style="{{ $isDraggable === 'false' && $task['assigned_to'] ? 'opacity: 0.6; cursor: not-allowed;' : '' }}">

                            <div class="text-caption text-muted">{{ $task['phase_title'] }}</div>
                            <div class="mt-1 mb-3 text-small font-semibold text-ink">{{ $task['title'] }}</div>

                            @if($column === 'QA_Review' && $task['qa_sla_expires_at'])
                                @php
                                    $expires = \Carbon\Carbon::parse($task['qa_sla_expires_at']);
                                    $isExpired = $expires->isPast();
                                @endphp
                                <span class="pill {{ $isExpired ? 'pill-danger' : 'pill-warning' }} mb-3">
                                    {{ $isExpired ? 'Review window passed' : 'Review due ' . $expires->diffForHumans() }}
                                </span>
                            @endif

                            @if(!$task['assigned_to'])
                                @if($canMoveOwn)
                                    <button type="button" wire:click="assignToMe('{{ $task['id'] }}')" class="btn btn-secondary btn-sm w-full">
                                        <i data-lucide="user-plus"></i> Assign to me
                                    </button>
                                @endif
                            @else
                                <div class="flex items-center justify-between border-t border-line pt-2">
                                    <span class="grid h-6 min-w-6 place-items-center rounded-full bg-teal-tint px-1.5 text-caption font-semibold text-petrol num" title="{{ $task['story_points'] }} story points">{{ $task['story_points'] }}</span>
                                    <span class="inline-flex items-center gap-1 text-caption text-slate">
                                        <i data-lucide="user" class="h-3.5 w-3.5"></i>
                                        {{ substr($task['first_name'], 0, 1) }}. {{ $task['last_name'] }}
                                    </span>
                                </div>
                            @endif

                            @if($task['has_active_qa'] ?? false)
                                <div class="group relative mt-3">
                                    <a href="{{ route('hq.ops.qa', ['ticket' => $task['qa_ticket_id']]) }}" class="btn btn-sm w-full border-danger bg-danger-tint text-danger no-underline" wire:navigate>
                                        <i data-lucide="circle-alert"></i> Open issue
                                    </a>

                                    <div class="pointer-events-none invisible absolute bottom-full left-1/2 z-10 mb-2 w-[240px] -translate-x-1/2 rounded-lg border border-line bg-paper p-3 text-left opacity-0 transition-opacity group-hover:visible group-hover:opacity-100" style="box-shadow: var(--shadow-float);">
                                        <div class="text-caption font-semibold text-danger">{{ $task['qa_ticket_title'] ?? 'Issue reported' }}</div>
                                        <div class="mt-1 text-caption text-slate">{{ $task['qa_ticket_description'] ?? 'No description yet.' }}</div>
                                        <div class="mt-2 text-caption text-muted">Select to open it in Quality and issues.</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if(count($tasks[$column]) === 0)
                        <div class="rounded-lg border border-dashed border-line p-4 text-center text-caption text-muted">
                            No tasks. Drag a task here.
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <script>
        function sprintBoard(hasActiveTasks) {
            return {
                dragover: false,
                dragStatus: null,
                draggedTaskId: null,
                sourceColumn: null,

                // Presence check
                showPresenceModal: false,
                presenceTimeout: null,
                warningTimeout: null,

                init() {
                    if (hasActiveTasks) {
                        this.startPresenceMonitor();
                    }
                },

                startPresenceMonitor() {
                    clearTimeout(this.presenceTimeout);
                    clearTimeout(this.warningTimeout);
                    this.showPresenceModal = false;

                    // 45 minutes = 2700000 ms
                    this.presenceTimeout = setTimeout(() => {
                        this.showPresenceModal = true;

                        // 5 minutes to answer = 300000 ms
                        this.warningTimeout = setTimeout(() => {
                            this.showPresenceModal = false;
                            @this.call('forcePauseTimers'); // pauses this person's open timers
                        }, 300000);

                    }, 2700000);
                },

                confirmPresence() {
                    this.startPresenceMonitor(); // Resets the clocks
                },

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
