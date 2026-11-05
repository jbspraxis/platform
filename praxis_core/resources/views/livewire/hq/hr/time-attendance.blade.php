<div>
    @php
        $regionLabels = ['CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya', 'GLOBAL' => 'Other'];
        $hours = fn ($v) => ($v ?? 0) > 0 ? number_format($v, 1) . ' h' : '–';
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ta-done-title">
                <h2 id="ta-done-title" class="modal-title">Attendance calculated</h2>
                <p class="text-sm text-slate">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Time and attendance</h1>
            <p class="page-subtitle">Overtime, night hours and unexcused absences for each month, calculated from clock-in records before the payroll run.</p>
        </div>
    </div>


    <div class="card mb-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-end gap-4">
                <div class="field">
                    <label class="label" for="ta-month">Month</label>
                    <input id="ta-month" type="number" min="1" max="12" wire:model.live="targetMonth" class="input" style="width: 100px; font-variant-numeric: tabular-nums;">
                </div>
                <div class="field">
                    <label class="label" for="ta-year">Year</label>
                    <input id="ta-year" type="number" min="2020" wire:model.live="targetYear" class="input" style="width: 120px; font-variant-numeric: tabular-nums;">
                </div>
                <div class="field">
                    <label class="label" for="ta-region">Country</label>
                    <select id="ta-region" wire:model.live="targetRegion" class="select" style="min-width: 180px;">
                        <option value="ALL">All countries</option>
                        <option value="CM">Cameroon</option>
                        <option value="NG">Nigeria</option>
                        <option value="KE">Kenya</option>
                    </select>
                </div>
            </div>

            @can('hr.edit')
            <button type="button" wire:click="runAggregationEngine" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="runAggregationEngine" class="inline-flex items-center gap-2"><i data-lucide="calculator"></i> Calculate attendance</span>
                <span wire:loading wire:target="runAggregationEngine">Calculating…</span>
            </button>
            @endcan
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card">
            <div class="stat-label">Staff members</div>
            <div class="stat-value">{{ count($aggregatedData) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Overtime and night hours</div>
            <div class="stat-value">
                {{ number_format(collect($aggregatedData)->sum(function($row) {
                    return ($row['overtime_hours_120'] ?? 0) + ($row['overtime_hours_130'] ?? 0) + ($row['overtime_hours_140'] ?? 0) + ($row['night_premium_hours'] ?? 0);
                }), 1) }}
            </div>
        </div>
        <div class="card">
            <div class="stat-label">Unexcused absences (days)</div>
            <div class="stat-value">{{ number_format(collect($aggregatedData)->sum('unexcused_absence_days'), 1) }}</div>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Staff member</th>
                        <th>Country</th>
                        <th class="num text-right">Overtime at 120%</th>
                        <th class="num text-right">Overtime at 130%</th>
                        <th class="num text-right">Overtime at 140%</th>
                        <th class="num text-right">Night hours</th>
                        <th class="num text-right">Unexcused absences</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($aggregatedData as $row)
                        @php
                            $hasOt = (($row['overtime_hours_120'] ?? 0) > 0 || ($row['overtime_hours_130'] ?? 0) > 0 || ($row['overtime_hours_140'] ?? 0) > 0);
                            $hasAbsence = (($row['unexcused_absence_days'] ?? 0) > 0);
                        @endphp
                        <tr>
                            <td>
                                <div class="font-medium text-ink">{{ $row['first_name'] ?? '' }} {{ $row['last_name'] ?? '' }}</div>
                                <div class="help" style="font-variant-numeric: tabular-nums;">{{ $row['employee_code'] ?? 'No staff code' }}</div>
                            </td>
                            <td>{{ $regionLabels[$row['tax_region'] ?? 'GLOBAL'] ?? $row['tax_region'] }}</td>
                            <td class="num">{{ $hours($row['overtime_hours_120'] ?? 0) }}</td>
                            <td class="num">{{ $hours($row['overtime_hours_130'] ?? 0) }}</td>
                            <td class="num">{{ $hours($row['overtime_hours_140'] ?? 0) }}</td>
                            <td class="num">{{ $hours($row['night_premium_hours'] ?? 0) }}</td>
                            <td class="num {{ $hasAbsence ? 'font-semibold text-ink' : '' }}">
                                {{ $hasAbsence ? number_format($row['unexcused_absence_days'], 1) . ' days' : '–' }}
                            </td>
                            <td>
                                @if($row['is_processed'] ?? false)
                                    <span class="pill pill-neutral">In payroll</span>
                                @elseif($hasAbsence)
                                    <span class="pill pill-danger">Deduction due</span>
                                @elseif($hasOt)
                                    <span class="pill pill-warning">Overtime due</span>
                                @else
                                    <span class="pill pill-success">No changes</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <strong>No active staff members for this selection</strong>
                                    Choose another month or country. Once staff members are active, select Calculate attendance to fill in their hours.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
