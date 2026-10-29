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
        
        .btn-danger { border-color: #FF3366; color: #FF3366; }
        .btn-danger:hover:not(:disabled) { background: #FF3366; color: #FFF; box-shadow: 0 0 15px rgba(255, 51, 102, 0.4); }

        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid transparent; background: transparent; color: #8A9BB2; }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: #00E5FF; color: #00E5FF; font-weight: bold; }

        /* Status Badges */
        .status-badge { padding: 4px 8px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; border: 1px solid; }
        .status-draft { color: #8A9BB2; border-color: #8A9BB2; background: rgba(138, 155, 178, 0.1); }
        .status-review { color: #FFBD2E; border-color: #FFBD2E; background: rgba(255, 189, 46, 0.1); }
        .status-approved { color: #00E5FF; border-color: #00E5FF; background: rgba(0, 229, 255, 0.1); }
        .status-disbursed { color: #00FF66; border-color: #00FF66; background: rgba(0, 255, 102, 0.1); }

        /* Modals & Panels */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: #0A1128; border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; max-width: 420px; width: 90%; }
        .spinner { border: 2px solid rgba(0, 229, 255, 0.1); border-top: 2px solid #00E5FF; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .table-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; }
        th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase; text-align: left; }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Inter', sans-serif; font-size: 0.8rem; color: #FFF; }
        
        .audit-trail { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; display: flex; gap: 15px; margin-top: 10px; }
        .audit-step { background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 2px; border: 1px solid rgba(255,255,255,0.05); }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px;">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">WORKFLOW UPDATED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 25px;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action btn-success" style="width: 100%;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">TREASURY COMMAND CENTER</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">PAYROLL LEDGER & DISBURSEMENT PIPELINE</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="setTab('ledger')" class="nav-pill {{ $activeTab === 'ledger' ? 'active' : '' }}">MASTER LEDGER</button>
            <button wire:click="setTab('inspector')" class="nav-pill {{ $activeTab === 'inspector' ? 'active' : '' }}">PAYSLIP INSPECTOR</button>
        </div>
    </div>

    @if(session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border-left: 2px solid #FF3366; padding: 10px; margin-bottom: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FF3366;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    @if($activeTab === 'ledger')
        
        @if(!$selectedRunId)
            <div style="background: rgba(0, 229, 255, 0.05); border: 1px dashed rgba(0, 229, 255, 0.3); padding: 20px; border-radius: 4px; margin-bottom: 30px; display: flex; align-items: flex-end; gap: 15px;">
                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">Period Month</label>
                    <input type="number" min="1" max="12" wire:model="draftPeriodMonth" class="cyber-input" style="width: 100px;">
                </div>
                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">Period Year</label>
                    <input type="number" min="2020" wire:model="draftPeriodYear" class="cyber-input" style="width: 120px;">
                </div>
                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">Target Jurisdiction</label>
                    <select wire:model="targetRegion" class="cyber-select" style="width: 200px;">
                        <option value="ALL">Global (All Regions)</option>
                        <option value="NG">Nigeria (PITA)</option>
                        <option value="CM">Cameroon (CGI)</option>
                        <option value="KE">Kenya (KRA)</option>
                    </select>
                </div>
                <button wire:click="generateDraftRun" class="btn-action" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generateDraftRun"><i data-lucide="play" style="width: 14px; height: 14px;"></i> GENERATE DRAFT LEDGER</span>
                    <span wire:loading wire:target="generateDraftRun">CALCULATING... <div class="spinner"></div></span>
                </button>
            </div>

            <div class="table-panel">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Ref Number</th>
                            <th>Pay Period</th>
                            <th>Jurisdiction</th>
                            <th>Pipeline Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payrollRuns as $run)
                            <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                                <td style="font-family: 'Roboto Mono', monospace; color: #00E5FF; font-weight: bold;">{{ $run->reference_number }}</td>
                                <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">
                                    {{ \Carbon\Carbon::parse($run->pay_period_start)->format('M Y') }}
                                </td>
                                <td>{{ $run->region_code }}</td>
                                <td>
                                    <span class="status-badge 
                                        {{ $run->status === 'Draft' ? 'status-draft' : '' }}
                                        {{ $run->status === 'Pending_Review' ? 'status-review' : '' }}
                                        {{ $run->status === 'Approved' ? 'status-approved' : '' }}
                                        {{ $run->status === 'Disbursed' ? 'status-disbursed' : '' }}">
                                        {{ str_replace('_', ' ', $run->status) }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button wire:click="viewRun('{{ $run->id }}')" style="background: transparent; border: 1px solid #8A9BB2; color: #8A9BB2; padding: 4px 10px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; border-radius: 2px;">INSPECT RUN</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> NO HISTORICAL PAYROLL LEDGERS DETECTED.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            <div style="margin-bottom: 20px;">
                <button wire:click="closeRunView" style="background: transparent; border: none; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                    <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i> BACK TO MASTER LEDGER
                </button>
            </div>

            <div style="background: rgba(10, 17, 40, 0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 25px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 20px;">
                    <div>
                        <h2 style="font-family: 'Montserrat', sans-serif; color: #FFF; margin: 0; font-size: 1.2rem;">LEDGER: {{ $runDetails['reference_number'] }}</h2>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; margin-top: 5px;">
                            Period: {{ \Carbon\Carbon::parse($runDetails['pay_period_start'])->format('F Y') }} | Region: {{ $runDetails['region_code'] }}
                        </div>
                        
                        <div class="audit-trail">
                            <div class="audit-step">
                                <span style="color: #FFF;">Maker:</span> {{ $runDetails['maker_first'] ?? 'Pending' }} {{ $runDetails['maker_last'] ?? '' }}
                            </div>
                            <div class="audit-step">
                                <span style="color: #FFBD2E;">Checker:</span> {{ $runDetails['checker_first'] ?? 'Pending' }} {{ $runDetails['checker_last'] ?? '' }}
                            </div>
                            <div class="audit-step">
                                <span style="color: #00FF66;">Approver:</span> {{ $runDetails['approver_first'] ?? 'Pending' }} {{ $runDetails['approver_last'] ?? '' }}
                            </div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge 
                            {{ $runDetails['status'] === 'Draft' ? 'status-draft' : '' }}
                            {{ $runDetails['status'] === 'Pending_Review' ? 'status-review' : '' }}
                            {{ $runDetails['status'] === 'Approved' ? 'status-approved' : '' }}
                            {{ $runDetails['status'] === 'Disbursed' ? 'status-disbursed' : '' }}" style="font-size: 0.8rem; padding: 6px 12px;">
                            CURRENT STATE: {{ str_replace('_', ' ', $runDetails['status']) }}
                        </span>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; justify-content: flex-end; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                    
                    @if($runDetails['status'] === 'Draft')
                        <button wire:click="submitForReview('{{ $selectedRunId }}')" class="btn-action btn-warning">
                            <i data-lucide="send" style="width: 14px; height: 14px;"></i> SUBMIT FOR CHECKER REVIEW
                        </button>
                    @endif

                    @if($runDetails['status'] === 'Pending_Review')
                        <button wire:click="rejectRun('{{ $selectedRunId }}')" class="btn-action btn-danger">
                            <i data-lucide="x-circle" style="width: 14px; height: 14px;"></i> REJECT TO DRAFT
                        </button>
                        <button wire:click="approveRun('{{ $selectedRunId }}')" class="btn-action btn-success">
                            <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> CFO APPROVE & LOCK LEDGER
                        </button>
                    @endif

                    @if($runDetails['status'] === 'Approved')
                        <button wire:click="rejectRun('{{ $selectedRunId }}')" class="btn-action btn-danger">
                            <i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i> REVOKE APPROVAL
                        </button>
                        <button wire:click="disburseRun('{{ $selectedRunId }}')" class="btn-action" style="border-color: #00FF66; color: #00FF66; background: rgba(0,255,102,0.1);">
                            <i data-lucide="banknote" style="width: 14px; height: 14px;"></i> MARK AS DISBURSED
                        </button>
                    @endif

                    @if($runDetails['status'] === 'Disbursed')
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #00FF66; display: flex; align-items: center; gap: 5px;">
                            <i data-lucide="lock" style="width: 14px; height: 14px;"></i> LEDGER PERMANENTLY LOCKED
                        </div>
                        <button class="btn-action" disabled style="opacity: 0.5;">EXPORT BANK REMITTANCE CSV (Phase 10)</button>
                    @endif

                </div>
            </div>

            <div class="table-panel">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Personnel</th>
                            <th>Cash Gross</th>
                            <th>Taxable Gross (inc. BIK)</th>
                            <th>Total Tax/Pension</th>
                            <th style="text-align: right; color: #00FF66;">Net Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($runLines as $line)
                            <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                                <td>
                                    <div style="font-weight: bold; color: #FFF;">{{ $line->first_name }} {{ $line->last_name }}</div>
                                    <div style="font-size: 0.65rem; color: #8A9BB2; font-family: 'Roboto Mono', monospace; margin-top: 4px;">{{ $line->employee_code }} | {{ $line->tax_region }}</div>
                                </td>
                                <td style="font-family: 'Roboto Mono', monospace;">{{ number_format($line->gross_pay, 2) }}</td>
                                <td style="font-family: 'Roboto Mono', monospace; color: #FFBD2E;">{{ number_format($line->taxable_gross ?? 0, 2) }}</td>
                                <td style="font-family: 'Roboto Mono', monospace; color: #FF3366;">-{{ number_format($line->total_deductions ?? 0, 2) }}</td>
                                <td style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.95rem; font-weight: bold; color: #00FF66;">
                                    {{ number_format($line->net_pay ?? 0, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> RUN GENERATED BUT NO APPLICABLE EMPLOYEES FOUND FOR THIS PERIOD.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @endif

    @endif

    @if($activeTab === 'inspector')
        <div style="text-align: center; padding: 60px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border: 1px dashed rgba(255,255,255,0.1); border-radius: 4px;">
            <i data-lucide="file-text" style="width: 32px; height: 32px; opacity: 0.5; margin-bottom: 10px;"></i><br>
            > PAYSLIP INSPECTOR MODULE WILL BE IMPLEMENTED IN PHASE 11
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