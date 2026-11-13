<div class="flex min-h-screen flex-col bg-mist"
     x-data="trackerMatrixEngine()"
     @modal-close.window="closeModal($event.detail[0].modal)"
     @system-alert.window="addAlert($event.detail[0])"
     @message-transmitted.window="scrollChatToBottom()">
    @php
        // Stored task statuses (database values) and what people read
        $taskStatusLabels = ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done'];
        $isLead = auth('tracker')->user()->role !== 'consultant';
        // Progress as people read it: whole per cent, and the task count behind it
        $taskCount = $milestones->sum(fn ($m) => $m->tasks->count());
        $doneCount = $milestones->sum(fn ($m) => $m->tasks->where('status', 'done')->count());
        $donePercent = $taskCount ? (int) round($doneCount / $taskCount * 100) : 0;
    @endphp

    <!-- Messages after an action -->
    <div class="pointer-events-none fixed left-1/2 top-6 z-[60] flex w-full max-w-[480px] -translate-x-1/2 flex-col items-stretch gap-2 px-4" aria-live="polite">
        <template x-for="alert in alerts" :key="alert.id">
            <div class="alert pointer-events-auto" :class="alert.type === 'error' ? 'alert-danger' : 'alert-success'" style="box-shadow: var(--shadow-float);">
                <span x-text="alert.message" class="flex-1"></span>
                <button type="button" class="btn btn-ghost btn-sm -my-1 text-current" @click="dismissAlert(alert.id)" aria-label="Dismiss">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-line bg-paper px-4 py-4 sm:px-8">
        <div class="flex min-w-0 items-center gap-4">
            <img src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" class="h-7 w-auto">
            <div class="min-w-0 border-l border-line pl-4">
                <div class="text-caption text-muted">Project tracker</div>
                <h1 class="truncate text-page-title font-semibold text-ink">{{ $activeTracker->project_name }}</h1>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-6">
            <div class="min-w-[200px]">
                <div class="mb-1 flex justify-between gap-4 text-caption text-muted">
                    <span><span class="num">{{ $doneCount }}</span> of <span class="num">{{ $taskCount }}</span> {{ $taskCount === 1 ? 'task' : 'tasks' }} done</span>
                    <span class="num font-semibold text-ink">{{ $donePercent }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $donePercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Tasks done">
                    <div class="h-full rounded-full bg-teal transition-[width] duration-500" style="width: {{ $donePercent }}%;"></div>
                </div>
            </div>

            @if($isLead)
                <button type="button" class="btn btn-primary" @click="openModal('milestone')">
                    <i data-lucide="plus"></i> Add milestone
                </button>
            @endif
        </div>
    </header>

    <main class="flex-1 overflow-x-auto px-4 py-6 sm:px-8">
        <div class="flex items-start gap-4">
            @forelse($milestones as $milestone)
                <section class="card flex w-[320px] min-w-[320px] flex-col p-0" aria-label="{{ $milestone->title }}">
                    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                        <h2 class="text-ui font-semibold text-ink">{{ $milestone->title }}</h2>

                        @if($isLead)
                            <div class="flex shrink-0 gap-1">
                                <button type="button" @click="$wire.set('editingMilestoneId', '{{ $milestone->id }}'); $wire.set('editingMilestoneTitle', '{{ addslashes($milestone->title) }}'); openModal('editMilestone');"
                                        class="btn btn-ghost btn-sm" title="Rename milestone" aria-label="Rename milestone">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button type="button" wire:click="deleteMilestone('{{ $milestone->id }}')" wire:confirm="Delete this milestone? Its tasks and their messages are deleted too, and this can’t be undone."
                                        class="btn btn-ghost btn-sm text-danger" title="Delete milestone" aria-label="Delete milestone">
                                    <i data-lucide="trash-2"></i>
                                </button>
                                <button type="button" @click="activeMilestoneId = '{{ $milestone->id }}'; $wire.set('newTaskTitle', ''); $wire.set('newTaskAssignee', ''); $wire.set('newTaskDueDate', ''); openModal('task');"
                                        class="btn btn-secondary btn-sm" title="Add task" aria-label="Add task">
                                    <i data-lucide="plus"></i>
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-3 p-3">
                        @forelse($milestone->tasks as $task)
                            @php
                                $statusEdge = $task->status === 'done' ? 'var(--color-success)' : ($task->status === 'in_progress' ? 'var(--color-warning)' : 'var(--color-line-strong)');
                            @endphp
                            <div class="flex flex-col gap-3 rounded-lg border border-line bg-paper p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-small font-medium text-ink">{{ $task->title }}</div>

                                    @if($isLead)
                                        <div class="flex shrink-0 gap-1">
                                            <button type="button" @click="$wire.loadTaskForEditing('{{ $task->id }}')" class="btn btn-ghost btn-sm" title="Edit task" aria-label="Edit task">
                                                <i data-lucide="pencil"></i>
                                            </button>
                                            <button type="button" wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Delete this task and its messages? This can’t be undone." class="btn btn-ghost btn-sm text-danger" title="Delete task" aria-label="Delete task">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center justify-between gap-2 border-b border-line pb-3">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="avatar h-6 w-6 text-[0.6875rem]">{{ $task->assignee ? substr($task->assignee->name, 0, 1) : '?' }}</span>
                                        <span class="truncate text-caption text-slate">{{ $task->assignee ? $task->assignee->name : 'Not assigned' }}</span>
                                    </div>
                                    @if($task->due_date)
                                        <span class="inline-flex shrink-0 items-center gap-1 text-caption text-muted">
                                            <i data-lucide="calendar" class="h-3.5 w-3.5"></i> <span class="num">{{ \Carbon\Carbon::parse($task->due_date)->format('j M') }}</span>
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center justify-between gap-2">
                                    <label for="status-{{ $task->id }}" class="sr-only">Status</label>
                                    <select id="status-{{ $task->id }}" class="select w-[150px] py-1.5 text-caption" style="border-color: {{ $statusEdge }};"
                                            wire:change="updateTaskStatus('{{ $task->id }}', $event.target.value)"
                                            @if(auth('tracker')->user()->role === 'consultant' && $task->assigned_to !== auth('tracker')->id()) disabled @endif>
                                        <option value="todo" @if($task->status == 'todo') selected @endif>{{ $taskStatusLabels['todo'] }}</option>
                                        <option value="in_progress" @if($task->status == 'in_progress') selected @endif>{{ $taskStatusLabels['in_progress'] }}</option>
                                        <option value="done" @if($task->status == 'done') selected @endif>{{ $taskStatusLabels['done'] }}</option>
                                    </select>

                                    @if($isLead)
                                        <button type="button" @click="$wire.set('activeTaskId', '{{ $task->id }}'); openModal('reassign');" class="btn btn-ghost btn-sm">
                                            <i data-lucide="users"></i> Reassign
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-line p-4 text-center text-caption text-muted">No tasks in this milestone yet.</div>
                        @endforelse
                    </div>
                </section>
            @empty
                <div class="card empty-state mx-auto w-full max-w-[560px]">
                    <i data-lucide="layout-template" class="mx-auto mb-3 h-8 w-8 text-muted"></i>
                    <strong>No milestones yet</strong>
                    {{ $isLead ? 'Add a milestone to start planning the work, then add its tasks.' : 'Your project lead adds the milestones and tasks here.' }}
                </div>
            @endforelse
        </div>
    </main>

    <!-- Messages -->
    <button type="button" class="hq-lift hq-page-fab fixed bottom-6 right-6 z-30 grid h-14 w-14 place-items-center rounded-full bg-deep-teal text-white hover:bg-petrol" style="box-shadow: var(--shadow-float);"
            @click="modals.chat = true; scrollChatToBottom()" aria-label="Open messages" title="Messages">
        <i data-lucide="message-circle" class="h-6 w-6"></i>
    </button>

    <div class="fixed inset-0 z-40 bg-ink/50" x-show="modals.chat" x-transition.opacity @click="modals.chat = false" style="display: none;"></div>
    {{-- Hidden (display: none) when closed, so it casts no shadow and keyboard focus can’t reach its fields --}}
    <aside class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[440px] flex-col bg-paper"
           style="display: none; box-shadow: var(--shadow-float);"
           x-show="modals.chat"
           x-transition:enter="transition-transform duration-300"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition-transform duration-300"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           x-cloak aria-label="Messages">
        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
            <div>
                <h2 class="text-section font-semibold text-ink">Messages</h2>
                <p class="help">Between the delivery team and the client, about this project.</p>
            </div>
            <button type="button" @click="modals.chat = false" class="btn btn-ghost btn-sm" aria-label="Close messages">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="flex flex-1 flex-col gap-3 overflow-y-auto px-6 py-4" id="chat-scroller">
            {{-- The latest messages show first; older ones a page at a time --}}
            @if($hasEarlierMessages)
                <button type="button" wire:click="showEarlierMessages" wire:loading.attr="disabled" wire:target="showEarlierMessages" class="btn btn-ghost btn-sm self-center">
                    <i data-lucide="arrow-up"></i> Show earlier messages
                </button>
            @endif
            @forelse($messages as $msg)
                <div class="max-w-[85%] rounded-lg px-4 py-3 text-small {{ $msg->sender_type === 'staff' ? 'self-end bg-teal-tint text-ink' : 'self-start border border-line bg-mist text-ink' }}">
                    <div class="mb-1 flex justify-between gap-4 text-caption text-muted">
                        <span class="font-semibold">{{ $msg->sender_type === 'staff' ? ($msg->sender->name ?? 'JBS Praxis') : 'Client' }}</span>
                        <span class="num">{{ $msg->created_at->format('H:i') }}</span>
                    </div>

                    @if($msg->task_id)
                        <div class="mb-2 rounded border-l-2 border-deep-teal bg-paper px-2 py-1 text-caption text-slate">
                            About: {{ $msg->task->title ?? 'a task' }}
                        </div>
                    @endif

                    @if($msg->media_type === 'image')
                        <a href="{{ asset('storage/' . $msg->media_path) }}" target="_blank">
                            <img src="{{ asset('storage/' . $msg->media_path) }}" class="mt-1 max-w-full rounded border border-line" alt="Image shared in the conversation">
                        </a>
                    @endif

                    @if($msg->body)
                        <div class="mt-1 whitespace-pre-wrap">{{ $msg->body }}</div>
                    @endif
                </div>
            @empty
                <div class="empty-state">
                    <strong>No messages yet</strong>
                    Send a message or an image to start the conversation.
                </div>
            @endforelse
        </div>

        <form wire:submit.prevent="transmitMessage" class="border-t border-line px-6 py-4">
            @error('chatMessage') <div class="field-error mb-2">{{ $message }}</div> @enderror
            @error('mediaUpload') <div class="field-error mb-2">{{ $message }}</div> @enderror

            @if($mediaUpload)
                <div class="callout mb-3 flex items-center justify-between py-2 text-caption">
                    <span class="inline-flex items-center gap-2"><i data-lucide="image" class="h-4 w-4"></i> Image ready to send</span>
                    <button type="button" wire:click="$set('mediaUpload', null)" class="btn btn-ghost btn-sm text-danger" aria-label="Remove image"><i data-lucide="x"></i></button>
                </div>
            @endif

            <div class="flex items-end gap-2">
                <input type="file" id="mediaUploadInput" wire:model="mediaUpload" accept="image/*" class="hidden">
                <button type="button" onclick="document.getElementById('mediaUploadInput').click()" class="btn btn-secondary h-11 w-11 shrink-0 p-0" title="Attach an image" aria-label="Attach an image">
                    <i data-lucide="paperclip"></i>
                </button>

                <label for="chat-message" class="sr-only">Message</label>
                <textarea id="chat-message" wire:model.defer="chatMessage" class="textarea min-h-[44px] max-h-[120px] resize-none" rows="1"></textarea>

                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary h-11 w-11 shrink-0 p-0" title="Send message" aria-label="Send message">
                    <i data-lucide="send" wire:loading.remove wire:target="transmitMessage, mediaUpload"></i>
                    <i data-lucide="loader" class="animate-spin" wire:loading wire:target="transmitMessage, mediaUpload"></i>
                </button>
            </div>
        </form>
    </aside>

    {{-- Add milestone --}}
    <div x-show="modals.milestone" class="modal-backdrop" x-transition.opacity style="display: none;">
        <div class="modal max-w-[420px]" @click.away="closeModal('milestone')" role="dialog" aria-modal="true" aria-labelledby="milestone-title">
            <h2 id="milestone-title" class="modal-title">Add milestone</h2>
            <div class="field mt-4">
                <label for="new-milestone" class="label">Milestone name</label>
                <input id="new-milestone" type="text" class="input" wire:model.defer="newMilestoneTitle" placeholder="e.g. Data migration" @keydown.enter="$wire.injectMilestone()">
                @error('newMilestoneTitle') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" @click="closeModal('milestone')">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="injectMilestone" wire:loading.attr="disabled" wire:target="injectMilestone">
                    <span wire:loading.remove wire:target="injectMilestone">Add milestone</span>
                    <span wire:loading wire:target="injectMilestone">Adding…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Rename milestone --}}
    <div x-show="modals.editMilestone" class="modal-backdrop" x-transition.opacity style="display: none;">
        <div class="modal max-w-[420px]" @click.away="closeModal('editMilestone')" role="dialog" aria-modal="true" aria-labelledby="edit-milestone-title">
            <h2 id="edit-milestone-title" class="modal-title">Rename milestone</h2>
            <div class="field mt-4">
                <label for="edit-milestone" class="label">Milestone name</label>
                <input id="edit-milestone" type="text" class="input" wire:model.defer="editingMilestoneTitle" @keydown.enter="$wire.updateMilestoneTitle()">
                @error('editingMilestoneTitle') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" @click="closeModal('editMilestone')">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="updateMilestoneTitle">Save name</button>
            </div>
        </div>
    </div>

    {{-- Add task --}}
    <div x-show="modals.task" class="modal-backdrop" x-transition.opacity style="display: none;">
        <div class="modal max-w-[460px]" @click.away="closeModal('task')" role="dialog" aria-modal="true" aria-labelledby="task-title">
            <h2 id="task-title" class="modal-title">Add task</h2>

            <div class="mt-4 flex flex-col gap-4">
                <div class="field">
                    <label for="new-task" class="label">Task name</label>
                    <input id="new-task" type="text" class="input" wire:model.defer="newTaskTitle">
                    @error('newTaskTitle') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="new-task-assignee" class="label">Assigned to (optional)</label>
                    <select id="new-task-assignee" class="select" wire:model.defer="newTaskAssignee">
                        <option value="">Not assigned</option>
                        @foreach($consultants as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role) }})</option>
                        @endforeach
                    </select>
                    @error('newTaskAssignee') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="new-task-due" class="label">Due date (optional)</label>
                    <input id="new-task-due" type="date" class="input" wire:model.defer="newTaskDueDate">
                    @error('newTaskDueDate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" @click="closeModal('task')">Cancel</button>
                <button type="button" class="btn btn-primary" @click="$wire.injectTask(activeMilestoneId)" wire:loading.attr="disabled" wire:target="injectTask">
                    <span wire:loading.remove wire:target="injectTask">Add task</span>
                    <span wire:loading wire:target="injectTask">Adding…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Edit task --}}
    <div x-show="modals.editTask" class="modal-backdrop" x-transition.opacity style="display: none;">
        <div class="modal max-w-[460px]" @click.away="closeModal('editTask')" role="dialog" aria-modal="true" aria-labelledby="edit-task-title">
            <h2 id="edit-task-title" class="modal-title">Edit task</h2>

            <div class="mt-4 flex flex-col gap-4">
                <div class="field">
                    <label for="edit-task" class="label">Task name</label>
                    <input id="edit-task" type="text" class="input" wire:model.defer="editTaskTitle">
                    @error('editTaskTitle') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="edit-task-assignee" class="label">Assigned to (optional)</label>
                    <select id="edit-task-assignee" class="select" wire:model.defer="editTaskAssignee">
                        <option value="">Not assigned</option>
                        @foreach($consultants as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('editTaskAssignee') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="edit-task-due" class="label">Due date (optional)</label>
                    <input id="edit-task-due" type="date" class="input" wire:model.defer="editTaskDueDate">
                    @error('editTaskDueDate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" @click="closeModal('editTask')">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="updateTaskDetails" wire:loading.attr="disabled" wire:target="updateTaskDetails">
                    <span wire:loading.remove wire:target="updateTaskDetails">Save task</span>
                    <span wire:loading wire:target="updateTaskDetails">Saving…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Reassign task --}}
    <div x-show="modals.reassign" class="modal-backdrop" x-transition.opacity style="display: none;">
        <div class="modal max-w-[420px]" @click.away="closeModal('reassign')" role="dialog" aria-modal="true" aria-labelledby="reassign-title">
            <h2 id="reassign-title" class="modal-title">Reassign task</h2>
            <div class="field mt-4">
                <label for="reassign-to" class="label">Assign to</label>
                <select id="reassign-to" class="select" wire:model.defer="newAssigneeId">
                    <option value="">Choose a person</option>
                    @foreach($consultants as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('newAssigneeId') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" @click="closeModal('reassign')">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="reassignTask" wire:loading.attr="disabled" wire:target="reassignTask">
                    <span wire:loading.remove wire:target="reassignTask">Reassign task</span>
                    <span wire:loading wire:target="reassignTask">Saving…</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('trackerMatrixEngine', () => ({
                isOnline: navigator.onLine,
                alerts: [],
                activeMilestoneId: null,
                modals: {
                    milestone: false,
                    editMilestone: false,
                    task: false,
                    editTask: false,
                    reassign: false,
                    chat: false
                },

                init() {
                    window.addEventListener('online', () => { this.isOnline = true; });
                    window.addEventListener('offline', () => { this.isOnline = false; });

                    // Livewire asks to open a specific dialog (like editTask)
                    Livewire.on('open-modal', (data) => {
                        this.openModal(data[0].modal);
                    });
                },

                addAlert(alertData) {
                    const id = Date.now();
                    this.alerts.push({ id, ...alertData });
                    setTimeout(() => {
                        this.dismissAlert(id);
                    }, 4000);
                },

                dismissAlert(id) {
                    this.alerts = this.alerts.filter(a => a.id !== id);
                },

                openModal(name) {
                    // Close all first, then open the one asked for
                    Object.keys(this.modals).forEach(k => this.modals[k] = false);
                    this.modals[name] = true;
                },

                closeModal(name) {
                    if(this.modals.hasOwnProperty(name)) {
                        this.modals[name] = false;
                    }
                },

                scrollChatToBottom() {
                    setTimeout(() => {
                        const el = document.getElementById('chat-scroller');
                        if(el) el.scrollTop = el.scrollHeight;
                    }, 150);
                }
            }));
        });
    </script>
</div>
