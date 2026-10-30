<div wire:poll.60s="loadAllData">
    @php $cols = $canSeeMoney ? 6 : 5; @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Time tracking</h1>
            <p class="page-subtitle">{{ $canSeeAll ? 'Timers running now, time that needs a second look, the time log and correction requests.' : 'Your finished timers. A timer runs while one of your tasks is In progress.' }}</p>
        </div>
        <p class="help">Updates every 60 seconds.</p>
    </div>

    @if (session()->has('error'))
        <div class="alert alert-danger mb-4" role="alert"><i data-lucide="circle-alert"></i> <span>{{ session('error') }}</span></div>
    @endif
    @if (session()->has('success'))
        <div class="alert alert-success mb-4" role="status"><i data-lucide="check-circle"></i> <span>{{ session('success') }}</span></div>
    @endif

    <div class="tabs" role="tablist">
        @if($canSeeAll)
            <button type="button" role="tab" wire:click="$set('activeTab', 'live_burn')" class="tab {{ $activeTab === 'live_burn' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'live_burn' ? 'true' : 'false' }}">Running now</button>
            <button type="button" role="tab" wire:click="$set('activeTab', 'anomalies')" class="tab {{ $activeTab === 'anomalies' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'anomalies' ? 'true' : 'false' }}">To check</button>
        @endif

        <button type="button" role="tab" wire:click="$set('activeTab', 'ledger')" class="tab {{ $activeTab === 'ledger' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'ledger' ? 'true' : 'false' }}">Time log</button>

        @if($canManage)
            <button type="button" role="tab" wire:click="$set('activeTab', 'corrections')" class="tab {{ $activeTab === 'corrections' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'corrections' ? 'true' : 'false' }}">Correction requests</button>
        @endif
    </div>

    <!-- Tab 1: running now -->
    @if($activeTab === 'live_burn' && $canSeeAll)
        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th>Project and task</th>
                            <th>Started</th>
                            <th class="num text-right">Running for</th>
                            @if($canSeeMoney) <th class="num text-right">Cost so far</th> @endif
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($liveBurnData as $burn)
                            <tr>
                                <td class="font-medium text-ink">{{ $burn['first_name'] }} {{ $burn['last_name'] }}</td>
                                <td>
                                    <div class="help">{{ $burn['project_title'] }}</div>
                                    <div>{{ $burn['task_title'] }}</div>
                                </td>
                                <td class="num text-left">{{ \Carbon\Carbon::parse($burn['start_time'])->format('j M, H:i') }}</td>
                                <td class="num font-semibold text-ink">{{ $burn['live_duration'] }}</td>
                                @if($canSeeMoney)
                                    <td class="num">${{ number_format($burn['live_cost'], 2) }}</td>
                                @endif
                                <td>
                                    @if($burn['is_rework'])
                                        <span class="pill pill-warning">Rework</span>
                                    @else
                                        <span class="pill pill-info">Work</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $cols }}">
                                <div class="empty-state">
                                    <strong>No timers running</strong>
                                    A timer starts when a staff member moves a task into In progress, and stops when the task moves on.
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 2: to check -->
    @if($activeTab === 'anomalies' && $canSeeAll)
        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th>Task</th>
                            <th class="num text-right">Estimate</th>
                            <th class="num text-right">Logged</th>
                            <th>Why it’s here</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($anomaliesData as $anomaly)
                            @php
                                $estimate = $anomaly->estimated_hours ?: ($anomaly->story_points * 3);
                                $actual = $anomaly->actual_hours_logged;
                                $reason = $actual > ($estimate * 1.5) ? 'More than 1.5 times the estimate' : 'One entry longer than 4 hours';
                            @endphp
                            <tr>
                                <td class="font-medium text-ink">{{ $anomaly->first_name }} {{ $anomaly->last_name }}</td>
                                <td>{{ $anomaly->title }}</td>
                                <td class="num">{{ number_format($estimate, 2) }} h</td>
                                <td class="num font-semibold text-danger">{{ number_format($actual, 2) }} h</td>
                                <td><span class="pill pill-warning">{{ $reason }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">
                                <div class="empty-state">
                                    <strong>Nothing to check</strong>
                                    A task appears here when its logged time passes 1.5 times the estimate, or when one time entry runs longer than 4 hours.
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 3: time log -->
    @if($activeTab === 'ledger')
        @if($canSeeAll)
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <p class="help">The latest 100 finished time entries.</p>
                <div class="field">
                    <label for="ledger-group" class="label">Group by</label>
                    <select id="ledger-group" wire:model.live="ledgerGroupBy" class="select">
                        <option value="engineer">Staff member</option>
                        <option value="project">Project</option>
                    </select>
                </div>
            </div>
        @endif

        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ $ledgerGroupBy === 'engineer' ? 'Project and task' : 'Staff member and task' }}</th>
                            <th>Started</th>
                            <th>Ended</th>
                            <th class="num text-right">Duration</th>
                            @if($canSeeMoney) <th class="num text-right">Cost</th> @endif
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgerData as $groupName => $logs)
                            <tr class="bg-mist">
                                <td colspan="{{ $cols }}" class="font-semibold text-ink">{{ $groupName }}</td>
                            </tr>
                            @foreach($logs as $log)
                                <tr>
                                    <td>
                                        @if($ledgerGroupBy === 'engineer')
                                            <div class="help">{{ $log->project_title }}</div>
                                            <div>{{ $log->task_title }}</div>
                                        @else
                                            <div class="font-medium text-ink">{{ $log->first_name }} {{ $log->last_name }}</div>
                                            <div class="help">{{ $log->task_title }}</div>
                                        @endif
                                    </td>
                                    <td class="num text-left">{{ \Carbon\Carbon::parse($log->start_time)->format('j M, H:i') }}</td>
                                    <td class="num text-left">{{ \Carbon\Carbon::parse($log->end_time)->format('j M, H:i') }}</td>
                                    <td class="num font-semibold text-ink">{{ number_format($log->duration_minutes / 60, 2) }} h</td>

                                    @if($canSeeMoney)
                                        <td class="num {{ $log->is_rework ? 'text-danger' : '' }}">${{ number_format($log->cost_calculated, 2) }}</td>
                                    @endif

                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            @if($log->is_rework)
                                                <span class="pill pill-warning">Rework</span>
                                            @endif
                                            @if($log->is_system_terminated)
                                                <span class="pill pill-neutral">Stopped automatically</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="{{ $cols }}">
                                <div class="empty-state">
                                    <strong>No time logged yet</strong>
                                    {{ $canSeeAll ? 'Finished timers appear here, grouped as you choose.' : 'Your finished timers appear here once you move a task out of In progress.' }}
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 4: correction requests and manual time entry -->
    @if($activeTab === 'corrections' && $canManage)
        <h2 class="section-title">Waiting for review</h2>
        <div class="card mb-8 p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th>Task</th>
                            <th>Time requested</th>
                            <th>Reason</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($correctionRequests as $req)
                            <tr>
                                <td class="font-medium text-ink">{{ $req->first_name }} {{ $req->last_name }}</td>
                                <td>{{ $req->task_title }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="num text-left">{{ \Carbon\Carbon::parse($req->requested_start_time)->format('j M, H:i') }}</span>
                                    <span class="muted">to</span>
                                    <span class="num text-left">{{ \Carbon\Carbon::parse($req->requested_end_time)->format('j M, H:i') }}</span>
                                </td>
                                <td><div class="help max-w-[280px]">{{ $req->reason_for_correction }}</div></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" wire:click="approveCorrection('{{ $req->id }}')" class="btn btn-primary btn-sm">
                                            <i data-lucide="check"></i> Approve
                                        </button>
                                        <button type="button" wire:click="rejectCorrection('{{ $req->id }}')" class="btn btn-secondary btn-sm">
                                            <i data-lucide="x"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">
                                <div class="empty-state">
                                    <strong>No correction requests</strong>
                                    When a staff member asks to correct their time, the request appears here for you to approve or reject.
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($canInject)
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Manual time entry</h2>
                </div>
                <p class="help mb-4">Add time for a staff member without a correction request. The entry is approved at once, counts towards pay and project cost, and is recorded in the audit trail.</p>

                <div class="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                    <div class="field">
                        <label for="manual-user" class="label">Staff member</label>
                        <select id="manual-user" wire:model="godModeUserId" class="select">
                            <option value="">Choose a staff member</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                            @endforeach
                        </select>
                        @error('godModeUserId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="manual-task" class="label">Task</label>
                        <select id="manual-task" wire:model="godModeTaskId" class="select">
                            <option value="">Choose a task</option>
                            @foreach($allTasks as $t)
                                <option value="{{ $t->id }}">{{ \Illuminate\Support\Str::limit($t->title, 30) }}</option>
                            @endforeach
                        </select>
                        @error('godModeTaskId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="manual-start" class="label">Start</label>
                        <input id="manual-start" type="datetime-local" wire:model="godModeStartTime" class="input">
                        @error('godModeStartTime') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="manual-end" class="label">End</label>
                        <input id="manual-end" type="datetime-local" wire:model="godModeEndTime" class="input">
                        @error('godModeEndTime') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <button type="button" wire:click="executeGodMode" class="btn btn-primary w-full" onclick="confirm('Add this time? It is approved at once and counts towards pay and project cost.') || event.stopImmediatePropagation()">
                            <i data-lucide="plus"></i> Add time
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
