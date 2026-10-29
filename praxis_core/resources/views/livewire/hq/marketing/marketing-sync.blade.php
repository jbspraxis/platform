<div style="position: relative; overflow: hidden; min-height: 85vh;">
    <style>
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-border-dark); background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }
        
        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-teal); text-transform: uppercase; background: rgba(0,229,255,0.05); }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; font-size: 0.85rem; color: #E2E8F0; }
        .bloomberg-table tr:hover td { background: rgba(0, 229, 255, 0.03); }

        .status-badge { padding: 5px 10px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-planned { background: rgba(255,255,255,0.1); color: var(--erp-muted); border: 1px solid var(--erp-muted); }
        .status-pending { background: rgba(255, 189, 46, 0.1); color: #FFBD2E; border: 1px solid #FFBD2E; }
        .status-revision { background: rgba(255, 51, 102, 0.1); color: var(--erp-red); border: 1px solid var(--erp-red); }
        .status-active { background: rgba(0, 255, 102, 0.1); color: #00FF66; border: 1px solid #00FF66; }

        .btn-action { background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; border-radius: 2px; transition: 0.3s; }
        .btn-action:hover { border-color: var(--erp-teal); color: var(--erp-teal); }
        
        .btn-primary { background: var(--erp-teal); color: var(--erp-bg-main); border: 1px solid var(--erp-teal); padding: 10px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: var(--erp-white); box-shadow: 0 0 15px rgba(0,229,255,0.4); }

        .cyber-input, .cyber-select, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: var(--erp-teal); background: rgba(0,229,255,0.02); }
        .cyber-select option { background-color: var(--erp-bg-main); }
        .form-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; display: block; }
        
        .glass-modal { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.9); backdrop-filter: blur(10px); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: var(--erp-bg-main); border: 1px solid var(--erp-border-dark); border-top: 3px solid var(--erp-teal); border-radius: 4px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); text-transform: uppercase; margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }

        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .metric-card { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 20px; border-left: 2px solid var(--erp-teal); }
        .metric-title { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; }
        .metric-val { font-family: 'Roboto Mono', monospace; font-size: 1.5rem; color: var(--erp-white); font-weight: bold; }

        .vault-container { background: rgba(0, 229, 255, 0.05); border: 1px dashed var(--erp-teal); padding: 20px; margin-bottom: 20px; font-family: 'Roboto Mono', monospace; }
        .history-list { list-style: none; font-size: 0.75rem; color: var(--erp-muted); border-left: 2px solid var(--erp-border-dark); padding-left: 15px; margin-top: 15px; }
        .history-list li { margin-bottom: 10px; }
        .history-list li span { color: var(--erp-white); }
        .offcanvas-backdrop { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.7); backdrop-filter: blur(5px); z-index: 99998; }
        .offcanvas-panel { position: fixed; top: 0; right: 0; bottom: 0; width: 100%; max-width: 600px; background: var(--erp-bg-main); border-left: 2px solid var(--erp-teal); box-shadow: -20px 0 50px rgba(0,0,0,0.8); z-index: 99999; overflow-y: auto; padding: 40px 30px; animation: slideInRight 0.3s forwards; }
        .offcanvas-header { font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); text-transform: uppercase; margin-bottom: 30px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">MARKETING SYNC & ROAS</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ LEVEL 0.25 AGENCY ISOLATION ]</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="switchTab('campaigns')" class="nav-pill {{ $activeTab === 'campaigns' ? 'active' : '' }}">CAMPAIGN COMMAND</button>
            <button wire:click="switchTab('assets')" class="nav-pill {{ $activeTab === 'assets' ? 'active' : '' }}">DIGITAL ASSETS & CREDENTIALS</button>
            <button wire:click="switchTab('social')" class="nav-pill {{ $activeTab === 'social' ? 'active' : '' }}">ORGANIC TELEMETRY</button>
        </div>
    </div>

    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('success') }}</div>
    @endif
    @if (session()->has('warning'))
        <div style="background: rgba(255,189,46,0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">
            @foreach ($errors->all() as $error) <div>> {{ $error }}</div> @endforeach
        </div>
    @endif

    @if($activeTab === 'campaigns')
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-title">TOTAL AD SPEND (API)</div>
                <div class="metric-val" style="color: var(--erp-amber);">${{ number_format(collect($campaigns)->sum('actual_spend'), 2) }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">AVG. ACQUISITION COST (CAC)</div>
                <div class="metric-val" style="color: var(--erp-red);">${{ collect($campaigns)->avg('calculated_cac') ? number_format(collect($campaigns)->avg('calculated_cac'), 2) : '0.00' }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">TOTAL OPPORTUNITIES</div>
                <div class="metric-val">{{ collect($campaigns)->sum('opportunities') }}</div>
            </div>
            <div class="metric-card" style="border-left-color: #00FF66;">
                <div class="metric-title">LIQUID ROAS (CASH SECURED)</div>
                <div class="metric-val" style="color: #00FF66;">${{ number_format(collect($campaigns)->sum('liquid_roas'), 2) }}</div>
            </div>
        </div>

        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px; min-height: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">> CAMPAIGN LEDGER</div>
                <button wire:click="$set('showCreateModal', true)" class="btn-primary">
                    <i data-lucide="plus" style="width: 16px; height: 16px;"></i> DRAFT NEW BUDGET
                </button>
            </div>

            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>Campaign Name & UTM</th>
                        <th>Platform</th>
                        <th>Status</th>
                        <th>Approved Budget</th>
                        <th>API Spend</th>
                        <th>Liquid ROAS</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $camp)
                        <tr>
                            <td>
                                <div style="font-weight: bold; color: var(--erp-white); margin-bottom: 4px;">{{ $camp['name'] }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted);">{{ $camp['utm_campaign_tag'] ?? 'Pending Generation' }}</div>
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace; font-weight: bold; color: var(--erp-teal);">{{ $camp['platform'] }}</td>
                            <td>
                                @if($camp['status'] === 'PENDING_APPROVAL')
                                    <span class="status-badge status-pending">AWAITING L4/L5</span>
                                @elseif($camp['status'] === 'REVISION_REQUIRED')
                                    <span class="status-badge status-revision">REVISION REQ</span>
                                @elseif($camp['status'] === 'PLANNED')
                                    <span class="status-badge status-planned">HQ APPROVED (READY)</span>
                                @elseif($camp['status'] === 'ACTIVE')
                                    <span class="status-badge status-active">LIVE (TRACKING)</span>
                                @else
                                    <span class="status-badge" style="background: rgba(255,255,255,0.1); color: var(--erp-muted); border: 1px solid var(--erp-muted);">{{ $camp['status'] }}</span>
                                @endif
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace;">${{ number_format($camp['budget_amount'], 2) }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; color: var(--erp-amber);">${{ number_format($camp['actual_spend'], 2) }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; color: #00FF66; font-weight: bold;">${{ number_format($camp['liquid_roas'], 2) }}</td>
                            <td style="text-align: right;">
                                @if($userClearance >= 4 && $camp['status'] === 'PENDING_APPROVAL')
                                    <button wire:click="openReviewModal('{{ $camp['id'] }}')" class="btn-action" style="border-color: #FFBD2E; color: #FFBD2E;">REVIEW & APPROVE</button>
                                @endif

                                @if($camp['status'] === 'PLANNED')
                                    <button wire:click="openActivateModal('{{ $camp['id'] }}')" class="btn-action" style="border-color: #00FF66; color: #00FF66;">LINK META/GOOGLE ID</button>
                                @endif

                                @if($camp['status'] === 'REVISION_REQUIRED')
                                    <button wire:click="openReviewModal('{{ $camp['id'] }}')" class="btn-action" style="border-color: var(--erp-red); color: var(--erp-red);">VIEW REJECTION</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO CAMPAIGNS IN SYSTEM</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($activeTab === 'assets')
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px; min-height: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">> CORPORATE DIGITAL FOOTPRINT & CREDENTIAL VAULT</div>
                <button wire:click="$set('showAssetModal', true)" class="btn-primary"><i data-lucide="shield" style="width: 16px; height: 16px;"></i> REGISTER NEW ASSET</button>
            </div>
            
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>Platform / Channel</th>
                        <th>Account Name</th>
                        <th>Login ID</th>
                        <th>Status</th>
                        <th style="text-align: right;">Security</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($socialAccounts as $asset)
                        <tr>
                            <td style="font-weight: bold; color: var(--erp-white); font-family: 'Roboto Mono', monospace;">{{ $asset['platform'] }}</td>
                            <td>{{ $asset['account_name'] }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">{{ $asset['login_identifier'] ?? 'N/A' }}</td>
                            <td><span class="status-badge status-{{ strtolower($asset['status']) === 'active' ? 'active' : 'pending' }}">{{ $asset['status'] }}</span></td>
                            <td style="text-align: right;">
                                <button wire:click="openCredentialModal('{{ $asset['id'] }}')" class="btn-action" style="border-color: var(--erp-teal); color: var(--erp-teal);">
                                    <i data-lucide="key" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> MANAGE ACCESS
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO DIGITAL ASSETS REGISTERED.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($activeTab === 'social')
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px; min-height: 500px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 20px;">> ORGANIC BRAND HEALTH (API SYNCED)</div>
            
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Account Name</th>
                        <th>Total Followers</th>
                        <th>Weekly Reach</th>
                        <th>Engagement Rate</th>
                        <th>Last Sync</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($socialAccounts as $social)
                        <tr>
                            <td style="font-weight: bold; color: var(--erp-white);">{{ $social['platform'] }}</td>
                            <td>{{ $social['account_name'] }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; color: var(--erp-teal);">{{ number_format($social['total_followers']) }}</td>
                            <td style="font-family: 'Roboto Mono', monospace;">{{ number_format($social['weekly_reach']) }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; color: #00FF66;">{{ $social['engagement_rate'] }}%</td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">{{ $social['last_synced_at'] ?? 'Never' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO ACCOUNTS LINKED. AWAITING API INTEGRATION.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($showCreateModal)
        <div class="offcanvas-backdrop" wire:click="$set('showCreateModal', false)"></div>
        <div class="offcanvas-panel">
            <div class="offcanvas-header">
                <span>> INITIATE CAMPAIGN DRAFT</span>
                <button wire:click="$set('showCreateModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.8rem; transition: 0.3s;" onmouseover="this.style.color='var(--erp-teal)'" onmouseout="this.style.color='var(--erp-muted)'">×</button>
            </div>
            
            <form wire:submit.prevent="submitDraft" style="display: flex; flex-direction: column; min-height: calc(100% - 80px);">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div><label class="form-label">CAMPAIGN ALIAS</label><input type="text" wire:model="campName" class="cyber-input" placeholder="e.g. Q2 ERP Logistics Push"></div>
                    <div>
                        <label class="form-label">AD NETWORK PLATFORM</label>
                        <select wire:model="platform" class="cyber-select">
                            <option value="META">Meta (Facebook & IG)</option>
                            <option value="GOOGLE">Google Search / Display</option>
                            <option value="LINKEDIN">LinkedIn B2B</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label">TRAFFIC TARGET (LANDING PAGE)</label>
                        <select wire:model="targetUrl" class="cyber-select">
                            <option value="/">Homepage (COPQ Conversion Hub)</option>
                            <option value="/framework">The Framework</option>
                            <option value="/kaizen">Kaizen Articles</option>
                            <option value="/contact">Direct Diagnostic Request</option>
                        </select>
                    </div>
                    <div><label class="form-label">BUDGET REQUEST (USD)</label><input type="number" step="0.01" wire:model="budgetAmount" class="cyber-input" placeholder="$0.00"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div><label class="form-label">START DATE</label><input type="date" wire:model="startDate" class="cyber-input"></div>
                    <div><label class="form-label">END DATE (OPTIONAL)</label><input type="date" wire:model="endDate" class="cyber-input"></div>
                </div>

                <div style="border-top: 1px dashed var(--erp-border-dark); padding-top: 20px; margin-top: 10px; margin-bottom: 20px;">
                    <label class="form-label">> CREATIVE ASSETS (SELECT ONE)</label>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                        <div>
                            <label style="font-size: 0.65rem; color: var(--erp-muted);">LOCAL UPLOAD (Under 10MB)</label>
                            <input type="file" wire:model="creativeFile" class="cyber-input" style="padding: 9px;">
                            <div wire:loading wire:target="creativeFile" style="font-family: 'Roboto Mono'; font-size: 0.65rem; color: var(--erp-teal); margin-top: 5px;">Uploading to Praxis Vault...</div>
                        </div>
                        <div>
                            <label style="font-size: 0.65rem; color: var(--erp-muted);">EXTERNAL LINK (Figma/Drive >10MB)</label>
                            <input type="text" wire:model="creativeLink" class="cyber-input" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: auto;">
                    <label class="form-label">STRATEGIC REMARKS (TO HQ)</label>
                    <textarea wire:model="remarks" class="cyber-textarea" placeholder="Explain the targeting rationale..." style="min-height: 120px;"></textarea>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--erp-border-dark); padding-top: 25px; margin-top: 30px;">
                    <button type="button" wire:click="$set('showCreateModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; transition: 0.3s;" onmouseover="this.style.color='var(--erp-white)'" onmouseout="this.style.color='var(--erp-muted)'">CANCEL DRAFT</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitDraft">SUBMIT TO COMMAND (L5)</span>
                        <span wire:loading wire:target="submitDraft">ENCRYPTING & ROUTING...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($showAssetModal)
        <div class="offcanvas-backdrop" wire:click="$set('showAssetModal', false)"></div>
        <div class="offcanvas-panel" style="display: flex; flex-direction: column;">
            <div class="offcanvas-header">
                <span>> SECURE ASSET REGISTRATION</span>
                <button wire:click="$set('showAssetModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.8rem; transition: 0.3s;" onmouseover="this.style.color='var(--erp-teal)'" onmouseout="this.style.color='var(--erp-muted)'">×</button>
            </div>
            <form wire:submit.prevent="registerDigitalAsset" style="display: flex; flex-direction: column; flex-grow: 1;">
                <div style="flex-grow: 1;">
                    <div style="margin-bottom: 25px;">
                        <label class="form-label">PLATFORM / NETWORK</label>
                        <input type="text" wire:model="assetPlatform" class="cyber-input" placeholder="e.g. Meta Business Manager">
                    </div>
                    <div style="margin-bottom: 25px;">
                        <label class="form-label">ACCOUNT NAME</label>
                        <input type="text" wire:model="assetName" class="cyber-input" placeholder="e.g. Praxis Official">
                    </div>
                    <div style="margin-bottom: 25px;">
                        <label class="form-label">LOGIN EMAIL / USERNAME</label>
                        <input type="text" wire:model="loginIdentifier" class="cyber-input" placeholder="admin@jbspraxis.com">
                    </div>
                    <div style="margin-bottom: 25px;">
                        <label class="form-label">MASTER PASSWORD</label>
                        <input type="password" wire:model="assetPassword" class="cyber-input" placeholder="Will be AES-256 encrypted">
                    </div>
                    <div style="margin-bottom: 25px;">
                        <label class="form-label">PERSONAL ADMIN PROFILE URL (META/LINKEDIN REQUIRED)</label>
                        <input type="url" wire:model="adminProfileLink" class="cyber-input" placeholder="https://facebook.com/personal-profile-link">
                        <span style="font-size: 0.65rem; color: var(--erp-muted); margin-top: 8px; display: block; line-height: 1.4;">If the platform requires a personal account to manage the business page, link the personal profile here.</span>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--erp-border-dark); padding-top: 25px; margin-top: 30px;">
                    <button type="button" wire:click="$set('showAssetModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; transition: 0.3s;" onmouseover="this.style.color='var(--erp-white)'" onmouseout="this.style.color='var(--erp-muted)'">CANCEL</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">LOCK CREDENTIALS IN VAULT</button>
                </div>
            </form>
        </div>
    @endif

    @if($showCredentialModal && $selectedAsset)
        <div class="offcanvas-backdrop" wire:click="$set('showCredentialModal', false)"></div>
        <div class="offcanvas-panel" style="display: flex; flex-direction: column; border-left-color: var(--erp-red);">
            <div class="offcanvas-header" style="border-bottom-color: var(--erp-red);">
                <span style="color: var(--erp-red);"><i data-lucide="shield-alert" style="display: inline-block; vertical-align: middle;"></i> ACCESS CONTROL</span>
                <button wire:click="$set('showCredentialModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.8rem; transition: 0.3s;" onmouseover="this.style.color='var(--erp-red)'" onmouseout="this.style.color='var(--erp-muted)'">×</button>
            </div>

            <div style="flex-grow: 1; overflow-y: auto;">
                <div class="vault-container">
                    <div style="margin-bottom: 15px;"><span style="color: var(--erp-muted); font-size: 0.65rem; display: block; margin-bottom: 4px;">ASSET:</span> <span style="color: var(--erp-white); font-size: 1.1rem;">{{ $selectedAsset->account_name }} ({{ $selectedAsset->platform }})</span></div>
                    <div style="margin-bottom: 15px;"><span style="color: var(--erp-muted); font-size: 0.65rem; display: block; margin-bottom: 4px;">LOGIN ID:</span> <span style="color: var(--erp-white);">{{ $selectedAsset->login_identifier }}</span></div>
                    <div style="margin-bottom: 15px;"><span style="color: var(--erp-muted); font-size: 0.65rem; display: block; margin-bottom: 4px;">ADMIN LINK:</span> <span style="color: var(--erp-white); word-break: break-all;">{{ $selectedAsset->admin_profile_link ?? 'N/A' }}</span></div>
                    
                    <div style="margin-top: 25px; border-top: 1px dashed var(--erp-teal); padding-top: 20px;">
                        <span style="color: var(--erp-teal); font-weight: bold; font-size: 0.75rem; margin-bottom: 10px; display: block;">CURRENT ENCRYPTED PASSWORD:</span>
                        <div style="background: rgba(0,0,0,0.5); border: 1px solid var(--erp-border-dark); padding: 15px; font-size: 1.3rem; color: var(--erp-red); text-align: center; letter-spacing: 4px;">
                            {{ $decryptedPassword }}
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px; background: rgba(255,51,102,0.05); border: 1px solid rgba(255,51,102,0.2); padding: 25px;">
                    <label class="form-label" style="color: var(--erp-red);">UPDATE PASSWORD (OVERWRITE VAULT)</label>
                    <input type="password" wire:model="newPassword" class="cyber-input" placeholder="Type new password to overwrite..." style="margin-bottom: 15px; border-color: rgba(255,51,102,0.4);">
                    <button wire:click="updateAssetPassword" class="btn-primary" style="width: 100%; justify-content: center; background: var(--erp-red); border-color: var(--erp-red);">ENCRYPT & OVERWRITE</button>
                    <span style="font-size: 0.65rem; color: var(--erp-red); text-align: center; display: block; margin-top: 12px; opacity: 0.8;">WARNING: This action is permanently logged in the Audit Trail. L5 will be notified.</span>
                </div>

                @if($userClearance == 5 && count($credentialHistory) > 0)
                    <div style="margin-top: 30px; border-top: 1px solid var(--erp-border-dark); padding-top: 20px;">
                        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); text-transform: uppercase;">> IMMUTABLE MODIFICATION LEDGER</span>
                        <ul class="history-list">
                            @foreach($credentialHistory as $log)
                                <li><span style="color: var(--erp-white);">{{ \Carbon\Carbon::parse($log['created_at'])->format('M d, Y H:i') }}</span> | Changed by: {{ $log['user_name'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($showReviewModal && $selectedCampaign)
        <div class="offcanvas-backdrop" wire:click="$set('showReviewModal', false)"></div>
        <div class="offcanvas-panel" style="display: flex; flex-direction: column;">
            <div class="offcanvas-header" style="border-bottom-color: {{ $selectedCampaign->status === 'REVISION_REQUIRED' ? 'var(--erp-red)' : 'var(--erp-teal)' }};">
                <span>> CAMPAIGN AUDIT</span>
                <button wire:click="$set('showReviewModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.8rem; transition: 0.3s;" onmouseover="this.style.color='var(--erp-teal)'" onmouseout="this.style.color='var(--erp-muted)'">×</button>
            </div>
            
            <div style="flex-grow: 1;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 25px;">
                    <div>
                        <span style="color: var(--erp-muted); display: block; font-size: 0.65rem; margin-bottom: 4px;">NAME:</span> 
                        <span style="color: var(--erp-white);">{{ $selectedCampaign->name }}</span>
                    </div>
                    <div>
                        <span style="color: var(--erp-muted); display: block; font-size: 0.65rem; margin-bottom: 4px;">PLATFORM:</span> 
                        <span style="color: var(--erp-teal); font-weight: bold;">{{ $selectedCampaign->platform }}</span>
                    </div>
                    <div>
                        <span style="color: var(--erp-muted); display: block; font-size: 0.65rem; margin-bottom: 4px;">TARGET URL:</span> 
                        <span style="color: var(--erp-white); word-break: break-all;">jbspraxis.com{{ $selectedCampaign->target_url }}</span>
                    </div>
                    <div>
                        <span style="color: var(--erp-muted); display: block; font-size: 0.65rem; margin-bottom: 4px;">BUDGET REQ:</span> 
                        <span style="color: var(--erp-amber); font-weight: bold; font-size: 1.1rem;">${{ number_format($selectedCampaign->budget_amount, 2) }}</span>
                    </div>
                </div>

                <label class="form-label">STRATEGIC REMARKS</label>
                <div style="background: rgba(255,255,255,0.02); padding: 20px; border-left: 2px solid var(--erp-teal); font-size: 0.85rem; color: #B0B8C4; margin-bottom: 25px; white-space: pre-wrap; line-height: 1.5;">{{ $selectedCampaign->remarks ?? 'No remarks provided.' }}</div>

                @if($selectedCampaign->status === 'REVISION_REQUIRED')
                    <div style="background: rgba(255,51,102,0.1); padding: 20px; border: 1px solid var(--erp-red); font-size: 0.85rem; color: var(--erp-red); margin-bottom: 25px; line-height: 1.5;">
                        <strong style="display: block; margin-bottom: 8px;">> HQ REJECTION DIRECTIVE:</strong>
                        {{ $selectedCampaign->rejection_reason }}
                    </div>
                @endif

                @if($userClearance >= 4 && $selectedCampaign->status === 'PENDING_APPROVAL')
                    <div style="border-top: 1px dashed var(--erp-border-dark); padding-top: 25px; margin-top: 10px;">
                        <label class="form-label" style="color: var(--erp-red);">REJECTION DIRECTIVE (REQUIRED FOR DENIAL)</label>
                        <textarea wire:model="rejectionReason" class="cyber-textarea" placeholder="If rejecting, state the operational reason..." style="border-color: var(--erp-red); min-height: 100px;"></textarea>
                    </div>
                @endif
            </div>

            <div style="border-top: 1px solid var(--erp-border-dark); padding-top: 25px; margin-top: 30px;">
                @if($userClearance >= 4 && $selectedCampaign->status === 'PENDING_APPROVAL')
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                        <button wire:click="rejectCampaign" class="btn-action" style="color: var(--erp-red); border-color: var(--erp-red); background: rgba(255,51,102,0.05); padding: 12px 20px; flex: 1; text-align: center;">REJECT TO AGENCY</button>
                        <button wire:click="approveCampaign" class="btn-primary" style="background: #00FF66; color: #0A1128; border-color: #00FF66; padding: 12px 20px; flex: 2; justify-content: center;">
                            APPROVE & SPAWN CASH REQ
                        </button>
                    </div>
                @else
                    <div style="display: flex; justify-content: flex-end;">
                        <button type="button" wire:click="$set('showReviewModal', false)" class="btn-primary" style="background: transparent; border-color: var(--erp-border-dark); color: var(--erp-white);">CLOSE AUDIT</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($showActivateModal && $selectedCampaign)
        <div class="offcanvas-backdrop" wire:click="$set('showActivateModal', false)"></div>
        <div class="offcanvas-panel" style="display: flex; flex-direction: column; border-left-color: #00FF66;">
            <div class="offcanvas-header" style="border-bottom-color: #00FF66;">
                <span style="color: #00FF66;">> LINK EXTERNAL TELEMETRY</span>
                <button wire:click="$set('showActivateModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.8rem; transition: 0.3s;" onmouseover="this.style.color='#00FF66'" onmouseout="this.style.color='var(--erp-muted)'">×</button>
            </div>
            
            <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 20px;">
                <i data-lucide="link" style="width: 64px; height: 64px; color: #00FF66; margin-bottom: 20px;"></i>
                <h3 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); text-transform: uppercase; margin-bottom: 15px;">LINK TO {{ $selectedCampaign->platform }}</h3>
                <p style="font-size: 0.9rem; color: var(--erp-muted); margin-bottom: 30px; line-height: 1.6;">
                    HQ has approved the budget. Create the ad in {{ $selectedCampaign->platform }} and paste the Campaign ID below so our Cron Job can sync the API telemetry.
                </p>
                
                <div style="width: 100%; text-align: left; margin-bottom: 30px;">
                    <label class="form-label" style="color: #00FF66;">EXTERNAL CAMPAIGN ID</label>
                    <input type="text" wire:model="externalCampaignId" class="cyber-input" placeholder="e.g. 238491029384" style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 1.2rem; padding: 15px; border-color: rgba(0,255,102,0.3); background: rgba(0,255,102,0.05); color: #00FF66;">
                </div>
            </div>

            <div style="border-top: 1px solid var(--erp-border-dark); padding-top: 25px; margin-top: auto; display: flex; gap: 15px;">
                <button wire:click="$set('showActivateModal', false)" style="flex: 1; background: transparent; border: 1px solid var(--erp-muted); color: var(--erp-white); padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; cursor: pointer; border-radius: 2px; transition: 0.3s;" onmouseover="this.style.borderColor='var(--erp-white)'" onmouseout="this.style.borderColor='var(--erp-muted)'">CANCEL</button>
                <button wire:click="activateCampaign" style="flex: 2; background: #00FF66; color: #0A1128; border: none; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; border-radius: 2px; transition: 0.3s;" onmouseover="this.style.boxShadow='0 0 15px rgba(0,255,102,0.4)'" onmouseout="this.style.boxShadow='none'">ACTIVATE TELEMETRY</button>
            </div>
        </div>
    @endif

    <script> document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); </script>
</div>