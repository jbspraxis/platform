<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #FFFFFF; padding: 10px 12px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; border-radius: 2px; transition: 0.3s;
        }
        .cyber-input:focus, .cyber-select:focus { border-color: #00E5FF; outline: none; background: rgba(0, 229, 255, 0.05); }
        .cyber-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        
        .locked-input { width: 100%; background: rgba(0,0,0,0.5); border: 1px dashed rgba(255,255,255,0.2); color: #00E5FF; padding: 10px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; border-radius: 2px; cursor: not-allowed; }
        
        .btn-action { background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-action:hover:not(:disabled) { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-action:disabled { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.2); cursor: not-allowed; }
        .btn-warning { border-color: #FFBD2E; color: #FFBD2E; }
        .btn-warning:hover:not(:disabled) { background: #FFBD2E; color: #0A1128; box-shadow: 0 0 15px rgba(255, 189, 46, 0.4); }

        /* --- WIZARD PROGRESS BAR --- */
        .wizard-tracker { display: flex; justify-content: space-between; margin-bottom: 25px; position: relative; }
        .wizard-tracker::before { content: ''; position: absolute; top: 12px; left: 0; right: 0; height: 2px; background: rgba(255,255,255,0.1); z-index: 1; }
        .wizard-step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 8px; width: 33%; }
        .step-circle { width: 26px; height: 26px; border-radius: 50%; background: #0A1128; border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; color: #8A9BB2; transition: 0.3s; }
        .step-label { font-family: 'Montserrat', sans-serif; font-size: 0.65rem; color: #8A9BB2; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; text-align: center; }
        
        .wizard-step.active .step-circle { border-color: #00E5FF; background: rgba(0, 229, 255, 0.2); color: #00E5FF; box-shadow: 0 0 10px rgba(0, 229, 255, 0.4); }
        .wizard-step.active .step-label { color: #00E5FF; }
        .wizard-step.completed .step-circle { border-color: #00FF66; background: #00FF66; color: #0A1128; }
        .wizard-step.completed .step-label { color: #00FF66; }

        /* --- POKA-YOKE CALCULATOR PANEL --- */
        .calc-panel { background: rgba(0, 229, 255, 0.05); border: 1px solid rgba(0, 229, 255, 0.3); border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        .calc-header { font-family: 'Montserrat', sans-serif; font-size: 0.75rem; font-weight: bold; color: #00E5FF; display: flex; align-items: center; gap: 8px; margin-bottom: 15px; border-bottom: 1px dashed rgba(0, 229, 255, 0.3); padding-bottom: 8px; }

        /* --- TOGGLE SWITCH (BIK) --- */
        .cyber-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; margin-bottom: 10px; }
        .cyber-toggle input { display: none; }
        .toggle-box { width: 36px; height: 18px; background: rgba(255,255,255,0.1); border-radius: 10px; position: relative; transition: 0.3s; border: 1px solid rgba(255,255,255,0.2); }
        .toggle-box::after { content: ''; position: absolute; top: 2px; left: 2px; width: 12px; height: 12px; background: #8A9BB2; border-radius: 50%; transition: 0.3s; }
        .cyber-toggle input:checked + .toggle-box { background: rgba(0, 255, 102, 0.2); border-color: #00FF66; }
        .cyber-toggle input:checked + .toggle-box::after { left: 20px; background: #00FF66; box-shadow: 0 0 5px #00FF66; }
        .cyber-toggle input:checked ~ .toggle-label { color: #00FF66; font-weight: bold; }

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: #0A1128; border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 420px; width: 90%; }
        .spinner { border: 3px solid rgba(0, 229, 255, 0.1); border-top: 3px solid #00E5FF; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid transparent; background: transparent; color: #8A9BB2; }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: #00E5FF; color: #00E5FF; font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: #FFF; }
        .validation-error { color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; margin-top: 4px; display: block; }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(0,255,102,0.4);">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">PERSONNEL INTEGRATED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 25px; line-height: 1.5;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action" style="width: 100%; background: #00FF66; color: #0A1128; border-color: #00FF66;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">EMPLOYEE REGISTRY</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">HUMAN CAPITAL MDM & PAYROLL MATRIX</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="setTab('ALL')" class="nav-pill {{ $activeTab === 'ALL' ? 'active' : '' }}">GLOBAL HQ</button>
            <button wire:click="setTab('CM')" class="nav-pill {{ $activeTab === 'CM' ? 'active' : '' }}">CAMEROON</button>
            <button wire:click="setTab('NG')" class="nav-pill {{ $activeTab === 'NG' ? 'active' : '' }}">NIGERIA</button>
            <button wire:click="setTab('KE')" class="nav-pill {{ $activeTab === 'KE' ? 'active' : '' }}">KENYA</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 450px 1fr; gap: 30px; align-items: start;">
        
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 25px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
            
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #FFF; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                <span>> {{ $editMode ? 'MODIFY' : 'REGISTER' }} PERSONNEL</span>
                @if($editMode) <button wire:click="resetForm" style="background: rgba(255,51,102,0.1); color: #FF3366; padding: 3px 8px; font-size: 0.6rem; border-radius: 2px; border: 1px solid #FF3366; cursor: pointer;">CANCEL EDIT</button> @endif
            </div>

            <div class="wizard-tracker">
                <div class="wizard-step {{ $currentStep === 1 ? 'active' : ($currentStep > 1 ? 'completed' : '') }}">
                    <div class="step-circle">1</div>
                    <div class="step-label">Identity<br>& Org Chart</div>
                </div>
                <div class="wizard-step {{ $currentStep === 2 ? 'active' : ($currentStep > 2 ? 'completed' : '') }}">
                    <div class="step-circle">2</div>
                    <div class="step-label">Statutory<br>Compliance</div>
                </div>
                <div class="wizard-step {{ $currentStep === 3 ? 'active' : '' }}">
                    <div class="step-circle">3</div>
                    <div class="step-label">Treasury &<br>Compensation</div>
                </div>
            </div>

            @if ($errors->any())
                <div style="background: rgba(255,51,102,0.1); border-left: 2px solid #FF3366; padding: 10px; margin-bottom: 20px;">
                    <ul style="color: #FF3366; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; margin: 0; padding-left: 15px;">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <form wire:submit.prevent="saveEmployee">
                
                @if($currentStep === 1)
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">System User Link *</label>
                            <select wire:model="user_id" required class="cyber-select" {{ $editMode ? 'disabled' : '' }}>
                                <option value="">-- Select Active User --</option>
                                @foreach($availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                            @error('user_id') <span class="validation-error">{{ $message }}</span> @enderror
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Employee Code</label>
                                <input type="text" wire:model="employee_code" readonly class="locked-input">
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Hire Date *</label>
                                <input type="date" wire:model="hire_date" required class="cyber-input">
                                @error('hire_date') <span class="validation-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Job Classification / Grade *</label>
                            <input type="text" wire:model="job_classification" required class="cyber-input" placeholder="e.g. Senior Dev - Level 3">
                            @error('job_classification') <span class="validation-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Direct Line Manager</label>
                            <select wire:model="line_manager_id" class="cyber-select">
                                <option value="">-- No Direct Report (C-Level) --</option>
                                @foreach($allManagers as $mgr)
                                    <option value="{{ $mgr->id }}">{{ $mgr->first_name }} {{ $mgr->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Marital Status</label>
                                <select wire:model="marital_status" required class="cyber-select">
                                    <option value="Single">Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widowed">Widowed</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Dependent Children</label>
                                <input type="number" wire:model="dependents_count" required min="0" class="cyber-input">
                            </div>
                        </div>
                    </div>
                @endif

                @if($currentStep === 2)
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="background: rgba(255,189,46,0.05); border: 1px solid rgba(255,189,46,0.3); padding: 15px; border-radius: 4px;">
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; color: #FFBD2E; margin-bottom: 8px;">JURISDICTION & TAX REGION *</label>
                            <select wire:model.live="tax_region" required class="cyber-select" style="border-color: #FFBD2E;">
                                <option value="GLOBAL">Global / Expatriate (No Local Tax Assumed)</option>
                                <option value="CM">Cameroon (OHADA / CGI Compliance)</option>
                                <option value="NG">Nigeria (PITA / PRA Compliance)</option>
                                <option value="KE">Kenya (KRA Compliance)</option>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Tax ID (TIN / NIU)</label>
                                <input type="text" wire:model="tin_number" class="cyber-input" placeholder="Required for Tax Clearance">
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">National ID / Passport</label>
                                <input type="text" wire:model="national_id" class="cyber-input">
                            </div>
                        </div>

                        @if($tax_region === 'CM')
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin-bottom: 4px;">CNPS Number (Social Security)</label>
                                <input type="text" wire:model="social_security_number" class="cyber-input" style="border-color: rgba(0,229,255,0.3);">
                            </div>
                        @elseif($tax_region === 'NG')
                            <div style="border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 15px;">
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00FF66; margin-bottom: 4px;">State of Residence (PAYE Routing) *</label>
                                <input type="text" wire:model="state_of_residence" class="cyber-input" placeholder="e.g. Lagos, FCT Abuja">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00FF66; margin-bottom: 4px;">PFA Name *</label>
                                    <input type="text" wire:model="pfa_name" class="cyber-input" placeholder="e.g. Stanbic IBTC">
                                </div>
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00FF66; margin-bottom: 4px;">RSA PIN *</label>
                                    <input type="text" wire:model="rsa_number" class="cyber-input">
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00FF66; margin-bottom: 4px;">NHF Number</label>
                                <input type="text" wire:model="nhf_number" class="cyber-input">
                            </div>
                        @elseif($tax_region === 'KE')
                            <div style="border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 15px;">
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FF3366; margin-bottom: 4px;">KRA PIN *</label>
                                <input type="text" wire:model="kra_pin" class="cyber-input" style="border-color: rgba(255, 51, 102, 0.4);">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FF3366; margin-bottom: 4px;">NSSF Number *</label>
                                    <input type="text" wire:model="nssf_number" class="cyber-input" style="border-color: rgba(255, 51, 102, 0.4);">
                                </div>
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FF3366; margin-bottom: 4px;">NHIF Number *</label>
                                    <input type="text" wire:model="nhif_number" class="cyber-input" style="border-color: rgba(255, 51, 102, 0.4);">
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($currentStep === 3)
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Frequency</label>
                                <select wire:model="pay_frequency" class="cyber-select"><option value="Monthly">Monthly</option></select>
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Currency</label>
                                <select wire:model="currency" class="cyber-select">
                                    <option value="USD">USD</option>
                                    <option value="XAF">XAF</option>
                                    <option value="NGN">NGN</option>
                                    <option value="KES">KES</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Method</label>
                                <select wire:model="payment_method" class="cyber-select"><option value="Bank Transfer">Bank</option><option value="Crypto">Crypto</option></select>
                            </div>
                        </div>

                        <div class="calc-panel">
                            <div class="calc-header">
                                <i data-lucide="cpu" style="width: 14px; height: 14px;"></i> POKA-YOKE ALGORITHMIC ENGINE (V2.0)
                            </div>
                            
                            @if(session()->has('gross_up_success'))
                                <div style="color: #00FF66; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-bottom: 10px; font-weight: bold;">
                                    ✓ {{ session('gross_up_success') }}
                                </div>
                            @endif

                            <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: end; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #00E5FF; margin-bottom: 4px;">1. Net-to-Gross Estimator (Target Net)</label>
                                    <input type="number" step="0.01" wire:model="target_net_pay" class="cyber-input" style="border-color: rgba(0,229,255,0.4);" placeholder="Enter desired take-home pay...">
                                </div>
                                <button type="button" wire:click="estimateGrossFromNet" class="btn-action" style="padding: 10px 15px;">
                                    <span wire:loading.remove wire:target="estimateGrossFromNet">GROSS UP</span>
                                    <div wire:loading wire:target="estimateGrossFromNet" class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></div>
                                </button>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: end; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 15px;">
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; font-weight: bold; margin-bottom: 4px;">2. Total Gross Compensation *</label>
                                    <input type="number" step="0.01" wire:model="total_gross" class="cyber-input" style="font-size: 1.1rem; color: #FFF; font-weight: bold;" required>
                                    @error('total_gross') <span class="validation-error" style="color:#FF3366; font-size: 0.6rem;">{{ $message }}</span> @enderror
                                </div>
                                <button type="button" wire:click="autoSplitCompensation" class="btn-action btn-warning" style="padding: 10px 15px;">
                                    <span wire:loading.remove wire:target="autoSplitCompensation">APPLY LEGAL SPLIT</span>
                                    <div wire:loading wire:target="autoSplitCompensation" class="spinner" style="width: 16px; height: 16px; border-width: 2px; border-top-color: #FFBD2E;"></div>
                                </button>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Basic Salary *</label>
                                <input type="number" step="0.01" wire:model="base_salary" class="cyber-input" style="color: #00FF66;" required>
                                @error('base_salary') <span class="validation-error" style="color:#FF3366; font-size: 0.6rem;">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Housing Allowance</label>
                                <input type="number" step="0.01" wire:model="allowance_housing" class="cyber-input">
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Transport Allowance</label>
                                <input type="number" step="0.01" wire:model="allowance_transport" class="cyber-input">
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Utility Allowance</label>
                                <input type="number" step="0.01" wire:model="allowance_utility" class="cyber-input">
                            </div>
                        </div>

                        <div style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 4px;">
                            <label style="display: block; font-family: 'Montserrat', sans-serif; font-size: 0.75rem; font-weight: bold; color: #FFF; margin-bottom: 10px;">Benefits In Kind (BIK) Triggers</label>
                            
                            <label class="cyber-toggle">
                                <input type="checkbox" wire:model="bik_housing_provided">
                                <div class="toggle-box"></div>
                                <span class="toggle-label">Company Housing Provided (Triggers +15% CGI Base)</span>
                            </label>
                            <label class="cyber-toggle">
                                <input type="checkbox" wire:model="bik_vehicle_provided">
                                <div class="toggle-box"></div>
                                <span class="toggle-label">Company Vehicle Provided (Triggers +10% CGI Base)</span>
                            </label>
                            <label class="cyber-toggle">
                                <input type="checkbox" wire:model="bik_domestic_staff">
                                <div class="toggle-box"></div>
                                <span class="toggle-label">Domestic Staff Provided (Triggers +5% CGI Base)</span>
                            </label>
                        </div>

                    </div>
                @endif

                <div style="display: flex; justify-content: space-between; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <button type="button" wire:click="prevStep" class="btn-action" style="border-color: #8A9BB2; color: #8A9BB2; visibility: {{ $currentStep > 1 ? 'visible' : 'hidden' }};">
                        <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i> BACK
                    </button>
                    
                    @if($currentStep < 3)
                        <button type="button" wire:click="nextStep" class="btn-action" style="background: rgba(0, 229, 255, 0.1);">
                            NEXT STEP <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                        </button>
                    @else
                        <button type="submit" wire:loading.attr="disabled" class="btn-action" style="background: #00FF66; color: #0A1128; border-color: #00FF66;">
                            <span wire:loading.remove wire:target="saveEmployee"><i data-lucide="save" style="width: 14px; height: 14px;"></i> COMMIT TO LEDGER</span>
                            <span wire:loading wire:target="saveEmployee">ENCRYPTING...</span>
                        </button>
                    @endif
                </div>

            </form>
        </div>

        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; max-height: 85vh;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase;">Personnel & Role</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase;">Tax & Compliance</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: right; text-transform: uppercase;">Total Gross</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; text-transform: uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                        @php 
                            $empTotalGross = $emp->base_salary + $emp->allowance_housing + $emp->allowance_transport + $emp->allowance_utility;
                            // Compliance Warnings (Includes Phase 13 Kenya Checks)
                            $missingIds = [];
                            if ($emp->tax_region === 'NG' && (!$emp->pfa_name || !$emp->rsa_number)) $missingIds[] = 'PRA Details Missing';
                            if ($emp->tax_region === 'CM' && !$emp->social_security_number) $missingIds[] = 'CNPS Missing';
                            if ($emp->tax_region === 'KE' && (!$emp->kra_pin || !$emp->nssf_number || !$emp->nhif_number)) $missingIds[] = 'KRA/NSSF/NHIF Missing';
                        @endphp
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: #FFF;">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                <div style="font-size: 0.7rem; color: #00E5FF; margin-top: 2px;">{{ $emp->job_classification }}</div>
                                @if($emp->manager_first)
                                    <div style="font-size: 0.6rem; color: #8A9BB2; margin-top: 4px; font-family: 'Roboto Mono', monospace;"><i data-lucide="corner-down-right" style="width: 10px; height: 10px; display: inline-block; opacity: 0.5;"></i> Reports to: {{ $emp->manager_first }} {{ $emp->manager_last }}</div>
                                @endif
                            </td>
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                <span style="display: inline-block; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 2px; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; color: {{ $emp->tax_region === 'NG' ? '#00FF66' : ($emp->tax_region === 'CM' ? '#00E5FF' : ($emp->tax_region === 'KE' ? '#FF3366' : '#FFBD2E')) }}; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 4px;">{{ $emp->tax_region }} REGIME</span>
                                
                                @if(!empty($missingIds))
                                    @foreach($missingIds as $warning)
                                        <div style="font-size: 0.6rem; color: #FF3366; font-family: 'Roboto Mono', monospace; margin-top: 2px;">⚠️ {{ $warning }}</div>
                                    @endforeach
                                @else
                                    <div style="font-size: 0.6rem; color: #8A9BB2; font-family: 'Roboto Mono', monospace; margin-top: 2px;">✓ IDs Verified</div>
                                @endif
                            </td>
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right;">
                                <div style="font-size: 0.95rem; font-weight: bold; color: #FFF; font-family: 'Roboto Mono', monospace;">{{ number_format($empTotalGross, 2) }} <span style="font-size: 0.7rem; color: #00E5FF;">{{ $emp->currency }}</span></div>
                                <div style="font-size: 0.6rem; color: #8A9BB2; margin-top: 2px; font-family: 'Roboto Mono', monospace;">BASE: {{ number_format($emp->base_salary, 0) }} | ALLW: {{ number_format($empTotalGross - $emp->base_salary, 0) }}</div>
                            </td>
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center; vertical-align: middle;">
                                <button wire:click="editEmployee('{{ $emp->id }}')" style="background: transparent; border: none; color: #FFBD2E; cursor: pointer; font-size: 0.75rem; font-weight: bold; margin-right: 10px;">EDIT</button>
                                <button wire:click="toggleStatus('{{ $emp->id }}', {{ $emp->is_active }})" style="background: transparent; border: none; color: {{ $emp->is_active ? '#FF3366' : '#00FF66' }}; cursor: pointer; font-size: 0.75rem; font-weight: bold;">
                                    {{ $emp->is_active ? 'HALT' : 'REHIRE' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 60px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                > NO PERSONNEL FOUND IN REGISTRY FOR THIS JURISDICTION.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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