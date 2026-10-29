<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select, .cyber-textarea {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #FFFFFF; padding: 10px 12px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; border-radius: 2px; transition: 0.3s;
        }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { 
            border-color: #00E5FF; outline: none; background: rgba(0, 229, 255, 0.05); 
        }
        .cyber-select { 
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); 
            background-repeat: no-repeat; background-position: right 10px center; 
        }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        
        .btn-action { 
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px; 
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; 
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
        }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-danger { border-color: #FF3366; color: #FF3366; }
        .btn-danger:hover { background: #FF3366; color: #FFF; box-shadow: 0 0 15px rgba(255, 51, 102, 0.4); }

        /* Form Headings */
        .form-section-head {
            font-family: 'Montserrat', sans-serif; font-size: 0.7rem; font-weight: 900; color: #00E5FF; 
            text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px dashed rgba(0,229,255,0.3); 
            padding-bottom: 4px; margin-bottom: 15px; margin-top: 20px;
        }

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: #0A1128; border: 1px solid #00FF66; border-top: 4px solid #00FF66; border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 420px; width: 90%; }
        .spinner { border: 3px solid rgba(0, 229, 255, 0.1); border-top: 3px solid #00E5FF; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(0,255,102,0.4);">⚖️</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: #00FF66; margin-bottom: 10px; letter-spacing: 1px;">ENTITY REGISTERED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2; margin-bottom: 25px; line-height: 1.5;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action" style="width: 100%; background: #00FF66; color: #0A1128; border-color: #00FF66;">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">CORPORATE FOOTPRINT & COMPLIANCE</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">GLOBAL LEGAL ENTITY REGISTRY FOR PAYROLL ANCHORING</p>
        </div>
    </div>

    @if ($errors->any())
        <div style="background: rgba(255,51,102,0.1); border-left: 2px solid #FF3366; padding: 10px; margin-bottom: 20px; border-radius: 2px;">
            <ul style="color: #FF3366; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; margin: 0; padding-left: 15px;">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 420px 1fr; gap: 30px; align-items: start;">
        
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 25px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
            
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #FFF; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <span>> {{ $editMode ? 'MODIFY' : 'REGISTER' }} LEGAL ENTITY</span>
                @if($editMode) <span style="background: rgba(255,189,46,0.1); color: #FFBD2E; padding: 3px 8px; font-size: 0.6rem; border-radius: 2px; border: 1px solid #FFBD2E;">EDIT MODE</span> @endif
            </div>

            <form wire:submit.prevent="saveEntity" style="display: flex; flex-direction: column; gap: 12px;">
                
                <div class="form-section-head" style="margin-top: 0;">1. Jurisdiction & Identity</div>
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Region Code *</label>
                        <select wire:model="region_code" required class="cyber-select" style="border-color: #00E5FF;" {{ $editMode ? 'disabled' : '' }}>
                            <option value="">Select</option>
                            <option value="CM">CM - Cameroon</option>
                            <option value="NG">NG - Nigeria</option>
                            <option value="KE">KE - Kenya</option>
                            <option value="GLOBAL">GLOBAL - HQ</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Official Legal Name *</label>
                        <input type="text" wire:model="legal_name" required class="cyber-input" placeholder="e.g. JBS Praxis SARL">
                    </div>
                </div>

                <div class="form-section-head">2. Statutory Compliance IDs</div>

                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Corporate Registration Number (RC) *</label>
                    <input type="text" wire:model="registration_number" required class="cyber-input" placeholder="Registre du Commerce / RC Number">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Corporate Tax ID *</label>
                        <input type="text" wire:model="tax_identification_number" required class="cyber-input" placeholder="NIU or TIN">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Employer Social ID *</label>
                        <input type="text" wire:model="employer_social_security_code" required class="cyber-input" placeholder="Employer CNPS / NSITF">
                    </div>
                </div>

                <div class="form-section-head">3. Legal Representation</div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Representative Name *</label>
                        <input type="text" wire:model="legal_representative_name" required class="cyber-input" placeholder="Signatory Director">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Official Title *</label>
                        <input type="text" wire:model="legal_representative_title" required class="cyber-input" placeholder="e.g. Managing Director">
                    </div>
                </div>

                <div class="form-section-head">4. Registered Contact & Location</div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Corporate Email</label>
                        <input type="email" wire:model="contact_email" class="cyber-input">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Corporate Phone</label>
                        <input type="text" wire:model="contact_phone" class="cyber-input">
                    </div>
                </div>
                <div>
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 4px;">Registered Head Office Address *</label>
                    <textarea wire:model="registered_address" required class="cyber-textarea" rows="2" placeholder="Required for legal contracts..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                    <button type="submit" wire:loading.attr="disabled" class="btn-action" style="flex: 2; background: rgba(0, 229, 255, 0.1);">
                        <span wire:loading.remove wire:target="saveEntity"><i data-lucide="shield-check" style="width: 14px; height: 14px;"></i> {{ $editMode ? 'UPDATE ENTITY' : 'SECURE ENTITY' }}</span>
                        <span wire:loading wire:target="saveEntity">ENCRYPTING...</span>
                    </button>
                    @if($editMode)
                        <button type="button" wire:click="resetForm" class="btn-action btn-danger" style="flex: 1;">CANCEL</button>
                    @endif
                </div>
            </form>
        </div>

        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase;">Jurisdiction & Entity</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-transform: uppercase;">Statutory Anchors</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: right; text-transform: uppercase;">Human Capital Telemetry</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; text-transform: uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entities as $entity)
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                                    <span style="background: rgba(255,255,255,0.1); color: {{ $entity->region_code === 'NG' ? '#00FF66' : ($entity->region_code === 'CM' ? '#00E5FF' : '#FFBD2E') }}; border: 1px solid rgba(255,255,255,0.2); padding: 2px 6px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; border-radius: 2px; font-weight: bold;">{{ $entity->region_code }}</span>
                                    <div style="font-family: 'Inter', sans-serif; font-weight: bold; font-size: 0.85rem; color: #FFF;">{{ $entity->legal_name }}</div>
                                </div>
                                <div style="font-size: 0.65rem; color: #8A9BB2; font-family: 'Roboto Mono', monospace;"><i data-lucide="map-pin" style="width: 10px; height: 10px; display: inline-block;"></i> {{ \Illuminate\Support\Str::limit($entity->registered_address, 35) }}</div>
                                <div style="font-size: 0.65rem; color: #8A9BB2; font-family: 'Roboto Mono', monospace; margin-top: 2px;"><i data-lucide="user" style="width: 10px; height: 10px; display: inline-block;"></i> Rep: {{ $entity->legal_representative_name }}</div>
                            </td>
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                <div style="font-size: 0.7rem; color: #8A9BB2; font-family: 'Roboto Mono', monospace;">RC: <span style="color: #FFF;">{{ $entity->registration_number }}</span></div>
                                <div style="font-size: 0.7rem; color: #8A9BB2; font-family: 'Roboto Mono', monospace; margin-top: 2px;">TAX: <span style="color: #FFF;">{{ $entity->tax_identification_number }}</span></div>
                                <div style="font-size: 0.7rem; color: #8A9BB2; font-family: 'Roboto Mono', monospace; margin-top: 2px;">EMP: <span style="color: #FFF;">{{ $entity->employer_social_security_code }}</span></div>
                            </td>
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right;">
                                <div style="font-size: 0.7rem; color: #00E5FF; font-family: 'Roboto Mono', monospace; font-weight: bold; margin-bottom: 4px;">
                                    {{ $entity->active_headcount }} ACTIVE PERSONNEL
                                </div>
                                <div style="font-size: 0.95rem; font-weight: bold; color: #FFF; font-family: 'Roboto Mono', monospace;">
                                    {{ number_format($entity->monthly_liability, 2) }} <span style="font-size: 0.65rem; color: #8A9BB2;">{{ $entity->local_currency }} /mo</span>
                                </div>
                                <div style="font-size: 0.6rem; color: #FFBD2E; margin-top: 2px; font-family: 'Roboto Mono', monospace;">GROSS STATUTORY LIABILITY</div>
                            </td>
                            
                            <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center; vertical-align: middle;">
                                <button wire:click="editEntity('{{ $entity->id }}')" style="background: transparent; border: none; color: #FFBD2E; cursor: pointer; font-size: 0.75rem; font-weight: bold; margin-right: 10px;">EDIT</button>
                                <button wire:click="toggleStatus('{{ $entity->id }}', {{ $entity->is_active }})" style="background: transparent; border: none; color: {{ $entity->is_active ? '#FF3366' : '#00FF66' }}; cursor: pointer; font-size: 0.75rem; font-weight: bold;">
                                    {{ $entity->is_active ? 'HALT' : 'ACTIVATE' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 60px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2;">
                                > NO CORPORATE ENTITIES REGISTERED. PLEASE ADD HQ JURISDICTION.
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