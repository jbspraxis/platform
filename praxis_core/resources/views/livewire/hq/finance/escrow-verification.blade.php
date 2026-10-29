<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select {
            width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(0, 229, 255, 0.2);
            color: #FFFFFF; padding: 12px 15px; font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem; border-radius: 2px; transition: 0.3s; outline: none;
        }
        .cyber-input:focus, .cyber-select:focus { 
            border-color: #00E5FF; background: rgba(0, 229, 255, 0.05); box-shadow: inset 2px 0 0 #00E5FF; 
        }
        
        .btn-action {
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px;
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer;
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; gap: 8px; justify-content: center;
            text-decoration: none;
        }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        
        .btn-danger { border-color: #FF3366; color: #FF3366; }
        .btn-danger:hover { background: #FF3366; color: #FFF; box-shadow: 0 0 15px rgba(255, 51, 102, 0.4); }

        .btn-success { border-color: #00FF66; color: #00FF66; }
        .btn-success:hover { background: #00FF66; color: #0A1128; box-shadow: 0 0 15px rgba(0, 255, 102, 0.4); }

        /* --- TABS --- */
        .ws-tab {
            font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #8A9BB2;
            padding: 8px 20px; border: 1px solid transparent; text-decoration: none; transition: 0.3s; cursor: pointer;
            background: transparent; border-radius: 2px;
        }
        .ws-tab:hover { color: #FFF; }
        .ws-tab.active { color: #00E5FF; background: rgba(0, 229, 255, 0.05); border-color: #00E5FF; }

        /* --- LEDGER TABLE --- */
        .ledger-panel {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 4px; 
            overflow: hidden; box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
        }
        .ledger-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ledger-table th {
            font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;
            padding: 15px 20px; border-bottom: 1px dashed rgba(255, 255, 255, 0.1); text-transform: uppercase;
        }
        .ledger-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.02); vertical-align: middle; }
        .ledger-table tr:hover { background: rgba(0, 229, 255, 0.02); }

        /* Badges */
        .status-badge { padding: 4px 10px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; border-radius: 2px; text-transform: uppercase; border: 1px solid; display: inline-block;}
        .badge-pending { color: #FFBD2E; border-color: #FFBD2E; background: rgba(255, 189, 46, 0.1); }
        .badge-verified { color: #00FF66; border-color: #00FF66; background: rgba(0, 255, 102, 0.1); }
        .badge-rejected { color: #FF3366; border-color: #FF3366; background: rgba(255, 51, 102, 0.1); }

        /* --- MODAL OVERLAY --- */
        .glass-modal {
            position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px);
            z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-box {
            background: rgba(10, 17, 40, 0.95); border: 1px solid rgba(0, 229, 255, 0.3); border-top: 4px solid #00E5FF;
            padding: 0; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); width: 100%; max-width: 900px;
            max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column;
        }
        .modal-header {
            padding: 20px 30px; border-bottom: 1px dashed rgba(255,255,255,0.1); background: rgba(0,0,0,0.3);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-body {
            display: grid; grid-template-columns: 1fr 1fr; gap: 30px; padding: 30px;
        }
        
        .evidence-box {
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05); border-radius: 4px; padding: 20px;
        }
        .data-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; }
        .data-label { color: #8A9BB2; }
        .data-value { color: #FFF; font-weight: bold; text-align: right; }

        @media (max-width: 768px) {
            .modal-body { grid-template-columns: 1fr; }
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; padding-bottom: 15px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.8rem; font-weight: 900; letter-spacing: 1px; color: #FFF; margin: 0;">ESCROW & POP VERIFICATION</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; margin: 5px 0 0 0;">> Cryptographic Clearing & Guillotine Defrosting Center</p>
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

    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
        <button wire:click="setTab('Pending Verification')" class="ws-tab {{ $activeTab === 'Pending Verification' ? 'active' : '' }}">[ PENDING_CLEARANCE ]</button>
        <button wire:click="setTab('Verified')" class="ws-tab {{ $activeTab === 'Verified' ? 'active' : '' }}">[ SECURED_FUNDS ]</button>
        <button wire:click="setTab('Rejected')" class="ws-tab {{ $activeTab === 'Rejected' ? 'active' : '' }}">[ REJECTED_TX ]</button>
    </div>

    <div class="ledger-panel">
        <div style="overflow-x: auto;">
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Date / TX Hash</th>
                        <th>Project & Invoice</th>
                        <th style="text-align: right;">Claimed / WHT</th>
                        <th style="text-align: center;">Escrow Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FFF;">{{ \Carbon\Carbon::parse($tx->created_at)->format('M d, H:i') }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-top: 4px; word-break: break-all;">
                                    [{{ strtoupper($tx->method) }}] {{ Str::limit($tx->tx_hash_or_ref, 15) }}
                                </div>
                            </td>
                            <td>
                                <div style="font-family: 'Inter', sans-serif; font-weight: bold; color: #FFF; font-size: 0.85rem;">{{ $tx->client_name }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 4px;">
                                    INV: {{ $tx->invoice_number }}
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; color: #00FF66; font-size: 1rem;">
                                    {{ $tx->currency }} {{ number_format($tx->amount_claimed, 2) }}
                                </div>
                                @if($tx->wht_deducted > 0)
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFBD2E; margin-top: 4px;">
                                        + WHT: {{ number_format($tx->wht_deducted, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @php
                                    $badgeClass = match($tx->status) {
                                        'Pending Verification' => 'badge-pending',
                                        'Verified' => 'badge-verified',
                                        'Rejected' => 'badge-rejected',
                                        default => 'badge-pending'
                                    };
                                @endphp
                                <span class="status-badge {{ $badgeClass }}">{{ strtoupper($tx->status) }}</span>
                            </td>
                            <td style="text-align: right;">
                                @if($tx->status === 'Pending Verification')
                                    <button wire:click="openVerifyModal('{{ $tx->id }}')" class="btn-action" style="padding: 6px 12px; font-size: 0.7rem;">
                                        <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> ADJUDICATE
                                    </button>
                                @else
                                    <button wire:click="openVerifyModal('{{ $tx->id }}')" class="btn-action" style="padding: 6px 12px; font-size: 0.7rem; border-color: #8A9BB2; color: #8A9BB2;">
                                        <i data-lucide="eye" style="width: 14px; height: 14px;"></i> VIEW RECORD
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                > NO TRANSACTIONS FOUND IN THIS CATEGORY.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showVerifyModal && $selectedTx && $linkedInvoice)
        <div class="glass-modal">
            <div class="modal-box">
                <div class="modal-header">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #00E5FF; font-weight: bold;">
                        > ESCROW VERIFICATION SEQUENCE
                    </span>
                    <button wire:click="$set('showVerifyModal', false)" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer;"><i data-lucide="x" style="width: 18px; height: 18px;"></i></button>
                </div>

                <div class="modal-body">
                    <div class="evidence-box">
                        <div style="font-family: 'Montserrat', sans-serif; font-size: 0.85rem; color: #00E5FF; margin-bottom: 15px; border-bottom: 1px dashed rgba(0,229,255,0.3); padding-bottom: 5px;">
                            INVOICE RECONCILIATION
                        </div>
                        
                        <div class="data-row">
                            <span class="data-label">Invoice Number:</span>
                            <span class="data-value">{{ $linkedInvoice->invoice_number }}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Target Amount:</span>
                            <span class="data-value" style="color: #00E5FF;">{{ $linkedInvoice->currency }} {{ number_format($linkedInvoice->total_amount, 2) }}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Amount Claimed (POP):</span>
                            <span class="data-value" style="color: #00FF66;">{{ $selectedTx->currency }} {{ number_format($selectedTx->amount_claimed, 2) }}</span>
                        </div>
                        
                        @if($selectedTx->wht_deducted > 0)
                            <div class="data-row">
                                <span class="data-label">Withholding Tax (WHT):</span>
                                <span class="data-value" style="color: #FFBD2E;">+ {{ number_format($selectedTx->wht_deducted, 2) }}</span>
                            </div>
                        @endif

                        <div class="data-row" style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                            <span class="data-label">Mathematical Variance:</span>
                            @php
                                $shortfall = $linkedInvoice->total_amount - ($selectedTx->amount_claimed + $selectedTx->wht_deducted);
                            @endphp
                            <span class="data-value" style="color: {{ $shortfall > 0 ? '#FF3366' : '#00FF66' }};">
                                {{ $shortfall > 0 ? '-' : '+' }} {{ number_format(abs($shortfall), 2) }}
                            </span>
                        </div>

                        <div style="margin-top: 25px;">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; margin-bottom: 10px;">CRYPTOGRAPHIC PROOF FILES</div>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                @if($selectedTx->proof_file_path)
                                    <a href="{{ route('hq.file', ['path' => $selectedTx->proof_file_path]) }}" target="_blank" class="btn-action" style="font-size: 0.7rem; justify-content: flex-start; background: rgba(0,0,0,0.5);">
                                        <i data-lucide="external-link" style="width: 14px; height: 14px;"></i> VIEW PRIMARY RECEIPT (POP)
                                    </a>
                                @endif
                                
                                @if($selectedTx->wht_deducted > 0 && $selectedTx->wht_proof_file_path)
                                    <a href="{{ route('hq.file', ['path' => $selectedTx->wht_proof_file_path]) }}" target="_blank" class="btn-action" style="font-size: 0.7rem; justify-content: flex-start; border-color: #FFBD2E; color: #FFBD2E; background: rgba(0,0,0,0.5);">
                                        <i data-lucide="external-link" style="width: 14px; height: 14px;"></i> VIEW TAX REVERSAL (WHT)
                                    </a>
                                @endif
                                
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; padding: 10px; background: rgba(0, 229, 255, 0.05); border: 1px dashed rgba(0, 229, 255, 0.3); border-radius: 2px; word-break: break-all; margin-top: 5px;">
                                    <strong>TX HASH / REF:</strong><br>{{ $selectedTx->tx_hash_or_ref }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div style="font-family: 'Montserrat', sans-serif; font-size: 0.85rem; color: #FFBD2E; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,189,46,0.3); padding-bottom: 5px;">
                            ADJUDICATION & TOLERANCE OVERRIDE
                        </div>

                        @if($selectedTx->status === 'Pending Verification')
                            <form wire:submit.prevent="approveTransaction" style="display: flex; flex-direction: column; gap: 20px;">
                                
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">
                                        NETWORK / BANK FEE TOLERANCE WRITE-OFF
                                        <span style="display:block; font-size: 0.55rem; opacity: 0.7; margin-top: 4px;">If the client underpaid slightly due to Crypto Gas or wire fees, input the amount to write-off to Expense.</span>
                                    </label>
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 15px; top: 12px; color: #8A9BB2; font-family: 'Roboto Mono', monospace;">{{ $linkedInvoice->currency }}</span>
                                        <input type="number" wire:model="tolerance_writeoff" step="0.01" min="0" class="cyber-input" style="padding-left: 50px; font-weight: bold; color: #FFBD2E;">
                                    </div>
                                    @error('tolerance_writeoff') <span style="color: #FF3366; font-size: 0.65rem;">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">
                                        PARTIAL PAYMENT: GUILLOTINE EXTENSION
                                        <span style="display:block; font-size: 0.55rem; opacity: 0.7; margin-top: 4px;">If this is a partial payment and you want to grant the client extra days to pay the balance, add days here. (0 = Do not extend).</span>
                                    </label>
                                    <input type="number" wire:model="extend_guillotine_days" step="1" min="0" class="cyber-input">
                                </div>

                                @can('finance.approve')
                                <div style="display: flex; gap: 10px; margin-top: 20px;">
                                    <button type="button" wire:click="rejectTransaction" class="btn-action btn-danger" style="flex: 1;" onclick="confirm('Are you sure you want to REJECT this payment? The client will be notified immediately.') || event.stopImmediatePropagation()">
                                        <i data-lucide="ban" style="width: 14px; height: 14px;"></i> REJECT
                                    </button>
                                    <button type="submit" class="btn-action btn-success" style="flex: 2; background: rgba(0, 255, 102, 0.1);" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="approveTransaction"><i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> VERIFY & CLEAR FUNDS</span>
                                        <span wire:loading wire:target="approveTransaction">SECURING...</span>
                                    </button>
                                </div>
                                @endcan
                            </form>
                        @else
                            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 4px; text-align: center; border: 1px dashed rgba(255,255,255,0.2);">
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 10px;">THIS TRANSACTION HAS BEEN RESOLVED.</div>
                                <div class="status-badge {{ $selectedTx->status === 'Verified' ? 'badge-verified' : 'badge-rejected' }}" style="font-size: 0.8rem; padding: 8px 15px;">
                                    STATUS: {{ strtoupper($selectedTx->status) }}
                                </div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 15px;">
                                    ADJUDICATED BY SYSTEM COMMAND<br>
                                    {{ \Carbon\Carbon::parse($selectedTx->verified_at)->format('d M Y - H:i:s') }}
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
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