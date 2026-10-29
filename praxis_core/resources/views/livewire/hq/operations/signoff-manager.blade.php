<div style="display: flex; flex-direction: column; min-height: 100vh; padding-bottom: 80px;">
    <style>
        .page-header { margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end; }
        
        /* Main Tabs */
        .tab-nav { display: flex; gap: 5px; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 25px; }
        .tab-btn { background: transparent; border: 1px solid transparent; border-bottom: none; color: var(--erp-muted); padding: 12px 25px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; cursor: pointer; transition: 0.3s; font-weight: bold; outline: none; }
        .tab-btn:hover { color: var(--erp-teal); background: rgba(0,229,255,0.02); }
        .tab-btn.active { color: var(--erp-teal); border-color: var(--erp-border-dark); background: rgba(10, 17, 40, 0.8); border-top: 2px solid var(--erp-teal); border-radius: 4px 4px 0 0; }

        /* Sub Tabs (Marketing) */
        .sub-tab-nav { display: flex; gap: 10px; margin-bottom: 20px; }
        .sub-tab-btn { background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-muted); padding: 8px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; transition: 0.3s; border-radius: 2px; }
        .sub-tab-btn:hover { border-color: var(--erp-gold); color: var(--erp-gold); }
        .sub-tab-btn.active { background: rgba(212, 175, 55, 0.1); border-color: var(--erp-gold); color: var(--erp-gold); font-weight: bold; }

        /* Bloomberg Data Grid */
        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; background: rgba(10, 17, 40, 0.4); border: 1px solid var(--erp-border-dark); }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-teal); text-transform: uppercase; background: rgba(0,229,255,0.05); }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; font-size: 0.8rem; color: #E2E8F0; }
        .bloomberg-table tbody tr:hover td { background: rgba(0, 229, 255, 0.05); }

        .status-badge { padding: 4px 8px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; border: 1px solid transparent; }
        .badge-draft { background: rgba(138, 155, 178, 0.1); color: var(--erp-muted); border-color: var(--erp-muted); }
        .badge-awaiting { background: rgba(255, 189, 46, 0.1); color: #FFBD2E; border-color: #FFBD2E; animation: pulse-amber 2s infinite; }
        .badge-signed { background: rgba(0, 255, 102, 0.1); color: #00FF66; border-color: #00FF66; }
        .badge-frozen { background: rgba(0, 229, 255, 0.1); color: var(--erp-teal); border-color: var(--erp-teal); }

        @keyframes pulse-amber { 0% { box-shadow: 0 0 0 0 rgba(255, 189, 46, 0.4); } 70% { box-shadow: 0 0 0 4px rgba(255, 189, 46, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 189, 46, 0); } }

        /* Forms & Inputs */
        .cyber-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 25px; border-radius: 4px; margin-bottom: 25px; }
        .panel-title { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal); margin-bottom: 15px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        
        .cyber-input, .cyber-select, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 10px 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: var(--erp-teal); }
        .cyber-select option { background-color: var(--erp-bg-main); }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }
        .form-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; display: block; }

        .btn-primary { background: var(--erp-teal); color: var(--erp-bg-main); border: 1px solid var(--erp-teal); padding: 10px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:hover:not(:disabled) { background: var(--erp-white); box-shadow: 0 0 15px rgba(0,229,255,0.4); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .btn-ai { background: rgba(212, 175, 55, 0.1); color: var(--erp-gold); border: 1px solid var(--erp-gold); padding: 10px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-ai:hover:not(:disabled) { background: var(--erp-gold); color: var(--erp-bg-main); box-shadow: 0 0 15px rgba(212,175,55,0.4); }
        .btn-ai:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-action { background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; border-radius: 2px; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; }
        .btn-action:hover { border-color: var(--erp-teal); color: var(--erp-teal); }
        .btn-danger { color: var(--erp-red); border-color: var(--erp-red); }
        .btn-danger:hover { background: rgba(255,51,102,0.1); color: var(--erp-red); }

        .ai-staging-area { background: rgba(0,0,0,0.4); border: 1px dashed var(--erp-gold); padding: 25px; border-radius: 4px; margin-top: 20px; }
    </style>

    <div class="page-header">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">PROJECT SIGNOFFS & KAIZEN PUSH</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ LEGAL HANDOVERS & AI PORTFOLIO GENERATION ]</p>
        </div>
    </div>

    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('error') }}</div>
    @endif
    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('success') }}</div>
    @endif

    <div class="tab-nav">
        <button wire:click="switchMainTab('ledger')" class="tab-btn {{ $mainTab === 'ledger' ? 'active' : '' }}">
            <i data-lucide="shield-check" style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;"></i> THE SIGNOFF LEDGER
        </button>
        @if($canViewKaizen)
            <button wire:click="switchMainTab('marketing')" class="tab-btn {{ $mainTab === 'marketing' ? 'active' : '' }}">
                <i data-lucide="sparkles" style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;"></i> AI MARKETING PUSH
            </button>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: THE SIGNOFF LEDGER                  -->
    <!-- ========================================== -->
    @if($mainTab === 'ledger')
        
        <!-- Synthesis Generator Form -->
        @if($canDraft)
        <div class="cyber-panel">
            <div class="panel-title"><i data-lucide="cpu" style="width: 16px; height: 16px;"></i> SYNTHESIZE NEW SIGNOFF (AI GENERATOR)</div>
            @if(empty($eligibleProjects))
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FFBD2E;">
                    > SYSTEM LOCK: No eligible projects detected. 100% of tasks must be marked 'Done' to generate a legal handover document.
                </div>
            @else
                <form wire:submit.prevent="generateSignoffDraft" class="form-grid">
                    <div>
                        <label class="form-label">SELECT ELIGIBLE PROJECT</label>
                        <select wire:model="draftProjectId" class="cyber-select" required>
                            <option value="">-- Select Battlespace --</option>
                            @foreach($eligibleProjects as $p)
                                <option value="{{ $p->id }}">{{ $p->client_name }} - {{ \Illuminate\Support\Str::limit($p->title, 40) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">CLIENT SIGNATORY EMAIL</label>
                        <input type="email" wire:model="draftSignatoryEmail" class="cyber-input" placeholder="ceo@client.com" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="generateSignoffDraft">
                                <i data-lucide="file-text" style="width: 16px; height: 16px;"></i> SYNTHESIZE HANDOVER
                            </span>
                            <span wire:loading wire:target="generateSignoffDraft">
                                <i data-lucide="loader" class="animate-spin" style="width: 16px; height: 16px;"></i> AGGREGATING DATA...
                            </span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
        @endif

        <!-- Bloomberg Grid of All Signoffs -->
        <div style="overflow-x: auto;">
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>ENTERPRISE & PROJECT</th>
                        <th>STATUS & TIMESTAMPS</th>
                        <th>SIGNATORY (CLIENT)</th>
                        <th>CRYPTOGRAPHIC HASH</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($signoffs as $doc)
                        <tr>
                            <td>
                                <div style="font-weight: bold; color: var(--erp-white); margin-bottom: 3px;">{{ $doc->client_name }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal);">{{ \Illuminate\Support\Str::limit($doc->project_title, 35) }}</div>
                            </td>
                            <td>
                                <div style="margin-bottom: 5px;">
                                    <span class="status-badge badge-{{ strtolower(str_replace(' ', '-', $doc->status)) }}">{{ $doc->status }}</span>
                                </div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted);">
                                    @if($doc->status === 'Signed' || $doc->status === 'Frozen')
                                        Signed: {{ \Carbon\Carbon::parse($doc->signed_at)->format('M d, Y') }}
                                    @else
                                        Created: {{ \Carbon\Carbon::parse($doc->created_at)->format('M d, Y') }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.8rem; color: #E2E8F0;">{{ $doc->signatory_email }}</div>
                                @if($doc->signatory_name)
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted);">{{ $doc->signatory_name }} ({{ $doc->signatory_job_title }})</div>
                                @endif
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem;">
                                @if($doc->client_signature_hash)
                                    <div style="color: #00FF66;" title="{{ $doc->client_signature_hash }}">
                                        <i data-lucide="lock" style="width: 10px; height: 10px; display: inline-block;"></i> SHA-256 SECURED
                                    </div>
                                    <div style="color: var(--erp-muted);">IP: {{ $doc->client_ip_address }}</div>
                                @else
                                    <div style="color: var(--erp-muted);">PENDING HASH</div>
                                @endif
                            </td>
                            <td>
                                @if(($doc->status === 'Draft' || $doc->status === 'Awaiting Signature') && $doc->token)
                                    <!-- We will link this to the frontend gateway in Phase 4 -->
                                    <button onclick="navigator.clipboard.writeText('{{ route('portal.signoff.gateway', ['token' => $doc->token]) }}'); alert('Magic Link Copied to Clipboard!');" class="btn-action">
                                        <i data-lucide="link" style="width: 14px; height: 14px;"></i> COPY LINK
                                    </button>
                                @endif

                                @if($doc->status === 'Frozen' && $canUnfreeze)
                                    <button wire:click="unfreezeSignoff('{{ $doc->id }}')" class="btn-action btn-danger" onclick="confirm('UNFREEZE: This will reopen the Sprint Board. Proceed?') || event.stopImmediatePropagation()">
                                        <i data-lucide="flame" style="width: 14px; height: 14px;"></i> L5 DEFROST
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO SIGNOFFS GENERATED.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @endif

    <!-- ========================================== -->
    <!-- TAB 2: AI MARKETING PUSH                   -->
    <!-- ========================================== -->
    @if($mainTab === 'marketing' && $canViewKaizen)

        <div class="sub-tab-nav">
            @if($canPushKaizen)
                <button wire:click="switchMarketingTab('portfolio_gen')" class="sub-tab-btn {{ $marketingTab === 'portfolio_gen' ? 'active' : '' }}">PORTFOLIO GENERATOR</button>
                <button wire:click="switchMarketingTab('kaizen_gen')" class="sub-tab-btn {{ $marketingTab === 'kaizen_gen' ? 'active' : '' }}">KAIZEN GENERATOR</button>
            @endif
            <button wire:click="switchMarketingTab('portfolio_ledger')" class="sub-tab-btn {{ $marketingTab === 'portfolio_ledger' ? 'active' : '' }}">PORTFOLIO LEDGER</button>
            <button wire:click="switchMarketingTab('kaizen_ledger')" class="sub-tab-btn {{ $marketingTab === 'kaizen_ledger' ? 'active' : '' }}">KAIZEN LEDGER</button>
        </div>

        <!-- GENERATOR TABS -->
        @if(($marketingTab === 'portfolio_gen' || $marketingTab === 'kaizen_gen') && $canPushKaizen)
            <div class="cyber-panel">
                <div class="panel-title"><i data-lucide="wand-2" style="width: 16px; height: 16px; color: var(--erp-gold);"></i> AI SANITIZATION PIPELINE</div>
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 20px;">
                    Select a cryptographically signed project. The AI will strip sensitive data and generate a highly tailored asset for the public {{ $marketingTab === 'portfolio_gen' ? 'Portfolio' : 'Kaizen Blog' }}.
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 15px;">
                    <div>
                        <label class="form-label">SELECT COMPLETED PROJECT</label>
                        <select wire:model="selectedSignoffForMarketing" class="cyber-select">
                            <option value="">-- Select Completed Signoff --</option>
                            @foreach(collect($signoffs)->whereIn('status', ['Signed', 'Frozen'])->where('is_pushed_to_kaizen', 0) as $s)
                                <option value="{{ $s->id }}">{{ $s->client_name }} - {{ \Illuminate\Support\Str::limit($s->project_title, 40) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">TARGET BRAND TONE</label>
                        <select wire:model="marketingTone" class="cyber-select">
                            <option value="Clinical & Authoritative">Clinical & Authoritative (Standard Praxis)</option>
                            <option value="Aggressive & Financial">Aggressive & Financial (Focus on margin bleed/ROI)</option>
                            <option value="Academic & Technical">Academic & Technical (Deep architectural focus)</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label class="form-label">SANITIZATION RULES & CUSTOM PROMPT</label>
                    <input type="text" wire:model="marketingSanitizationRules" class="cyber-input">
                </div>

                <button wire:click="generateMarketingAsset" class="btn-ai" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generateMarketingAsset">
                        <i data-lucide="zap" style="width: 16px; height: 16px;"></i> ENGAGE AI SANITIZER
                    </span>
                    <span wire:loading wire:target="generateMarketingAsset">
                        <i data-lucide="loader" class="animate-spin" style="width: 16px; height: 16px;"></i> GENERATING ASSET...
                    </span>
                </button>

                <!-- AI Output Staging Area -->
                @if($generatedTitle)
                    <div class="ai-staging-area">
                        <div class="panel-title" style="color: var(--erp-gold); border-color: var(--erp-gold);">> HUMAN REVIEW & STAGING</div>
                        
                        <label class="form-label">PUBLIC TITLE</label>
                        <input type="text" wire:model="generatedTitle" class="cyber-input" style="font-weight: bold; margin-bottom: 15px; font-size: 1rem;">

                        <label class="form-label">SANITIZED NARRATIVE (HTML/TEXT)</label>
                        <textarea wire:model="generatedNarrative" class="cyber-textarea" style="min-height: 200px; margin-bottom: 15px; line-height: 1.5;"></textarea>

                        <label class="form-label">SUGGESTED EXECUTIVE QUOTE</label>
                        <textarea wire:model="generatedQuote" class="cyber-textarea" style="min-height: 80px; margin-bottom: 15px; border-left: 3px solid var(--erp-gold);"></textarea>

                        @if($marketingTab === 'portfolio_gen')
                            <label class="form-label" style="color: var(--erp-teal);">DALL-E 3 SUGGESTED IMAGE PROMPT</label>
                            <textarea wire:model="generatedDallePrompt" class="cyber-textarea" style="min-height: 60px; margin-bottom: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal);" readonly></textarea>
                        @endif

                        <button wire:click="commitMarketingAsset" class="btn-primary" style="width: 100%;">
                            <i data-lucide="upload-cloud" style="width: 16px; height: 16px;"></i> COMMIT TO PUBLIC DATABASE
                        </button>
                    </div>
                @endif
            </div>
        @endif

        <!-- LEDGER TABS -->
        @if($marketingTab === 'portfolio_ledger' || $marketingTab === 'kaizen_ledger')
            <div style="overflow-x: auto;">
                <table class="bloomberg-table">
                    <thead>
                        <tr>
                            <th>TITLE & PREVIEW</th>
                            <th>STATUS</th>
                            <th>CREATED AT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $ledgerLoop = $marketingTab === 'portfolio_ledger' ? $portfolioLedger : $kaizenLedger; @endphp
                        
                        @forelse($ledgerLoop as $item)
                            <tr>
                                <td>
                                    <div style="font-weight: bold; color: var(--erp-white); margin-bottom: 5px;">{{ $item->title }}</div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">
                                        {{ $marketingTab === 'portfolio_ledger' ? $item->client_pseudonym : \Illuminate\Support\Str::limit($item->executive_summary, 60) }}
                                    </div>
                                </td>
                                <td>
                                    @if(isset($item->is_published) && $item->is_published)
                                        <span class="status-badge badge-signed">PUBLISHED</span>
                                    @elseif(isset($item->status))
                                        <span class="status-badge" style="background: rgba(255,255,255,0.1); color: var(--erp-white);">{{ strtoupper($item->status) }}</span>
                                    @else
                                        <span class="status-badge badge-draft">STAGED</span>
                                    @endif
                                </td>
                                <td style="font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($item->created_at)->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="text-align: center; padding: 40px; color: var(--erp-muted);">NO ASSETS FOUND IN THIS LEDGER.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    @endif

    <script> document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); </script>
</div>