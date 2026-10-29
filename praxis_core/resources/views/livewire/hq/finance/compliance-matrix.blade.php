<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Payroll compliance</h1>
            <p class="page-subtitle">Net pay, income tax and pension owed for a payroll run, with the bank and tax files to pay them.</p>
        </div>
        @can('payroll.export')
        <div class="flex flex-wrap gap-3">
            <button type="button" wire:click="exportStatutoryRemittance" class="btn btn-secondary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="exportStatutoryRemittance" class="inline-flex items-center gap-2"><i data-lucide="landmark"></i> Download tax and pension file</span>
                <span wire:loading wire:target="exportStatutoryRemittance">Preparing…</span>
            </button>
            <button type="button" wire:click="exportBankRemittance" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="exportBankRemittance" class="inline-flex items-center gap-2"><i data-lucide="download"></i> Download net pay bank file</span>
                <span wire:loading wire:target="exportBankRemittance">Preparing…</span>
            </button>
        </div>
        @endcan
    </div>

    <div class="card mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="field min-w-[260px] flex-1">
                <label class="label" for="pc-source">Figures from</label>
                <select id="pc-source" wire:model.live="matrix_run_filter" class="select">
                    <option value="live">Forecast for this month (current staff and tax rules)</option>
                    <optgroup label="Approved payroll runs">
                        @foreach($payrollRuns as $run)
                            <option value="{{ $run->id }}">{{ $run->reference_number }} ({{ \Carbon\Carbon::parse($run->pay_period_end)->format('M Y') }}, {{ $run->region_code }})</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>

            @if($matrix_run_filter === 'live')
            <div class="field min-w-[200px]">
                <label class="label" for="pc-region">Country</label>
                <select id="pc-region" wire:model.live="matrix_region_filter" class="select">
                    <option value="ALL">All countries</option>
                    <option value="CM">Cameroon (OHADA)</option>
                    <option value="NG">Nigeria (PITA)</option>
                    <option value="KE">Kenya (KRA)</option>
                </select>
            </div>
            @endif
        </div>

        @if($matrix_run_filter === 'live')
            <div class="alert alert-warning mt-4" role="note">
                <i data-lucide="triangle-alert"></i>
                <span>This is a forecast, calculated from current staff records and tax rules. Don't use it to pay tax or salaries; choose an approved payroll run instead.</span>
            </div>
        @else
            <div class="alert alert-success mt-4" role="note">
                <i data-lucide="lock"></i>
                <span>These figures come from an approved payroll run and no longer change. Use them for payment and audit.</span>
            </div>
        @endif
    </div>

    <div wire:loading wire:target="matrix_run_filter, matrix_region_filter" class="mb-6 w-full">
        <p class="help">Updating figures…</p>
    </div>

    <div wire:loading.remove wire:target="matrix_run_filter, matrix_region_filter">
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="card">
                <div class="stat-label">Staff members paid</div>
                <div class="stat-value">{{ $matrix_kpis['headcount'] ?? 0 }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Gross pay</div>
                <div class="stat-value">{{ number_format($matrix_kpis['total_gross'] ?? 0, 2) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Net pay to staff</div>
                <div class="stat-value">{{ number_format($matrix_kpis['total_net'] ?? 0, 2) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Income tax owed (PAYE, IRPP)</div>
                <div class="stat-value">{{ number_format($matrix_kpis['total_paye_irpp'] ?? 0, 2) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Pension owed, employee and employer shares</div>
                <div class="stat-value">{{ number_format(($matrix_kpis['total_pension_cnps_employee'] ?? 0) + ($matrix_kpis['total_pension_cnps_employer'] ?? 0), 2) }}</div>
            </div>
            <div class="card">
                <div class="stat-label">Other employer contributions</div>
                <div class="stat-value">{{ number_format($matrix_kpis['total_other_taxes'] ?? 0, 2) }}</div>
            </div>
        </div>

        <section class="card mb-6 p-0">
            <div class="border-b border-line px-6 py-4">
                <h2 class="card-title">Net pay by bank transfer</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th>Bank</th>
                            <th>Account number</th>
                            <th class="num">Net pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($remittance_list as $row)
                            <tr>
                                <td class="font-medium text-ink">{{ $row['name'] }}</td>
                                <td>{{ $row['bank'] }}</td>
                                <td style="font-variant-numeric: tabular-nums;">{{ $row['account'] }}</td>
                                <td class="num">
                                    <span class="font-semibold text-ink">{{ number_format($row['amount'], 2) }}</span> <span class="help">{{ $row['currency'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <strong>No bank transfers for this selection</strong>
                                        Staff members paid by bank transfer appear here. Check their payment details in the staff directory.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card p-0">
            <div class="border-b border-line px-6 py-4">
                <h2 class="card-title">Tax and pension to pay</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th>Tax ID (TIN)</th>
                            <th>Social security ID (CNPS, RSA)</th>
                            <th class="num">Income tax</th>
                            <th class="num">Pension, employee and employer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statutory_remittance_list as $row)
                            <tr>
                                <td class="font-medium text-ink">{{ $row['name'] }}</td>
                                <td style="font-variant-numeric: tabular-nums;">{{ $row['tin'] }}</td>
                                <td style="font-variant-numeric: tabular-nums;">{{ $row['social_id'] }}</td>
                                <td class="num">
                                    <span class="text-ink">{{ number_format($row['tax_amount'], 2) }}</span> <span class="help">{{ $row['currency'] }}</span>
                                </td>
                                <td class="num">
                                    <span class="text-ink">{{ number_format($row['pension_employee'] + $row['pension_employer'], 2) }}</span> <span class="help">{{ $row['currency'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No tax or pension owed for this selection</strong>
                                        Choose another payroll run or country to see what is owed.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
