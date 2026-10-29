<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select, .cyber-textarea {
            background: rgba(0,0,0,0.4); border: 1px solid rgba(0, 229, 255, 0.2);
            color: #FFFFFF; padding: 10px 15px; font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem; border-radius: 2px; transition: 0.3s; outline: none;
        }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { 
            border-color: #00E5FF; background: rgba(0, 229, 255, 0.05); box-shadow: inset 2px 0 0 #00E5FF; 
        }
        .cyber-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
        }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        
        .btn-action {
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px;
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer;
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; gap: 8px; justify-content: center;
        }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        
        .btn-danger { border-color: #FF3366; color: #FF3366; }
        .btn-danger:hover { background: #FF3366; color: #FFF; box-shadow: 0 0 15px rgba(255, 51, 102, 0.4); }

        /* --- FILTERS BAR --- */
        .filter-bar {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-left: 4px solid #00E5FF;
            padding: 20px; border-radius: 4px; display: flex; gap: 15px; align-items: flex-end; margin-bottom: 30px;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.2); flex-wrap: wrap;
        }

        /* --- JOURNAL ENTRY CARDS --- */
        .journal-card {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 4px; 
            margin-bottom: 25px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); transition: 0.3s;
        }
        .journal-card:hover { border-color: rgba(0, 229, 255, 0.2); }
        .journal-card.reversed { border-left: 4px solid #FF3366; opacity: 0.8; }
        .journal-card.is-reversal { border-left: 4px solid #FFBD2E; }

        .journal-header {
            background: rgba(0, 0, 0, 0.4); padding: 15px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;
        }
        .journal-ref { font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1.1rem; color: #FFF; letter-spacing: 1px; }
        .journal-desc { font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #8A9BB2; margin-top: 5px; }
        .journal-meta { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #00E5FF; text-align: right; }

        /* --- IFRS LEDGER TABLE FORMATTING --- */
        .journal-lines { width: 100%; border-collapse: collapse; text-align: left; }
        .journal-lines th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 10px 25px; border-bottom: 1px dashed rgba(255, 255, 255, 0.1); text-transform: uppercase; }
        .journal-lines td { padding: 15px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.02); vertical-align: top; }
        .journal-lines tr:last-child td { border-bottom: none; }
        
        .debit-val { color: #00FF66; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.9rem; }
        .credit-val { color: #FFBD2E; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.9rem; }
        .account-name { color: #FFF; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: bold; }
        .account-code { color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; margin-right: 10px; display: inline-block; width: 85px; }

        /* Badges */
        .status-badge { padding: 4px 10px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; border-radius: 2px; text-transform: uppercase; border: 1px solid; display: inline-block; margin-top: 5px;}
        .badge-reversed { color: #FF3366; border-color: #FF3366; background: rgba(255, 51, 102, 0.1); }
        .badge-reversal { color: #FFBD2E; border-color: #FFBD2E; background: rgba(255, 189, 46, 0.1); }

        /* --- MODAL OVERLAY --- */
        .glass-modal {
            position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px);
            z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-box {
            background: rgba(10, 17, 40, 0.95); border: 1px solid rgba(255, 51, 102, 0.3); border-top: 4px solid #FF3366;
            padding: 30px; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); width: 100%; max-width: 600px;
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; padding-bottom: 15px;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.8rem; font-weight: 900; letter-spacing: 1px; color: #FFF; margin: 0;">MASTER GENERAL LEDGER</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; margin: 5px 0 0 0;">> Immutable IFRS Double-Entry Auditing Matrix</p>
        </div>
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

    <div class="filter-bar">
        <div style="flex: 1; min-width: 150px;">
            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; display: block; margin-bottom: 5px;">START DATE</label>
            <input type="date" wire:model="startDate" class="cyber-input">
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; display: block; margin-bottom: 5px;">END DATE</label>
            <input type="date" wire:model="endDate" class="cyber-input">
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; display: block; margin-bottom: 5px;">REGION TAG</label>
            <select wire:model="regionFilter" class="cyber-select">
                <option value="ALL">ALL REGIONS (GLOBAL)</option>
                <option value="NG">JBS PRAXIS - NIGERIA</option>
                <option value="CM">JBS PRAXIS - CAMEROON</option>
                <option value="GLOBAL">CORPORATE HQ</option>
            </select>
        </div>
        <div style="flex: 0 0 auto;">
            <button wire:click="filterLedger" class="btn-action" style="padding: 10px 25px; height: 41px;">
                <i data-lucide="filter" style="width: 16px; height: 16px;"></i> QUERY LEDGER
            </button>
        </div>
    </div>

    <div>
        @forelse($entries as $entry)
            <div class="journal-card {{ $entry->is_reversal && $entry->reverses_entry_id ? 'is-reversal' : ($entry->is_reversal ? 'reversed' : '') }}">
                <div class="journal-header">
                    <div>
                        <div class="journal-ref">
                            {{ $entry->reference_number }}
                            @if($entry->is_reversal && !$entry->reverses_entry_id)
                                <span class="status-badge badge-reversed" style="margin-left: 10px;">[ VOIDED / REVERSED ]</span>
                            @elseif($entry->is_reversal && $entry->reverses_entry_id)
                                <span class="status-badge badge-reversal" style="margin-left: 10px;">[ CORRECTIVE REVERSAL ENTRY ]</span>
                            @endif
                        </div>
                        <div class="journal-desc">{{ $entry->description }}</div>
                    </div>
                    
                    <div class="journal-meta">
                        <div style="color: #FFF; font-weight: bold; font-size: 0.8rem; margin-bottom: 4px;">
                            {{ \Carbon\Carbon::parse($entry->entry_date)->format('M d, Y') }}
                        </div>
                        <div>MODULE: {{ strtoupper($entry->source_module) }} | REGION: {{ $entry->region_code }}</div>
                        
                        @if(!$entry->is_reversal)
                            <button wire:click="triggerReversal('{{ $entry->id }}')" class="btn-action btn-danger" style="padding: 4px 10px; font-size: 0.6rem; margin-top: 10px; border-color: rgba(255,51,102,0.3);">
                                <i data-lucide="rotate-ccw" style="width: 12px; height: 12px;"></i> EXECUTE IFRS REVERSAL
                            </button>
                        @endif
                    </div>
                </div>

                <table class="journal-lines">
                    <thead>
                        <tr>
                            <th style="width: 60%;">Account Title & Line Memo</th>
                            <th style="text-align: right; width: 20%;">Debit (DR)</th>
                            <th style="text-align: right; width: 20%;">Credit (CR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalDebit = 0; $totalCredit = 0;
                        @endphp
                        
                        @foreach($entry->lines as $line)
                            @php
                                $totalDebit += $line->debit_amount;
                                $totalCredit += $line->credit_amount;
                                
                                // Standard IFRS rule: Credits are indented to the right
                                $isCredit = $line->credit_amount > 0;
                            @endphp
                            <tr>
                                <td style="padding-left: {{ $isCredit ? '50px' : '25px' }};">
                                    <div>
                                        <span class="account-code">[{{ $line->account_code }}]</span>
                                        <span class="account-name">{{ $line->account_name }}</span>
                                    </div>
                                    
                                    @if($line->memo)
                                        <div style="font-family: 'Inter', sans-serif; font-size: 0.7rem; color: #8A9BB2; margin-top: 6px; font-style: italic; display: flex; align-items: flex-start; gap: 6px;">
                                            <i data-lucide="corner-down-right" style="width: 12px; height: 12px; opacity: 0.5; margin-top: 2px;"></i>
                                            {{ $line->memo }}
                                        </div>
                                    @endif
                                </td>
                                
                                <td style="text-align: right;">
                                    @if($line->debit_amount > 0)
                                        <span class="debit-val">{{ number_format($line->debit_amount, 2) }}</span>
                                    @endif
                                </td>
                                
                                <td style="text-align: right;">
                                    @if($line->credit_amount > 0)
                                        <span class="credit-val">{{ number_format($line->credit_amount, 2) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        
                        <tr style="background: rgba(0,0,0,0.2);">
                            <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; text-transform: uppercase; border-top: 1px solid rgba(255,255,255,0.1);">
                                ENTRY TOTALS
                            </td>
                            <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.95rem; color: #FFF; border-top: 1px solid rgba(255,255,255,0.1); border-bottom: 3px double rgba(255,255,255,0.3);">
                                ${{ number_format($totalDebit, 2) }}
                            </td>
                            <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.95rem; color: #FFF; border-top: 1px solid rgba(255,255,255,0.1); border-bottom: 3px double rgba(255,255,255,0.3);">
                                ${{ number_format($totalCredit, 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @empty
            <div style="text-align: center; padding: 60px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 4px; color: #8A9BB2; font-family: 'Roboto Mono', monospace;">
                > NO JOURNAL ENTRIES DETECTED FOR THIS QUERY RANGE.
            </div>
        @endforelse
    </div>

    @if($showReversalModal)
        <div class="glass-modal">
            <div class="modal-box">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #FF3366; border-bottom: 1px dashed rgba(255,51,102,0.3); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="triangle-alert" style="width: 18px; height: 18px;"></i> INITIATE IFRS REVERSAL
                    </span>
                    <button wire:click="$set('showReversalModal', false)" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer;"><i data-lucide="x" style="width: 18px; height: 18px;"></i></button>
                </div>

                <div style="background: rgba(255,51,102,0.1); border-left: 2px solid #FF3366; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FF3366; margin-bottom: 20px; line-height: 1.5;">
                    <strong>WARNING:</strong> Per IFRS compliance, Journal Entries cannot be permanently deleted. This action will generate a new 'Reversing Entry' that mathematically swaps the Debits and Credits to nullify the original transaction.
                </div>

                <form wire:submit.prevent="executeReversingEntry" style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">AUDIT TRAIL JUSTIFICATION (REQUIRED)</label>
                        <textarea wire:model="reversalReason" class="cyber-textarea" rows="3" placeholder="Provide a detailed explanation for internal/external auditors as to why this entry is being nullified..." required minlength="10"></textarea>
                        @error('reversalReason') <span style="color: #FF3366; font-size: 0.65rem; margin-top: 5px; display: block;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <button type="button" wire:click="$set('showReversalModal', false)" class="btn-action" style="flex: 1; border-color: #8A9BB2; color: #8A9BB2;">ABORT</button>
                        <button type="submit" class="btn-action btn-danger" style="flex: 2;" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="executeReversingEntry">EXECUTE REVERSAL SEQUENCE</span>
                            <span wire:loading wire:target="executeReversingEntry">POSTING ENTRY...</span>
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