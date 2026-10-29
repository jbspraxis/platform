<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-select {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #FFFFFF; padding: 10px 12px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; border-radius: 2px; transition: 0.3s;
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); 
            background-repeat: no-repeat; background-position: right 10px center;
        }
        .cyber-select:focus { border-color: #00E5FF; outline: none; background: rgba(0, 229, 255, 0.05); }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        
        .btn-action { 
            background: rgba(0, 229, 255, 0.1); border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px; 
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; 
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
        }
        .btn-action:hover:not(:disabled) { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-action:disabled { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.2); cursor: not-allowed; background: transparent; }

        .spinner { border: 2px solid rgba(0, 229, 255, 0.1); border-top: 2px solid #00E5FF; border-radius: 50%; width: 14px; height: 14px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* --- OFFICIAL PAYSLIP DOCUMENT STYLES --- */
        .payslip-container { background: #03050A; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; padding: 40px; margin-top: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); max-width: 900px; margin-left: auto; margin-right: auto; position: relative; overflow: hidden; }
        .payslip-container::before { content: 'OFFICIAL RECORD'; position: absolute; top: 40%; left: -10%; transform: rotate(-45deg); font-family: 'Montserrat', sans-serif; font-size: 8rem; color: rgba(255,255,255,0.02); pointer-events: none; white-space: nowrap; font-weight: 900; z-index: 0; }
        
        .payslip-content { position: relative; z-index: 1; }

        .slip-header { border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .slip-title { font-family: 'Montserrat', sans-serif; font-size: 1.4rem; color: #FFF; margin: 0; letter-spacing: 2px; font-weight: 900; text-transform: uppercase; }
        .slip-subtitle { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; margin-top: 5px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #E0E6ED; margin-bottom: 30px; }
        .info-block { background: rgba(255,255,255,0.02); padding: 15px; border-radius: 2px; border: 1px solid rgba(255,255,255,0.05); }
        .info-label { color: #8A9BB2; display: inline-block; width: 120px; }
        .info-val { color: #FFF; font-weight: bold; }

        .slip-section-title { font-family: 'Montserrat', sans-serif; font-size: 0.75rem; color: #00E5FF; text-transform: uppercase; border-bottom: 1px dashed rgba(0,229,255,0.3); padding-bottom: 5px; margin-bottom: 10px; margin-top: 25px; font-weight: bold; }
        
        .slip-row { display: flex; justify-content: space-between; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #E0E6ED; padding: 6px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        .slip-row:hover { background: rgba(255,255,255,0.02); }
        .slip-row.total { font-weight: bold; color: #FFF; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 10px; margin-top: 5px; background: rgba(0,0,0,0.2); }
        .slip-row.grand-total { font-size: 1.4rem; padding: 20px 10px; border-top: 2px solid #FFF; border-bottom: 4px double #00FF66; margin-top: 20px; background: rgba(0, 255, 102, 0.05); }
        
        .text-red { color: #FF3366 !important; }
        .text-green { color: #00FF66 !important; }
        
        .ytd-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; }
        .ytd-box { text-align: center; font-family: 'Roboto Mono', monospace; }
        .ytd-label { font-size: 0.6rem; color: #8A9BB2; text-transform: uppercase; margin-bottom: 4px; display: block; }
        .ytd-val { font-size: 0.9rem; color: #FFF; font-weight: bold; }

        .auth-footer { margin-top: 40px; padding-top: 20px; border-top: 1px dashed rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: flex-end; font-family: 'Roboto Mono', monospace; }
        .qr-placeholder { width: 80px; height: 80px; background: #FFF; padding: 5px; display: flex; align-items: center; justify-content: center; }
        .qr-placeholder img { width: 100%; height: 100%; object-fit: contain; }

        /* --- PRINT STYLESHEET --- */
        @media print {
            body { background: #FFF !important; color: #000 !important; }
            body * { visibility: hidden; }
            #printable-payslip, #printable-payslip * { 
                visibility: visible; 
                color: #000 !important; 
                background: transparent !important; 
                border-color: #CCC !important;
                text-shadow: none !important;
                box-shadow: none !important;
            }
            #printable-payslip { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 100%; 
                max-width: 100%;
                margin: 0;
                padding: 0;
                border: none !important;
            }
            .payslip-container::before { color: rgba(0,0,0,0.05) !important; } /* Watermark for print */
            .text-red, .text-green { color: #000 !important; } /* B&W forcing */
            .slip-row.grand-total { border-bottom: 4px double #000 !important; }
            .slip-section-title { color: #000 !important; border-bottom: 1px solid #000 !important; }
            .qr-placeholder { border: 2px solid #000 !important; }
        }
    </style>

    <div class="no-print" style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
        <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">STATUTORY PAYSLIP INSPECTOR</h1>
        <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">LABOR LAW COMPLIANCE & DOCUMENT RENDERER</p>
    </div>

    <div class="no-print" style="background: rgba(0, 229, 255, 0.05); border: 1px dashed rgba(0, 229, 255, 0.3); padding: 20px; border-radius: 4px; margin-bottom: 30px; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px;">
            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">1. Select Historical Ledger (Approved/Disbursed)</label>
            <select wire:model.live="selectedRunId" class="cyber-select">
                <option value="">-- SELECT IMMUTABLE PAYROLL BATCH --</option>
                @foreach($payrollRuns as $run)
                    <option value="{{ $run->id }}">[{{ $run->region_code }}] {{ $run->reference_number }} - {{ \Carbon\Carbon::parse($run->pay_period_end)->format('M Y') }}</option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 250px;">
            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">2. Select Personnel</label>
            <select wire:model="selectedEmployeeId" class="cyber-select" {{ empty($employeesInRun) ? 'disabled' : '' }}>
                <option value="">-- SELECT EMPLOYEE FROM BATCH --</option>
                @foreach($employeesInRun as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="inspectPayslip" class="btn-action" wire:loading.attr="disabled" {{ empty($employeesInRun) ? 'disabled' : '' }}>
            <span wire:loading.remove wire:target="inspectPayslip"><i data-lucide="search" style="width: 14px; height: 14px;"></i> RENDER DOCUMENT</span>
            <span wire:loading wire:target="inspectPayslip">RENDERING... <div class="spinner"></div></span>
        </button>
        
        @if($payslipData)
            <button class="btn-action" style="border-color: #8A9BB2; color: #8A9BB2; background: transparent;" onclick="window.print()">
                <i data-lucide="printer" style="width: 14px; height: 14px;"></i> PRINT / PDF
            </button>
        @endif
    </div>

    @if($payslipData)
        <div class="payslip-container" id="printable-payslip">
            <div class="payslip-content">
                <div class="slip-header">
                    <div>
                        <h2 class="slip-title">{{ $payslipData['employer_name'] }}</h2>
                        <div class="slip-subtitle">{{ $payslipData['employer_address'] }}</div>
                        <div class="slip-subtitle">Tax ID: {{ $payslipData['employer_tax_id'] }} | Employer Social ID: {{ $payslipData['employer_social_id'] }}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 1.2rem; color: #00E5FF; font-weight: bold;">PAYSLIP / BULLETIN DE PAIE</div>
                        <div class="slip-subtitle" style="margin-top: 8px;">Jurisdiction: {{ $payslipData['tax_region'] }}</div>
                        <div class="slip-subtitle">Period: {{ \Carbon\Carbon::parse($payslipData['pay_period_start'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($payslipData['pay_period_end'])->format('d M Y') }}</div>
                        <div class="slip-subtitle">Payment Date: {{ \Carbon\Carbon::parse($payslipData['payment_date'])->format('d M Y') }}</div>
                        <div class="slip-subtitle">Pay Frequency: {{ $payslipData['pay_frequency'] }}</div>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-block">
                        <div style="margin-bottom: 6px;"><span class="info-label">Name:</span> <span class="info-val">{{ $payslipData['employee_name'] }}</span></div>
                        <div style="margin-bottom: 6px;"><span class="info-label">Address:</span> <span class="info-val" style="color: #8A9BB2; font-weight: normal;">{{ $payslipData['employee_address'] }}</span></div>
                        <div style="margin-bottom: 6px;"><span class="info-label">Employee Code:</span> <span class="info-val">{{ $payslipData['employee_code'] }}</span></div>
                        <div><span class="info-label">Classification:</span> <span class="info-val">{{ $payslipData['job_classification'] }}</span></div>
                    </div>
                    <div class="info-block">
                        <div style="margin-bottom: 6px;"><span class="info-label">TIN / NIU:</span> <span class="info-val">{{ $payslipData['tin_number'] }}</span></div>
                        <div style="margin-bottom: 6px;"><span class="info-label">Social ID:</span> <span class="info-val">{{ $payslipData['social_id'] }}</span></div>
                        <div style="margin-bottom: 6px;"><span class="info-label">National ID:</span> <span class="info-val">{{ $payslipData['national_id'] }}</span></div>
                        <div><span class="info-label">Family Status:</span> <span class="info-val">{{ $payslipData['marital_status'] }} | Deps: {{ $payslipData['dependents'] }}</span></div>
                    </div>
                </div>

                <div class="slip-section-title">Earnings / Gains</div>
                <div class="slip-row">
                    <span>Basic Salary (Salaire de Base)</span>
                    <span>{{ number_format($payslipData['base_salary'], 2) }}</span>
                </div>
                @if($payslipData['allowance_housing'] > 0)
                <div class="slip-row">
                    <span>Housing Allowance (Indemnité de Logement)</span>
                    <span>{{ number_format($payslipData['allowance_housing'], 2) }}</span>
                </div>
                @endif
                @if($payslipData['allowance_transport'] > 0)
                <div class="slip-row">
                    <span>Transport Allowance (Indemnité de Transport)</span>
                    <span>{{ number_format($payslipData['allowance_transport'], 2) }}</span>
                </div>
                @endif
                @if($payslipData['overtime_pay'] > 0)
                <div class="slip-row">
                    <span>Overtime Premium (Heures Supplémentaires)</span>
                    <span>{{ number_format($payslipData['overtime_pay'], 2) }}</span>
                </div>
                @endif
                @if($payslipData['performance_bonus'] > 0)
                <div class="slip-row">
                    <span>Performance/Seniority Bonus (Prime)</span>
                    <span>{{ number_format($payslipData['performance_bonus'], 2) }}</span>
                </div>
                @endif
                @if($payslipData['benefits_in_kind'] > 0)
                <div class="slip-row" style="color: #FFBD2E;">
                    <span>Benefits In Kind - Taxable Notional (Avantages en Nature)</span>
                    <span>{{ number_format($payslipData['benefits_in_kind'], 2) }}</span>
                </div>
                @endif
                <div class="slip-row total">
                    <span>Total Gross Earnings (Salaire Brut)</span>
                    <span>{{ number_format($payslipData['gross_pay'], 2) }} {{ $payslipData['currency'] }}</span>
                </div>

                <div class="slip-section-title">Deductions / Retenues</div>
                <div class="slip-row">
                    <span>Income Tax (PAYE / IRPP)</span>
                    <span class="text-red">-{{ number_format($payslipData['tax_paye'], 2) }}</span>
                </div>
                @if($payslipData['deduction_pension'] > 0)
                <div class="slip-row">
                    <span>Pension / Social Security (Employee Share)</span>
                    <span class="text-red">-{{ number_format($payslipData['deduction_pension'], 2) }}</span>
                </div>
                @endif
                @if($payslipData['deduction_nhf'] > 0)
                <div class="slip-row">
                    <span>Housing Fund / CFC</span>
                    <span class="text-red">-{{ number_format($payslipData['deduction_nhf'], 2) }}</span>
                </div>
                @endif
                @if($payslipData['deduction_nhis'] > 0)
                <div class="slip-row">
                    <span>Health Insurance / NHIS</span>
                    <span class="text-red">-{{ number_format($payslipData['deduction_nhis'], 2) }}</span>
                </div>
                @endif
                @if($payslipData['deduction_other'] > 0)
                <div class="slip-row">
                    <span>Other Deductions (Absences / Communes)</span>
                    <span class="text-red">-{{ number_format($payslipData['deduction_other'], 2) }}</span>
                </div>
                @endif
                <div class="slip-row total">
                    <span>Total Deductions (Total des Retenues)</span>
                    <span class="text-red">-{{ number_format($payslipData['total_deductions'], 2) }} {{ $payslipData['currency'] }}</span>
                </div>

                <div class="slip-row grand-total">
                    <span>NET PAY (NET À PAYER)</span>
                    <span class="text-green">{{ number_format($payslipData['net_pay'], 2) }} {{ $payslipData['currency'] }}</span>
                </div>

                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; text-align: center; margin-top: 10px;">
                    Routing: {{ $payslipData['payment_method'] }} | {{ $payslipData['account_routing'] }}
                </div>

                <div class="ytd-grid">
                    <div class="ytd-box">
                        <span class="ytd-label">YTD Gross</span>
                        <span class="ytd-val">{{ number_format($payslipData['ytd_gross'], 2) }}</span>
                    </div>
                    <div class="ytd-box">
                        <span class="ytd-label">YTD Tax Paid</span>
                        <span class="ytd-val text-red">{{ number_format($payslipData['ytd_tax'], 2) }}</span>
                    </div>
                    <div class="ytd-box">
                        <span class="ytd-label">Annual Leave Bal.</span>
                        <span class="ytd-val" style="color: #00E5FF;">{{ number_format($payslipData['annual_leave_balance'], 1) }} Days</span>
                    </div>
                    <div class="ytd-box">
                        <span class="ytd-label">Sick Leave Taken</span>
                        <span class="ytd-val" style="color: #FFBD2E;">{{ number_format($payslipData['sick_leave_taken'], 1) }} Days</span>
                    </div>
                </div>

                <div class="auth-footer">
                    <div>
                        <div style="font-size: 0.6rem; color: #8A9BB2; text-transform: uppercase; margin-bottom: 5px;">Cryptographic Authentication Hash</div>
                        <div style="color: #00E5FF; font-size: 0.75rem;">{{ $payslipData['security_hash'] }}</div>
                        <div style="margin-top: 15px; font-size: 0.7rem; color: #FFF; border-top: 1px solid rgba(255,255,255,0.2); display: inline-block; padding-top: 5px; width: 200px; text-align: center;">
                            Authorized Signature / Stamp
                        </div>
                    </div>
                    <div class="qr-placeholder">
                        <div style="border: 2px dashed #0A1128; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #0A1128; font-size: 0.6rem; font-weight: bold;">QR HASH</div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div style="text-align: center; padding: 60px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border: 1px dashed rgba(255,255,255,0.1); border-radius: 4px;">
            <i data-lucide="file-text" style="width: 32px; height: 32px; opacity: 0.5; margin-bottom: 10px;"></i><br>
            > AWAITING HISTORICAL LEDGER AND PERSONNEL SELECTION
        </div>
    @endif

    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        });
    </script>
</div>