<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select, .cyber-textarea {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #FFFFFF; padding: 10px 12px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; border-radius: 2px; transition: 0.3s;
        }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: #00E5FF; outline: none; background: rgba(0, 229, 255, 0.05); }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        .cyber-textarea { resize: vertical; min-height: 80px; }
        
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

        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid transparent; background: transparent; color: #8A9BB2; }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: #00E5FF; color: #00E5FF; font-weight: bold; }

        /* Modals & Panels */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: #0A1128; border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; max-width: 420px; width: 90%; }
        .spinner { border: 2px solid rgba(0, 229, 255, 0.1); border-top: 2px solid #00E5FF; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .table-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; height: 100%; }
        th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase; text-align: left; }
        td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Inter', sans-serif; font-size: 0.8rem; color: #FFF; cursor: pointer; }
        tr.active-row { background: rgba(0, 229, 255, 0.1); border-left: 3px solid #00E5FF; }
        
        .form-panel { background: #03050A; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 25px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-label { display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px; text-transform: uppercase; }
        .form-section-title { font-family: 'Montserrat', sans-serif; font-size: 0.75rem; color: #00E5FF; text-transform: uppercase; border-bottom: 1px dashed rgba(0,229,255,0.3); padding-bottom: 5px; margin-bottom: 15px; margin-top: 20px; font-weight: bold; }
        .validation-error { color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; margin-top: 4px; display: block; }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px;">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">RECORD SECURED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 25px;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action btn-success" style="width: 100%;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">HR COMPLIANCE & TALENT TRACKING</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">LEGAL CONTRACTS & PERFORMANCE APPRAISALS</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="setTab('contracts')" class="nav-pill {{ $activeTab === 'contracts' ? 'active' : '' }}">LEGAL CONTRACTS</button>
            <button wire:click="setTab('appraisals')" class="nav-pill {{ $activeTab === 'appraisals' ? 'active' : '' }}">KPI APPRAISALS</button>
        </div>
    </div>

    @if(session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border-left: 2px solid #FF3366; padding: 10px; margin-bottom: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FF3366;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 400px 1fr; gap: 20px; align-items: start;">
        
        <div class="table-panel" style="max-height: 700px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Personnel Directory</th>
                        <th style="text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @if($activeTab === 'contracts')
                        @foreach($employees as $emp)
                            <tr wire:click="selectContract('{{ $emp->id }}')" class="{{ $selectedEmployeeId === $emp->id ? 'active-row' : '' }}" style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.05)'" onmouseout="this.style.background='transparent'">
                                <td>
                                    <div style="font-weight: bold; color: #FFF;">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 4px;">{{ $emp->employee_code }} | {{ $emp->tax_region }}</div>
                                </td>
                                <td style="text-align: right;">
                                    @if($emp->contract_type)
                                        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00FF66; border: 1px solid #00FF66; padding: 2px 6px; border-radius: 2px;">{{ $emp->contract_type }}</span>
                                    @else
                                        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FF3366; border: 1px solid #FF3366; padding: 2px 6px; border-radius: 2px;">MISSING</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        @foreach($appraisals as $emp)
                            <tr wire:click="selectAppraisal('{{ $emp->id }}')" class="{{ $selectedEmployeeId === $emp->id ? 'active-row' : '' }}" style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.05)'" onmouseout="this.style.background='transparent'">
                                <td>
                                    <div style="font-weight: bold; color: #FFF;">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 4px;">{{ $emp->employee_code }}</div>
                                </td>
                                <td style="text-align: right; color: #00E5FF;">
                                    <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div class="form-panel">
            @if(!$selectedEmployeeId)
                <div style="text-align: center; padding: 60px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border: 1px dashed rgba(255,255,255,0.1); border-radius: 4px;">
                    <i data-lucide="user-check" style="width: 32px; height: 32px; opacity: 0.5; margin-bottom: 10px;"></i><br>
                    > SELECT PERSONNEL FROM DIRECTORY TO INITIATE {{ $activeTab === 'contracts' ? 'CONTRACT BUILDER' : 'APPRAISAL' }}
                </div>
            @else
                
                @if($activeTab === 'contracts')
                    <form wire:submit.prevent="saveContract">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: #FFF; margin: 0;">CONTRACT GENERATOR</h2>
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #00E5FF;"><i data-lucide="file-signature" style="width: 14px; height: 14px; vertical-align: middle;"></i> LEGAL BINDING</span>
                        </div>

                        <div class="form-section-title">NATURE OF EMPLOYMENT (OHADA/ECOWAS COMPLIANCE)</div>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">Contract Type *</label>
                                <select wire:model.live="contract_type" class="cyber-select" required>
                                    <option value="CDI">CDI (Indefinite Term)</option>
                                    <option value="CDD">CDD (Fixed Term)</option>
                                    <option value="Probation">Probation / Stage</option>
                                    <option value="Freelance">Freelance / Consultant</option>
                                </select>
                                @error('contract_type') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Professional Category / Echelon</label>
                                <input type="text" wire:model="professional_category" class="cyber-input" placeholder="e.g. Category 8B">
                                @error('professional_category') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Standard Weekly Hours *</label>
                                <input type="number" wire:model="standard_weekly_hours" class="cyber-input" required min="1" max="60">
                                @error('standard_weekly_hours') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Hourly Rate Offered (Optional)</label>
                                <input type="number" step="0.01" wire:model="hourly_rate_offered" class="cyber-input">
                                @error('hourly_rate_offered') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="form-section-title">TERMS & DURATIONS</div>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">Probation End Date</label>
                                <input type="date" wire:model="probation_end_date" class="cyber-input">
                                @error('probation_end_date') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label" style="{{ $contract_type === 'CDD' ? 'color: #FFBD2E; font-weight: bold;' : '' }}">
                                    Contract End Date {{ $contract_type === 'CDD' ? '(REQUIRED FOR CDD) *' : '' }}
                                </label>
                                <input type="date" wire:model="contract_end_date" class="cyber-input" style="{{ $contract_type === 'CDD' ? 'border-color: #FFBD2E;' : '' }}" {{ $contract_type === 'CDD' ? 'required' : '' }}>
                                @error('contract_end_date') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div style="grid-column: span 2;">
                                <label class="form-label">Legal Notice Period (Days) *</label>
                                <input type="number" wire:model="notice_period_days" class="cyber-input" required min="0">
                                @error('notice_period_days') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div style="grid-column: span 2;">
                                <label class="form-label">Severance & Special Terms</label>
                                <textarea wire:model="severance_terms" class="cyber-textarea" placeholder="Input any negotiated severance matrix or non-compete clauses..."></textarea>
                                @error('severance_terms') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 20px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 20px;">
                            <button type="submit" class="btn-action btn-success" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveContract"><i data-lucide="save" style="width: 14px; height: 14px;"></i> SECURE CONTRACT TERMS</span>
                                <span wire:loading wire:target="saveContract">SECURING... <div class="spinner"></div></span>
                            </button>
                        </div>
                    </form>

                @else
                    <form wire:submit.prevent="submitAppraisal">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: #FFF; margin: 0;">KPI APPRAISAL CONSOLE</h2>
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #FFBD2E;"><i data-lucide="target" style="width: 14px; height: 14px; vertical-align: middle;"></i> PERFORMANCE TRACKING</span>
                        </div>

                        <div class="form-section-title">EVALUATION PERIOD & SCORING</div>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">Review Period Start *</label>
                                <input type="date" wire:model="review_period_start" class="cyber-input" required>
                                @error('review_period_start') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Review Period End *</label>
                                <input type="date" wire:model="review_period_end" class="cyber-input" required>
                                @error('review_period_end') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                            <div style="grid-column: span 2;">
                                <label class="form-label">Overall KPI Score (Out of 5.00) *</label>
                                <input type="number" step="0.01" min="0" max="5" wire:model="kpi_score_overall" class="cyber-input" style="font-size: 1.2rem; color: #00FF66; font-weight: bold;" required>
                                @error('kpi_score_overall') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="form-section-title">QUALITATIVE ASSESSMENT</div>
                        <div class="form-grid">
                            <div style="grid-column: span 2;">
                                <label class="form-label">Line Manager Comments</label>
                                <textarea wire:model="manager_comments" class="cyber-textarea" placeholder="Detail achievements, areas for improvement, and justifications for the KPI score..."></textarea>
                                @error('manager_comments') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 20px; align-items: center;">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">
                                <i data-lucide="info" style="width: 12px; height: 12px; vertical-align: middle;"></i> High scores can justify 'Prime de Performance' in the variable compensation ledger.
                            </div>
                            <button type="submit" class="btn-action btn-warning" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submitAppraisal"><i data-lucide="send" style="width: 14px; height: 14px;"></i> SUBMIT EVALUATION</span>
                                <span wire:loading wire:target="submitAppraisal">SUBMITTING... <div class="spinner" style="border-top-color: #FFBD2E;"></div></span>
                            </button>
                        </div>
                    </form>
                    
                    <div style="margin-top: 30px;">
                        <div class="form-section-title">ARCHIVED / PENDING REVIEWS</div>
                        @php
                            $userAppraisals = collect($appraisals)->where('id', $selectedEmployeeId);
                        @endphp
                        @if($userAppraisals->isEmpty())
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">No pending or recent appraisals found for this personnel.</div>
                        @else
                            <table style="width: 100%; border-collapse: collapse; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #E0E6ED;">
                                <thead>
                                    <tr>
                                        <th style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1);">End Date</th>
                                        <th style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1);">Score</th>
                                        <th style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1);">Status</th>
                                        <th style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($userAppraisals as $app)
                                        <tr>
                                            <td style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.02);">{{ \Carbon\Carbon::parse($app->review_period_end)->format('M Y') }}</td>
                                            <td style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.02); color: #00FF66; font-weight: bold;">{{ number_format($app->kpi_score_overall, 2) }}</td>
                                            <td style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.02);">{{ str_replace('_', ' ', $app->status) }}</td>
                                            <td style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right;">
                                                @if($app->status !== 'Finalized')
                                                    <button wire:click="finalizeAppraisal('{{ $app->appraisal_id }}')" style="background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 2px 8px; border-radius: 2px; cursor: pointer; font-size: 0.65rem;">FINALIZE</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endif
            @endif
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