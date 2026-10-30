<div>
    <style>
        /* Three-step progress for the staff record form */
        .steps { display: flex; gap: 8px; margin: 0 0 24px; padding: 0; list-style: none; counter-reset: step; }
        .steps li { flex: 1; display: flex; flex-direction: column; gap: 6px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
        .steps li::before { content: ""; display: block; height: 4px; border-radius: 2px; background: var(--color-line); }
        .steps li.is-current { color: var(--color-ink); font-weight: 600; }
        .steps li.is-current::before { background: var(--color-deep-teal); }
        .steps li.is-done::before { background: var(--color-teal); }
    </style>

    @php
        $regionLabels = ['GLOBAL' => 'Global', 'CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya'];
        $steps = [1 => 'Person and role', 2 => 'Tax and social security', 3 => 'Pay and bank details'];
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="staff-saved-title">
                <h2 id="staff-saved-title" class="modal-title">Staff record saved</h2>
                <p class="muted">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Staff directory</h1>
            <p class="page-subtitle">Each staff member’s role, tax and social security numbers, and pay. Payroll uses these records.</p>
        </div>
    </div>

    <div class="tabs" role="tablist" aria-label="Country">
        @foreach(['ALL' => 'All countries', 'CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya'] as $tabKey => $tabLabel)
            <button type="button" role="tab" wire:click="setTab('{{ $tabKey }}')" class="tab {{ $activeTab === $tabKey ? 'is-active' : '' }}" aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}">{{ $tabLabel }}</button>
        @endforeach
    </div>

    <x-flash class="mb-6" />

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[400px_minmax(0,1fr)]">

        <div class="card">

            <div class="card-header">
                <h2 class="card-title">{{ $editMode ? 'Edit staff record' : 'Add a staff member' }}</h2>
                @if($editMode) <button type="button" wire:click="resetForm" class="btn btn-ghost btn-sm">Cancel</button> @endif
            </div>

            <ol class="steps" aria-label="Steps">
                @foreach($steps as $n => $stepLabel)
                    <li class="{{ $currentStep === $n ? 'is-current' : ($currentStep > $n ? 'is-done' : '') }}" @if($currentStep === $n) aria-current="step" @endif>
                        <span>{{ $n }}. {{ $stepLabel }}</span>
                    </li>
                @endforeach
            </ol>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;">
                    <i data-lucide="circle-alert"></i>
                    <div>
                        <strong>Some details need attention.</strong>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form wire:submit.prevent="saveEmployee">

                @if($currentStep === 1)
                    <div class="flex flex-col gap-4">
                        <div class="field">
                            <label class="label" for="emp-user">HQ account</label>
                            <select id="emp-user" wire:model="user_id" required class="select" {{ $editMode ? 'disabled' : '' }} aria-describedby="emp-user-help">
                                <option value="">Choose an account</option>
                                @foreach($availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                            <span id="emp-user-help" class="help">Only active JBS Praxis accounts without a staff record are listed.</span>
                            @error('user_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="field">
                                <label class="label" for="emp-code">Staff number</label>
                                <input id="emp-code" type="text" wire:model="employee_code" readonly class="input" style="background: var(--color-mist); font-variant-numeric: tabular-nums; cursor: not-allowed;">
                            </div>
                            <div class="field">
                                <label class="label" for="emp-hire">Start date</label>
                                <input id="emp-hire" type="date" wire:model="hire_date" required class="input">
                                @error('hire_date') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="emp-job">Job title and grade</label>
                            <input id="emp-job" type="text" wire:model="job_classification" required class="input" placeholder="e.g. Senior developer, level 3">
                            @error('job_classification') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="emp-manager">Line manager (optional)</label>
                            <select id="emp-manager" wire:model="line_manager_id" class="select">
                                <option value="">No line manager</option>
                                @foreach($allManagers as $mgr)
                                    <option value="{{ $mgr->id }}">{{ $mgr->first_name }} {{ $mgr->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="field">
                                <label class="label" for="emp-marital">Marital status</label>
                                <select id="emp-marital" wire:model="marital_status" required class="select">
                                    <option value="Single">Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widowed">Widowed</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="emp-dependents">Dependent children</label>
                                <input id="emp-dependents" type="number" wire:model="dependents_count" required min="0" class="input" style="font-variant-numeric: tabular-nums;">
                                @error('dependents_count') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                @if($currentStep === 2)
                    <div class="flex flex-col gap-4">
                        <div class="field">
                            <label class="label" for="emp-region">Country for tax and payroll</label>
                            <select id="emp-region" wire:model.live="tax_region" required class="select">
                                <option value="GLOBAL">Global or expatriate (no local tax)</option>
                                <option value="CM">Cameroon (CGI)</option>
                                <option value="NG">Nigeria (personal income tax and pensions)</option>
                                <option value="KE">Kenya (KRA)</option>
                            </select>
                            @error('tax_region') <span class="field-error">{{ $message }}</span> @enderror
                        </div>

                        <p class="help" style="margin: 0;">You can save without these numbers, but the staff list shows the record as incomplete until they are added.</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="field">
                                <label class="label" for="emp-tin">Tax ID (TIN or NIU)</label>
                                <input id="emp-tin" type="text" wire:model="tin_number" class="input">
                            </div>
                            <div class="field">
                                <label class="label" for="emp-national-id">National ID or passport number</label>
                                <input id="emp-national-id" type="text" wire:model="national_id" class="input">
                            </div>
                        </div>

                        @if($tax_region === 'CM')
                            <div class="field">
                                <label class="label" for="emp-cnps">CNPS number (social security)</label>
                                <input id="emp-cnps" type="text" wire:model="social_security_number" class="input">
                            </div>
                        @elseif($tax_region === 'NG')
                            <hr class="divider" style="margin: 0;">
                            <div class="field">
                                <label class="label" for="emp-state">State of residence</label>
                                <input id="emp-state" type="text" wire:model="state_of_residence" class="input" placeholder="e.g. Lagos" aria-describedby="emp-state-help">
                                <span id="emp-state-help" class="help">PAYE tax is paid to this state.</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="field">
                                    <label class="label" for="emp-pfa">Pension fund administrator</label>
                                    <input id="emp-pfa" type="text" wire:model="pfa_name" class="input">
                                </div>
                                <div class="field">
                                    <label class="label" for="emp-rsa">RSA PIN</label>
                                    <input id="emp-rsa" type="text" wire:model="rsa_number" class="input">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label" for="emp-nhf">NHF number</label>
                                <input id="emp-nhf" type="text" wire:model="nhf_number" class="input">
                            </div>
                        @elseif($tax_region === 'KE')
                            <hr class="divider" style="margin: 0;">
                            <div class="field">
                                <label class="label" for="emp-kra">KRA PIN</label>
                                <input id="emp-kra" type="text" wire:model="kra_pin" class="input">
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="field">
                                    <label class="label" for="emp-nssf">NSSF number</label>
                                    <input id="emp-nssf" type="text" wire:model="nssf_number" class="input">
                                </div>
                                <div class="field">
                                    <label class="label" for="emp-nhif">NHIF number</label>
                                    <input id="emp-nhif" type="text" wire:model="nhif_number" class="input">
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($currentStep === 3)
                    <div class="flex flex-col gap-4">

                        <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                            <div class="field">
                                <label class="label" for="emp-frequency">Pay frequency</label>
                                <select id="emp-frequency" wire:model="pay_frequency" class="select"><option value="Monthly">Monthly</option></select>
                            </div>
                            <div class="field">
                                <label class="label" for="emp-currency">Currency</label>
                                <select id="emp-currency" wire:model="currency" class="select">
                                    <option value="USD">USD</option>
                                    <option value="XAF">XAF</option>
                                    <option value="NGN">NGN</option>
                                    <option value="KES">KES</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="emp-method">Payment method</label>
                                <select id="emp-method" wire:model="payment_method" class="select"><option value="Bank Transfer">Bank transfer</option><option value="Crypto">Crypto</option></select>
                            </div>
                        </div>

                        <div class="callout flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-ink" style="font-weight: 600;">
                                <i data-lucide="calculator" style="width: 18px; height: 18px;"></i> Pay calculator
                            </div>

                            <x-flash :keys="['gross_up_success']" style="margin: 0;" />

                            <div class="grid items-end gap-3 sm:grid-cols-[1fr_auto] xl:grid-cols-1">
                                <div class="field">
                                    <label class="label" for="emp-target-net">Net pay wanted, a month (optional)</label>
                                    <input id="emp-target-net" type="number" step="0.01" wire:model="target_net_pay" class="input" style="font-variant-numeric: tabular-nums;">
                                </div>
                                <button type="button" wire:click="estimateGrossFromNet" class="btn btn-secondary">
                                    <span wire:loading.remove wire:target="estimateGrossFromNet">Work out gross pay</span>
                                    <span wire:loading wire:target="estimateGrossFromNet">Working it out…</span>
                                </button>
                            </div>

                            <div class="grid items-end gap-3 sm:grid-cols-[1fr_auto] xl:grid-cols-1" style="border-top: 1px solid var(--color-line); padding-top: 16px;">
                                <div class="field">
                                    <label class="label" for="emp-total-gross">Total gross pay, a month</label>
                                    <input id="emp-total-gross" type="number" step="0.01" wire:model="total_gross" class="input" style="font-variant-numeric: tabular-nums; font-weight: 600;" required>
                                    @error('total_gross') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <button type="button" wire:click="autoSplitCompensation" class="btn btn-secondary">
                                    <span wire:loading.remove wire:target="autoSplitCompensation">Split into salary and allowances</span>
                                    <span wire:loading wire:target="autoSplitCompensation">Splitting…</span>
                                </button>
                            </div>
                            <span class="help" style="color: var(--color-slate);">The split follows the usual shares for the country: in Nigeria 50% salary, 30% housing and 20% transport; in Cameroon 70%, 15% and 15%.</span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="field">
                                <label class="label" for="emp-base">Basic salary</label>
                                <input id="emp-base" type="number" step="0.01" wire:model="base_salary" class="input" style="font-variant-numeric: tabular-nums;" required>
                                @error('base_salary') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="emp-housing">Housing allowance</label>
                                <input id="emp-housing" type="number" step="0.01" wire:model="allowance_housing" class="input" style="font-variant-numeric: tabular-nums;">
                            </div>
                            <div class="field">
                                <label class="label" for="emp-transport">Transport allowance</label>
                                <input id="emp-transport" type="number" step="0.01" wire:model="allowance_transport" class="input" style="font-variant-numeric: tabular-nums;">
                            </div>
                            <div class="field">
                                <label class="label" for="emp-utility">Utility allowance</label>
                                <input id="emp-utility" type="number" step="0.01" wire:model="allowance_utility" class="input" style="font-variant-numeric: tabular-nums;">
                            </div>
                        </div>

                        <fieldset class="flex flex-col gap-3" style="border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; margin: 0;">
                            <legend class="label" style="padding: 0 4px;">Benefits in kind</legend>
                            <label class="flex items-start gap-2" style="cursor: pointer;">
                                <input type="checkbox" wire:model="bik_housing_provided" class="checkbox" style="margin-top: 3px;">
                                <span>Company housing <span class="help">(adds 15% to the CGI taxable base)</span></span>
                            </label>
                            <label class="flex items-start gap-2" style="cursor: pointer;">
                                <input type="checkbox" wire:model="bik_vehicle_provided" class="checkbox" style="margin-top: 3px;">
                                <span>Company vehicle <span class="help">(adds 10% to the CGI taxable base)</span></span>
                            </label>
                            <label class="flex items-start gap-2" style="cursor: pointer;">
                                <input type="checkbox" wire:model="bik_domestic_staff" class="checkbox" style="margin-top: 3px;">
                                <span>Domestic staff <span class="help">(adds 5% to the CGI taxable base)</span></span>
                            </label>
                        </fieldset>

                    </div>
                @endif

                <div class="flex items-center justify-between gap-3" style="margin-top: 24px; border-top: 1px solid var(--color-line); padding-top: 16px;">
                    <button type="button" wire:click="prevStep" class="btn btn-secondary" style="visibility: {{ $currentStep > 1 ? 'visible' : 'hidden' }};">
                        <i data-lucide="arrow-left"></i> Back
                    </button>

                    @if($currentStep < 3)
                        <button type="button" wire:click="nextStep" class="btn btn-primary">
                            Next <i data-lucide="arrow-right"></i>
                        </button>
                    @else
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                            <span wire:loading.remove wire:target="saveEmployee">{{ $editMode ? 'Save changes' : 'Add staff member' }}</span>
                            <span wire:loading wire:target="saveEmployee">Saving…</span>
                        </button>
                    @endif
                </div>

            </form>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 180px;">Staff member and role</th>
                            <th style="min-width: 170px;">Tax and social security</th>
                            <th class="num text-right" style="min-width: 150px;">Gross pay a month</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $emp)
                            @php
                                $empTotalGross = $emp->base_salary + $emp->allowance_housing + $emp->allowance_transport + $emp->allowance_utility;
                                // Numbers payroll needs for each country
                                $missingIds = [];
                                if ($emp->tax_region === 'NG' && (!$emp->pfa_name || !$emp->rsa_number)) $missingIds[] = 'Pension details missing';
                                if ($emp->tax_region === 'CM' && !$emp->social_security_number) $missingIds[] = 'CNPS number missing';
                                if ($emp->tax_region === 'KE' && (!$emp->kra_pin || !$emp->nssf_number || !$emp->nhif_number)) $missingIds[] = 'KRA, NSSF or NHIF number missing';
                            @endphp
                            <tr>
                                <td>
                                    <div class="text-ink" style="font-weight: 600;">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                    <div style="margin-top: 2px;">{{ $emp->job_classification }}</div>
                                    @if($emp->manager_first)
                                        <div class="help" style="margin-top: 4px;">Reports to {{ $emp->manager_first }} {{ $emp->manager_last }}</div>
                                    @endif
                                </td>

                                <td>
                                    <span class="pill pill-neutral">{{ $regionLabels[$emp->tax_region] ?? $emp->tax_region }}</span>
                                    @if(!empty($missingIds))
                                        @foreach($missingIds as $warning)
                                            <div class="flex items-center gap-1" style="margin-top: 6px; font-size: 0.8125rem; color: var(--color-warning);">
                                                <i data-lucide="triangle-alert" style="width: 14px; height: 14px;"></i> {{ $warning }}
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="help" style="margin-top: 6px;">Numbers recorded</div>
                                    @endif
                                </td>

                                <td class="num">
                                    <div class="text-ink" style="font-weight: 600;">{{ number_format($empTotalGross, 2) }} <span class="help">{{ $emp->currency }}</span></div>
                                    <div class="help" style="margin-top: 2px;">Salary {{ number_format($emp->base_salary, 0) }} · allowances {{ number_format($empTotalGross - $emp->base_salary, 0) }}</div>
                                </td>

                                <td>
                                    @if($emp->is_active)
                                        <span class="pill pill-success">Active</span>
                                    @else
                                        <span class="pill pill-neutral">Left</span>
                                        @if(!empty($emp->termination_date))
                                            <div class="help" style="margin-top: 4px; white-space: nowrap;">{{ \Carbon\Carbon::parse($emp->termination_date)->format('j M Y') }}</div>
                                        @endif
                                    @endif
                                </td>

                                <td>
                                    <div class="flex flex-col items-end gap-1">
                                        <button type="button" wire:click="editEmployee('{{ $emp->id }}')" class="btn btn-ghost btn-sm">Edit</button>
                                        <button type="button" wire:click="toggleStatus('{{ $emp->id }}', {{ $emp->is_active }})" class="btn btn-ghost btn-sm" @if($emp->is_active) style="color: var(--color-danger);" @endif>
                                            {{ $emp->is_active ? 'Mark as left' : 'Reinstate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No staff records{{ $activeTab !== 'ALL' ? ' in ' . ($regionLabels[$activeTab] ?? $activeTab) : '' }} yet</strong>
                                        Add a staff member with the form. They need an active HQ account first, which you create in Settings and access.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
