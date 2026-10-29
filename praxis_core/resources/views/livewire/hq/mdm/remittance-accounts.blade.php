<div>
    <style>
        /* Shared Sub-Nav (Seamless Continuity) */
        .ws-tab {
            font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); 
            padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;
        }
        .ws-tab:hover { color: #FFF; }
        .ws-tab.active {
            color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); 
            border: 1px solid var(--erp-teal); border-radius: 4px;
        }

        /* Form Inputs */
        .cyber-input, .cyber-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 12px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border-radius: 2px; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus { border-color: var(--erp-teal); outline: none; background: rgba(0,0,0,0.5); }
        .cyber-select option { background-color: var(--erp-bg-main); color: var(--erp-white); padding: 10px; }

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: var(--erp-bg-main); border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 450px; width: 90%; }
        
        /* Bloomberg Data Grid */
        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02); position: sticky; top: 0; z-index: 10; }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: top; }
        .bloomberg-table tr:hover { background: rgba(0, 229, 255, 0.03); }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(0,255,102,0.4);">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">LEDGER UPDATED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 25px; line-height: 1.5;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" style="background: #00FF66; color: #000; border: none; padding: 12px 30px; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; border-radius: 2px; cursor: pointer; width: 100%;">ACKNOWLEDGE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: var(--erp-white); letter-spacing: 1px;">TREASURY ACCOUNTS</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">FIRM REMITTANCE LEDGERS & WALLET INFRASTRUCTURE</p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        @php
            $baseStyle = "font-family: 'Roboto Mono', monospace; font-size: 0.85rem; padding: 5px 15px; text-decoration: none; transition: 0.3s;";
            $activeStyle = "color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px;";
            $inactiveStyle = "color: var(--erp-muted); border: 1px solid transparent;";
        @endphp

        <a href="{{ route('hq.mdm.dictionary') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.dictionary') ? $activeStyle : $inactiveStyle }}">[ FINANCIAL_DICTIONARY ]</a>
        <a href="{{ route('hq.mdm.copq') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.copq') ? $activeStyle : $inactiveStyle }}">[ COPQ_MATRICES ]</a>
        <a href="{{ route('hq.mdm.tax') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.tax') ? $activeStyle : $inactiveStyle }}">[ TAX_&_COMPLIANCE ]</a>
        <a href="{{ route('hq.mdm.remittance') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.remittance') ? $activeStyle : $inactiveStyle }}">[ TREASURY_ACCOUNTS ]</a>
    </div>
    </div>

    <div style="display: grid; grid-template-columns: 360px 1fr; gap: 30px; align-items: start;">
        
        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">
                > {{ $editMode ? 'UPDATE' : 'REGISTER' }} TREASURY NODE
            </div>

            <form wire:submit.prevent="saveAccount" style="display: flex; flex-direction: column; gap: 15px;">
                
                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">INFRASTRUCTURE TYPE</label>
                    <select wire:model.live="type" class="cyber-select">
                        <option value="Bank">Bank (Fiat Ledger)</option>
                        <option value="Mobile Money">Mobile Money (MoMo)</option>
                        <option value="Crypto">Crypto (Web3 Wallet)</option>
                        <option value="Cash">Cash (Physical Vault)</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">
                        {{ $type === 'Crypto' ? 'EXCHANGE / PROTOCOL' : 'PROVIDER / INSTITUTION NAME' }}
                    </label>
                    <input type="text" wire:model="provider_name" required placeholder="{{ $type === 'Crypto' ? 'e.g. Binance, TrustWallet' : 'e.g. Chase Bank, MTN' }}" class="cyber-input">
                </div>

                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">BENEFICIARY / ACCOUNT NAME</label>
                    <input type="text" wire:model="account_name" required placeholder="JBS Praxis LLC" class="cyber-input">
                </div>

                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">
                        {{ $type === 'Crypto' ? 'PUBLIC WALLET ADDRESS' : 'ACCOUNT NUMBER / IBAN' }}
                    </label>
                    <input type="text" wire:model="account_reference" required class="cyber-input" style="color: var(--erp-teal); font-weight: bold;">
                    @error('account_reference') <span style="color: var(--erp-red); font-size: 0.7rem; font-family: 'Roboto Mono', monospace; margin-top: 5px; display: block;">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">
                        {{ $type === 'Crypto' ? 'NETWORK (e.g. TRC20, ERC20)' : ($type === 'Bank' ? 'SWIFT / ROUTING NUMBER' : 'COUNTRY CODE / SHORTCODE') }}
                    </label>
                    <input type="text" wire:model="routing_code" placeholder="Optional" class="cyber-input">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" style="flex: 2; background: var(--erp-teal); border: 1px solid var(--erp-teal); color: #0A1128; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 12px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.boxShadow='0 0 15px rgba(0,229,255,0.4)'" onmouseout="this.style.boxShadow='none'">
                        {{ $editMode ? 'OVERWRITE NODE' : 'REGISTER TO TREASURY' }}
                    </button>
                    @if($editMode)
                        <button type="button" wire:click="resetForm" style="flex: 1; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; padding: 12px; border-radius: 2px; cursor: pointer;">CANCEL</button>
                    @endif
                </div>
            </form>
        </div>

        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; max-height: 65vh; overflow-y: auto;">
            
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th style="min-width: 180px;">Provider & Type</th>
                        <th style="min-width: 220px;">Account Reference & Beneficiary</th>
                        <th style="min-width: 120px;">Ledger Mapping</th>
                        <th style="text-align: right; min-width: 100px;">Status</th>
                        <th style="text-align: center; width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                    <tr>
                        <td>
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-white);">{{ $acc->provider_name }}</div>
                            <span style="display: inline-block; background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 2px; font-size: 0.65rem; color: {{ $acc->type === 'Crypto' ? '#FFBD2E' : ($acc->type === 'Bank' ? '#00E5FF' : '#FF3366') }}; margin-top: 4px; text-transform: uppercase;">{{ $acc->type }}</span>
                        </td>
                        <td>
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; color: var(--erp-teal); margin-bottom: 4px;">{{ $acc->account_reference }}</div>
                            <div style="font-size: 0.75rem; color: var(--erp-muted);">
                                <strong>{{ $acc->account_name }}</strong> 
                                @if($acc->routing_code)
                                    <span style="margin-left: 5px; padding-left: 5px; border-left: 1px solid rgba(255,255,255,0.1);">{{ $acc->routing_code }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($acc->ledger_code)
                                <span style="font-family: 'Roboto Mono', monospace; background: rgba(0, 229, 255, 0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 4px 8px; border-radius: 2px; font-size: 0.75rem; font-weight: bold; display: inline-block;">
                                    COA: [{{ $acc->ledger_code }}]
                                </span>
                            @else
                                <span style="font-family: 'Roboto Mono', monospace; color: var(--erp-red); font-size: 0.7rem; border: 1px dashed var(--erp-red); padding: 3px 6px; border-radius: 2px;">
                                    UNLINKED
                                </span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            @if($acc->is_active)
                                <span style="color: #00FF66; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; border: 1px solid #00FF66; padding: 2px 6px; border-radius: 2px;">ACTIVE</span>
                            @else
                                <span style="color: var(--erp-red); font-size: 0.65rem; font-family: 'Roboto Mono', monospace; border: 1px solid var(--erp-red); padding: 2px 6px; border-radius: 2px;">FROZEN</span>
                            @endif
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <button wire:click="editAccount({{ $acc->id }})" style="background: transparent; border: none; color: #FFBD2E; cursor: pointer; font-size: 0.75rem; font-weight: bold; margin-right: 10px;">EDIT</button>
                            <button wire:click="toggleStatus({{ $acc->id }}, {{ $acc->is_active }})" style="background: transparent; border: none; color: {{ $acc->is_active ? 'var(--erp-red)' : '#00FF66' }}; cursor: pointer; font-size: 0.75rem; font-weight: bold;">
                                {{ $acc->is_active ? 'FREEZE' : 'UNFREEZE' }}
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="padding: 40px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">
                            > NO TREASURY ACCOUNTS FOUND IN REGISTRY.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>