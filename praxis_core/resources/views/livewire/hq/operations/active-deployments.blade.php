{{-- Opening the Create project panel loads its client list (ActiveDeployments::$creating); closing drops it with the next request --}}
<div x-data="{ openCreateDrawer: false }" x-init="$watch('openCreateDrawer', open => $wire.$set('creating', open, open))">
    @php
        // Stored project statuses (database values) and what people read
        $statusLabels = ['Active' => 'Active', 'Scoping' => 'Scoping', 'Locked' => 'On hold', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
        $statusPills = ['Active' => 'pill-success', 'Scoping' => 'pill-neutral', 'Locked' => 'pill-warning', 'Completed' => 'pill-success', 'Cancelled' => 'pill-neutral'];
        // The project page and its milestone board open for managers and auditors, and for people on the project (App\Support\ProjectAccess)
        $canOpenAnyBoard = auth()->user()->can('projects.approve') || auth()->user()->can('projects.export') || ! $canSeeAll;
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">All projects</h1>
            <p class="page-subtitle">{{ $canSeeAll ? 'Progress on every active project, and the full list of projects.' : 'Progress on the projects you have tasks on.' }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @if($canExport)
                <button type="button" wire:click="exportCsv" class="btn btn-secondary">
                    <i data-lucide="download"></i> Export CSV
                </button>
            @endif
            @if($canCreate)
                <button type="button" @click="openCreateDrawer = true" class="btn btn-primary">
                    <i data-lucide="plus"></i> Create project
                </button>
            @endif
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="switchTab('matrix')" class="tab {{ $activeTab === 'matrix' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'matrix' ? 'true' : 'false' }}">Active projects</button>
        <button type="button" role="tab" wire:click="switchTab('ledger')" class="tab {{ $activeTab === 'ledger' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'ledger' ? 'true' : 'false' }}">Full list</button>
    </div>

    <!-- Search and filters -->
    <div class="card mb-6 flex flex-wrap items-end gap-4 py-4">
        <div class="field min-w-[200px] flex-1">
            <label for="project-search" class="label">Search</label>
            <input id="project-search" type="search" wire:model.live.debounce.300ms="searchQuery" class="input" placeholder="Project or client name">
        </div>

        <div class="field min-w-[180px]">
            <label for="project-status" class="label">Status</label>
            <select id="project-status" wire:model.live="filterStatus" class="select">
                <option value="">Any status</option>
                @if($activeTab === 'ledger')
                    <option value="Scoping">Scoping</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                @endif
                <option value="Active">Active</option>
                <option value="Financially Locked">On hold</option>
            </select>
        </div>

        @if($canSeeMoney && $activeTab === 'matrix')
            <div class="field min-w-[200px]">
                <label for="project-health" class="label">Budget</label>
                <select id="project-health" wire:model.live="filterHealth" class="select">
                    <option value="">Any budget position</option>
                    <option value="Healthy">On budget</option>
                    <option value="Bleeding">Spending ahead of progress</option>
                </select>
            </div>
        @endif

        @if($canSeeAll && $activeTab === 'matrix')
            <div class="field min-w-[180px]">
                <label for="project-assignee" class="label">Assigned to</label>
                <select id="project-assignee" wire:model.live="filterAssignee" class="select">
                    <option value="">Everyone</option>
                    @foreach($availableAssignees as $user)
                        <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>


    <!-- Tab 1: active projects -->
    @if($activeTab === 'matrix')
        {{-- Only while the cards are on screen (Livewire also slows it down in a background tab) --}}
        <div wire:poll.30s.visible="loadData">
            <div class="mb-6 grid gap-4 sm:grid-cols-2 {{ $canSeeMoney ? 'xl:grid-cols-4' : '' }}">
                <div class="card">
                    <div class="stat-label">Active projects</div>
                    <div class="stat-value">{{ $kpiActiveDeployments }}</div>
                </div>
                <div class="card">
                    <div class="stat-label">{{ __('projects.all.done_week') }}</div>
                    <div class="stat-value">{{ $kpiSprintVelocity }}</div>
                </div>

                @if($canSeeMoney)
                    <div class="card">
                        <div class="stat-label">Unpaid invoices on active projects</div>
                        <x-hq.money-totals :totals="$kpiCapitalAtRisk" class="{{ !empty($kpiCapitalAtRisk) ? 'text-warning' : '' }}" />
                    </div>
                    <div class="card">
                        <div class="stat-label">Projects spending ahead of progress</div>
                        <div class="stat-value {{ $kpiBleedingProjects > 0 ? 'text-danger' : '' }}">{{ $kpiBleedingProjects }}</div>
                    </div>
                @endif
            </div>
            <p class="help mb-4">Updates every 30 seconds.</p>

            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @forelse($matrixProjects as $p)
                    @php
                        $circumference = 150.79;
                        $offset = $circumference - ($p['progress_percent'] / 100) * $circumference;
                        $ringColor = $p['progress_percent'] == 100 ? 'var(--color-success)' : 'var(--color-teal)';
                    @endphp

                    <div class="card flex flex-col">
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="text-caption text-muted">{{ $p['client_name'] }}</div>
                                <div class="mt-1 mb-3 font-semibold text-ink">{{ $p['title'] }}</div>

                                <div class="flex flex-wrap gap-2">
                                    @if($p['is_financially_locked'])
                                        <span class="pill pill-danger">On hold: unpaid invoice</span>
                                    @else
                                        <span class="pill pill-success">Active</span>
                                    @endif

                                    @if($p['needs_midpoint_funds'] && !$p['is_financially_locked'])
                                        <span class="pill pill-warning">Invoice unpaid</span>
                                    @endif

                                    @if($p['is_bleeding'])
                                        <span class="pill pill-danger">Spending ahead of progress</span>
                                    @endif
                                </div>
                            </div>

                            <div class="relative grid h-[60px] w-[60px] shrink-0 place-items-center" role="img" aria-label="{{ __('projects.stats.progress_label', ['value' => $p['progress_percent'] . '%']) }}">
                                <svg width="60" height="60" class="-rotate-90">
                                    <circle stroke="var(--color-line)" stroke-width="4" fill="transparent" r="24" cx="30" cy="30"/>
                                    <circle stroke="{{ $ringColor }}" stroke-width="4" stroke-linecap="round" fill="transparent" r="24" cx="30" cy="30" style="stroke-dasharray: {{ $circumference }}; stroke-dashoffset: {{ $offset }};"/>
                                </svg>
                                <span class="absolute text-caption font-semibold text-ink num">{{ $p['progress_percent'] }}%</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-between gap-4 rounded-lg bg-mist px-4 py-3">
                            <div>
                                <div class="stat-label">{{ __('projects.all.tasks_done') }}</div>
                                <div class="text-small font-semibold text-ink num text-left">{{ $p['completed_points'] }} of {{ $p['total_points'] }}</div>
                            </div>
                            @if($canSeeMoney)
                                <div class="text-right">
                                    <div class="stat-label">Budget used</div>
                                    <div class="text-small font-semibold num {{ $p['is_bleeding'] ? 'text-danger' : ($p['burn_percent'] > $p['progress_percent'] ? 'text-warning' : 'text-success') }}">
                                        {{-- Spend is hours × the firm hourly rate, which is in US dollars --}}
                                        {{ $p['burn_percent'] }}% ({{ \App\Livewire\Hq\HqMoney::format('USD', $p['spent_amount'], 0) }})
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-auto flex flex-col gap-2 pt-4">
                            {{-- The project page (docs/PROJECTS.md) follows the same rule --}}
                            @if($canOpenAnyBoard)
                                <a href="{{ route('hq.ops.project', $p['id']) }}" class="btn btn-primary w-full" wire:navigate>
                                    <i data-lucide="folder-kanban"></i> {{ __('projects.open_page') }}
                                </a>
                            @endif

                            @if($canCreate)
                                <a href="{{ route('hq.ops.scrum', $p['id']) }}" class="btn btn-secondary w-full">
                                    <i data-lucide="list-checks"></i> {{ __('projects.all.plan') }}
                                </a>
                            @endif

                            @if($canOpenAnyBoard)
                                <a href="{{ route('hq.ops.project', ['project' => $p['id'], 'tab' => 'board']) }}" class="btn btn-secondary w-full" wire:navigate>
                                    <i data-lucide="layout-dashboard"></i> {{ __('projects.open_board') }}
                                </a>
                            @endif

                            @if($canLock)
                                @if($p['is_financially_locked'])
                                    <button type="button" wire:click="toggleFinancialLock('{{ $p['id'] }}')" class="btn btn-secondary w-full">
                                        <i data-lucide="unlock"></i> Release hold
                                    </button>
                                @else
                                    <button type="button" wire:click="toggleFinancialLock('{{ $p['id'] }}')" class="btn btn-ghost w-full text-danger">
                                        <i data-lucide="lock"></i> Put on hold
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="card empty-state md:col-span-2 2xl:col-span-3">
                        <strong>No active projects to show</strong>
                        {{ $canSeeAll ? 'Active projects appear here with their progress. Clear the search or filters, or create a project.' : 'A project appears here once a task on it is assigned to you.' }}
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Tab 2: full list -->
    @if($activeTab === 'ledger')
        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Hold</th>
                            <th class="num text-right">Budget</th>
                            <th>Dates</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgerProjects as $p)
                            <tr>
                                <td><span class="pill {{ $statusPills[$p['status']] ?? 'pill-neutral' }}">{{ $statusLabels[$p['status']] ?? $p['status'] }}</span></td>
                                <td>{{ $p['client_name'] }}</td>
                                <td class="font-medium text-ink">{{ $p['title'] }}</td>
                                <td>
                                    @if($p['is_financially_locked'])
                                        <span class="pill pill-danger">On hold</span>
                                    @else
                                        <span class="muted">No</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if($canSeeMoney)
                                        {{ \App\Livewire\Hq\HqMoney::format($p['currency'], $p['total_budget']) }}
                                    @else
                                        <span class="muted" title="Shown to people with finance access">Not shown</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="num text-left">{{ $p['start_date'] ? \Carbon\Carbon::parse($p['start_date'])->format('j M Y') : 'Not set' }}</span>
                                    <span class="muted">to</span>
                                    <span class="num text-left">{{ $p['target_end_date'] ? \Carbon\Carbon::parse($p['target_end_date'])->format('j M Y') : 'Not set' }}</span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if($canCreate)
                                            <a href="{{ route('hq.ops.scrum', $p['id']) }}" class="btn btn-secondary btn-sm">{{ __('projects.all.plan') }}</a>
                                        @endif
                                        @if($canOpenAnyBoard)
                                            <a href="{{ route('hq.ops.project', $p['id']) }}" class="btn btn-secondary btn-sm" wire:navigate>{{ __('projects.open_page') }}</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <strong>No projects to show</strong>
                                        {{ $canCreate ? 'Projects appear here once they are created. Clear the search or filters, or create a project.' : 'Projects you have tasks on appear here.' }}
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Create project panel -->
    <div x-cloak x-show="openCreateDrawer" class="fixed inset-0 z-40 bg-ink/50" style="display: none;" @click="openCreateDrawer = false" x-transition.opacity></div>

    {{-- Hidden (display: none) when closed, so it casts no shadow and keyboard focus can’t reach its fields --}}
    <div class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[480px] flex-col bg-paper"
         style="display: none; box-shadow: var(--shadow-float);"
         x-show="openCreateDrawer"
         x-transition:enter="transition-transform duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition-transform duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         x-cloak role="dialog" aria-modal="true" aria-labelledby="create-project-title">
        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
            <div>
                <h2 id="create-project-title" class="text-section font-semibold text-ink">Create project</h2>
                <p class="help mt-1">Set up the project. You can plan its milestones next.</p>
            </div>
            <button type="button" @click="openCreateDrawer = false" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
        </div>

        <div class="flex flex-1 flex-col gap-5 overflow-y-auto px-6 py-6">
            <div class="field">
                <label for="new-org" class="label">Client</label>
                <select id="new-org" wire:model="newOrgId" class="select">
                    <option value="">Choose a client</option>
                    @foreach($availableOrganizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
                @error('newOrgId') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="new-title" class="label">Project name</label>
                <input id="new-title" type="text" wire:model="newTitle" class="input" placeholder="e.g. Finance system rollout">
                @error('newTitle') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="new-status" class="label">Status</label>
                <select id="new-status" wire:model="newStatus" class="select">
                    <option value="Scoping">Scoping (before the contract is signed)</option>
                    <option value="Active">Active (work under way)</option>
                </select>
                @error('newStatus') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="new-budget" class="label">Budget (USD)</label>
                <input id="new-budget" type="number" wire:model="newBudget" class="input num text-left" placeholder="0.00" step="1000">
                @error('newBudget') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="field">
                    <label for="new-start" class="label">Start date (optional)</label>
                    <input id="new-start" type="date" wire:model="newStartDate" class="input">
                    @error('newStartDate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="new-end" class="label">Planned end date (optional)</label>
                    <input id="new-end" type="date" wire:model="newEndDate" class="input">
                    @error('newEndDate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="field">
                <label for="new-description" class="label">Scope (optional)</label>
                <textarea id="new-description" wire:model="newDescription" class="textarea" placeholder="What the project covers and what it delivers"></textarea>
            </div>
        </div>

        <div class="border-t border-line px-6 py-4">
            <button type="button" wire:click="createProject" class="btn btn-primary w-full" @click="setTimeout(() => { if(!$wire.errors.length) openCreateDrawer = false; }, 500)">
                Create project
            </button>
        </div>
    </div>
</div>
