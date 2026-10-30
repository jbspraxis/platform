<div>
    @php
        $taskStatus = fn ($s) => match ($s) {
            'To-Do' => ['To do', 'pill-neutral'],
            'In-Progress' => ['In progress', 'pill-info'],
            'QA_Review' => ['Client review', 'pill-warning'],
            'Failed' => ['Changes requested', 'pill-danger'],
            default => [\Illuminate\Support\Str::ucfirst(strtolower(str_replace('-', ' ', (string) $s))), 'pill-neutral'],
        };
        // Amounts with their currency (docs/BRAND.md section 4): ₦ and $ before the amount, FCFA after it
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 0);
        // Stored appointment titles from the website ('Executive Consultation: …') and HQ ('Free audit (booked from HQ)') are both free audits
        $apptLabel = fn ($title) => (str_starts_with((string) $title, 'Executive Consultation') || str_starts_with((string) $title, 'Free audit')) ? 'Free audit' : $title;
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">My workspace</h1>
            <p class="page-subtitle">Hello {{ Auth::user()->first_name ?? 'there' }}. Your tasks, time, schedule and notifications.</p>
        </div>
    </div>

    @canany(['dashboard.view', 'audit.view'])
    <nav class="tabs" aria-label="Overviews">
        @can('dashboard.view')
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="tab">Company overview</a>
        @endcan
        <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="tab is-active" aria-current="page">My workspace</a>
        @can('dashboard.view')
            <a href="{{ route('hq.dash.investor') }}" wire:navigate class="tab">Investor view</a>
        @endcan
        @can('audit.view')
            <a href="{{ route('hq.dash.audit') }}" wire:navigate class="tab">Audit log</a>
        @endcan
    </nav>
    @endcanany

    @if(session()->has('error'))
        <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;">
            <i data-lucide="circle-alert"></i><span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" style="margin-bottom: 24px;">
        <div class="card">
            <div class="stat-label">My open tasks</div>
            <div class="stat-value">{{ number_format($stats['tasks_open']) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Hours logged this week</div>
            <div class="stat-value">{{ $stats['hours_this_week'] }} h</div>
        </div>
        <div class="card">
            <div class="stat-label">Proposals awaiting signature</div>
            <div class="stat-value">{{ number_format($stats['approvals_count']) }}</div>
        </div>
        @can('crm.view')
        <div class="card flex flex-col justify-center gap-2">
            <a href="{{ route('hq.crm.leads') }}" wire:navigate class="btn btn-primary btn-sm"><i data-lucide="plus"></i> Add a lead</a>
            <a href="{{ route('hq.crm.proposal') }}" wire:navigate class="btn btn-secondary btn-sm"><i data-lucide="file-text"></i> Start a proposal</a>
        </div>
        @endcan
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 items-start">

        <div class="xl:col-span-2 flex flex-col gap-6">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">My tasks</h2>
                </div>

                @if(count($activeTasks) > 0)
                    <div class="flex flex-col gap-3">
                        @foreach($activeTasks as $task)
                            @php [$taskText, $taskPill] = $taskStatus($task->status); $timing = $activeTimer && $activeTimer->task_id === $task->id; @endphp
                            <div class="flex flex-wrap justify-between items-center gap-3" style="padding: 12px 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); {{ $timing ? 'background: var(--color-teal-tint); box-shadow: inset 2px 0 0 var(--color-deep-teal);' : '' }}">
                                <div style="min-width: 0;">
                                    <div class="text-caption text-muted">{{ $task->project_title }} · {{ $task->phase_title }}</div>
                                    <div class="font-semibold text-ink text-small">{{ $task->title }}</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="pill {{ $taskPill }}">{{ $taskText }}</span>

                                    @if($timing)
                                        <button type="button" wire:click="stopTimer" class="btn btn-secondary btn-sm"><i data-lucide="square"></i> Stop timer</button>
                                    @else
                                        <button type="button" wire:click="startTimer('{{ $task->id }}')" wire:loading.attr="disabled" class="btn btn-secondary btn-sm"><i data-lucide="play"></i> Start timer</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <strong>No open tasks</strong>
                        Tasks assigned to you on a sprint board appear here.
                    </div>
                @endif
            </section>

            @can('dashboard.view')
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Proposals awaiting client signature</h2>
                </div>

                @if(count($pendingApprovals) > 0)
                    <div class="flex flex-col">
                        @foreach($pendingApprovals as $approval)
                            <div class="flex flex-wrap justify-between items-center gap-3" style="padding: 12px 0; border-top: 1px solid var(--color-line);">
                                <div style="min-width: 0;">
                                    <div class="text-caption text-muted">{{ $approval->client_name }}</div>
                                    <div class="font-semibold text-ink text-small">{{ \Illuminate\Support\Str::limit($approval->title, 40) }}</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-small text-ink" style="font-variant-numeric: tabular-nums;">{{ $money($approval->currency, $approval->total_value) }}</span>
                                    @can('crm.view')
                                        <a href="{{ route('hq.crm.proposal', ['id' => $approval->id]) }}" wire:navigate class="btn btn-secondary btn-sm">Open</a>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-small text-muted">No proposals are waiting for a client signature.</div>
                @endif
            </section>
            @endcan
        </div>

        <div class="flex flex-col gap-6">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Time tracking</h2>
                    @if($activeTimer)
                        <span class="pill pill-success">Running</span>
                    @else
                        <span class="pill pill-neutral">Not running</span>
                    @endif
                </div>

                @if($activeTimer)
                    <p class="text-small" style="margin: 0 0 4px;">Logging time for <strong class="text-ink">{{ \Illuminate\Support\Str::limit($activeTimer->task_title, 35) }}</strong></p>
                    <p class="text-caption text-muted" style="margin: 0 0 16px; font-variant-numeric: tabular-nums;">Started at {{ \Carbon\Carbon::parse($activeTimer->start_time)->format('H:i T') }}</p>
                    <button type="button" wire:click="stopTimer" class="btn btn-secondary" style="width: 100%;"><i data-lucide="square"></i> Stop timer</button>
                @else
                    <p class="text-small text-muted" style="margin: 0;">Start the timer on one of your tasks to log time against it.</p>
                @endif
            </section>

            <section class="card">
                <h2 class="card-title" style="margin-bottom: 12px;">Coming up</h2>
                @forelse($upcomingAppointments as $appt)
                    <div style="padding: 10px 0; border-top: 1px solid var(--color-line);">
                        <div class="text-caption" style="color: var(--color-deep-teal); font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($appt->start_time)->format('D j M, H:i') }} WAT</div>
                        <div class="font-semibold text-ink text-small">{{ $apptLabel($appt->title) }}</div>
                        <div class="text-caption text-muted">{{ $appt->client_name ?? 'Internal' }}</div>
                    </div>
                @empty
                    <div class="text-small text-muted">No meetings scheduled.</div>
                @endforelse
            </section>
        </div>

        <section class="card">
            <h2 class="card-title" style="margin-bottom: 12px;">Notifications</h2>

            @forelse($recentNotifications as $note)
                <div class="flex gap-3" style="padding: 10px 0; border-top: 1px solid var(--color-line);">
                    <div style="margin-top: 2px; color: var(--color-deep-teal);">
                        @if($note->type === 'Financial')
                            <i data-lucide="banknote" style="width: 16px; height: 16px;"></i>
                        @elseif($note->type === 'Project')
                            <i data-lucide="folder-kanban" style="width: 16px; height: 16px;"></i>
                        @else
                            <i data-lucide="info" style="width: 16px; height: 16px;"></i>
                        @endif
                    </div>
                    <div style="min-width: 0;">
                        <div class="font-semibold text-ink text-small">{{ $note->title }}</div>
                        <div class="text-caption text-slate">{{ $note->message }}</div>
                        <div class="text-caption text-muted" style="margin-top: 2px;">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <div class="text-small text-muted">No notifications yet.</div>
            @endforelse
        </section>

    </div>
</div>
