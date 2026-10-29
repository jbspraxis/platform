<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select {
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #FFFFFF; padding: 10px 12px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; border-radius: 2px; transition: 0.3s;
        }
        .cyber-input:focus, .cyber-select:focus { border-color: #00E5FF; outline: none; background: rgba(0, 229, 255, 0.05); }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        
        .btn-action { 
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px; 
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; 
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
        }
        .btn-action:hover:not(:disabled) { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-action:disabled { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.2); cursor: not-allowed; }
        
        .btn-warning { border-color: #FFBD2E; color: #FFBD2E; }
        .btn-warning:hover:not(:disabled) { background: #FFBD2E; color: #0A1128; box-shadow: 0 0 15px rgba(255, 189, 46, 0.4); }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .kpi-card { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 4px; border-top: 3px solid #00E5FF; box-shadow: inset 0 0 15px rgba(0,0,0,0.3); }
        .kpi-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .kpi-val { font-family: 'Roboto Mono', monospace; font-size: 1.4rem; font-weight: bold; color: #FFF; }

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: #0A1128; border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; max-width: 420px; width: 90%; }
        .spinner { border: 2px solid rgba(0, 229, 255, 0.1); border-top: 2px solid #00E5FF; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .table-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; }
        th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase; text-align: left; }
        td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Inter', sans-serif; font-size: 0.8rem; color: #FFF; }
        
        .badge { padding: 3px 6px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; font-weight: bold; }
        .badge-ot { background: rgba(255, 189, 46, 0.1); color: #FFBD2E; border: 1px solid #FFBD2E; }
        .badge-absent { background: rgba(255, 51, 102, 0.1); color: #FF3366; border: 1px solid #FF3366; }
        .badge-ok { background: rgba(0, 255, 102, 0.1); color: #00FF66; border: 1px solid #00FF66; }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px;">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">AGGREGATION COMPLETE</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 25px;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action" style="border-color: #00FF66; color: #00FF66; width: 100%;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">TIME & ATTENDANCE AGGREGATOR</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">PRE-PAYROLL EFFECTIVE PRESENCE ENGINE</p>
        </div>
    </div>

    <div style="background: rgba(0, 229, 255, 0.05); border: 1px dashed rgba(0, 229, 255, 0.3); padding: 20px; border-radius: 4px; margin-bottom: 30px; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; gap: 15px; align-items: flex-end;">
            <div>
                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">Target Month</label>
                <input type="number" min="1" max="12" wire:model.live="targetMonth" class="cyber-input" style="width: 100px;">
            </div>
            <div>
                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">Target Year</label>
                <input type="number" min="2020" wire:model.live="targetYear" class="cyber-input" style="width: 120px;">
            </div>
            <div>
                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">Jurisdiction Filter</label>
                <select wire:model.live="targetRegion" class="cyber-select" style="width: 180px;">
                    <option value="ALL">All Regions</option>
                    <option value="CM">Cameroon</option>
                    <option value="NG">Nigeria</option>
                    <option value="KE">Kenya</option>
                </select>
            </div>
        </div>
        
        <button wire:click="runAggregationEngine" class="btn-action btn-warning" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="runAggregationEngine"><i data-lucide="cpu" style="width: 14px; height: 14px;"></i> RUN AGGREGATION ENGINE</span>
            <span wire:loading wire:target="runAggregationEngine">CALCULATING... <div class="spinner" style="border-top-color: #FFBD2E;"></div></span>
        </button>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-label">Analyzed Personnel</span>
            <span class="kpi-val">{{ count($aggregatedData) }}</span>
        </div>
        <div class="kpi-card" style="border-top-color: #FFBD2E;">
            <span class="kpi-label">Total Overtime Hours</span>
            <span class="kpi-val" style="color: #FFBD2E;">
                {{ number_format(collect($aggregatedData)->sum(function($row) {
                    return ($row['overtime_hours_120'] ?? 0) + ($row['overtime_hours_130'] ?? 0) + ($row['overtime_hours_140'] ?? 0) + ($row['night_premium_hours'] ?? 0);
                }), 1) }}
            </span>
        </div>
        <div class="kpi-card" style="border-top-color: #FF3366;">
            <span class="kpi-label">Unexcused Absence (Days)</span>
            <span class="kpi-val" style="color: #FF3366;">{{ number_format(collect($aggregatedData)->sum('unexcused_absence_days'), 1) }}</span>
        </div>
    </div>

    <div class="table-panel">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Personnel</th>
                    <th>Jurisdiction</th>
                    <th style="text-align: center;">OT (1.2x)</th>
                    <th style="text-align: center;">OT (1.3x)</th>
                    <th style="text-align: center;">OT (1.4x)</th>
                    <th style="text-align: center;">Night Prem.</th>
                    <th style="text-align: right;">Unexcused Absences</th>
                    <th style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($aggregatedData as $row)
                    @php
                        $hasOt = (($row['overtime_hours_120'] ?? 0) > 0 || ($row['overtime_hours_130'] ?? 0) > 0 || ($row['overtime_hours_140'] ?? 0) > 0);
                        $hasAbsence = (($row['unexcused_absence_days'] ?? 0) > 0);
                    @endphp
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                        <td>
                            <div style="font-weight: bold; color: #FFF;">{{ $row['first_name'] ?? '' }} {{ $row['last_name'] ?? '' }}</div>
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 4px;">{{ $row['employee_code'] ?? 'N/A' }}</div>
                        </td>
                        <td><span style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #00E5FF;">{{ $row['tax_region'] ?? 'GLOBAL' }}</span></td>
                        
                        <td style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: {{ ($row['overtime_hours_120'] ?? 0) > 0 ? '#FFBD2E' : '#8A9BB2' }};">
                            {{ ($row['overtime_hours_120'] ?? 0) > 0 ? number_format($row['overtime_hours_120'], 1) . 'h' : '-' }}
                        </td>
                        <td style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: {{ ($row['overtime_hours_130'] ?? 0) > 0 ? '#FFBD2E' : '#8A9BB2' }};">
                            {{ ($row['overtime_hours_130'] ?? 0) > 0 ? number_format($row['overtime_hours_130'], 1) . 'h' : '-' }}
                        </td>
                        <td style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: {{ ($row['overtime_hours_140'] ?? 0) > 0 ? '#FFBD2E' : '#8A9BB2' }};">
                            {{ ($row['overtime_hours_140'] ?? 0) > 0 ? number_format($row['overtime_hours_140'], 1) . 'h' : '-' }}
                        </td>
                        <td style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: {{ ($row['night_premium_hours'] ?? 0) > 0 ? '#00E5FF' : '#8A9BB2' }};">
                            {{ ($row['night_premium_hours'] ?? 0) > 0 ? number_format($row['night_premium_hours'], 1) . 'h' : '-' }}
                        </td>
                        
                        <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; color: {{ $hasAbsence ? '#FF3366' : '#8A9BB2' }};">
                            {{ $hasAbsence ? number_format($row['unexcused_absence_days'], 1) . ' Days' : '-' }}
                        </td>
                        
                        <td style="text-align: center;">
                            @if($row['is_processed'] ?? false)
                                <span class="badge" style="background: rgba(138, 155, 178, 0.1); color: #8A9BB2; border: 1px solid #8A9BB2;">LOCKED IN PAYROLL</span>
                            @elseif($hasAbsence)
                                <span class="badge badge-absent">DEDUCTION PENDING</span>
                            @elseif($hasOt)
                                <span class="badge badge-ot">PREMIUMS PENDING</span>
                            @else
                                <span class="badge badge-ok">STANDARD</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                            > NO PAYROLL VARIABLES DETECTED FOR {{ str_pad($targetMonth, 2, '0', STR_PAD_LEFT) }}/{{ $targetYear }}. RUN ENGINE TO GENERATE.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        });
    </script>
</div>