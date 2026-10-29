<div style="position: relative; overflow: hidden; min-height: 85vh;" x-data="{ showOffcanvas: false }" @open-offcanvas.window="showOffcanvas = true" @close-offcanvas.window="showOffcanvas = false">
    <style>
        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-teal); text-transform: uppercase; background: rgba(0,229,255,0.05); }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; font-size: 0.8rem; color: #E2E8F0; cursor: pointer; transition: 0.2s; }
        .bloomberg-table tbody tr:hover td { background: rgba(0, 229, 255, 0.05); }

        .status-badge { padding: 4px 8px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; border: 1px solid transparent; }
        .badge-open { background: rgba(255, 51, 102, 0.1); color: var(--erp-red); border-color: var(--erp-red); }
        .badge-investigating { background: rgba(255, 189, 46, 0.1); color: #FFBD2E; border-color: #FFBD2E; }
        .badge-resolved { background: rgba(0, 255, 102, 0.1); color: #00FF66; border-color: #00FF66; }

        .severity-Critical { color: var(--erp-red); font-weight: bold; }
        .severity-Medium { color: #FFBD2E; }
        .severity-Low { color: #00FF66; }

        .cyber-select { background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-teal); padding: 8px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; outline: none; transition: 0.3s; font-weight: bold; }
        .cyber-select option { background-color: var(--erp-bg-main); }
        .cyber-input, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .cyber-input:focus, .cyber-textarea:focus { border-color: var(--erp-teal); }

        .btn-action { background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 8px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; cursor: pointer; border-radius: 2px; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; font-weight: bold; }
        .btn-action:hover { border-color: var(--erp-teal); color: var(--erp-teal); }
        .btn-primary { background: var(--erp-teal); color: var(--erp-bg-main); border: 1px solid var(--erp-teal); }
        .btn-primary:hover { background: var(--erp-white); box-shadow: 0 0 15px rgba(0,229,255,0.4); }

        /* SLA Pulse Animation */
        .sla-breached { animation: pulse-sla 1.5s infinite; color: var(--erp-red); font-weight: bold; border: 1px solid var(--erp-red); padding: 2px 6px; border-radius: 2px; }
        @keyframes pulse-sla { 0% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0.4); } 70% { box-shadow: 0 0 0 5px rgba(255, 51, 102, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0); } }

        /* Offcanvas Styling */
        .offcanvas { position: fixed; top: 0; right: -800px; width: 800px; max-width: 100vw; height: 100vh; background: rgba(10, 17, 40, 0.95); backdrop-filter: blur(10px); border-left: 1px solid var(--erp-border-dark); box-shadow: -10px 0 30px rgba(0,0,0,0.8); transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1); z-index: 1050; display: flex; flex-direction: column; }
        .offcanvas.show { right: 0; }
        .offcanvas-header { padding: 20px 30px; border-bottom: 1px solid var(--erp-border-dark); display: flex; justify-content: space-between; align-items: flex-start; }
        .offcanvas-body { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .offcanvas-footer { padding: 20px 30px; border-top: 1px solid var(--erp-border-dark); background: rgba(0,0,0,0.4); }
        
        .offcanvas-body::-webkit-scrollbar { width: 6px; }
        .offcanvas-body::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .offcanvas-body::-webkit-scrollbar-thumb { background: var(--erp-teal); border-radius: 3px; }

        .comment-box { padding: 15px; border-radius: 4px; margin-bottom: 15px; border: 1px solid var(--erp-border-dark); background: rgba(0,0,0,0.3); }
        .comment-internal { border-left: 3px solid #FFBD2E; background: rgba(255, 189, 46, 0.05); }
        .comment-client { border-left: 3px solid var(--erp-teal); }
        .comment-meta { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 10px; display: flex; justify-content: space-between; }
        
        .evidence-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 10px; }
        .evidence-card { border: 1px solid var(--erp-border-dark); padding: 10px; text-align: center; border-radius: 2px; background: rgba(0,0,0,0.5); transition: 0.3s; }
        .evidence-card:hover { border-color: var(--erp-teal); }
        .evidence-card a { color: var(--erp-teal); font-size: 0.7rem; font-family: 'Roboto Mono', monospace; text-decoration: none; word-break: break-all; }
        
        .backdrop { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1040; display: none; }
        .backdrop.show { display: block; }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">QA VULNERABILITY HUB</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ GLOBAL FIREHOSE & COPQ TRIAGE ]</p>
        </div>
        <div>
            <select wire:model.live="filterStatus" class="cyber-select">
                <option value="Open">ACTION REQUIRED (Open & Investigating)</option>
                <option value="Resolved">RESOLVED VULNERABILITIES</option>
                <option value="All">ALL TICKETS (Global Ledger)</option>
            </select>
        </div>
    </div>

    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('error') }}</div>
    @endif
    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('success') }}</div>
    @endif

    <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; overflow-x: auto;">
        <table class="bloomberg-table">
            <thead>
                <tr>
                    <th>TICKET ID</th>
                    <th>ENTERPRISE & PROJECT</th>
                    <th>VULNERABILITY DESCRIPTION</th>
                    <th>SEVERITY</th>
                    <th>STATUS</th>
                    <th>SLA CLOCK</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $t)
                    <tr wire:click="openTicket('{{ $t['id'] }}')">
                        <td style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal);">#{{ substr($t['id'], 0, 8) }}</td>
                        <td>
                            <div style="font-weight: bold; color: var(--erp-white);">{{ $t['client_name'] }}</div>
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted);">{{ Str::limit($t['project_title'], 35) }}</div>
                        </td>
                        <td>
                            <div style="font-weight: bold; color: #B0B8C4; margin-bottom: 3px;">{{ Str::limit($t['title'], 50) }}</div>
                            <div style="font-size: 0.75rem; color: var(--erp-muted);">Submitted by: {{ $t['first_name'] }} {{ $t['last_name'] }}</div>
                        </td>
                        <td class="severity-{{ $t['severity'] }}">{{ strtoupper($t['severity']) }}</td>
                        <td>
                            <span class="status-badge badge-{{ strtolower($t['status']) }}">{{ str_replace('_', ' ', $t['status']) }}</span>
                        </td>
                        <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">
                            @if(in_array($t['status'], ['Open', 'Investigating']))
                                @if($t['is_breached'])
                                    <span class="sla-breached">SLA BREACHED</span>
                                @else
                                    <span style="color: #FFBD2E;">{{ $t['time_remaining'] }} remaining</span>
                                @endif
                            @else
                                <span style="color: #00FF66;">RESOLVED</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; font-family: 'Roboto Mono', monospace; color: var(--erp-muted);">
                            NO VULNERABILITIES DETECTED IN THE GLOBAL FIREHOSE.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="backdrop" :class="{ 'show': showOffcanvas }" @click="showOffcanvas = false"></div>
    
    <div class="offcanvas" :class="{ 'show': showOffcanvas }">
        @if($selectedTicket)
            <div class="offcanvas-header">
                <div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 5px;">> TICKET #{{ strtoupper(substr($selectedTicket['id'], 0, 8)) }} | {{ strtoupper($selectedTicket['client_name']) }}</div>
                    <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: var(--erp-white); margin-bottom: 10px;">{{ $selectedTicket['title'] }}</h2>
                    <span class="status-badge badge-{{ strtolower($selectedTicket['status']) }}">{{ $selectedTicket['status'] }}</span>
                    <span class="status-badge" style="border-color: var(--erp-border-dark); color: var(--erp-muted); margin-left: 10px;">SEVERITY: {{ $selectedTicket['severity'] }}</span>
                </div>
                <button @click="showOffcanvas = false" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; padding: 5px;">
                    <i data-lucide="x" style="width: 24px; height: 24px;"></i>
                </button>
            </div>

            <div class="offcanvas-body">
                
                @if($canWork && in_array($selectedTicket['status'], ['Open', 'Investigating']))
                    <div style="background: rgba(0,0,0,0.5); border: 1px dashed {{ $activeTimerId ? '#00FF66' : 'var(--erp-border-dark)' }}; padding: 20px; border-radius: 4px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: {{ $activeTimerId ? '#00FF66' : 'var(--erp-teal)' }}; font-weight: bold; margin-bottom: 5px;">COPQ REWORK TIMER (MARGIN BLEED)</div>
                            <div style="font-size: 0.75rem; color: var(--erp-muted);">Track time spent fixing this vulnerability against original Task: <strong>{{ $selectedTicket['task_title'] ?? 'UNLINKED' }}</strong></div>
                        </div>
                        <button wire:click="toggleReworkTimer" class="btn-action {{ $activeTimerId ? 'btn-danger' : 'btn-primary' }}" style="border-color: {{ $activeTimerId ? 'var(--erp-red)' : 'var(--erp-teal)' }}; color: {{ $activeTimerId ? 'var(--erp-red)' : 'var(--erp-bg-main)' }};">
                            <i data-lucide="{{ $activeTimerId ? 'pause-circle' : 'play-circle' }}" style="width: 16px; height: 16px;"></i> 
                            {{ $activeTimerId ? 'PAUSE TIMER' : 'START TIMER' }}
                        </button>
                    </div>
                @endif

                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 10px;">> VULNERABILITY REPORT:</div>
                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); padding: 15px; border-radius: 2px; color: var(--erp-white); font-size: 0.85rem; line-height: 1.6; margin-bottom: 25px;">
                    {!! nl2br(e($selectedTicket['description'])) !!}
                </div>

                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 10px;">> EVIDENCE GALLERY:</div>
                <div class="evidence-grid" style="margin-bottom: 30px;">
                    @forelse($ticketAttachments as $att)
                        <div class="evidence-card">
                            <i data-lucide="image" style="width: 24px; height: 24px; color: var(--erp-muted); margin-bottom: 10px;"></i><br>
                            <a href="{{ route('hq.file', ['path' => $att->file_path]) }}" target="_blank">{{ Str::limit($att->file_name, 15) }}</a>
                        </div>
                    @empty
                        <div style="grid-column: 1 / -1; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); padding: 10px;">No attachments provided.</div>
                    @endforelse
                </div>

                @if($canWork && in_array($selectedTicket['status'], ['Open', 'Investigating']))
                    <div style="display: flex; gap: 10px; margin-bottom: 30px; align-items: center;">
                        <input type="file" wire:model="newAttachment" class="cyber-input" style="padding: 6px; font-size: 0.75rem;">
                        <button wire:click="uploadEvidence" class="btn-action" wire:loading.attr="disabled" wire:target="newAttachment, uploadEvidence">
                            <span wire:loading.remove wire:target="uploadEvidence"><i data-lucide="upload" style="width: 14px; height: 14px;"></i> UPLOAD</span>
                            <span wire:loading wire:target="uploadEvidence">PROCESSING...</span>
                        </button>
                    </div>
                @endif

                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-bottom: 15px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 5px;">> COMMUNICATION THREAD</div>
                
                @forelse($ticketComments as $comment)
                    <div class="comment-box {{ $comment->is_internal_only ? 'comment-internal' : 'comment-client' }}">
                        <div class="comment-meta">
                            <span>
                                <strong>{{ $comment->first_name }} {{ $comment->last_name }}</strong> 
                                @if($comment->is_internal_only) <span style="color: #FFBD2E;">(INTERNAL NOTE)</span> @endif
                            </span>
                            <span>{{ \Carbon\Carbon::parse($comment->created_at)->format('M d, H:i') }}</span>
                        </div>
                        <div style="color: var(--erp-white); font-size: 0.85rem; line-height: 1.5;">
                            {!! nl2br(e($comment->comment_text)) !!}
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">No thread history.</div>
                @endforelse

                @if($canWork && in_array($selectedTicket['status'], ['Open', 'Investigating']))
                    <div style="margin-top: 20px;">
                        <textarea wire:model="newCommentText" class="cyber-textarea" rows="3" placeholder="Type response here..."></textarea>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #FFBD2E; display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                <input type="checkbox" wire:model="isInternalOnly"> INTERNAL NOTE (Hide from Client Portal)
                            </label>
                            <button wire:click="postComment" class="btn-primary" style="padding: 8px 20px;">
                                <i data-lucide="send" style="width: 14px; height: 14px;"></i> TRANSMIT
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="offcanvas-footer">
                @if($canResolve && in_array($selectedTicket['status'], ['Open', 'Investigating']))
                    <button wire:click="markResolved" class="btn-action" style="width: 100%; justify-content: center; border-color: #00FF66; color: #00FF66; padding: 12px; font-size: 0.85rem;" onclick="confirm('Verify fix deployed and notify client?') || event.stopImmediatePropagation()">
                        <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i> MARK AS RESOLVED & NOTIFY CLIENT
                    </button>
                @elseif(in_array($selectedTicket['status'], ['Open', 'Investigating']))
                    <div style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #FFBD2E;">
                        <i data-lucide="lock" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle;"></i> RESOLUTION LOCKED TO LEVEL 4+ COMMAND
                    </div>
                @else
                    <div style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #00FF66; font-weight: bold;">
                        <i data-lucide="shield-check" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"></i> VULNERABILITY PATCHED
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script> document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); </script>
</div>