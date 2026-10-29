<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #FFFFFF; padding: 10px 12px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; border-radius: 2px; transition: 0.3s;
        }
        .cyber-input:focus, .cyber-select:focus { 
            border-color: #00E5FF; outline: none; background: rgba(0, 229, 255, 0.05); 
        }
        
        .btn-action { 
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px; 
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; 
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
        }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-success { border-color: #00FF66; color: #00FF66; }
        .btn-success:hover { background: #00FF66; color: #0A1128; box-shadow: 0 0 15px rgba(0, 255, 102, 0.4); }
        .btn-danger { border-color: #FF3366; color: #FF3366; padding: 5px 10px; font-size: 0.7rem; }
        .btn-danger:hover { background: #FF3366; color: #FFF; box-shadow: 0 0 15px rgba(255, 51, 102, 0.4); }

        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid transparent; background: transparent; color: #8A9BB2; }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: #00E5FF; color: #00E5FF; font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: #FFF; }

        .section-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 25px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); margin-bottom: 20px;}
        .section-header { font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: bold; color: #00E5FF; margin-bottom: 20px; border-bottom: 1px dashed rgba(0, 229, 255, 0.3); padding-bottom: 10px; display: flex; align-items: center; gap: 8px;}
        
        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: #0A1128; border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 420px; width: 90%; }
        .spinner { border: 2px solid rgba(0, 229, 255, 0.1); border-top: 2px solid #00E5FF; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-label { display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px; }
        .helper-text { font-family: 'Roboto Mono', monospace; font-size: 0.55rem; color: #FFBD2E; margin-top: 4px; }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(0,255,102,0.4);">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">LEDGER UPDATED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 25px; line-height: 1.5;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action btn-success" style="width: 100%;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">TAX & COMPLIANCE CONFIGURATOR</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">GLOBAL STATUTORY PARAMETERS MASTER</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="setRegion('NG')" class="nav-pill {{ $activeRegion === 'NG' ? 'active' : '' }}">NIGERIA (PITA)</button>
            <button wire:click="setRegion('CM')" class="nav-pill {{ $activeRegion === 'CM' ? 'active' : '' }}">CAMEROON (CGI)</button>
            <button wire:click="setRegion('KE')" class="nav-pill {{ $activeRegion === 'KE' ? 'active' : '' }}">KENYA (KRA)</button>
            <button wire:click="setRegion('GLOBAL')" class="nav-pill {{ $activeRegion === 'GLOBAL' ? 'active' : '' }}">GLOBAL (FLAT)</button>
        </div>
    </div>

    @if(session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border-left: 2px solid #FF3366; padding: 10px; margin-bottom: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FF3366;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
        
        <div>
            <form wire:submit.prevent="saveConfiguration">
                <div class="section-panel">
                    <div class="section-header">
                        <i data-lucide="sliders" style="width: 16px; height: 16px;"></i> FLAT RATES & EXEMPTIONS [{{ $activeRegion }}]
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Statutory Minimum Wage</label>
                            <input type="number" step="0.01" wire:model="config.minimum_wage" class="cyber-input" placeholder="e.g. 60000">
                        </div>
                    </div>

                    <div style="border-top: 1px dashed rgba(255,255,255,0.1); margin: 15px 0;"></div>
                    <div style="font-family: 'Montserrat', sans-serif; font-size: 0.7rem; color: #FFBD2E; margin-bottom: 10px; font-weight: bold;">SOCIAL & PENSION FUNDS</div>

                    <div class="form-grid">
                        <div>
                            <label class="form-label">Pension (Employee Rate %)</label>
                            <input type="number" step="0.0001" wire:model="config.pension_employee_rate" class="cyber-input" placeholder="e.g. 0.0800 for 8%">
                        </div>
                        <div>
                            <label class="form-label">Pension (Employer Rate %)</label>
                            <input type="number" step="0.0001" wire:model="config.pension_employer_rate" class="cyber-input" placeholder="e.g. 0.1000 for 10%">
                        </div>
                        <div>
                            <label class="form-label">Health/NHIS (Employee Rate %)</label>
                            <input type="number" step="0.0001" wire:model="config.health_employee_rate" class="cyber-input">
                        </div>
                        <div>
                            <label class="form-label">Housing Fund (Employee Rate %)</label>
                            <input type="number" step="0.0001" wire:model="config.housing_fund_rate" class="cyber-input">
                        </div>
                        <div style="grid-column: span 2;">
                            <label class="form-label">Pension / CNPS Cap Amount</label>
                            <input type="number" step="0.01" wire:model="config.pension_cap_amount" class="cyber-input" placeholder="e.g. 750000 (Leave blank if none)">
                            <div class="helper-text">Maximum monthly gross subject to pension deductions (e.g. Cameroon CNPS).</div>
                        </div>
                    </div>

                    <div style="border-top: 1px dashed rgba(255,255,255,0.1); margin: 15px 0;"></div>
                    <div style="font-family: 'Montserrat', sans-serif; font-size: 0.7rem; color: #00FF66; margin-bottom: 10px; font-weight: bold;">TAX RELIEFS & ABATEMENTS</div>

                    <div class="form-grid">
                        <div>
                            <label class="form-label">Fixed Abatement (Monthly)</label>
                            <input type="number" step="0.01" wire:model="config.fixed_abatement" class="cyber-input" placeholder="e.g. 41666.67">
                        </div>
                        <div>
                            <label class="form-label">Proportional Abatement Rate</label>
                            <input type="number" step="0.0001" wire:model="config.proportional_abatement_rate" class="cyber-input" placeholder="e.g. 0.3000 for 30%">
                        </div>
                        <div>
                            <label class="form-label">CRA Fixed Amount (Annualized)</label>
                            <input type="number" step="0.01" wire:model="config.cra_fixed_amount" class="cyber-input" placeholder="e.g. 200000">
                        </div>
                        <div>
                            <label class="form-label">CRA Proportional Rate</label>
                            <input type="number" step="0.0001" wire:model="config.cra_proportional_rate" class="cyber-input" placeholder="e.g. 0.2000 for 20%">
                        </div>
                        <div style="grid-column: span 2;">
                            <label class="form-label">Minimum Statutory Tax Rate</label>
                            <input type="number" step="0.0001" wire:model="config.minimum_tax_rate" class="cyber-input" placeholder="e.g. 0.0100 for 1%">
                        </div>
                    </div>

                    <button type="submit" class="btn-action btn-success" style="width: 100%; margin-top: 10px;" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveConfiguration"><i data-lucide="save" style="width: 16px; height: 16px;"></i> SAVE CONFIGURATION</span>
                        <span wire:loading wire:target="saveConfiguration">PROCESSING... <div class="spinner"></div></span>
                    </button>
                </div>
            </form>
        </div>

        <div>
            <div class="section-panel">
                <div class="section-header">
                    <i data-lucide="layers" style="width: 16px; height: 16px;"></i> PROGRESSIVE TAX BANDS [{{ $activeRegion }}]
                </div>

                <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05); border-radius: 4px; overflow-x: auto; margin-bottom: 20px;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr>
                                <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">Band Name</th>
                                <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">Range Limit</th>
                                <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">Tax Rate</th>
                                <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center;">Act</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bands as $band)
                                <tr>
                                    <td style="padding: 12px; font-family: 'Inter', sans-serif; font-size: 0.75rem; color: #FFF; border-bottom: 1px solid rgba(255,255,255,0.02);">{{ $band->band_name }}</td>
                                    <td style="padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #00E5FF; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                        {{ number_format($band->lower_limit, 0) }} - {{ $band->upper_limit ? number_format($band->upper_limit, 0) : 'INFINITY' }}
                                    </td>
                                    <td style="padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #00FF66; border-bottom: 1px solid rgba(255,255,255,0.02);">{{ ($band->tax_rate * 100) }}%</td>
                                    <td style="padding: 12px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                        <button wire:click="removeTaxBand('{{ $band->id }}')" class="btn-danger" style="background: transparent; border: 1px solid #FF3366; border-radius: 2px; cursor: pointer;"><i data-lucide="trash-2" style="width: 12px; height: 12px;"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2;">No tax bands defined. System will use flat global rate.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 15px;">
                    <div style="font-family: 'Montserrat', sans-serif; font-size: 0.7rem; color: #FFF; margin-bottom: 10px; font-weight: bold;">ADD NEW BAND</div>
                    <form wire:submit.prevent="addTaxBand">
                        <div class="form-grid">
                            <div style="grid-column: span 2;">
                                <label class="form-label">Band Name / Description</label>
                                <input type="text" wire:model="newBandName" required class="cyber-input" placeholder="e.g. First 300,000 NGN">
                                @error('newBandName') <span style="color: #FF3366; font-size: 0.65rem; font-family: 'Roboto Mono', monospace;">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Lower Limit (Start)</label>
                                <input type="number" step="0.01" wire:model="newBandLower" required class="cyber-input">
                                @error('newBandLower') <span style="color: #FF3366; font-size: 0.65rem; font-family: 'Roboto Mono', monospace;">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Upper Limit (End)</label>
                                <input type="number" step="0.01" wire:model="newBandUpper" class="cyber-input" placeholder="Leave blank for infinity">
                                @error('newBandUpper') <span style="color: #FF3366; font-size: 0.65rem; font-family: 'Roboto Mono', monospace;">{{ $message }}</span> @enderror
                            </div>
                            <div style="grid-column: span 2;">
                                <label class="form-label">Tax Rate (Decimal)</label>
                                <input type="number" step="0.0001" wire:model="newBandRate" required class="cyber-input" placeholder="e.g. 0.0700 for 7%">
                                @error('newBandRate') <span style="color: #FF3366; font-size: 0.65rem; font-family: 'Roboto Mono', monospace;">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn-action" style="width: 100%;" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="addTaxBand"><i data-lucide="plus" style="width: 16px; height: 16px;"></i> APPEND BAND TO MATRIX</span>
                            <span wire:loading wire:target="addTaxBand">APPENDING... <div class="spinner"></div></span>
                        </button>
                    </form>
                </div>

            </div>
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