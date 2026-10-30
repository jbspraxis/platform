<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Project budgets</h1>
            <p class="page-subtitle">How each project’s budget is split into lines, and how much of each line is spent or held by open cash requests.</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            @if(count($allProjects))
            <div class="field">
                <label class="label" for="pb-project">Project</label>
                <select id="pb-project" wire:model="activeProjectId" wire:change="switchProject($event.target.value)" class="select" style="min-width: 240px; max-width: 100%;">
                    @foreach($allProjects as $proj)
                        <option value="{{ $proj->id }}">{{ $proj->title }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @can('finance.edit')
            @if($activeProject)
            <button type="button" wire:click="openSweepModal" class="btn btn-secondary">
                <i data-lucide="refresh-cw"></i> Move budget between lines
            </button>
            @endif
            @endcan
        </div>
    </div>

    <x-flash class="mb-6" />

    @if($activeProject)
        @php $cur = $activeProject->currency; @endphp
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="card">
                <div class="stat-label">Contract value</div>
                <div class="stat-value">{{ \App\Livewire\Hq\HqMoney::format($cur, $metrics['total_budget']) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Allocated to lines</div>
                <div class="stat-value">{{ \App\Livewire\Hq\HqMoney::format($cur, $metrics['total_allocated']) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Held by open cash requests</div>
                <div class="stat-value">{{ \App\Livewire\Hq\HqMoney::format($cur, $metrics['total_accrued']) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Spent</div>
                <div class="stat-value">{{ \App\Livewire\Hq\HqMoney::format($cur, $metrics['total_spent']) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Not yet allocated</div>
                <div class="stat-value" @if($metrics['remaining_pool'] < 0) style="color: var(--color-danger);" @endif>{{ \App\Livewire\Hq\HqMoney::format($cur, $metrics['remaining_pool']) }}</div>
                @if($metrics['remaining_pool'] < 0)
                    <span class="pill pill-danger mt-2">Over-allocated</span>
                @endif
            </div>
        </div>

        <section class="card p-0">
            <div class="border-b border-line px-6 py-4">
                <h2 class="card-title">Budget lines</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Budget line</th>
                            <th class="num text-right">Allocated</th>
                            <th class="num text-right">Spent</th>
                            <th class="num text-right">Held by requests</th>
                            <th class="num text-right">Available</th>
                            <th style="width: 160px;">Used</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($budgetLines as $line)
                            @php
                                $totalCommitted = $line['total_spent'] + $line['accrued_amount'];
                                $burnPercent = $line['total_allocated'] > 0 ? ($totalCommitted / $line['total_allocated']) * 100 : ($totalCommitted > 0 ? 100 : 0);
                                $burnColor = 'var(--color-teal)';
                                if($burnPercent >= 75) $burnColor = 'var(--color-warning)';
                                if($burnPercent >= 95) $burnColor = 'var(--color-danger)';
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-medium text-ink">{{ $line['description'] }}</div>
                                    <div class="help" style="font-variant-numeric: tabular-nums;">{{ $line['item_code'] ?? 'Custom' }} · {{ $line['category'] ?? 'No category' }}</div>
                                </td>
                                <td class="num text-ink">{{ number_format($line['total_allocated'], 2) }}</td>
                                <td class="num">{{ number_format($line['total_spent'], 2) }}</td>
                                <td class="num">{{ number_format($line['accrued_amount'], 2) }}</td>
                                <td class="num font-semibold" style="color: {{ $line['available_balance'] < 0 ? 'var(--color-danger)' : 'var(--color-ink)' }};">{{ $line['available_balance'] < 0 ? '−' : '' }}{{ number_format(abs($line['available_balance']), 2) }}</td>
                                <td>
                                    <div class="flex justify-between text-xs">
                                        <span class="muted">{{ $burnPercent >= 95 ? 'Nearly used up' : ($burnPercent >= 75 ? 'Running low' : 'Used') }}</span>
                                        <span class="font-semibold" style="color: {{ $burnPercent >= 75 ? $burnColor : 'var(--color-ink)' }}; font-variant-numeric: tabular-nums;">{{ number_format($burnPercent, 1) }}%</span>
                                    </div>
                                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full" style="background: var(--color-mist);" role="progressbar" aria-valuenow="{{ round(min($burnPercent, 100)) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Share of the line used">
                                        <div class="h-full" style="width: {{ min($burnPercent, 100) }}%; background: {{ $burnColor }};"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <strong>No budget lines for this project yet</strong>
                                        Budget lines split the contract value into expense items, such as travel or software.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="card">
            <div class="empty-state">
                <strong>No projects yet</strong>
                Each project’s budget appears here once the project is created.
            </div>
        </div>
    @endif

    @if($showSweepModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="move-budget-title">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 id="move-budget-title" class="modal-title">Move budget between lines</h2>
                    <button type="button" wire:click="$set('showSweepModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                <div class="callout mb-4 text-sm">
                    This changes how the budget is split; the project’s total stays the same. You can’t move money that is already spent or held by open cash requests.
                </div>

                <form wire:submit.prevent="executeSweep" class="flex flex-col gap-4">

                    <div class="field">
                        <label class="label" for="sweep-from">Move from</label>
                        <select id="sweep-from" wire:model="sweepFromId" class="select" required>
                            <option value="">Choose a budget line</option>
                            @foreach($budgetLines as $line)
                                @if($line['available_balance'] > 0)
                                    <option value="{{ $line['id'] }}">{{ $line['description'] }} ({{ number_format($line['available_balance'], 2) }} available)</option>
                                @endif
                            @endforeach
                        </select>
                        @error('sweepFromId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="label" for="sweep-to">Move to</label>
                        <select id="sweep-to" wire:model="sweepToId" class="select" required>
                            <option value="">Choose a budget line</option>
                            @foreach($budgetLines as $line)
                                <option value="{{ $line['id'] }}">{{ $line['description'] }}</option>
                            @endforeach
                        </select>
                        @error('sweepToId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="label" for="sweep-amount">Amount ({{ $activeProject->currency ?? '' }})</label>
                        <input id="sweep-amount" type="number" wire:model="sweepAmount" step="0.01" min="0.01" class="input" style="font-variant-numeric: tabular-nums;" required>
                        @error('sweepAmount') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="modal-actions">
                        <button type="button" wire:click="$set('showSweepModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="executeSweep">Move budget</span>
                            <span wire:loading wire:target="executeSweep">Moving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
