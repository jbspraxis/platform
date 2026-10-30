<div>
    @php
        // Stored statuses stay as they are; these are the labels people read
        $statusLabels = ['Draft' => 'Draft', 'Pending_Review' => 'Awaiting approval', 'Approved' => 'Approved', 'Disbursed' => 'Paid'];
        $statusPills = ['Draft' => 'pill-neutral', 'Pending_Review' => 'pill-warning', 'Approved' => 'pill-info', 'Disbursed' => 'pill-success'];
        $regionLabels = ['ALL' => 'All countries', 'NG' => 'Nigeria', 'CM' => 'Cameroon', 'KE' => 'Kenya'];
        $me = (string) auth()->id();
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="payroll-done-title">
                <h2 id="payroll-done-title" class="modal-title">Payroll run updated</h2>
                <p class="text-sm text-slate">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Payroll</h1>
            <p class="page-subtitle">Prepare each month’s payroll run, have a second person check it, then approve it and mark it as paid.</p>
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setTab('ledger')" class="tab {{ $activeTab === 'ledger' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'ledger' ? 'true' : 'false' }}">Payroll runs</button>
        <button type="button" role="tab" wire:click="setTab('inspector')" class="tab {{ $activeTab === 'inspector' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'inspector' ? 'true' : 'false' }}">Payslips</button>
    </div>

    @if(session()->has('error'))
        <div class="alert alert-danger mb-6" role="alert"><i data-lucide="circle-alert"></i><span>{{ session('error') }}</span></div>
    @endif

    @if($activeTab === 'ledger')

        @if(!$selectedRunId)
            @can('payroll.create')
            <div class="card mb-6">
                <h2 class="card-title mb-4">Create a draft payroll run</h2>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="field">
                        <label class="label" for="pr-month">Month</label>
                        <input id="pr-month" type="number" min="1" max="12" wire:model="draftPeriodMonth" class="input" style="width: 100px; font-variant-numeric: tabular-nums;">
                        @error('draftPeriodMonth') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="pr-year">Year</label>
                        <input id="pr-year" type="number" min="2020" wire:model="draftPeriodYear" class="input" style="width: 120px; font-variant-numeric: tabular-nums;">
                        @error('draftPeriodYear') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="pr-region">Country</label>
                        <select id="pr-region" wire:model="targetRegion" class="select" style="min-width: 200px;">
                            <option value="ALL">All countries</option>
                            <option value="NG">Nigeria (PITA)</option>
                            <option value="CM">Cameroon (CGI)</option>
                            <option value="KE">Kenya (KRA)</option>
                        </select>
                    </div>
                    <button type="button" wire:click="generateDraftRun" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="generateDraftRun">Create draft run</span>
                        <span wire:loading wire:target="generateDraftRun">Creating…</span>
                    </button>
                </div>
            </div>
            @endcan

            <div class="card p-0">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Pay period</th>
                                <th>Country</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payrollRuns as $run)
                                <tr>
                                    <td class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ $run->reference_number }}</td>
                                    <td style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($run->pay_period_start)->format('M Y') }}</td>
                                    <td>{{ $regionLabels[$run->region_code] ?? $run->region_code }}</td>
                                    <td><span class="pill {{ $statusPills[$run->status] ?? 'pill-neutral' }}">{{ $statusLabels[$run->status] ?? $run->status }}</span></td>
                                    <td class="text-right">
                                        <button type="button" wire:click="viewRun('{{ $run->id }}')" class="btn btn-secondary btn-sm">Open run</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <strong>No payroll runs yet</strong>
                                            Choose a month and country above, then create a draft run.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @else
            <div class="mb-4">
                <button type="button" wire:click="closeRunView" class="btn btn-ghost btn-sm">
                    <i data-lucide="arrow-left"></i> Back to payroll runs
                </button>
            </div>

            @php
                $preparedByMe = $me !== '' && (string) ($runDetails['maker_id'] ?? '') === $me;
                $checkedByMe = $me !== '' && (string) ($runDetails['checker_id'] ?? '') === $me;
            @endphp

            <div class="card mb-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="section-title mb-1" style="font-variant-numeric: tabular-nums;">Payroll run {{ $runDetails['reference_number'] }}</h2>
                        <p class="text-sm muted">{{ \Carbon\Carbon::parse($runDetails['pay_period_start'])->format('F Y') }} · {{ $regionLabels[$runDetails['region_code']] ?? $runDetails['region_code'] }}</p>
                    </div>
                    <span class="pill {{ $statusPills[$runDetails['status']] ?? 'pill-neutral' }}">{{ $statusLabels[$runDetails['status']] ?? $runDetails['status'] }}</span>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-lg bg-mist p-3">
                        <dt class="stat-label">Prepared by</dt>
                        <dd class="font-medium text-ink">{{ trim(($runDetails['maker_first'] ?? '') . ' ' . ($runDetails['maker_last'] ?? '')) ?: 'Not recorded' }}</dd>
                    </div>
                    <div class="rounded-lg bg-mist p-3">
                        <dt class="stat-label">Checked by</dt>
                        <dd class="font-medium text-ink">{{ trim(($runDetails['checker_first'] ?? '') . ' ' . ($runDetails['checker_last'] ?? '')) ?: 'Not yet checked' }}</dd>
                    </div>
                    <div class="rounded-lg bg-mist p-3">
                        <dt class="stat-label">Approved by</dt>
                        <dd class="font-medium text-ink">{{ trim(($runDetails['approver_first'] ?? '') . ' ' . ($runDetails['approver_last'] ?? '')) ?: 'Not yet approved' }}</dd>
                    </div>
                </dl>

                <hr class="divider">

                <div class="flex flex-wrap items-center justify-end gap-3">
                    @if($runDetails['status'] === 'Draft' && auth()->user()->can('payroll.edit'))
                        @if($preparedByMe)
                            <span class="help">You prepared this run, so someone else checks it.</span>
                        @else
                            <button type="button" wire:click="submitForReview('{{ $selectedRunId }}')" class="btn btn-primary">
                                <i data-lucide="send"></i> Mark as checked and send for approval
                            </button>
                        @endif
                    @endif

                    @if($runDetails['status'] === 'Pending_Review' && auth()->user()->can('payroll.approve'))
                        <button type="button" wire:click="rejectRun('{{ $selectedRunId }}')" class="btn btn-secondary">
                            <i data-lucide="rotate-ccw"></i> Return to draft
                        </button>
                        @if($preparedByMe || $checkedByMe)
                            <span class="help">You {{ $preparedByMe ? 'prepared' : 'checked' }} this run, so another approver approves it.</span>
                        @else
                            <button type="button" wire:click="approveRun('{{ $selectedRunId }}')" class="btn btn-primary">
                                <i data-lucide="check"></i> Approve payroll run
                            </button>
                        @endif
                    @endif

                    @if($runDetails['status'] === 'Approved' && auth()->user()->can('payroll.approve'))
                        <button type="button" wire:click="rejectRun('{{ $selectedRunId }}')" class="btn btn-secondary">
                            <i data-lucide="rotate-ccw"></i> Withdraw approval
                        </button>
                        @if($preparedByMe)
                            <span class="help">You prepared this run, so someone else marks it as paid.</span>
                        @else
                            <button type="button" wire:click="disburseRun('{{ $selectedRunId }}')" class="btn btn-primary">
                                <i data-lucide="banknote"></i> Mark as paid
                            </button>
                        @endif
                    @endif

                    @if($runDetails['status'] === 'Disbursed')
                        <span class="help flex items-center gap-1"><i data-lucide="lock" style="width: 14px; height: 14px;"></i> Paid. This run can no longer be changed.</span>
                        <button type="button" class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed;" title="Not available yet">Download bank payment file</button>
                    @endif
                </div>
            </div>

            <div class="card p-0">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff member</th>
                                <th class="num text-right">Gross pay</th>
                                <th class="num text-right">Taxable pay (incl. benefits in kind)</th>
                                <th class="num text-right">Tax and pension</th>
                                <th class="num text-right">Net pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($runLines as $line)
                                <tr>
                                    <td>
                                        <div class="font-medium text-ink">{{ $line->first_name }} {{ $line->last_name }}</div>
                                        <div class="help" style="font-variant-numeric: tabular-nums;">{{ $line->employee_code }} · {{ $regionLabels[$line->tax_region] ?? $line->tax_region }}</div>
                                    </td>
                                    <td class="num">{{ number_format($line->gross_pay, 2) }}</td>
                                    <td class="num">{{ number_format($line->taxable_gross ?? 0, 2) }}</td>
                                    <td class="num">−{{ number_format($line->total_deductions ?? 0, 2) }}</td>
                                    <td class="num font-semibold text-ink">{{ number_format($line->net_pay ?? 0, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <strong>No staff members in this run</strong>
                                            No active staff member with current pay details matched this month and country.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @endif

    @endif

    @if($activeTab === 'inspector')
        <div class="card">
            <div class="empty-state">
                <strong>Payslips have their own screen</strong>
                Open Payslips to see each staff member’s payslip for a payroll run.
                <div class="mt-4"><a href="{{ route('hq.finance.payslips') }}" wire:navigate class="btn btn-primary">Open payslips</a></div>
            </div>
        </div>
    @endif
</div>
