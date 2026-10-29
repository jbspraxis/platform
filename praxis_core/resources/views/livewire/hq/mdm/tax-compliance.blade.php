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

        /* Internal Toggles */
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-border-dark); background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }

        /* Form Inputs */
        .cyber-input, .cyber-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 12px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border-radius: 2px; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus { border-color: var(--erp-teal); outline: none; background: rgba(0,0,0,0.5); }
        .cyber-select option { background-color: var(--erp-bg-main); color: var(--erp-white); padding: 10px; }

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: var(--erp-bg-main); border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 450px; width: 90%; }
        .spinner { border: 3px solid rgba(0, 229, 255, 0.1); border-top: 3px solid var(--erp-teal); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        
        .cyber-checkbox { width: 18px; height: 18px; accent-color: var(--erp-red); cursor: pointer; }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(0,255,102,0.4);">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">SYSTEM SYNCHRONIZED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 25px; line-height: 1.5;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" style="background: #00FF66; color: #000; border: none; padding: 12px 30px; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; border-radius: 2px; cursor: pointer; width: 100%;">ACKNOWLEDGE</button>
            </div>
        </div>
    @endif

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

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div style="display: flex; gap: 10px;">
            <button wire:click="switchTab('tax')" class="nav-pill {{ $activeTab === 'tax' ? 'active' : '' }}">TAX JURISDICTIONS</button>
            <button wire:click="switchTab('currency')" class="nav-pill {{ $activeTab === 'currency' ? 'active' : '' }}">GLOBAL EXCHANGE RATES</button>
        </div>

        @if($activeTab === 'currency')
            <button wire:click="syncRatesFromApi" wire:loading.attr="disabled" style="background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px;">
                <span wire:loading.remove wire:target="syncRatesFromApi">SYNC LIVE API DATA</span>
                <span wire:loading wire:target="syncRatesFromApi">ESTABLISHING UPLINK...</span>
            </button>
        @endif
    </div>
    
    @error('api_sync')
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); border-left: 4px solid var(--erp-red); color: var(--erp-red); padding: 12px 15px; margin-bottom: 25px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; display: flex; align-items: center; gap: 10px; box-shadow: 0 0 10px rgba(255,51,102,0.2);">
            <strong style="font-size: 1rem;">[!] CONNECTION FAILURE:</strong> {{ $message }}
        </div>
    @enderror

    <div style="display: grid; grid-template-columns: 360px 1fr; gap: 30px; align-items: start;">
        
        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
            
            @if($activeTab === 'tax')
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">
                    > {{ $editMode ? 'UPDATE' : 'REGISTER' }} TAX JURISDICTION
                </div>

                <form wire:submit.prevent="saveTax" style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">REGION / AUTHORITY</label>
                        <input type="text" wire:model="region_name" required placeholder="e.g. United Kingdom (HMRC)" class="cyber-input">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">ISO COUNTRY</label>
                            <input type="text" wire:model="country_code" required placeholder="GB" class="cyber-input">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">VAT RATE (%)</label>
                            <input type="number" step="0.01" wire:model="vat_rate" required class="cyber-input" style="color: var(--erp-red); font-weight: bold;">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">TAX LOGIC TYPE</label>
                        <select wire:model="tax_logic_type" class="cyber-select">
                            <option value="Destination-based">Destination-based</option>
                            <option value="Origin-based">Origin-based</option>
                            <option value="Tax Exempt (B2B)">Tax Exempt (B2B)</option>
                            <option value="Reverse Charge">Reverse Charge</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">FIAT CURRENCY PEG</label>
                        <input type="text" wire:model="exchange_rate_peg" required placeholder="USD" class="cyber-input" style="color: var(--erp-teal); font-weight: bold;">
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" style="flex: 2; background: var(--erp-teal); color: #0A1128; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 12px; border-radius: 2px; cursor: pointer;">{{ $editMode ? 'UPDATE POLICY' : 'REGISTER JURISDICTION' }}</button>
                        @if($editMode) <button type="button" wire:click="resetForms" style="flex: 1; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; padding: 12px; border-radius: 2px; cursor: pointer;">CANCEL</button> @endif
                    </div>
                </form>

            @else
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">
                    > {{ $currEditMode ? 'ADJUST' : 'ADD' }} EXCHANGE RATE PEG
                </div>

                <form wire:submit.prevent="saveCurrency" style="display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">ISO CODE</label>
                            <input type="text" wire:model="currency_code" required placeholder="NGN" class="cyber-input" {{ $currEditMode ? 'readonly style=opacity:0.5;' : '' }}>
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 5px;">CURRENCY NAME</label>
                            <input type="text" wire:model="currency_name" required placeholder="Nigerian Naira" class="cyber-input">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal); margin-bottom: 5px;">EXCHANGE RATE (To 1 USD)</label>
                        <input type="number" step="0.000001" wire:model="rate_to_usd" required class="cyber-input" style="font-size: 1.2rem; font-weight: bold; color: #FFBD2E;">
                    </div>

                    <div style="background: rgba(255,51,102,0.1); border: 1px solid rgba(255,51,102,0.3); padding: 15px; border-radius: 4px; display: flex; align-items: flex-start; gap: 12px;">
                        <input type="checkbox" wire:model="is_manual_override" id="manual_override" class="cyber-checkbox" style="margin-top: 2px;">
                        <label for="manual_override" style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-white); cursor: pointer; line-height: 1.4;">
                            <strong style="color: var(--erp-red); display: block; margin-bottom: 2px;">LOCK MANUAL OVERRIDE</strong>
                            If checked, automated API syncs will NOT overwrite this specific rate. Use for parallel/corporate market pegs.
                        </label>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" style="flex: 2; background: var(--erp-teal); color: #0A1128; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 12px; border-radius: 2px; cursor: pointer;">{{ $currEditMode ? 'UPDATE PEG' : 'ADD CURRENCY' }}</button>
                        @if($currEditMode) <button type="button" wire:click="resetForms" style="flex: 1; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; padding: 12px; border-radius: 2px; cursor: pointer;">CANCEL</button> @endif
                    </div>
                </form>
            @endif
        </div>

        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
            
            @if($activeTab === 'tax')
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                    > GLOBAL TAX JURISDICTIONS
                </div>
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">REGION</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">LOGIC TYPE</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-align: right;">VAT / PEG</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-align: right;">STATUS</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-align: center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($taxes as $tax)
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-white);">{{ $tax->region_name }}</div>
                                <div style="font-size: 0.75rem; color: var(--erp-teal); margin-top: 2px;">{{ $tax->country_code }}</div>
                            </td>
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.75rem; color: var(--erp-muted);">
                                <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 2px;">{{ $tax->tax_logic_type }}</span>
                            </td>
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right; font-family: 'Roboto Mono', monospace;">
                                <span style="font-size: 0.85rem; font-weight: bold; color: var(--erp-red);">{{ number_format($tax->vat_rate, 2) }}%</span>
                                <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 2px;">{{ $tax->exchange_rate_peg }}</div>
                            </td>
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right;">
                                @if($tax->is_active)
                                    <span style="color: #00FF66; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; border: 1px solid #00FF66; padding: 2px 6px; border-radius: 2px;">ACTIVE</span>
                                @else
                                    <span style="color: var(--erp-muted); font-size: 0.7rem; font-family: 'Roboto Mono', monospace; border: 1px solid var(--erp-muted); padding: 2px 6px; border-radius: 2px;">RETIRED</span>
                                @endif
                            </td>
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
                                <button wire:click="editTax({{ $tax->id }})" style="background: transparent; border: none; color: #FFBD2E; cursor: pointer; font-size: 0.75rem; font-weight: bold; margin-right: 10px;">EDIT</button>
                                <button wire:click="toggleTaxStatus({{ $tax->id }}, {{ $tax->is_active }})" style="background: transparent; border: none; color: {{ $tax->is_active ? 'var(--erp-red)' : '#00FF66' }}; cursor: pointer; font-size: 0.75rem; font-weight: bold;">
                                    {{ $tax->is_active ? 'HALT' : 'LIVE' }}
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="padding: 40px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">> NO TAX JURISDICTIONS CONFIGURED.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            
            @else
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                    > FX / CURRENCY MATRICES
                </div>
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">CURRENCY</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-align: right;">RATE (1 USD)</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-align: center;">SYNC STATUS</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-align: center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currencies as $curr)
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-teal);">{{ $curr->currency_code }}</div>
                                <div style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 2px;">{{ $curr->currency_name }}</div>
                            </td>
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right; font-family: 'Roboto Mono', monospace;">
                                <span style="font-size: 1rem; font-weight: bold; color: #FFBD2E;">{{ number_format($curr->rate_to_usd, 4) }}</span>
                            </td>
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
                                @if($curr->is_manual_override)
                                    <span style="color: var(--erp-red); font-size: 0.65rem; font-family: 'Roboto Mono', monospace; border: 1px solid var(--erp-red); padding: 2px 6px; border-radius: 2px; background: rgba(255,51,102,0.1);">MANUAL PEG</span>
                                @else
                                    <span style="color: #00FF66; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; border: 1px solid #00FF66; padding: 2px 6px; border-radius: 2px; background: rgba(0,255,102,0.1);">API SYNCED</span>
                                    <div style="font-size: 0.6rem; color: var(--erp-muted); margin-top: 4px;">{{ $curr->last_synced_at ? \Carbon\Carbon::parse($curr->last_synced_at)->diffForHumans() : 'Never' }}</div>
                                @endif
                            </td>
                            <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
                                <button wire:click="editCurrency({{ $curr->id }})" style="background: transparent; border: none; color: #FFBD2E; cursor: pointer; font-size: 0.75rem; font-weight: bold;">EDIT</button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="padding: 40px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">> NO EXCHANGE RATES MAPPED.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif

        </div>
    </div>
</div>