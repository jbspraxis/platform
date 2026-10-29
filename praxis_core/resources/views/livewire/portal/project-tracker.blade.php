<div x-data="executiveTracker()" x-init="initEngine()" class="app-wrapper" :class="{ 'is-offline': !isOnline }">
    
    @push('styles')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800;900&family=Fira+Code:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==========================================================================
           JBS PRAXIS BRAND ARCHITECTURE (STRICT ENFORCEMENT)
           ========================================================================== */
        :root {
            --brand-navy: #0A1128;       /* Midnight Navy */
            --brand-teal: #00E5FF;       /* Luminous Teal */
            --brand-red: #FF3366;        /* Terminal Red */
            --brand-slate: #141E3C;      /* Slate Navy */
            --brand-white: #FFFFFF;      /* Stark White */
            --brand-ghost: #F3F4F6;      /* Ghost White */
            
            --color-text-main: #0A1128;
            --color-text-muted: #64748B;
            --color-border: rgba(10, 17, 40, 0.08);
            
            --status-done: #10B981;
            --status-progress: #F59E0B;
            
            --shadow-soft: 0 8px 30px rgba(10, 17, 40, 0.04);
            --shadow-glass: 0 10px 40px rgba(10, 17, 40, 0.08);
            
            --font-display: 'Montserrat', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-mono: 'Fira Code', monospace;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        .app-wrapper {
            min-height: 100vh; background-color: var(--brand-ghost);
            font-family: var(--font-body); color: var(--color-text-main);
            max-width: 600px; margin: 0 auto; position: relative;
            overflow-x: hidden; box-shadow: 0 0 50px rgba(0,0,0,0.05);
        }

        .mono-label {
            font-family: var(--font-mono); font-size: 0.65rem; letter-spacing: 1.5px;
            text-transform: uppercase; color: var(--brand-teal); font-weight: 700;
        }

        /* ==========================================================================
           OFFLINE BANNER
           ========================================================================== */
        .offline-banner {
            background: var(--brand-red); color: var(--brand-white); text-align: center;
            padding: 10px; font-size: 0.75rem; font-weight: 700; font-family: var(--font-mono);
            position: sticky; top: 0; z-index: 200; transform: translateY(-100%);
            transition: transform 0.3s ease; letter-spacing: 0.5px;
        }
        .is-offline .offline-banner { transform: translateY(0); }

        /* ==========================================================================
           AUTH SCREEN (APPLE PAY STYLE)
           ========================================================================== */
        .auth-screen {
            height: 100vh; display: flex; flex-direction: column; justify-content: center;
            align-items: center; padding: 40px 20px; background: var(--brand-white); text-align: center;
        }
        .auth-logo { height: 70px; margin-bottom: 40px; }
        .auth-title {
            font-family: var(--font-display); font-size: 1.6rem; font-weight: 900;
            color: var(--brand-navy); line-height: 1.2; margin-bottom: 10px;
        }
        .auth-subtitle { color: var(--color-text-muted); font-size: 0.95rem; margin-bottom: 40px; }

        .pin-wrapper { position: relative; margin-bottom: 30px; cursor: pointer; }
        .hidden-pin { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10; }
        .pin-dots-container { display: flex; gap: 20px; justify-content: center; pointer-events: none; }
        .pin-dot {
            width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--color-border);
            background: transparent; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .pin-dot.filled { background: var(--brand-navy); border-color: var(--brand-navy); transform: scale(1.15); }
        .pin-dot.error { border-color: var(--brand-red); animation: shake 0.4s; }
        .pin-dot.error.filled { background: var(--brand-red); }

        .auth-error-text { color: var(--brand-red); font-size: 0.85rem; font-weight: 700; min-height: 20px; margin-bottom: 20px; }

        /* ==========================================================================
           DASHBOARD HEADER (GLASSMORPHIC)
           ========================================================================== */
        .glass-header {
            background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            position: sticky; top: 0; z-index: 50; border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            padding: 25px 20px; display: flex; justify-content: space-between; align-items: flex-start; gap: 15px;
            box-shadow: 0 4px 30px rgba(10, 17, 40, 0.03);
        }

        .client-name { font-size: 0.8rem; font-weight: 700; color: var(--color-text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .project-title {
            font-family: var(--font-display); font-size: 1.15rem; font-weight: 900;
            color: var(--brand-navy); margin: 0 0 12px 0; line-height: 1.3;
        }

        .meta-tags-container { display: flex; gap: 8px; flex-wrap: wrap; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px;
            border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
            font-family: var(--font-mono); letter-spacing: 0.5px;
        }
        .status-active { background: rgba(0, 229, 255, 0.1); color: #00A3B5; }
        .status-archived { background: rgba(255, 51, 102, 0.1); color: var(--brand-red); }
        .status-deadline { background: var(--brand-slate); color: var(--brand-white); }

        .progress-ring { position: relative; width: 68px; height: 68px; flex-shrink: 0; }
        .progress-ring-svg { transform: rotate(-90deg); }
        .progress-circle-bg { fill: transparent; stroke: var(--color-border); stroke-width: 4; }
        .progress-circle-value { 
            fill: transparent; stroke: var(--brand-teal); stroke-width: 4; 
            stroke-linecap: round; transition: stroke-dashoffset 1s cubic-bezier(0.16, 1, 0.3, 1);
            filter: drop-shadow(0 0 6px rgba(0, 229, 255, 0.4));
        }
        .progress-text {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            font-family: var(--font-display); font-weight: 900; font-size: 0.85rem; color: var(--brand-navy);
        }

        /* ==========================================================================
           TIMELINE ARCHITECTURE
           ========================================================================== */
        .timeline-container { padding: 30px 20px 120px 20px; }
        
        .milestone-block { position: relative; padding-left: 32px; margin-bottom: 50px; }
        .milestone-block::before {
            content: ''; position: absolute; left: 7px; top: 8px; bottom: -50px;
            width: 2px; background: var(--color-border);
        }
        .milestone-block:last-child::before { display: none; }
        
        .milestone-dot {
            position: absolute; left: 0; top: 4px; width: 16px; height: 16px; border-radius: 50%; 
            background: var(--brand-ghost); border: 2px solid var(--brand-navy); z-index: 2; transition: 0.3s;
        }
        .milestone-dot.completed {
            background: var(--brand-teal); border-color: var(--brand-teal);
            box-shadow: 0 0 0 4px rgba(0, 229, 255, 0.2);
        }

        .milestone-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .milestone-title {
            font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
            color: var(--brand-navy); margin: 0 0 4px 0; line-height: 1.3;
        }
        .sprint-dates { font-family: var(--font-mono); font-size: 0.65rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase; }
        
        .payment-badge {
            background: rgba(16, 185, 129, 0.1); color: var(--status-done); border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 4px 8px; border-radius: 6px; font-size: 0.65rem; font-weight: 800; font-family: var(--font-mono);
            display: inline-flex; align-items: center; gap: 4px; margin-top: 6px;
        }

        .task-card {
            background: var(--brand-white); border: 1px solid var(--brand-white);
            border-radius: 16px; padding: 18px; margin-bottom: 12px;
            box-shadow: var(--shadow-soft); display: flex; gap: 15px; align-items: stretch;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s;
        }
        .task-card:active { transform: scale(0.98); box-shadow: 0 4px 15px rgba(10, 17, 40, 0.02); }

        .task-indicator { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
        .task-indicator.todo { border: 2px solid var(--color-border); }
        .task-indicator.in_progress { background: var(--status-progress); animation: pulse 2s infinite; }
        .task-indicator.done { background: var(--status-done); }

        .task-content { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
        .task-name { font-size: 0.95rem; font-weight: 600; line-height: 1.4; color: var(--color-text-main); margin-bottom: 8px; }
        
        .task-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
        .task-assignee { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; font-weight: 600; color: var(--color-text-muted); }
        .assignee-avatar { width: 20px; height: 20px; border-radius: 50%; background: var(--brand-slate); color: var(--brand-white); display: flex; align-items: center; justify-content: center; font-size: 0.5rem; font-weight: 800; }
        .task-deadline { font-family: var(--font-mono); font-size: 0.65rem; font-weight: 600; color: var(--brand-red); }

        .btn-reply {
            width: 40px; height: 40px; border-radius: 50%; border: none; align-self: center;
            background: var(--brand-ghost); color: var(--brand-navy); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
            cursor: pointer; transition: 0.2s;
        }
        .btn-reply:active { transform: scale(0.9); background: rgba(0, 229, 255, 0.1); color: var(--brand-teal); }

        /* ==========================================================================
           FLOATING ACTION BUTTON
           ========================================================================== */
        .fab-chat {
            position: fixed; bottom: 30px; right: 20px; width: 64px; height: 64px;
            background: var(--brand-navy); color: var(--brand-white); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
            box-shadow: var(--shadow-float); z-index: 90; border: none; cursor: pointer;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .fab-chat:active { transform: scale(0.92); }
        .unread-badge {
            position: absolute; top: -2px; right: -2px; background: var(--brand-red); color: var(--brand-white);
            font-size: 0.65rem; font-weight: 800; width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; border: 2px solid var(--brand-ghost);
        }

        /* ==========================================================================
           CHAT BOTTOM SHEET
           ========================================================================== */
        .chat-sheet { position: fixed; inset: 0; z-index: 100; pointer-events: none; display: flex; flex-direction: column; justify-content: flex-end; }
        .chat-sheet.open { pointer-events: auto; }
        .chat-backdrop { position: absolute; inset: 0; background: rgba(10, 17, 40, 0.6); backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.3s; }
        .chat-sheet.open .chat-backdrop { opacity: 1; }

        .chat-panel {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            height: 85vh; width: 100%; max-width: 600px; border-radius: 28px 28px 0 0; position: relative; 
            display: flex; flex-direction: column; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow-glass); border-top: 1px solid rgba(255,255,255,0.5);
        }
        .chat-sheet.open .chat-panel { transform: translateY(0); }

        .chat-sheet-header {
            padding: 20px 24px; border-bottom: 1px solid var(--color-border);
            display: flex; align-items: center; gap: 15px; position: sticky; top: 0; z-index: 10;
        }
        .btn-close-sheet {
            background: var(--brand-ghost); border: none; width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: var(--brand-navy);
            font-size: 1.1rem; cursor: pointer; transition: 0.2s;
        }
        .btn-close-sheet:active { transform: scale(0.9); background: #E2E8F0; }

        .chat-scroller { flex-grow: 1; overflow-y: auto; padding: 24px 20px; display: flex; flex-direction: column; gap: 16px; }

        .bubble { max-width: 85%; padding: 14px 18px; border-radius: 20px; font-size: 0.95rem; line-height: 1.45; box-shadow: 0 2px 5px rgba(0,0,0,0.02); position: relative; }
        .bubble-client { align-self: flex-end; background: var(--brand-navy); color: var(--brand-white); border-bottom-right-radius: 4px; }
        .bubble-staff { align-self: flex-start; background: var(--brand-white); color: var(--color-text-main); border: 1px solid var(--color-border); border-bottom-left-radius: 4px; }

        .bubble-sender { font-size: 0.7rem; font-weight: 800; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--brand-teal); }
        .bubble-client .bubble-sender { display: none; }
        
        .bubble-context {
            background: rgba(10, 17, 40, 0.04); border-left: 3px solid var(--brand-teal);
            padding: 8px 12px; border-radius: 6px; font-size: 0.75rem; margin-bottom: 10px; color: var(--color-text-main); font-weight: 600;
        }
        .bubble-client .bubble-context { background: rgba(255,255,255,0.1); color: var(--brand-white); border-color: var(--brand-teal); }
        .bubble-meta { font-size: 0.65rem; font-weight: 600; margin-top: 8px; text-align: right; opacity: 0.7; font-family: var(--font-mono); }
        .offline-tag { color: var(--brand-red); display: inline-flex; align-items: center; gap: 4px; }

        /* Media Attachments */
        .chat-audio { width: 100%; max-width: 240px; height: 36px; border-radius: 18px; outline: none; }
        .chat-image { max-width: 100%; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1); }
        .chat-doc { display: flex; align-items: center; gap: 12px; background: rgba(0,0,0,0.05); padding: 12px; border-radius: 10px; text-decoration: none; color: inherit; }
        .bubble-client .chat-doc { background: rgba(255,255,255,0.1); }
        .chat-doc-icon { font-size: 1.8rem; color: var(--brand-red); }
        .bubble-client .chat-doc-icon { color: var(--brand-white); }

        /* ==========================================================================
           CHAT INPUT AREA
           ========================================================================== */
        .chat-input-wrapper { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); padding: 15px 20px 30px 20px; border-top: 1px solid var(--color-border); display: flex; flex-direction: column; gap: 12px; }

        .reply-banner { background: var(--brand-ghost); padding: 12px 16px; border-radius: 12px; border-left: 3px solid var(--brand-teal); display: flex; justify-content: space-between; align-items: center; }
        .media-banner { background: rgba(255, 51, 102, 0.05); border: 1px solid rgba(255, 51, 102, 0.2); padding: 12px 16px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: var(--brand-red); }

        .input-row { display: flex; align-items: flex-end; gap: 10px; }
        .btn-attach { width: 44px; height: 44px; border-radius: 50%; border: none; background: var(--brand-ghost); color: var(--color-text-muted); font-size: 1.2rem; cursor: pointer; flex-shrink: 0; transition: 0.2s; }
        .btn-attach:active { background: #E2E8F0; transform: scale(0.9); }

        .chat-textarea { flex-grow: 1; background: var(--brand-ghost); border: 1px solid transparent; border-radius: 22px; padding: 12px 18px; font-family: var(--font-body); font-size: 0.95rem; color: var(--color-text-main); resize: none; max-height: 120px; min-height: 44px; outline: none; transition: 0.2s; }
        .chat-textarea:focus { border-color: rgba(0, 229, 255, 0.3); background: var(--brand-white); box-shadow: 0 0 0 4px rgba(0, 229, 255, 0.1); }

        .btn-send { width: 44px; height: 44px; border-radius: 50%; border: none; background: var(--brand-teal); color: var(--brand-navy); font-size: 1.1rem; cursor: pointer; flex-shrink: 0; transition: 0.2s; display: flex; align-items: center; justify-content: center; font-weight: 800; }
        .btn-send:active:not(:disabled) { transform: scale(0.9); filter: brightness(0.9); }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-mic { background: var(--brand-slate); color: var(--brand-white); }
        .btn-mic.recording { background: var(--brand-red); animation: pulse 1.5s infinite; color: var(--brand-white); }

        .archived-lock { background: #FEF2F2; color: var(--brand-red); text-align: center; padding: 20px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; font-family: var(--font-mono); border-top: 1px solid rgba(255, 51, 102, 0.2); }

        /* ==========================================================================
           ANIMATIONS
           ========================================================================== */
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(255, 51, 102, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0); } }
    </style>
    @endpush

    <div class="offline-banner">
        <i class="fa-solid fa-wifi-slash" style="margin-right: 6px;"></i> OFFLINE MODE. SECURE QUEUE ACTIVE.
    </div>

    @if(!$isAuthorized)
        <div class="auth-screen">
            <img src="https://i.ibb.co/kZJNFD4/JBS-LOGO-FULL-COLOUR.png" alt="JBS Praxis" class="auth-logo">
            
            <div class="mono-label" style="margin-bottom: 8px;">// SECURE_VAULT</div>
            <h1 class="auth-title">{{ $tracker->project_name }}</h1>
            <p class="auth-subtitle">Client authorization required.</p>
            
            <div class="pin-wrapper" @click="focusPin()">
                <div class="pin-dots-container">
                    <template x-for="i in 4" :key="i">
                        <div class="pin-dot" :class="{ 'filled': pinCode.length >= i, 'error': @json($pinError) }"></div>
                    </template>
                </div>
                
                <input type="password" inputmode="numeric" maxlength="4" 
                       class="hidden-pin" x-ref="hiddenPin" 
                       x-model="pinCode" @input="handlePinInput" autofocus>
            </div>

            <div class="auth-error-text">
                @if($pinError)
                    <i class="fa-solid fa-circle-exclamation"></i> Authorization Denied.
                @endif
            </div>

            <div style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; font-family: var(--font-mono);">
                <i class="fa-solid fa-lock" style="font-size: 0.65rem;"></i> 256-BIT ENCRYPTION
            </div>
        </div>

    @else
        <div class="glass-header">
            <div>
                <img src="https://i.ibb.co/kZJNFD4/JBS-LOGO-FULL-COLOUR.png" alt="JBS Praxis" style="height: 24px; margin-bottom: 15px; opacity: 0.9;">
                
                <div class="client-name">
                    <i class="fa-regular fa-building"></i> 
                    {{ $tracker->client_company ?? $tracker->client_name }}
                </div>
                
                <h1 class="project-title">{{ $tracker->project_name }}</h1>
                
                <div class="meta-tags-container">
                    <span class="status-badge {{ $isArchived ? 'status-archived' : 'status-active' }}">
                        <i class="fa-solid {{ $isArchived ? 'fa-lock' : 'fa-circle-check' }}"></i>
                        {{ $isArchived ? 'Archived' : 'Active Deployment' }}
                    </span>
                    @if($projectDeadline)
                        <span class="status-badge status-deadline">
                            <i class="fa-regular fa-calendar"></i>
                            TARGET: {{ $projectDeadline->format('M d, Y') }}
                        </span>
                    @endif
                </div>
            </div>
            
            <div class="progress-ring">
                <svg class="progress-ring-svg" width="68" height="68">
                    <circle class="progress-circle-bg" cx="34" cy="34" r="30"></circle>
                    <circle class="progress-circle-value" cx="34" cy="34" r="30" 
                            stroke-dasharray="188.49" 
                            stroke-dashoffset="{{ 188.49 - (188.49 * $tracker->progress_percentage) / 100 }}"></circle>
                </svg>
                <div class="progress-text">{{ round($tracker->progress_percentage) }}%</div>
            </div>
        </div>

        <div class="timeline-container">
            @forelse($milestones as $milestone)
                @php 
                    $allDone = $milestone->tasks->count() > 0 && $milestone->tasks->every(fn($t) => $t->status === 'done'); 
                @endphp
                <div class="milestone-block">
                    <div class="milestone-dot {{ $allDone ? 'completed' : '' }}"></div>
                    
                    <div class="milestone-header">
                        <div>
                            <h2 class="milestone-title">{{ $milestone->title }}</h2>
                            @if($milestone->start_date && $milestone->end_date)
                                <div class="sprint-dates">
                                    <i class="fa-regular fa-clock" style="margin-right: 4px;"></i>
                                    {{ \Carbon\Carbon::parse($milestone->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($milestone->end_date)->format('M d, Y') }}
                                </div>
                            @endif
                            @if($milestone->is_payment_trigger)
                                <div class="payment-badge">
                                    <i class="fa-solid fa-file-invoice-dollar"></i> PAYMENT MILESTONE ({{ $milestone->payment_percentage }}%)
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    @foreach($milestone->tasks as $task)
                        <div class="task-card">
                            <div class="task-indicator {{ $task->status }}"></div>
                            <div class="task-content">
                                <div class="task-name">{{ $task->title }}</div>
                                
                                <div class="task-footer">
                                    <div class="task-assignee">
                                        <div class="assignee-avatar">
                                            {{ substr($task->assignee->name ?? 'U', 0, 1) }}
                                        </div>
                                        {{ $task->assignee->name ?? 'Pending Assignment' }}
                                    </div>
                                    @if($task->due_date)
                                        <div class="task-deadline">
                                            DUE: {{ \Carbon\Carbon::parse($task->due_date)->format('M d') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            @if(!$isArchived)
                                <button class="btn-reply" wire:click="setReplyContext('{{ $task->id }}')" title="Discuss Task">
                                    <i class="fa-solid fa-reply"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div style="text-align: center; padding: 60px 20px; color: var(--color-text-muted);">
                    <i class="fa-solid fa-layer-group" style="font-size: 2.5rem; color: var(--color-border); margin-bottom: 20px;"></i>
                    <p style="font-weight: 600;">Architecture blueprint pending.</p>
                </div>
            @endforelse
        </div>

        <button class="fab-chat" @click="openChat()">
            <i class="fa-regular fa-comment-dots"></i>
            <span class="unread-badge" x-show="unreadCount > 0" x-transition x-text="unreadCount"></span>
        </button>

        <div class="chat-sheet" :class="{ 'open': chatOpen }">
            <div class="chat-backdrop" @click="chatOpen = false; unreadCount = 0"></div>
            
            <div class="chat-panel">
                <div class="chat-sheet-header">
                    <button class="btn-close-sheet" @click="chatOpen = false; unreadCount = 0">
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div>
                        <div style="font-family: var(--font-display); font-weight: 900; font-size: 1.1rem; color: var(--brand-navy);">JBS Praxis CommLink</div>
                        <div class="mono-label" style="font-size: 0.6rem; color: var(--brand-teal);"><i class="fa-solid fa-shield-halved"></i> SECURE CHANNEL</div>
                    </div>
                </div>

                <div class="chat-scroller" x-ref="chatBody">
                    @forelse($messages as $msg)
                        <div class="bubble {{ $msg->sender_type === 'client' ? 'bubble-client' : 'bubble-staff' }}">
                            
                            @if($msg->sender_type === 'staff')
                                <div class="bubble-sender">{{ $msg->sender->name ?? 'JBS Engineer' }}</div>
                            @endif
                            
                            @if($msg->task_id)
                                <div class="bubble-context">
                                    <i class="fa-solid fa-link" style="margin-right: 4px;"></i> {{ $msg->task->title ?? 'Linked Task' }}
                                </div>
                            @endif

                            @if($msg->media_type === 'voice')
                                <audio controls class="chat-audio">
                                    <source src="{{ asset('storage/' . $msg->media_path) }}" type="audio/mpeg">
                                </audio>
                            @elseif($msg->media_type === 'image')
                                <a href="{{ asset('storage/' . $msg->media_path) }}" target="_blank">
                                    <img src="{{ asset('storage/' . $msg->media_path) }}" class="chat-image">
                                </a>
                            @elseif($msg->media_type === 'document')
                                <a href="{{ asset('storage/' . $msg->media_path) }}" target="_blank" class="chat-doc">
                                    <i class="fa-solid fa-file-pdf chat-doc-icon"></i>
                                    <span style="font-size: 0.85rem; font-weight: 700;">View Attachment</span>
                                </a>
                            @else
                                <div style="white-space: pre-wrap; word-break: break-word;">{{ $msg->body }}</div>
                            @endif
                            
                            <div class="bubble-meta">
                                {{ $msg->created_at->format('H:i') }}
                            </div>
                        </div>
                    @empty
                        <div style="text-align: center; color: var(--color-text-muted); font-weight: 600; font-size: 0.9rem; margin-top: 40px;">
                            Secure channel established.<br>Awaiting communication.
                        </div>
                    @endforelse

                    <template x-for="(offMsg, index) in offlineMessages" :key="index">
                        <div class="bubble bubble-client" style="opacity: 0.7;">
                            <div class="bubble-context" x-show="offMsg.taskTitle">
                                <i class="fa-solid fa-wifi-slash"></i> <span x-text="offMsg.taskTitle"></span>
                            </div>
                            <div style="white-space: pre-wrap; word-break: break-word;" x-text="offMsg.body"></div>
                            <div class="bubble-meta offline-tag">
                                <i class="fa-solid fa-cloud-arrow-up"></i> QUEUED
                            </div>
                        </div>
                    </template>
                </div>

                @if(!$isArchived)
                    <div class="chat-input-wrapper">
                        
                        @if($replyingToTask)
                            <div class="reply-banner">
                                <div>
                                    <div class="mono-label" style="color: var(--brand-navy);">TARGET ARCHITECTURE</div>
                                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--brand-navy);" class="truncate">
                                        {{ $replyingToTaskTitle ?? 'Task Context' }}
                                    </div>
                                </div>
                                <button wire:click="clearReplyContext" style="background: none; border: none; color: var(--color-text-muted); font-size: 1.2rem; cursor: pointer;">&times;</button>
                            </div>
                        @endif

                        @if($mediaUpload)
                            <div class="media-banner">
                                <span><i class="fa-solid fa-paperclip" style="margin-right: 6px;"></i> Asset Ready</span>
                                <button type="button" wire:click="$set('mediaUpload', null)" style="background: none; border: none; color: var(--brand-red); cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        @endif

                        <div class="input-row">
                            <input type="file" id="clientMedia" wire:model="mediaUpload" accept="image/*, application/pdf" style="display: none;">
                            <button type="button" class="btn-attach" onclick="document.getElementById('clientMedia').click()">
                                <i class="fa-solid fa-plus"></i>
                            </button>

                            <textarea class="chat-textarea" x-model="localMessage" placeholder="Transmit to engineering..." 
                                      @input="resizeTextarea" x-ref="chatInput"
                                      @keydown.enter.prevent="sendProtocol"></textarea>
                            
                            <button type="button" class="btn-send btn-mic" :class="{ 'recording': isRecording }" 
                                    x-show="localMessage.length === 0 && !@this.mediaUpload" 
                                    @touchstart.prevent="startRecording" 
                                    @touchend.prevent="stopRecording"
                                    @mousedown.prevent="startRecording"
                                    @mouseup.prevent="stopRecording">
                                <i class="fa-solid fa-microphone"></i>
                            </button>
                            
                            <button type="button" class="btn-send" 
                                    x-show="localMessage.length > 0 || @this.mediaUpload" 
                                    @click="sendProtocol" 
                                    wire:loading.attr="disabled" wire:target="transmitMessage, mediaUpload">
                                <i class="fa-solid fa-arrow-up" wire:loading.remove wire:target="transmitMessage, mediaUpload"></i>
                                <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="transmitMessage, mediaUpload"></i>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="archived-lock">
                        <i class="fa-solid fa-lock" style="margin-right: 6px;"></i> Architecture Locked. Read-Only.
                    </div>
                @endif
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('executiveTracker', () => ({
                chatOpen: false,
                localMessage: '',
                unreadCount: 0,
                
                // PIN State
                pinCode: '',
                
                // Offline Logic
                isOnline: navigator.onLine,
                offlineMessages: JSON.parse(localStorage.getItem('jbs_tracker_offline_{{ $tracker->id }}') || '[]'),
                
                // Voice Recording Logic
                isRecording: false,
                mediaRecorder: null,
                audioChunks: [],

                initEngine() {
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.syncOfflineData();
                    });
                    window.addEventListener('offline', () => { this.isOnline = false; });

                    Livewire.on('message-transmitted', () => {
                        this.localMessage = '';
                        this.resizeTextarea();
                        this.scrollToBottom();
                    });

                    Livewire.on('focus-chat-input', () => {
                        this.openChat();
                    });

                    Livewire.on('system-alert', (data) => {
                        alert(data[0].message); 
                    });
                },

                handlePinInput() {
                    this.pinCode = this.pinCode.replace(/[^0-9]/g, '').substring(0, 4);
                    
                    if (this.pinCode.length === 4) {
                        @this.verifyPin(this.pinCode);
                        setTimeout(() => { if(@this.pinError) this.pinCode = ''; }, 600);
                    }
                },

                focusPin() {
                    if (this.$refs.hiddenPin) {
                        this.$refs.hiddenPin.focus();
                    }
                },

                openChat() {
                    this.chatOpen = true;
                    this.unreadCount = 0;
                    this.scrollToBottom();
                    setTimeout(() => {
                        if(this.$refs.chatInput) this.$refs.chatInput.focus();
                    }, 300);
                },

                scrollToBottom() {
                    setTimeout(() => {
                        if(this.$refs.chatBody) {
                            this.$refs.chatBody.scrollTop = this.$refs.chatBody.scrollHeight;
                        }
                    }, 150);
                },

                resizeTextarea() {
                    const el = this.$refs.chatInput;
                    if(el) {
                        el.style.height = 'auto';
                        el.style.height = (el.scrollHeight) + 'px';
                    }
                },

                sendProtocol() {
                    if (!this.localMessage.trim() && !@this.mediaUpload) return;

                    if (this.isOnline) {
                        @this.set('chatMessage', this.localMessage);
                        @this.transmitMessage();
                        this.localMessage = '';
                        this.resizeTextarea();
                    } else {
                        const payload = {
                            body: this.localMessage,
                            taskId: @this.replyingToTask,
                            taskTitle: @this.replyingToTaskTitle ? @this.replyingToTaskTitle : null,
                            timestamp: new Date().toISOString()
                        };
                        this.offlineMessages.push(payload);
                        localStorage.setItem('jbs_tracker_offline_{{ $tracker->id }}', JSON.stringify(this.offlineMessages));
                        
                        this.localMessage = '';
                        this.resizeTextarea();
                        this.scrollToBottom();
                        @this.clearReplyContext();
                    }
                },

                syncOfflineData() {
                    if (this.offlineMessages.length > 0) {
                        @this.syncOfflinePayload(this.offlineMessages).then(() => {
                            this.offlineMessages = [];
                            localStorage.removeItem('jbs_tracker_offline_{{ $tracker->id }}');
                            this.scrollToBottom();
                        });
                    }
                },

                async startRecording() {
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        alert('Voice capability requires modern browser environment.');
                        return;
                    }
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.mediaRecorder = new MediaRecorder(stream);
                        this.audioChunks = [];

                        this.mediaRecorder.ondataavailable = e => {
                            if (e.data.size > 0) this.audioChunks.push(e.data);
                        };

                        this.mediaRecorder.onstop = () => {
                            const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                            const audioFile = new File([audioBlob], 'voice_transmission.webm', { type: 'audio/webm' });
                            
                            @this.upload('mediaUpload', audioFile, () => {
                                @this.transmitMessage();
                            });
                        };

                        this.mediaRecorder.start();
                        this.isRecording = true;
                    } catch (err) {
                        alert('Microphone access denied. Secure your browser permissions.');
                    }
                },

                stopRecording() {
                    if (this.mediaRecorder && this.isRecording) {
                        this.mediaRecorder.stop();
                        this.isRecording = false;
                        this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
                    }
                }
            }));
        });
    </script>
</div>