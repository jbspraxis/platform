<div x-data="{ showOffcanvas: false }" @open-offcanvas.window="showOffcanvas = true" @close-offcanvas.window="showOffcanvas = false">
    @php
        // Stored ticket statuses and severities (database values) and what people read (BRAND.md section 12)
        $statusLabels = ['Open' => 'Open', 'Investigating' => 'In progress', 'Resolved' => 'Resolved'];
        $statusPills = ['Open' => 'pill-danger', 'Investigating' => 'pill-warning', 'Resolved' => 'pill-success'];
        $severityPills = ['Critical' => 'pill-danger', 'Medium' => 'pill-warning', 'Low' => 'pill-neutral'];
        $isWorkable = $selectedTicket && in_array($selectedTicket['status'], ['Open', 'Investigating']);
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Quality and issues</h1>
            <p class="page-subtitle">Issues reported on client projects, most urgent first. Open one to reply, add evidence or track rework time.</p>
        </div>
        <div class="field">
            <label for="qa-filter" class="label">Show</label>
            <select id="qa-filter" wire:model.live="filterStatus" class="select">
                <option value="Open">Open and in progress</option>
                <option value="Resolved">Resolved</option>
                <option value="All">All issues</option>
            </select>
        </div>
    </div>

    @if (session()->has('error'))
        <div class="alert alert-danger mb-4" role="alert"><i data-lucide="circle-alert"></i> <span>{{ session('error') }}</span></div>
    @endif
    @if (session()->has('success'))
        <div class="alert alert-success mb-4" role="status"><i data-lucide="check-circle"></i> <span>{{ session('success') }}</span></div>
    @endif

    <div class="card p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Client and project</th>
                        <th>Issue</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Response time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $t)
                        <tr wire:click="openTicket('{{ $t['id'] }}')" class="cursor-pointer {{ $selectedTicket && $selectedTicket['id'] === $t['id'] ? 'is-selected' : '' }}">
                            <td class="num text-left font-medium text-ink">{{ substr($t['id'], 0, 8) }}</td>
                            <td>
                                <div class="font-medium text-ink">{{ $t['client_name'] }}</div>
                                <div class="help">{{ Str::limit($t['project_title'], 35) }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-ink">{{ Str::limit($t['title'], 50) }}</div>
                                <div class="help">Reported by {{ $t['first_name'] }} {{ $t['last_name'] }}</div>
                            </td>
                            <td><span class="pill {{ $severityPills[$t['severity']] ?? 'pill-neutral' }}">{{ $t['severity'] }}</span></td>
                            <td><span class="pill {{ $statusPills[$t['status']] ?? 'pill-neutral' }}">{{ $statusLabels[$t['status']] ?? $t['status'] }}</span></td>
                            <td>
                                @if(in_array($t['status'], ['Open', 'Investigating']))
                                    @if($t['is_breached'])
                                        <span class="pill pill-danger">Overdue</span>
                                    @else
                                        <span class="num text-left">Due {{ $t['time_remaining'] }}</span>
                                    @endif
                                @else
                                    <span class="muted" aria-label="Not applicable">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>{{ $filterStatus === 'Open' ? 'No open issues' : 'No issues to show' }}</strong>
                                    Issues that clients or the delivery team report on a project appear here.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Issue panel -->
    <div class="fixed inset-0 z-40 bg-ink/50" x-show="showOffcanvas" x-transition.opacity @click="showOffcanvas = false" style="display: none;"></div>

    <div class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[720px] flex-col bg-paper transition-transform duration-300"
         style="box-shadow: var(--shadow-float);"
         :class="showOffcanvas ? 'translate-x-0' : 'translate-x-full'"
         x-cloak role="dialog" aria-modal="true" aria-labelledby="qa-ticket-title">
        @if($selectedTicket)
            <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
                <div>
                    <div class="text-caption text-muted"><span class="num">{{ substr($selectedTicket['id'], 0, 8) }}</span> · {{ $selectedTicket['client_name'] }}</div>
                    <h2 id="qa-ticket-title" class="mt-1 mb-2 text-section font-semibold text-ink">{{ $selectedTicket['title'] }}</h2>
                    <div class="flex flex-wrap gap-2">
                        <span class="pill {{ $statusPills[$selectedTicket['status']] ?? 'pill-neutral' }}">{{ $statusLabels[$selectedTicket['status']] ?? $selectedTicket['status'] }}</span>
                        <span class="pill {{ $severityPills[$selectedTicket['severity']] ?? 'pill-neutral' }}">Severity: {{ $selectedTicket['severity'] }}</span>
                    </div>
                </div>
                <button type="button" @click="showOffcanvas = false" class="btn btn-ghost btn-sm" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-6">

                @if($canWork && $isWorkable)
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4 {{ $activeTimerId ? 'border-success bg-success-tint' : 'border-line bg-mist' }}">
                        <div>
                            <div class="text-ui font-semibold text-ink">Rework timer {{ $activeTimerId ? '(running)' : '' }}</div>
                            <div class="help">Time spent fixing this issue counts as rework on the task: <strong class="text-ink">{{ $selectedTicket['task_title'] ?? 'not linked to a task' }}</strong></div>
                        </div>
                        <button type="button" wire:click="toggleReworkTimer" class="btn {{ $activeTimerId ? 'btn-secondary' : 'btn-primary' }}">
                            @if($activeTimerId)
                                <i data-lucide="pause"></i> Pause timer
                            @else
                                <i data-lucide="play"></i> Start timer
                            @endif
                        </button>
                    </div>
                @endif

                <h3 class="section-title text-ui">What was reported</h3>
                <div class="mb-6 rounded-lg border border-line bg-mist p-4 text-small text-slate">
                    {!! nl2br(e($selectedTicket['description'])) !!}
                </div>

                <h3 class="section-title text-ui">Evidence</h3>
                <div class="mb-4 grid grid-cols-[repeat(auto-fill,minmax(140px,1fr))] gap-3">
                    @forelse($ticketAttachments as $att)
                        <a href="{{ route('hq.file', ['path' => $att->file_path]) }}" target="_blank" class="flex flex-col items-center gap-2 rounded-lg border border-line p-3 text-center text-caption no-underline hover:border-deep-teal">
                            <i data-lucide="image" class="h-5 w-5 text-muted"></i>
                            <span class="break-all">{{ Str::limit($att->file_name, 18) }}</span>
                        </a>
                    @empty
                        <p class="help col-span-full">No files added yet.</p>
                    @endforelse
                </div>

                @if($canWork && $isWorkable)
                    <div class="mb-8 flex flex-wrap items-end gap-3">
                        <div class="field min-w-[220px] flex-1">
                            <label for="qa-evidence" class="label">Add a file</label>
                            <input id="qa-evidence" type="file" wire:model="newAttachment" class="input">
                            <span class="help">Up to 10 MB.</span>
                            @error('newAttachment') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <button type="button" wire:click="uploadEvidence" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="newAttachment, uploadEvidence">
                            <span wire:loading.remove wire:target="uploadEvidence" class="inline-flex items-center gap-2"><i data-lucide="upload"></i> Upload file</span>
                            <span wire:loading wire:target="uploadEvidence">Uploading…</span>
                        </button>
                    </div>
                @endif

                <div class="divider"></div>
                <h3 class="section-title text-ui">Conversation</h3>

                @forelse($ticketComments as $comment)
                    <div class="mb-3 rounded-lg border p-4 {{ $comment->is_internal_only ? 'border-warning bg-warning-tint' : 'border-line bg-paper' }}">
                        <div class="mb-2 flex flex-wrap justify-between gap-2 text-caption text-muted">
                            <span>
                                <strong class="text-ink">{{ $comment->first_name }} {{ $comment->last_name }}</strong>
                                @if($comment->is_internal_only) · <span class="text-warning">Internal note</span> @endif
                            </span>
                            <span class="num">{{ \Carbon\Carbon::parse($comment->created_at)->format('j M, H:i') }}</span>
                        </div>
                        <div class="text-small text-slate">
                            {!! nl2br(e($comment->comment_text)) !!}
                        </div>
                    </div>
                @empty
                    <p class="help mb-3">No replies yet.</p>
                @endforelse

                @if($canWork && $isWorkable)
                    <div class="field mt-4">
                        <label for="qa-reply" class="label">Reply</label>
                        <textarea id="qa-reply" wire:model="newCommentText" class="textarea" rows="3"></textarea>
                        @error('newCommentText') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <label class="inline-flex items-center gap-2 text-small text-slate">
                            <input type="checkbox" wire:model="isInternalOnly"> Internal note (not shown in the Client Portal)
                        </label>
                        <button type="button" wire:click="postComment" class="btn btn-primary">
                            <i data-lucide="send"></i> Send reply
                        </button>
                    </div>
                @endif
            </div>

            <div class="border-t border-line bg-mist px-6 py-4">
                @if($canResolve && $isWorkable)
                    <button type="button" wire:click="markResolved" class="btn btn-primary w-full" onclick="confirm('Mark this issue as resolved? The person who reported it will see it as resolved.') || event.stopImmediatePropagation()">
                        <i data-lucide="check-circle"></i> Mark as resolved
                    </button>
                @elseif($isWorkable)
                    <p class="help text-center"><i data-lucide="lock" class="inline h-3.5 w-3.5 align-middle"></i> A QA approver resolves issues once the fix is checked.</p>
                @else
                    <p class="text-center text-small font-semibold text-success"><i data-lucide="check-circle" class="inline h-4 w-4 align-middle"></i> Resolved</p>
                @endif
            </div>
        @endif
    </div>
</div>
