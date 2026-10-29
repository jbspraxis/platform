<div>
    @php
        $regionLabels = ['CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya'];
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="var-saved-title">
                <h2 id="var-saved-title" class="modal-title">Variable pay saved</h2>
                <p class="text-sm text-slate">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Variable pay</h1>
            <p class="page-subtitle">Enter each month's bonuses and one-off deductions before the payroll run. Months in an approved payroll run are locked.</p>
        </div>
    </div>

    @if(session()->has('error'))
        <div class="alert alert-danger mb-6" role="alert"><i data-lucide="circle-alert"></i><span>{{ session('error') }}</span></div>
    @endif

    <div class="card mb-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-end gap-4">
                <div class="field">
                    <label class="label" for="vp-month">Month</label>
                    <input id="vp-month" type="number" min="1" max="12" wire:model.live="targetMonth" class="input" style="width: 100px; font-variant-numeric: tabular-nums;">
                </div>
                <div class="field">
                    <label class="label" for="vp-year">Year</label>
                    <input id="vp-year" type="number" min="2020" wire:model.live="targetYear" class="input" style="width: 120px; font-variant-numeric: tabular-nums;">
                </div>
                <div class="field">
                    <label class="label" for="vp-region">Country</label>
                    <select id="vp-region" wire:model.live="targetRegion" class="select" style="min-width: 180px;">
                        <option value="ALL">All countries</option>
                        <option value="CM">Cameroon</option>
                        <option value="NG">Nigeria</option>
                        <option value="KE">Kenya</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col items-start gap-1">
                <button type="button" wire:click="autoCalculateAnciennete" class="btn btn-secondary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="autoCalculateAnciennete" class="inline-flex items-center gap-2"><i data-lucide="calculator"></i> Calculate seniority bonus</span>
                    <span wire:loading wire:target="autoCalculateAnciennete">Calculating…</span>
                </button>
                <span class="help">2% of base salary after 2 years, plus 1% a year after that. Check the figures, then save.</span>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="saveVariables">
        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 200px;">Staff member</th>
                            <th style="width: 150px;">Seniority bonus (prime d'ancienneté)</th>
                            <th style="width: 150px;">Performance bonus</th>
                            <th style="width: 150px;">13th-month pay</th>
                            <th style="width: 150px;">Other deductions</th>
                            <th style="width: 110px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employeeVariables as $empId => $data)
                            <tr>
                                <td>
                                    <div class="font-medium text-ink">{{ $data['name'] }}</div>
                                    <div class="help" style="font-variant-numeric: tabular-nums;">
                                        {{ $data['code'] }} · {{ $regionLabels[$data['region']] ?? $data['region'] }} · Hired {{ $data['hire_date'] ? \Carbon\Carbon::parse($data['hire_date'])->format('M Y') : 'date not recorded' }}
                                    </div>
                                </td>
                                <td>
                                    <label class="sr-only" for="vp-snr-{{ $empId }}">Seniority bonus for {{ $data['name'] }}</label>
                                    <input id="vp-snr-{{ $empId }}" type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.prime_anciennete" class="input" style="font-variant-numeric: tabular-nums;" {{ $data['is_processed'] ? 'disabled' : '' }}>
                                </td>
                                <td>
                                    <label class="sr-only" for="vp-perf-{{ $empId }}">Performance bonus for {{ $data['name'] }}</label>
                                    <input id="vp-perf-{{ $empId }}" type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.prime_performance" class="input" style="font-variant-numeric: tabular-nums;" {{ $data['is_processed'] ? 'disabled' : '' }}>
                                </td>
                                <td>
                                    <label class="sr-only" for="vp-13-{{ $empId }}">13th-month pay for {{ $data['name'] }}</label>
                                    <input id="vp-13-{{ $empId }}" type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.thirteenth_month_bonus" class="input" style="font-variant-numeric: tabular-nums;" {{ $data['is_processed'] ? 'disabled' : '' }}>
                                </td>
                                <td>
                                    <label class="sr-only" for="vp-ded-{{ $empId }}">Other deductions for {{ $data['name'] }}</label>
                                    <input id="vp-ded-{{ $empId }}" type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.other_deductions" class="input" style="font-variant-numeric: tabular-nums;" {{ $data['is_processed'] ? 'disabled' : '' }}>
                                </td>
                                <td>
                                    @if($data['is_processed'])
                                        <span class="pill pill-neutral"><i data-lucide="lock" style="width: 12px; height: 12px;"></i> Locked</span>
                                    @else
                                        <span class="pill pill-info">Open</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <strong>No active staff members for this country</strong>
                                        Staff members appear here once they're active in the staff directory. Choose another country to see others.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveVariables" class="inline-flex items-center gap-2"><i data-lucide="save"></i> Save variable pay</span>
                <span wire:loading wire:target="saveVariables">Saving…</span>
            </button>
        </div>
    </form>
</div>
