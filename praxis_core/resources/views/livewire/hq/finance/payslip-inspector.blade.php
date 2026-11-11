<div>
    @assets
    <style>
        /* Payslip document */
        .payslip { max-width: 900px; margin: 0 auto; padding: 40px; }
        .payslip-title { font-family: var(--font-serif); font-size: 1.625rem; line-height: 2.125rem; font-weight: 500; color: var(--color-ink); margin: 0; }
        .slip-meta { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .slip-section { margin-top: 24px; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid var(--color-line-strong); font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .slip-row { display: flex; justify-content: space-between; gap: 16px; padding: 6px 0; border-bottom: 1px solid var(--color-line); font-size: 0.875rem; color: var(--color-slate); }
        .slip-row > span:last-child { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--color-ink); }
        .slip-row.total { font-weight: 600; color: var(--color-ink); border-bottom-color: var(--color-line-strong); }
        .slip-row.grand-total { margin-top: 16px; padding: 14px 0; font-size: 1.125rem; font-weight: 600; color: var(--color-ink); border-top: 1px solid var(--color-ink); border-bottom: 3px double var(--color-ink); }
        @media (max-width: 640px) { .payslip { padding: 20px; } }

        @media print {
            body { background: #FFF !important; color: #000 !important; }
            body * { visibility: hidden; }
            #printable-payslip, #printable-payslip * { visibility: visible; color: #000 !important; box-shadow: none !important; }
            #printable-payslip { position: absolute; left: 0; top: 0; width: 100%; max-width: 100%; margin: 0; padding: 0; border: none !important; background: #FFF !important; }
        }
    </style>
    @endassets

    <div class="page-header">
        <div>
            <h1 class="page-title">Payslips</h1>
            <p class="page-subtitle">View and print a staff member’s payslip from an approved payroll run.</p>
        </div>
    </div>


    <div class="card mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="field min-w-[240px] flex-1">
                <label class="label" for="ps-run">Payroll run</label>
                <select id="ps-run" wire:model.live="selectedRunId" class="select">
                    <option value="">Choose an approved payroll run</option>
                    @foreach($payrollRuns as $run)
                        <option value="{{ $run->id }}">{{ $run->reference_number }} ({{ \Carbon\Carbon::parse($run->pay_period_end)->format('M Y') }}, {{ $run->region_code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="field min-w-[240px] flex-1">
                <label class="label" for="ps-staff">Staff member</label>
                <select id="ps-staff" wire:model="selectedEmployeeId" class="select" {{ empty($employeesInRun) ? 'disabled' : '' }}>
                    <option value="">{{ empty($selectedRunId) ? 'Choose a payroll run first' : 'Choose a staff member' }}</option>
                    @foreach($employeesInRun as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" wire:click="inspectPayslip" class="btn btn-primary" wire:loading.attr="disabled" {{ empty($employeesInRun) ? 'disabled' : '' }} @if(empty($employeesInRun)) style="opacity: 0.5; cursor: not-allowed;" @endif>
                <span wire:loading.remove wire:target="inspectPayslip" class="inline-flex items-center gap-2"><i data-lucide="search"></i> Show payslip</span>
                <span wire:loading wire:target="inspectPayslip">Loading…</span>
            </button>

            @if($payslipData)
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    <i data-lucide="printer"></i> Print or save as PDF
                </button>
            @endif
        </div>
    </div>

    @if($payslipData)
        <div class="card payslip" id="printable-payslip">
            <div class="flex flex-wrap justify-between gap-6 border-b border-line pb-5">
                <div>
                    <h2 class="payslip-title">{{ $payslipData['employer_name'] }}</h2>
                    <div class="slip-meta mt-1">{{ $payslipData['employer_address'] }}</div>
                    <div class="slip-meta" style="font-variant-numeric: tabular-nums;">Tax ID: {{ $payslipData['employer_tax_id'] }} · Employer social security number: {{ $payslipData['employer_social_id'] }}</div>
                </div>
                <div class="sm:text-right">
                    <div class="text-base font-semibold text-ink">Payslip · Bulletin de paie</div>
                    <div class="slip-meta mt-2">Country: {{ $payslipData['tax_region'] }}</div>
                    <div class="slip-meta" style="font-variant-numeric: tabular-nums;">Period: {{ \Carbon\Carbon::parse($payslipData['pay_period_start'])->format('j M Y') }} to {{ \Carbon\Carbon::parse($payslipData['pay_period_end'])->format('j M Y') }}</div>
                    <div class="slip-meta" style="font-variant-numeric: tabular-nums;">Paid on: {{ \Carbon\Carbon::parse($payslipData['payment_date'])->format('j M Y') }}</div>
                    <div class="slip-meta">Pay frequency: {{ $payslipData['pay_frequency'] }}</div>
                </div>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-lg bg-mist p-4 text-sm">
                    <dt class="muted">Name</dt><dd class="font-medium text-ink">{{ $payslipData['employee_name'] }}</dd>
                    <dt class="muted">Address</dt><dd>{{ $payslipData['employee_address'] }}</dd>
                    <dt class="muted">Staff code</dt><dd class="text-ink" style="font-variant-numeric: tabular-nums;">{{ $payslipData['employee_code'] }}</dd>
                    <dt class="muted">Job classification</dt><dd class="text-ink">{{ $payslipData['job_classification'] }}</dd>
                </dl>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-lg bg-mist p-4 text-sm" style="font-variant-numeric: tabular-nums;">
                    <dt class="muted">TIN / NIU</dt><dd class="text-ink">{{ $payslipData['tin_number'] }}</dd>
                    <dt class="muted">Social security number</dt><dd class="text-ink">{{ $payslipData['social_id'] }}</dd>
                    <dt class="muted">National ID</dt><dd class="text-ink">{{ $payslipData['national_id'] }}</dd>
                    <dt class="muted">Family status</dt><dd class="text-ink">{{ $payslipData['marital_status'] }}, {{ $payslipData['dependents'] }} {{ (int) $payslipData['dependents'] === 1 ? 'dependant' : 'dependants' }}</dd>
                </dl>
            </div>

            <div class="slip-section">Earnings · Gains</div>
            <div class="slip-row">
                <span>Basic salary (salaire de base)</span>
                <span>{{ number_format($payslipData['base_salary'], 2) }}</span>
            </div>
            @if($payslipData['allowance_housing'] > 0)
            <div class="slip-row">
                <span>Housing allowance (indemnité de logement)</span>
                <span>{{ number_format($payslipData['allowance_housing'], 2) }}</span>
            </div>
            @endif
            @if($payslipData['allowance_transport'] > 0)
            <div class="slip-row">
                <span>Transport allowance (indemnité de transport)</span>
                <span>{{ number_format($payslipData['allowance_transport'], 2) }}</span>
            </div>
            @endif
            @if($payslipData['overtime_pay'] > 0)
            <div class="slip-row">
                <span>Overtime (heures supplémentaires)</span>
                <span>{{ number_format($payslipData['overtime_pay'], 2) }}</span>
            </div>
            @endif
            @if($payslipData['performance_bonus'] > 0)
            <div class="slip-row">
                <span>Performance or seniority bonus (prime)</span>
                <span>{{ number_format($payslipData['performance_bonus'], 2) }}</span>
            </div>
            @endif
            @if($payslipData['benefits_in_kind'] > 0)
            <div class="slip-row">
                <span>Benefits in kind, taxable value (avantages en nature)</span>
                <span>{{ number_format($payslipData['benefits_in_kind'], 2) }}</span>
            </div>
            @endif
            <div class="slip-row total">
                <span>Gross pay (salaire brut)</span>
                <span>{{ number_format($payslipData['gross_pay'], 2) }} {{ $payslipData['currency'] }}</span>
            </div>

            <div class="slip-section">Deductions · Retenues</div>
            <div class="slip-row">
                <span>Income tax (PAYE / IRPP)</span>
                <span>−{{ number_format($payslipData['tax_paye'], 2) }}</span>
            </div>
            @if($payslipData['deduction_pension'] > 0)
            <div class="slip-row">
                <span>Pension and social security, employee share</span>
                <span>−{{ number_format($payslipData['deduction_pension'], 2) }}</span>
            </div>
            @endif
            @if($payslipData['deduction_nhf'] > 0)
            <div class="slip-row">
                <span>Housing fund (NHF / CFC)</span>
                <span>−{{ number_format($payslipData['deduction_nhf'], 2) }}</span>
            </div>
            @endif
            @if($payslipData['deduction_nhis'] > 0)
            <div class="slip-row">
                <span>Health insurance (NHIS)</span>
                <span>−{{ number_format($payslipData['deduction_nhis'], 2) }}</span>
            </div>
            @endif
            @if($payslipData['deduction_other'] > 0)
            <div class="slip-row">
                <span>Other deductions (absences, communal tax)</span>
                <span>−{{ number_format($payslipData['deduction_other'], 2) }}</span>
            </div>
            @endif
            <div class="slip-row total">
                <span>Total deductions (total des retenues)</span>
                <span>−{{ number_format($payslipData['total_deductions'], 2) }} {{ $payslipData['currency'] }}</span>
            </div>

            <div class="slip-row grand-total">
                <span>Net pay (net à payer)</span>
                <span>{{ number_format($payslipData['net_pay'], 2) }} {{ $payslipData['currency'] }}</span>
            </div>

            <p class="slip-meta mt-2 text-center" style="font-variant-numeric: tabular-nums;">
                Paid by {{ $payslipData['payment_method'] }}: {{ $payslipData['account_routing'] }}
            </p>

            <div class="mt-6 grid grid-cols-2 gap-4 border-t border-line pt-5 sm:grid-cols-4">
                <div>
                    <div class="stat-label">Gross pay this year</div>
                    <div class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ number_format($payslipData['ytd_gross'], 2) }}</div>
                </div>
                <div>
                    <div class="stat-label">Tax paid this year</div>
                    <div class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ number_format($payslipData['ytd_tax'], 2) }}</div>
                </div>
                <div>
                    <div class="stat-label">Annual leave left</div>
                    <div class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ number_format($payslipData['annual_leave_balance'], 1) }} days</div>
                </div>
                <div>
                    <div class="stat-label">Sick leave taken</div>
                    <div class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ number_format($payslipData['sick_leave_taken'], 1) }} days</div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap items-end justify-between gap-6 border-t border-line pt-5">
                <div class="min-w-0">
                    <div class="stat-label">Document fingerprint (SHA-256)</div>
                    <div class="break-all text-xs text-slate" style="font-variant-numeric: tabular-nums;">{{ $payslipData['security_hash'] }}</div>
                </div>
                <div class="w-[220px] border-t border-ink pt-1 text-center text-xs text-slate">
                    Signature and company stamp
                </div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="empty-state">
                <strong>No payslip selected</strong>
                Choose a payroll run and a staff member, then select Show payslip.
            </div>
        </div>
    @endif
</div>
