<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select, .cyber-textarea {
            width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(0, 229, 255, 0.2);
            color: #FFFFFF; padding: 12px 15px; font-family: 'Roboto Mono', monospace;
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

        .btn-warning { border-color: #FFBD2E; color: #FFBD2E; }
        .btn-warning:hover { background: #FFBD2E; color: #0A1128; box-shadow: 0 0 15px rgba(255, 189, 46, 0.4); }

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
        .badge-paid { color: #00FF66; border-color: #00FF66; background: rgba(0, 255, 102, 0.1); }
        .badge-overdue { color: #FF3366; border-color: #FF3366; background: rgba(255, 51, 102, 0.1); }
        .badge-cancelled { color: #8A9BB2; border-color: #8A9BB2; background: rgba(255, 255, 255, 0.05); }

        /* --- MODAL OVERLAY --- */
        .glass-modal {
            position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px);
            z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-box {
            background: rgba(10, 17, 40, 0.95); border: 1px solid rgba(0, 229, 255, 0.3); border-top: 4px solid #00E5FF;
            padding: 30px; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); width: 100%; max-width: 700px;
            max-height: 90vh; overflow-y: auto;
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; padding-bottom: 15px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.8rem; font-weight: 900; letter-spacing: 1px; color: #FFF; margin: 0;">CORPORATE INVOICE LEDGER</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; margin: 5px 0 0 0;">> Accounts Receivable & Guillotine Protocol Matrix</p>
        </div>
        <div style="display: flex; gap: 15px;">
            <button wire:click="enforceGuillotineProtocol" class="btn-action btn-danger" onclick="confirm('WARNING: Executing this audit will immediately lock Client OS access for any account with an expired due date. Proceed?') || event.stopImmediatePropagation()">
                <i data-lucide="skull" style="width: 16px; height: 16px;"></i> EXECUTE GUILLOTINE AUDIT
            </button>
            <button wire:click="$set('showCreateModal', true)" class="btn-action" style="background: rgba(0, 229, 255, 0.1);">
                <i data-lucide="receipt" style="width: 16px; height: 16px;"></i> DRAFT INVOICE
            </button>
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
        <button wire:click="setTab('Pending')" class="ws-tab {{ $activeTab === 'Pending' ? 'active' : '' }}">[ PENDING_COLLECTION ]</button>
        <button wire:click="setTab('Overdue')" class="ws-tab {{ $activeTab === 'Overdue' ? 'active' : '' }}">[ OVERDUE_LOCKED ]</button>
        <button wire:click="setTab('Paid')" class="ws-tab {{ $activeTab === 'Paid' ? 'active' : '' }}">[ CLEARED_PAID ]</button>
        <button wire:click="setTab('Cancelled')" class="ws-tab {{ $activeTab === 'Cancelled' ? 'active' : '' }}">[ VOIDED ]</button>
    </div>

    <div class="ledger-panel">
        <div style="overflow-x: auto;">
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Invoice Reference</th>
                        <th>Project / Client Designation</th>
                        <th style="text-align: right;">Total Amount</th>
                        <th style="text-align: center;">Due Date / Status</th>
                        <th style="text-align: right;">Commands</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                        <tr>
                            <td>
                                <div style="font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1rem; color: #FFF;">{{ $inv->invoice_number }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 4px;">{{ strtoupper($inv->invoice_type) }}</div>
                            </td>
                            <td>
                                <div style="font-family: 'Inter', sans-serif; font-weight: bold; color: #00E5FF; font-size: 0.9rem;">{{ $inv->project_title }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; margin-top: 4px;">
                                    <i data-lucide="building-2" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle;"></i> {{ $inv->client_name }}
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; color: #FFF; font-size: 1.1rem;">
                                    <span style="font-size: 0.75rem; color: #8A9BB2;">{{ $inv->currency }}</span> {{ number_format($inv->total_amount, 2) }}
                                </div>
                            </td>
                            <td style="text-align: center;">
                                @php
                                    $badgeClass = match($inv->status) {
                                        'Pending' => 'badge-pending',
                                        'Paid' => 'badge-paid',
                                        'Overdue' => 'badge-overdue',
                                        'Cancelled' => 'badge-cancelled',
                                        default => 'badge-pending'
                                    };
                                @endphp
                                <div class="status-badge {{ $badgeClass }}">{{ strtoupper($inv->status) }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 6px;">
                                    {{ \Carbon\Carbon::parse($inv->due_date)->format('d M, Y') }}
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button class="btn-action" style="padding: 6px 12px; font-size: 0.7rem;" onclick="alert('PDF Engine (Phase 16) initializing.')">
                                        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i> PDF
                                    </button>
                                    
                                    @if($inv->status === 'Pending')
                                        <button wire:click="cancelInvoice('{{ $inv->id }}')" class="btn-action btn-danger" style="padding: 6px 12px; font-size: 0.7rem;" onclick="confirm('Are you sure you want to completely void this invoice? This action cannot be fully erased from the audit logs.') || event.stopImmediatePropagation()">
                                            <i data-lucide="ban" style="width: 14px; height: 14px;"></i> VOID
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                > NO INVOICES FOUND IN {{ strtoupper($activeTab) }} STATE.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showCreateModal)
        <div class="glass-modal">
            <div class="modal-box">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #00E5FF; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <span>> DRAFT PHASE INVOICE</span>
                    <button wire:click="$set('showCreateModal', false)" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer;"><i data-lucide="x" style="width: 18px; height: 18px;"></i></button>
                </div>

                <form wire:submit.prevent="draftSubsequentInvoice" style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">TARGET BATTLESPACE (PROJECT)</label>
                        <select wire:model.live="new_project_id" class="cyber-select" required>
                            <option value="">-- SELECT PROJECT --</option>
                            @foreach($activeProjects as $proj)
                                <option value="{{ $proj->id }}">[{{ $proj->currency }}] {{ strtoupper($proj->client_name) }} - {{ $proj->title }}</option>
                            @endforeach
                        </select>
                        @error('new_project_id') <span style="color: #FF3366; font-size: 0.65rem;">{{ $message }}</span> @enderror
                    </div>

                    @if(!empty($projectPhases) && count($projectPhases) > 0)
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">TARGET PHASE (OPTIONAL)</label>
                            <select wire:model="new_phase_id" class="cyber-select">
                                <option value="">-- GENERAL PROJECT INVOICE --</option>
                                @foreach($projectPhases as $phase)
                                    <option value="{{ $phase->id }}">Phase {{ $phase->phase_number }}: {{ $phase->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 8px;">BASE AMOUNT (EXCL. VAT)</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 15px; top: 12px; color: #8A9BB2; font-family: 'Roboto Mono', monospace;">{{ $new_currency }}</span>
                                <input type="number" wire:model="new_amount_due" step="0.01" min="0.01" class="cyber-input" style="padding-left: 50px; border-color: rgba(0,229,255,0.4); color: #00E5FF; font-weight: bold;" required>
                            </div>
                            @error('new_amount_due') <span style="color: #FF3366; font-size: 0.65rem;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">PAYMENT TERMS (GUILLOTINE LOCK)</label>
                            <select wire:model="new_payment_terms" class="cyber-select" required>
                                <option value="Due on Receipt">Due on Receipt (0 Days)</option>
                                <option value="Net 7">Net 7 Days</option>
                                <option value="Net 10">Net 10 Days</option>
                                <option value="Net 15">Net 15 Days</option>
                                <option value="Net 30">Net 30 Days</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">REMITTANCE ROUTING</label>
                        <select wire:model="new_remittance_id" class="cyber-select" required>
                            <option value="">-- SELECT RECEIVING TREASURY --</option>
                            @foreach($remittanceAccounts as $acc)
                                <option value="{{ $acc->id }}">[{{ strtoupper($acc->type) }}] {{ $acc->provider_name }} - {{ $acc->account_name }}</option>
                            @endforeach
                        </select>
                        @error('new_remittance_id') <span style="color: #FF3366; font-size: 0.65rem;">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 8px;">INVOICE DESCRIPTION / MEMO</label>
                        <textarea wire:model="new_description" class="cyber-textarea" rows="2" placeholder="e.g. Phase 2 Architecture Sign-off" required></textarea>
                    </div>

                    <div style="background: rgba(0, 229, 255, 0.05); border-left: 2px solid #00E5FF; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; line-height: 1.5;">
                        <strong>SYSTEM NOTE:</strong> VAT will be automatically calculated based on the global tax matrix. The exact Forex rate (if applicable) will be locked the moment you click dispatch.
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="btn-action" style="flex: 1; border-color: #8A9BB2; color: #8A9BB2;">ABORT</button>
                        <button type="submit" class="btn-action" style="flex: 2; background: #00E5FF; color: #0A1128;" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="draftSubsequentInvoice">CRYPTOGRAPHICALLY SEAL & DISPATCH</span>
                            <span wire:loading wire:target="draftSubsequentInvoice">TRANSMITTING...</span>
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