<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        :root {
            --erp-bg-base: #010308;
            --erp-bg-main: #0A1128;
            --erp-border-dark: rgba(255,255,255,0.1);
            --erp-teal: #00E5FF;
            --erp-muted: #8A9BB2;
            --erp-white: #FFFFFF;
            --erp-yellow: #FFBD2E;
            --erp-red: #FF3366;
            --erp-green: #00FF66;
        }

        .cyber-input, .cyber-select {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: var(--erp-white); padding: 12px 15px; font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem; border-radius: 2px; transition: 0.3s; outline: none;
        }
        .cyber-input:focus, .cyber-select:focus { border-color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); box-shadow: 0 0 10px rgba(0,229,255,0.1); }
        
        .cyber-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
        }
        .cyber-select option { background-color: var(--erp-bg-main); color: var(--erp-white); padding: 10px; }

        .btn-action {
            background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal);
            padding: 12px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold;
            cursor: pointer; transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-action:hover:not(:disabled) { background: var(--erp-teal); color: var(--erp-bg-main); box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .btn-action:disabled { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.2); cursor: not-allowed; }

        .nav-pill {
            padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; 
            cursor: pointer; transition: 0.3s; border: 1px solid transparent; background: transparent; color: var(--erp-muted);
        }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }
        
        .validation-error { color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.65rem; margin-top: 5px; display: block; }

        /* Modals & Loaders */
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.85); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; }
        .success-box { background: var(--erp-bg-main); border: 1px solid var(--erp-green); border-top: 4px solid var(--erp-green); border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.9); z-index: 9999; max-width: 450px; width: 90%; }
        .spinner { border: 3px solid rgba(0, 229, 255, 0.1); border-top: 3px solid var(--erp-teal); border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* Sub-panel for Org Creation */
        .sub-panel-org {
            background: rgba(255, 189, 46, 0.03); border: 1px dashed rgba(255, 189, 46, 0.4); 
            border-radius: 4px; padding: 20px; margin-top: 15px; position: relative;
            animation: slideDown 0.3s ease-out forwards;
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(0,255,102,0.4);">✅</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: var(--erp-green); margin-bottom: 10px; letter-spacing: 1px;">IDENTITY ESTABLISHED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); margin-bottom: 25px; line-height: 1.6;">{{ $successMessage }}</p>
                <button wire:click="closeSuccessModal" class="btn-action" style="width: 100%; background: var(--erp-green); color: var(--erp-bg-main); border-color: var(--erp-green);">ACKNOWLEDGE & CLOSE</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: var(--erp-white); letter-spacing: 1px;">SYSTEM USER DIRECTORY</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">IDENTITY & ACCESS MATRIX</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="setTab('CREATE')" class="nav-pill {{ $activeTab === 'CREATE' ? 'active' : '' }}"><i data-lucide="user-plus" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> INIT USER</button>
            <button wire:click="setTab('INTERNAL')" class="nav-pill {{ $activeTab === 'INTERNAL' ? 'active' : '' }}">JBS COMMAND</button>
            <button wire:click="setTab('CLIENTS')" class="nav-pill {{ $activeTab === 'CLIENTS' ? 'active' : '' }}">CLIENT OS</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: {{ $activeTab === 'CREATE' ? 'minmax(600px, 800px)' : '1fr' }}; gap: 30px; justify-content: center;">
        
        @if($activeTab === 'CREATE')
            <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 35px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
                
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                    <span><i data-lucide="terminal" style="color: var(--erp-teal);"></i> > {{ $editMode ? 'MODIFY OPERATOR RECORD' : 'GENERATE NEW CRYPTOGRAPHIC IDENTITY' }}</span>
                    @if($editMode) 
                        <button wire:click="resetForm" style="background: rgba(255,51,102,0.1); color: var(--erp-red); padding: 3px 8px; font-size: 0.6rem; border-radius: 2px; border: 1px solid var(--erp-red); cursor: pointer;">CANCEL EDIT</button> 
                    @endif
                </div>

                <form wire:submit.prevent="saveUser">
                    
                    <h3 style="font-family: 'Montserrat', sans-serif; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 15px; letter-spacing: 1px; text-transform: uppercase;">1. Operator Identity</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 6px;">First Name *</label>
                            <input type="text" wire:model="first_name" required class="cyber-input" placeholder="Operator First Name">
                            @error('first_name') <span class="validation-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 6px;">Last Name *</label>
                            <input type="text" wire:model="last_name" required class="cyber-input" placeholder="Operator Last Name">
                            @error('last_name') <span class="validation-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 6px;">Secure Email *</label>
                            <input type="email" wire:model="email" required class="cyber-input" placeholder="comm@domain.com">
                            @error('email') <span class="validation-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 6px;">Comms (Phone)</label>
                            <input type="text" wire:model="phone" class="cyber-input" placeholder="+---">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 6px;">Job Title</label>
                            <input type="text" wire:model="job_title" class="cyber-input" placeholder="Designation">
                        </div>
                    </div>

                    <h3 style="font-family: 'Montserrat', sans-serif; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 15px; letter-spacing: 1px; text-transform: uppercase;">2. Access & Command Structure</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        
                        <div>
                            @if($editingIsClient || $this->isClientForm())
                                {{-- Client portal accounts have no staff role; the client's primary account manages their team --}}
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 6px; font-weight: bold;">Account Type</label>
                                <div style="font-size: 0.8rem; color: var(--erp-muted); padding: 10px 0;">Client portal account. The organisation's first account becomes its primary account.</div>
                            @else
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 6px; font-weight: bold;">System Role (Clearance) *</label>
                                <select wire:model="role_id" required class="cyber-select" style="border-color: rgba(0, 229, 255, 0.4);">
                                    <option value="">-- SELECT CLEARANCE LEVEL --</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}">LVL {{ $role->clearance_level }} - {{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('role_id') <span class="validation-error">{{ $message }}</span> @enderror
                            @endif
                        </div>

                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-yellow); margin-bottom: 6px; font-weight: bold;">Organization Assignment *</label>
                            <select wire:model.live="organization_id" required class="cyber-select" style="border-color: rgba(255, 189, 46, 0.4);">
                                <option value="">-- SELECT ORGANIZATION --</option>
                                <option value="NEW" style="color: var(--erp-yellow); font-weight: bold;">[+] INITIATE NEW ORGANIZATION</option>
                                <optgroup label="Existing Matrix" style="color: var(--erp-muted);">
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }} {{ $org->id === $jbsOrgId ? '(Internal)' : '' }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            @error('organization_id') <span class="validation-error">{{ $message }}</span> @enderror
                        </div>

                    </div>

                    @if($organization_id === 'NEW')
                        <div class="sub-panel-org">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-yellow); margin-bottom: 15px; font-weight: bold;">
                                <i data-lucide="building" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle;"></i> NEW ORGANIZATION CREATION PORTAL
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 6px;">Organization Name *</label>
                                    <input type="text" wire:model="new_org_name" required class="cyber-input" style="border-color: rgba(255, 189, 46, 0.3);">
                                    @error('new_org_name') <span class="validation-error">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 6px;">Industry *</label>
                                    <input type="text" wire:model="new_org_industry" required class="cyber-input" style="border-color: rgba(255, 189, 46, 0.3);" placeholder="e.g. Fintech, Logistics">
                                    @error('new_org_industry') <span class="validation-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    <div style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px; text-align: right;">
                        <button type="submit" wire:loading.attr="disabled" class="btn-action">
                            <span wire:loading.remove wire:target="saveUser"><i data-lucide="key" style="width: 14px; height: 14px;"></i> [ {{ $editMode ? 'UPDATE OPERATOR RECORD' : 'TRANSMIT ZERO-KNOWLEDGE INIT' }} ]</span>
                            <span wire:loading wire:target="saveUser"><div class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></div> {{ $editMode ? 'ENCRYPTING...' : 'GENERATING KEYS...' }}</span>
                        </button>
                    </div>

                </form>
            </div>

        @else
            <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto;">
                
                <div style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal);">> {{ $activeTab === 'INTERNAL' ? 'JBS COMMAND OS' : 'CLIENT DISTRIBUTED OS' }}</span>
                    <span style="color: var(--erp-muted); font-size: 0.75rem; font-family: 'Roboto Mono', monospace;">Total Records: {{ count($users) }}</span>
                </div>

                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Operator / Identity</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Organization Link</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Clearance Role</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; text-transform: uppercase;">Sys Status</th>
                            <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; text-transform: uppercase;">Command</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                                
                                <td style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                    <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-white);">{{ $user->first_name }} {{ $user->last_name }}</div>
                                    <div style="font-size: 0.7rem; color: var(--erp-teal); margin-top: 2px;">{{ $user->email }}</div>
                                </td>

                                <td style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                    <div style="font-size: 0.75rem; color: var(--erp-white); font-weight: 500;">{{ $user->org_name ?? 'ORPHANED' }}</div>
                                    @if($user->org_id === $jbsOrgId)
                                        <span style="display: inline-block; background: rgba(0, 229, 255, 0.1); border: 1px solid rgba(0, 229, 255, 0.3); padding: 2px 6px; border-radius: 2px; font-size: 0.6rem; font-family: 'Roboto Mono', monospace; color: var(--erp-teal); margin-top: 4px;">HOST_NODE</span>
                                    @else
                                        <span style="display: inline-block; background: rgba(255, 189, 46, 0.1); border: 1px solid rgba(255, 189, 46, 0.3); padding: 2px 6px; border-radius: 2px; font-size: 0.6rem; font-family: 'Roboto Mono', monospace; color: var(--erp-yellow); margin-top: 4px;">CLIENT_NODE</span>
                                    @endif
                                </td>

                                <td style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">
                                        @if($user->clearance_level !== null)
                                            <span style="color: var(--erp-white); font-weight: bold;">LVL {{ $user->clearance_level }}</span> - {{ $user->role_name }}
                                        @else
                                            <span style="color: var(--erp-white); font-weight: bold;">PORTAL</span> - {{ $user->role_name }}
                                        @endif
                                    </div>
                                </td>

                                <td style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
                                    @if($user->lifecycle_stage === 'Onboarding')
                                        <span style="color: var(--erp-yellow); background: rgba(255,189,46,0.1); font-size: 0.6rem; font-family: 'Roboto Mono', monospace; border: 1px solid var(--erp-yellow); padding: 3px 8px; border-radius: 50px; font-weight: bold;">INIT_PENDING</span>
                                    @elseif($user->status === 'Active')
                                        <span style="color: var(--erp-green); background: rgba(0,255,102,0.1); font-size: 0.6rem; font-family: 'Roboto Mono', monospace; border: 1px solid var(--erp-green); padding: 3px 8px; border-radius: 50px; font-weight: bold;">ACTIVE</span>
                                    @else
                                        <span style="color: var(--erp-red); background: rgba(255,51,102,0.1); font-size: 0.6rem; font-family: 'Roboto Mono', monospace; border: 1px solid var(--erp-red); padding: 3px 8px; border-radius: 50px; font-weight: bold;">SUSPENDED</span>
                                    @endif
                                </td>

                                <td style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
    
                                    @if($user->lifecycle_stage === 'Onboarding')
                                        <button wire:click="resendInvitation('{{ $user->id }}')" style="background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 4px 10px; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s; margin-right: 5px;" onmouseover="this.style.background='#00E5FF'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='#00E5FF';">
                                            RESEND INIT
                                        </button>
                                    @endif
                                
                                    <button wire:click="editUser('{{ $user->id }}')" style="background: transparent; border: 1px solid #FFBD2E; color: #FFBD2E; padding: 4px 10px; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s; margin-right: 5px;" onmouseover="this.style.background='#FFBD2E'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='#FFBD2E';">
                                        EDIT
                                    </button>
                                
                                    <button wire:click="toggleUserStatus('{{ $user->id }}', '{{ $user->status }}')" style="background: transparent; border: 1px solid {{ $user->status === 'Active' ? 'var(--erp-red)' : 'var(--erp-green)' }}; color: {{ $user->status === 'Active' ? 'var(--erp-red)' : 'var(--erp-green)' }}; padding: 4px 10px; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='{{ $user->status === 'Active' ? 'var(--erp-red)' : 'var(--erp-green)' }}'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='{{ $user->status === 'Active' ? 'var(--erp-red)' : 'var(--erp-green)' }}';">
                                        {{ $user->status === 'Active' ? 'REVOKE' : 'RESTORE' }}
                                    </button>
                                
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding: 60px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">
                                    > NO OPERATORS FOUND IN THIS LEDGER SECTOR.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        });
    </script>
</div>