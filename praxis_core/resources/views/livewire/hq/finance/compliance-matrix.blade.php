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
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px; 
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; 
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
        }
        .btn-action:hover:not(:disabled) { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-action:disabled { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.2); cursor: not-allowed; }
        
        .btn-success { border-color: #00FF66; color: #00FF66; }
        .btn-success:hover:not(:disabled) { background: #00FF66; color: #0A1128; box-shadow: 0 0 15px rgba(0, 255, 102, 0.4); }
        
        .btn-warning { border-color: #FFBD2E; color: #FFBD2E; }
        .btn-warning:hover:not(:disabled) { background: #FFBD2E; color: #0A1128; box-shadow: 0 0 15px rgba(255, 189, 46, 0.4); }

        /* KPI GRID */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .kpi-card { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 4px; border-top: 3px solid #00E5FF; box-shadow: inset 0 0 15px rgba(0,0,0,0.3); }
        .kpi-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .kpi-val { font-family: 'Roboto Mono', monospace; font-size: 1.4rem; font-weight: bold; color: #FFF; }

        .text-red { color: #FF3366 !important; }
        .text-green { color: #00FF66 !important; }
        
        .table-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; margin-bottom: 30px; }
        th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase; text-align: left; }
        td { padding: 12px 20px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #FFF; }
        
        .alert-box { background: rgba(0, 255, 102, 0.05); border-left: 2px solid #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #00FF66; margin-bottom: 20px; }
        .alert-warning { background: rgba(255, 189, 46, 0.05); border-color: #FFBD2E; color: #FFBD2E; }

        .spinner { border: 2px solid rgba(0, 229, 255, 0.1); border-top: 2px solid #00E5FF; border-radius: 50%; width: 14px; height: 14px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
        <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">COMPLIANCE & LIABILITY MATRIX</h1>
        <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">OHADA, PITA & KRA AGGREGATED REPORTING</p>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; gap: 20px; flex-grow: 1;">
            <div style="min-width: 300px;">
                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">DATA SOURCE TIMELINE</label>
                <select wire:model.live="matrix_run_filter" class="cyber-select" style="border-color: {{ $matrix_run_filter === 'live' ? '#FFBD2E' : '#00FF66' }};">
                    <option value="live">Live Forecast (Current Active Configuration)</option>
                    <optgroup label="Historical Batches (Immutable Actuals)">
                        @foreach($payrollRuns as $run)
                            <option value="{{ $run->id }}">{{ $run->reference_number }} ({{ \Carbon\Carbon::parse($run->pay_period_end)->format('M Y') }}) - {{ $run->region_code }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            
            @if($matrix_run_filter === 'live')
            <div style="min-width: 200px;">
                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">FILTER JURISDICTION</label>
                <select wire:model.live="matrix_region_filter" class="cyber-select">
                    <option value="ALL">ALL REGIONS</option>
                    <option value="CM">CAMEROON (OHADA)</option>
                    <option value="NG">NIGERIA (PITA)</option>
                    <option value="KE">KENYA (KRA)</option>
                </select>
            </div>
            @endif
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button wire:click="exportStatutoryRemittance" class="btn-action btn-warning" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="exportStatutoryRemittance"><i data-lucide="landmark" style="width: 14px; height: 14px;"></i> TAX/PENSION CSV</span>
                <span wire:loading wire:target="exportStatutoryRemittance">EXPORTING... <div class="spinner" style="border-top-color:#FFBD2E;"></div></span>
            </button>
            <button wire:click="exportBankRemittance" class="btn-action btn-success" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="exportBankRemittance"><i data-lucide="download" style="width: 14px; height: 14px;"></i> NET REMITTANCE CSV</span>
                <span wire:loading wire:target="exportBankRemittance">EXPORTING... <div class="spinner" style="border-top-color:#00FF66;"></div></span>
            </button>
        </div>
    </div>

    @if($matrix_run_filter === 'live')
        <div class="alert-box alert-warning">
            > VIEWING LIVE FORECAST: Data is dynamically calculated using active employee profiles and current tax laws. Not for financial remittance.
        </div>
    @else
        <div class="alert-box">
            > VIEWING HISTORICAL ACTUALS: Data is locked and pulled exclusively from immutable payroll ledger lines. Safe for audit and compliance checks.
        </div>
    @endif

    <div wire:loading wire:target="matrix_run_filter, matrix_region_filter" style="width: 100%; text-align: center; padding: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #00E5FF;">
        RECALCULATING MATRIX <div class="spinner" style="margin-left: 10px;"></div>
    </div>

    <div wire:loading.remove wire:target="matrix_run_filter, matrix_region_filter">
        <div class="kpi-grid">
            <div class="kpi-card">
                <span class="kpi-label">Active Headcount</span>
                <span class="kpi-val">{{ $matrix_kpis['headcount'] ?? 0 }}</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Total Gross Payroll</span>
                <span class="kpi-val">{{ number_format($matrix_kpis['total_gross'] ?? 0, 2) }}</span>
            </div>
            <div class="kpi-card" style="border-top-color: #00FF66;">
                <span class="kpi-label">Total Net Outflow</span>
                <span class="kpi-val text-green">{{ number_format($matrix_kpis['total_net'] ?? 0, 2) }}</span>
            </div>
            <div class="kpi-card" style="border-top-color: #FF3366;">
                <span class="kpi-label">PAYE / IRPP Liability</span>
                <span class="kpi-val text-red">{{ number_format($matrix_kpis['total_paye_irpp'] ?? 0, 2) }}</span>
            </div>
            <div class="kpi-card" style="border-top-color: #FFBD2E;">
                <span class="kpi-label">Pension / CNPS (Combined)</span>
                <span class="kpi-val" style="color: #FFBD2E;">{{ number_format(($matrix_kpis['total_pension_cnps_employee'] ?? 0) + ($matrix_kpis['total_pension_cnps_employer'] ?? 0), 2) }}</span>
            </div>
            <div class="kpi-card" style="border-top-color: #FF3366;">
                <span class="kpi-label">Other Tax Liabilities</span>
                <span class="kpi-val text-red">{{ number_format($matrix_kpis['total_other_taxes'] ?? 0, 2) }}</span>
            </div>
        </div>

        <div class="table-panel">
            <div style="padding: 15px 20px; border-bottom: 1px dashed rgba(255,255,255,0.1); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #00E5FF; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="landmark" style="width: 16px; height: 16px;"></i> BANK ROUTING DIRECTORY (NET PAYS)
            </div>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Bank Name</th>
                        <th>Account No.</th>
                        <th style="text-align: right;">Net Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($remittance_list as $row)
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                            <td style="font-weight: bold;">{{ $row['name'] }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #E0E6ED;">{{ $row['bank'] }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #00E5FF;">{{ $row['account'] }}</td>
                            <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; color: #00FF66;">
                                {{ number_format($row['amount'], 2) }} <span style="font-size: 0.65rem; color: #8A9BB2;">{{ $row['currency'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                > NO VALID BANK TRANSFER RECORDS FOUND FOR CURRENT FILTER.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-panel">
            <div style="padding: 15px 20px; border-bottom: 1px dashed rgba(255,255,255,0.1); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #FF3366; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="shield-alert" style="width: 16px; height: 16px;"></i> STATUTORY REMITTANCE (TAX & PENSION)
            </div>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Tax ID (TIN)</th>
                        <th>Social ID (CNPS/RSA)</th>
                        <th style="text-align: right;">Income Tax</th>
                        <th style="text-align: right;">Total Pension (Emp+Corp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($statutory_remittance_list as $row)
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(255, 51, 102, 0.05)'" onmouseout="this.style.background='transparent'">
                            <td style="font-weight: bold;">{{ $row['name'] }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #E0E6ED;">{{ $row['tin'] }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #E0E6ED;">{{ $row['social_id'] }}</td>
                            <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; color: #FF3366;">
                                {{ number_format($row['tax_amount'], 2) }} <span style="font-size: 0.65rem; color: #8A9BB2;">{{ $row['currency'] }}</span>
                            </td>
                            <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; color: #FFBD2E;">
                                {{ number_format($row['pension_employee'] + $row['pension_employer'], 2) }} <span style="font-size: 0.65rem; color: #8A9BB2;">{{ $row['currency'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                > NO STATUTORY LIABILITIES FOUND FOR CURRENT FILTER.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        });
    </script>
</div>