<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select {
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #FFFFFF; padding: 10px 12px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; border-radius: 2px; transition: 0.3s;
        }
        .cyber-input:focus, .cyber-select:focus { border-color: #00E5FF; outline: none; background: rgba(0, 229, 255, 0.05); }
        .cyber-input:disabled { background: rgba(0,0,0,0.6); border-color: rgba(255,255,255,0.05); color: #8A9BB2; cursor: not-allowed; }
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

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: #0A1128; border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; max-width: 420px; width: 90%; }
        .spinner { border: 2px solid rgba(0, 229, 255, 0.1); border-top: 2px solid #00E5FF; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .table-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; margin-bottom: 20px; }
        th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase; text-align: left; }
        td { padding: 10px 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Inter', sans-serif; font-size: 0.8rem; color: #FFF; vertical-align: middle; }
        
        .badge { padding: 3px 6px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; font-weight: bold; }
        .badge-locked { background: rgba(138, 155, 178, 0.1); color: #8A9BB2; border: 1px solid #8A9BB2; }
        .badge-open { background: rgba(0, 229, 255, 0.1); color: #00E5FF; border: 1px solid #00E5FF; }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px;">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">VARIABLES SAVED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 25px;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action btn-success" style="width: 100%;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">VARIABLE COMPENSATION LEDGER</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">MONTHLY PRIMES, BONUSES & MANUAL DEDUCTIONS</p>
        </div>
    </div>

    @if(session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border-left: 2px solid #FF3366; padding: 10px; margin-bottom: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FF3366;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

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
        
        <button wire:click="autoCalculateAnciennete" class="btn-action btn-warning" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="autoCalculateAnciennete"><i data-lucide="calculator" style="width: 14px; height: 14px;"></i> AUTO-CALCULATE ANCIENNETÉ</span>
            <span wire:loading wire:target="autoCalculateAnciennete">CALCULATING... <div class="spinner" style="border-top-color: #FFBD2E;"></div></span>
        </button>
    </div>

    <form wire:submit.prevent="saveVariables">
        <div class="table-panel">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Personnel</th>
                        <th style="width: 150px;">Prime d'Ancienneté</th>
                        <th style="width: 150px;">Performance Bonus</th>
                        <th style="width: 150px;">13th Month Pay</th>
                        <th style="width: 150px;">Other Deductions</th>
                        <th style="text-align: center; width: 120px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeeVariables as $empId => $data)
                        <tr style="transition: background 0.2s; {{ $data['is_processed'] ? 'opacity: 0.6;' : '' }}" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                            <td>
                                <div style="font-weight: bold; color: #FFF;">{{ $data['name'] }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 4px;">
                                    {{ $data['code'] }} | {{ $data['region'] }} | Hired: {{ $data['hire_date'] ? \Carbon\Carbon::parse($data['hire_date'])->format('M Y') : 'N/A' }}
                                </div>
                            </td>
                            <td>
                                <input type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.prime_anciennete" class="cyber-input" {{ $data['is_processed'] ? 'disabled' : '' }}>
                            </td>
                            <td>
                                <input type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.prime_performance" class="cyber-input" style="color: #00FF66;" {{ $data['is_processed'] ? 'disabled' : '' }}>
                            </td>
                            <td>
                                <input type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.thirteenth_month_bonus" class="cyber-input" {{ $data['is_processed'] ? 'disabled' : '' }}>
                            </td>
                            <td>
                                <input type="number" step="0.01" wire:model="employeeVariables.{{ $empId }}.other_deductions" class="cyber-input" style="color: #FF3366;" {{ $data['is_processed'] ? 'disabled' : '' }}>
                            </td>
                            <td style="text-align: center;">
                                @if($data['is_processed'])
                                    <span class="badge badge-locked"><i data-lucide="lock" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle; margin-right: 2px;"></i> LOCKED</span>
                                @else
                                    <span class="badge badge-open">OPEN</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                > NO ACTIVE PERSONNEL FOUND FOR THIS JURISDICTION.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: flex-end; padding-top: 10px;">
            <button type="submit" class="btn-action btn-success" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveVariables"><i data-lucide="save" style="width: 14px; height: 14px;"></i> SAVE VARIABLES TO LEDGER</span>
                <span wire:loading wire:target="saveVariables">SAVING... <div class="spinner"></div></span>
            </button>
        </div>
    </form>

    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        });
    </script>
</div>