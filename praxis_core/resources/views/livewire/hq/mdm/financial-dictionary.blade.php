<div>
    <style>
        /* Premium Custom Dropdown styling */
        .cyber-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: var(--erp-bg-base);
            border: 1px solid rgba(0, 229, 255, 0.3);
            color: var(--erp-teal);
            padding: 12px 15px;
            width: 100%;
            font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem;
            border-radius: 2px;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            transition: all 0.3s ease;
        }
        .cyber-select:focus { outline: none; border-color: var(--erp-teal); box-shadow: 0 0 10px rgba(0,229,255,0.2); }
        .cyber-select option { background-color: var(--erp-bg-main); color: var(--erp-white); padding: 10px; }

        /* Locked Input Styling */
        .locked-input-wrapper { position: relative; display: flex; align-items: center; }
        .locked-input-wrapper i { position: absolute; left: 12px; color: var(--erp-muted); width: 14px; height: 14px; }
        .locked-input { 
            width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); 
            color: var(--erp-muted); padding: 12px 15px 12px 35px; font-family: 'Roboto Mono', monospace; 
            font-size: 0.95rem; font-weight: bold; border-radius: 2px; cursor: not-allowed; letter-spacing: 1px;
        }

        /* Input Base */
        .cyber-input {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: var(--erp-white); padding: 12px 15px; font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem; border-radius: 2px; transition: 0.3s;
        }
        .cyber-input:focus { border-color: var(--erp-teal); outline: none; background: rgba(0,0,0,0.5); }

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: var(--erp-bg-main); border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 420px; width: 90%; }
        
        .spinner { border: 3px solid rgba(0, 229, 255, 0.1); border-top: 3px solid var(--erp-teal); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(0,255,102,0.4);">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">LEDGER SYNCHRONIZED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 25px; line-height: 1.5;">The Universal Financial Dictionary has been securely updated. Item Code <strong style="color: var(--erp-white);">{{ $item_code }}</strong> is now available for global operations.</p>
                <button wire:click="closeSuccessModal" style="background: #00FF66; color: #000; border: none; padding: 12px 30px; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; border-radius: 2px; cursor: pointer; transition: 0.3s; width: 100%;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: var(--erp-white); letter-spacing: 1px;">FINANCIAL DICTIONARY</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">MASTER ITEM SUBLEDGER & ROUTING MATRIX</p>
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

    <div style="display: grid; grid-template-columns: 380px 1fr; gap: 30px; align-items: start;">
        
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px; position: relative; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
            
            <div wire:loading.flex wire:target="saveCode" style="position: absolute; inset: 0; background: rgba(10,17,40,0.9); z-index: 50; flex-direction: column; align-items: center; justify-content: center; border-radius: 4px; backdrop-filter: blur(2px);">
                <div class="spinner"></div>
                <div style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: 15px; letter-spacing: 2px; animation: pulse 1.5s infinite;">ENCRYPTING RECORD...</div>
            </div>

            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <span>> {{ $editMode ? 'MODIFY' : 'REGISTER' }} MDM ITEM</span>
                @if($editMode) <span style="background: rgba(255,189,46,0.1); color: #FFBD2E; padding: 3px 8px; font-size: 0.6rem; border-radius: 2px; border: 1px solid #FFBD2E;">EDIT MODE</span> @endif
            </div>

            <form wire:submit.prevent="saveCode" style="display: flex; flex-direction: column; gap: 20px;">
                
                <div>
                    <label for="input_item_code" style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px; text-transform: uppercase;">Universal Item Code (Auto-Locked)</label>
                    <div class="locked-input-wrapper">
                        <i data-lucide="lock"></i>
                        <input type="text" id="input_item_code" name="item_code" wire:model="item_code" readonly class="locked-input">
                    </div>
                </div>

                <div>
                    <label for="input_category" style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px; text-transform: uppercase;">Financial Category</label>
                    <select id="input_category" name="category" wire:model.live="category" {{ $editMode ? 'disabled' : '' }} class="cyber-select">
                        @foreach($availableCategories as $val => $data)
                            <option value="{{ $val }}">{{ $data['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="input_description" style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px; text-transform: uppercase;">Line Item Description</label>
                    <input type="text" id="input_description" name="description" wire:model="description" required class="cyber-input" placeholder="Enter detailed operational description">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label for="input_uom" style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px; text-transform: uppercase;">Unit of Measure</label>
                        <input type="text" id="input_uom" name="uom" wire:model="uom" required class="cyber-input" placeholder="e.g. Month, License">
                    </div>
                    <div>
                        <label for="input_rate" style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 8px; text-transform: uppercase;">Base Rate (USD)</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 12px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.85rem;">$</span>
                            <input type="number" id="input_rate" name="default_unit_rate_usd" step="0.01" wire:model="default_unit_rate_usd" required class="cyber-input" style="padding-left: 25px; color: var(--erp-teal); font-weight: bold; border-color: rgba(0,229,255,0.2);">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" wire:loading.attr="disabled" style="flex: 1; background: var(--erp-teal); color: #0A1128; border: none; padding: 14px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.boxShadow='0 0 15px rgba(0,229,255,0.4)'" onmouseout="this.style.boxShadow='none'">
                        {{ $editMode ? 'OVERWRITE RECORD' : 'COMMIT TO LEDGER' }}
                    </button>
                    @if($editMode)
                        <button type="button" wire:click="resetForm" style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; padding: 14px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-red)'; this.style.color='#FFF';" onmouseout="this.style.background='rgba(255,51,102,0.1)'; this.style.color='var(--erp-red)';">CANCEL</button>
                    @endif
                </div>
            </form>
        </div>

        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; display: flex; justify-content: space-between;">
                <span>> MASTER DICTIONARY LEDGER</span>
                <span style="color: var(--erp-muted); font-size: 0.75rem;">Total Entries: {{ count($dictionary) }}</span>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Code Ref</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Descriptor / Category</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; text-transform: uppercase;">Ledger Routing (INC / EXP)</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right; text-transform: uppercase;">Standard Rate</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right; text-transform: uppercase;">Sys. Status</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; text-transform: uppercase;">Command</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dictionary as $item)
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                        
                        <td style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-teal); letter-spacing: 0.5px;">{{ $item->item_code }}</div>
                            <div style="font-size: 0.65rem; color: #5a6b82; margin-top: 4px; font-family: 'Roboto Mono', monospace;">UOM: {{ $item->default_quantity }}</div>
                        </td>
                        
                        <td style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                            <div style="font-size: 0.8rem; color: var(--erp-white); font-weight: 500;">{{ $item->description }}</div>
                            <span style="display: inline-block; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 2px; font-size: 0.6rem; font-family: 'Roboto Mono', monospace; color: var(--erp-muted); margin-top: 6px;">{{ strtoupper($item->category) }}</span>
                        </td>
                        
                        <td style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                @if($item->income_code)
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; background: rgba(0, 229, 255, 0.1); border: 1px solid rgba(0, 229, 255, 0.3); padding: 2px 6px; border-radius: 2px; width: 85px; text-align: left;">
                                        INC: [{{ $item->income_code }}]
                                    </span>
                                @else
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 2px 6px; border-radius: 2px; width: 85px; text-align: left;">
                                        INC: [ N/A ]
                                    </span>
                                @endif

                                @if($item->expense_code)
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFBD2E; background: rgba(255, 189, 46, 0.1); border: 1px solid rgba(255, 189, 46, 0.3); padding: 2px 6px; border-radius: 2px; width: 85px; text-align: left;">
                                        EXP: [{{ $item->expense_code }}]
                                    </span>
                                @else
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 2px 6px; border-radius: 2px; width: 85px; text-align: left;">
                                        EXP: [ N/A ]
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right; font-family: 'Roboto Mono', monospace;">
                            <span style="font-size: 0.9rem; font-weight: bold; color: var(--erp-white);">${{ number_format($item->default_unit_rate_usd, 2) }}</span>
                        </td>
                        
                        <td style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right;">
                            @if($item->is_active)
                                <span style="color: #00FF66; background: rgba(0,255,102,0.1); font-size: 0.6rem; font-family: 'Roboto Mono', monospace; border: 1px solid #00FF66; padding: 3px 8px; border-radius: 50px; font-weight: bold;">ACTIVE</span>
                            @else
                                <span style="color: var(--erp-red); background: rgba(255,51,102,0.1); font-size: 0.6rem; font-family: 'Roboto Mono', monospace; border: 1px solid var(--erp-red); padding: 3px 8px; border-radius: 50px; font-weight: bold;">SUSPENDED</span>
                            @endif
                        </td>
                        
                        <td style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
                            <button wire:click="editCode({{ $item->id }})" style="background: transparent; border: 1px solid #FFBD2E; color: #FFBD2E; padding: 4px 10px; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s; margin-right: 5px;" onmouseover="this.style.background='#FFBD2E'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='#FFBD2E';">EDIT</button>
                            
                            <button wire:click="toggleStatus({{ $item->id }}, {{ $item->is_active }})" style="background: transparent; border: 1px solid {{ $item->is_active ? 'var(--erp-red)' : '#00FF66' }}; color: {{ $item->is_active ? 'var(--erp-red)' : '#00FF66' }}; padding: 4px 10px; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='{{ $item->is_active ? 'var(--erp-red)' : '#00FF66' }}'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='{{ $item->is_active ? 'var(--erp-red)' : '#00FF66' }}';">
                                {{ $item->is_active ? 'HALT' : 'LIVE' }}
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="padding: 60px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">
                            > THE MASTER DICTIONARY IS CURRENTLY EMPTY.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>