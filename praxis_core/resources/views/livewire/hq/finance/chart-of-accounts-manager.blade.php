<div>
    <style>
        /* --- GLOBAL JBS PRAXIS STYLES --- */
        .cyber-input, .cyber-select {
            width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(0, 229, 255, 0.2);
            color: #FFFFFF; padding: 12px 15px; font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem; border-radius: 2px; transition: 0.3s; outline: none;
        }
        .cyber-input:focus, .cyber-select:focus { 
            border-color: #00E5FF; background: rgba(0, 229, 255, 0.05); box-shadow: inset 2px 0 0 #00E5FF; 
        }
        .cyber-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
        }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        .cyber-select:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .btn-action {
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px;
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer;
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }

        .btn-halt { border-color: #FF3366; color: #FF3366; }
        .btn-halt:hover { background: #FF3366; color: #FFF; box-shadow: 0 0 15px rgba(255, 51, 102, 0.4); }

        /* --- MODAL OVERLAY --- */
        .glass-modal {
            position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
        }
        .modal-box {
            background: rgba(10, 17, 40, 0.95); border: 1px solid rgba(0, 229, 255, 0.3); border-top: 4px solid #00E5FF;
            padding: 30px; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); width: 100%; max-width: 500px;
        }

        /* --- LEDGER TABLE --- */
        .ledger-panel {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 4px; 
            margin-bottom: 30px; overflow: hidden; box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
        }
        .ledger-header {
            background: rgba(0, 0, 0, 0.4); padding: 15px 25px; border-bottom: 1px solid rgba(0, 229, 255, 0.2);
            font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1rem; color: #FFF; letter-spacing: 2px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .ledger-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ledger-table th {
            font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;
            padding: 12px 25px; border-bottom: 1px dashed rgba(255, 255, 255, 0.1); text-transform: uppercase;
        }
        .ledger-table td { padding: 15px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.02); vertical-align: middle; }
        .ledger-table tr:hover { background: rgba(0, 229, 255, 0.02); }

        .master-row td { background: rgba(0, 0, 0, 0.2); font-weight: bold; }
        .sub-row td { font-size: 0.85rem; }
        .sub-indicator { 
            display: inline-block; width: 15px; border-bottom: 1px solid #8A9BB2; border-left: 1px solid #8A9BB2; 
            height: 15px; margin-right: 12px; margin-bottom: 4px; opacity: 0.6; 
        }
        
        /* Badges */
        .status-badge { padding: 4px 10px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; border-radius: 2px; text-transform: uppercase; border: 1px solid; }
        .badge-live { color: #00FF66; border-color: #00FF66; background: rgba(0, 255, 102, 0.1); }
        .badge-halt { color: #FF3366; border-color: #FF3366; background: rgba(255, 51, 102, 0.1); }
        .badge-locked { color: #FFBD2E; border-color: #FFBD2E; background: rgba(255, 189, 46, 0.1); }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; padding-bottom: 15px;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.8rem; font-weight: 900; letter-spacing: 1px; color: #FFF; margin: 0;">CHART OF ACCOUNTS</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; margin: 5px 0 0 0;">> Master Double-Entry Ledger Configuration Matrix</p>
        </div>
        @can('finance.create')
        <button wire:click="$set('showModal', true)" class="btn-action" style="background: rgba(0, 229, 255, 0.1);">
            <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i> PROVISION SUB-LEDGER
        </button>
        @endcan
    </div>

    @if(session()->has('success'))
        <div style="background: rgba(0, 255, 102, 0.1); border: 1px solid #00FF66; color: #00FF66; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; border-radius: 2px; text-align: center;">
            {{ session('success') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div style="background: rgba(255, 51, 102, 0.1); border: 1px solid #FF3366; color: #FF3366; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; border-radius: 2px; text-align: center;">
            {{ session('error') }}
        </div>
    @endif

    @foreach(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'] as $type)
        @if(isset($accounts[$type]) && count($accounts[$type]) > 0)
            <div class="ledger-panel">
                <div class="ledger-header">
                    <span><i data-lucide="layers" style="width: 18px; height: 18px; display: inline-block; vertical-align: text-bottom; margin-right: 8px; color: #00E5FF;"></i> {{ strtoupper($type) }}S LEDGER</span>
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">{{ count($accounts[$type]) }} NODES</span>
                </div>
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th style="width: 160px;">Account Code</th>
                            <th>Description / Nomenclature</th>
                            <th style="text-align: center;">Sys. Status</th>
                            <th style="text-align: right;">Protocol Override</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accounts[$type] as $acc)
                            <tr class="{{ is_null($acc->parent_account_id) ? 'master-row' : 'sub-row' }}">
                                
                                <td style="font-family: 'Roboto Mono', monospace; color: #00E5FF;">
                                    @if(is_null($acc->parent_account_id))
                                        <strong style="font-size: 1rem;">{{ $acc->account_code }}</strong>
                                    @else
                                        <span style="padding-left: 25px; opacity: 0.8;">{{ $acc->account_code }}</span>
                                    @endif
                                </td>
                                
                                <td style="color: #FFF;">
                                    @if(!is_null($acc->parent_account_id))
                                        <span class="sub-indicator"></span>
                                    @endif
                                    {{ $acc->account_name }}
                                    
                                    @if($acc->client_id)
                                        <span style="display: inline-block; margin-left: 10px; padding: 2px 6px; background: rgba(0, 229, 255, 0.1); border: 1px solid rgba(0, 229, 255, 0.3); font-family: 'Roboto Mono', monospace; font-size: 0.55rem; color: #00E5FF; border-radius: 2px;">
                                            [CLIENT NODE]
                                        </span>
                                    @endif
                                </td>
                                
                                <td style="text-align: center;">
                                    @if($acc->is_active)
                                        <span class="status-badge badge-live">LIVE</span>
                                    @else
                                        <span class="status-badge badge-halt">HALTED</span>
                                    @endif
                                    
                                    @if($acc->is_system_locked)
                                        <span class="status-badge badge-locked" style="margin-left: 5px;" title="Immutable System Account">
                                            <i data-lucide="lock" style="width: 10px; height: 10px; vertical-align: middle;"></i>
                                        </span>
                                    @endif
                                </td>
                                
                                <td style="text-align: right;">
                                    @if(!$acc->is_system_locked)
                                        @can('finance.edit')
                                        <button wire:click="toggleStatus({{ $acc->id }})" class="btn-action {{ $acc->is_active ? 'btn-halt' : '' }}" style="padding: 6px 12px; font-size: 0.65rem;">
                                            <i data-lucide="power" style="width: 12px; height: 12px;"></i>
                                            {{ $acc->is_active ? 'SUSPEND' : 'ACTIVATE' }}
                                        </button>
                                        @endcan
                                    @else
                                        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #5a6b82;">[ SYSTEM LOCKED ]</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    @if($showModal)
        <div class="glass-modal">
            <div class="modal-box">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #00E5FF; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <span>> PROVISION NEW SUB-LEDGER</span>
                    <button wire:click="$set('showModal', false)" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer;"><i data-lucide="x" style="width: 18px; height: 18px;"></i></button>
                </div>

                <form wire:submit.prevent="saveAccount" style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">MASTER PARENT ACCOUNT</label>
                        <select wire:model.live="parent_account_id" class="cyber-select">
                            <option value="">-- ROOT LEVEL (System Admin Only) --</option>
                            @foreach($parentAccounts as $parent)
                                <option value="{{ $parent->id }}">[{{ $parent->account_type }}] {{ $parent->account_code }} - {{ $parent->account_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">ACCOUNT CLASSIFICATION (Auto-inherited if Sub-Ledger)</label>
                        <select wire:model="account_type" class="cyber-select" {{ !empty($parent_account_id) ? 'disabled' : '' }}>
                            <option value="Asset">Asset (1000)</option>
                            <option value="Liability">Liability (2000)</option>
                            <option value="Equity">Equity (3000)</option>
                            <option value="Revenue">Revenue (4000)</option>
                            <option value="Expense">Expense (5000+)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">SUB-LEDGER NOMENCLATURE</label>
                        <input type="text" wire:model="account_name" class="cyber-input" placeholder="e.g. Access Bank NG, Client Retainer..." required>
                        @error('account_name') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; display: block; margin-top: 5px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-action" style="flex: 1; justify-content: center; border-color: #8A9BB2; color: #8A9BB2;">ABORT</button>
                        <button type="submit" class="btn-action" style="flex: 2; justify-content: center; background: #00E5FF; color: #0A1128;" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveAccount">COMPILE LEDGER NODE</span>
                            <span wire:loading wire:target="saveAccount">ENCRYPTING...</span>
                        </button>
                    </div>
                </form>
            </div>
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