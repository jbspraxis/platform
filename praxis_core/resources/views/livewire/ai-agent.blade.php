<div x-data="{ 
        isNexusOpen: false, 
        init() {
            window.addEventListener('scrollToBottom', () => {
                setTimeout(() => {
                    let container = this.$refs.chatContainer;
                    container.scrollTop = container.scrollHeight;
                }, 50);
            });
        }
    }" 
    class="nexus-ai-wrapper">
    
    <style>
        /* --- NEXUS AI WIDGET CSS --- */
        .nexus-ai-wrapper { position: fixed; bottom: 30px; right: 30px; z-index: 9999; font-family: 'Inter', sans-serif; }

        /* Trigger Node */
        .nexus-trigger-btn {
            width: 60px; height: 60px; background: rgba(10, 17, 40, 0.9); border: 1px solid var(--luminous-teal);
            border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.3), inset 0 0 10px rgba(0, 229, 255, 0.2);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); position: relative;
        }
        .nexus-trigger-btn::before {
            content: ''; position: absolute; top: -5px; left: -5px; right: -5px; bottom: -5px;
            border-radius: 50%; border: 1px solid rgba(0, 229, 255, 0.5); animation: radarPulse 2s infinite; pointer-events: none;
        }
        @keyframes radarPulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }
        .nexus-trigger-btn:hover { transform: scale(1.05); background: var(--luminous-teal); box-shadow: 0 0 30px rgba(0, 229, 255, 0.6); }
        .nexus-trigger-btn:hover .trigger-icon { color: var(--midnight-navy); }
        .trigger-icon { font-family: 'Roboto Mono', monospace; font-weight: 900; font-size: 1.2rem; color: var(--luminous-teal); transition: color 0.3s ease; }

        /* Terminal */
        .nexus-terminal {
            position: absolute; bottom: 80px; right: 0; width: 380px; height: 600px; max-height: 80vh;
            background: linear-gradient(145deg, rgba(10, 17, 40, 0.85) 0%, rgba(3, 5, 10, 0.95) 100%);
            backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(0, 229, 255, 0.2); border-top: 3px solid var(--luminous-teal); border-radius: 8px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.8); display: flex; flex-direction: column; overflow: hidden; transform-origin: bottom right;
        }

        .term-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0, 229, 255, 0.03); }
        .term-title { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; color: var(--stark-white); display: flex; align-items: center; gap: 10px; }
        .term-status { width: 8px; height: 8px; background: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; }
        .term-close { color: var(--text-secondary); cursor: pointer; font-size: 1.2rem; line-height: 1; transition: color 0.2s; }
        .term-close:hover { color: var(--terminal-red); }

        .term-body { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; scroll-behavior: smooth; }
        .term-body::-webkit-scrollbar { width: 4px; }
        .term-body::-webkit-scrollbar-thumb { background: rgba(0, 229, 255, 0.3); border-radius: 2px; }

        .msg-block { display: flex; flex-direction: column; max-width: 90%; }
        .msg-block.user { align-self: flex-end; align-items: flex-end; }
        .msg-block.ai { align-self: flex-start; align-items: flex-start; }

        .msg-bubble { padding: 12px 16px; border-radius: 6px; font-size: 0.85rem; line-height: 1.6; word-wrap: break-word; }
        
        .msg-block.user .msg-bubble {
            background: rgba(0, 229, 255, 0.1); border: 1px solid rgba(0, 229, 255, 0.3);
            color: var(--luminous-teal); border-bottom-right-radius: 0; font-family: 'Inter', sans-serif;
        }
        
        .msg-block.ai .msg-bubble {
            background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.05);
            border-left: 2px solid var(--luminous-teal); color: var(--stark-white);
            border-top-left-radius: 0; font-family: 'Roboto Mono', monospace;
        }

        .msg-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--text-secondary); margin-bottom: 5px; opacity: 0.7; }

        /* MARKDOWN RENDERING RULES */
        .msg-bubble p { margin-bottom: 12px; }
        .msg-bubble p:last-child { margin-bottom: 0; }
        
        /* Bolding & Links */
        .msg-bubble strong { font-weight: 900; color: var(--stark-white); }
        .msg-block.user .msg-bubble strong { color: var(--midnight-navy); }
        .msg-bubble a { color: var(--luminous-teal); text-decoration: underline; text-underline-offset: 3px; font-weight: bold; transition: color 0.3s; }
        .msg-bubble a:hover { color: var(--stark-white); }
        
        /* The specific Praxis Nexus glowing header */
        .msg-block.ai .msg-bubble strong:first-child { color: var(--luminous-teal); font-size: 0.95rem; letter-spacing: 1px; display: block; margin-bottom: 15px; }

        /* Input Area */
        .term-footer { padding: 15px; border-top: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.3); }
        .term-input-wrapper { display: flex; align-items: center; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 5px 10px; transition: border 0.3s; }
        .term-input-wrapper:focus-within { border-color: var(--luminous-teal); box-shadow: 0 0 15px rgba(0, 229, 255, 0.1); }
        
        .term-input { flex: 1; background: transparent; border: none; color: var(--stark-white); font-family: 'Inter', sans-serif; font-size: 0.85rem; padding: 10px; outline: none; }
        .term-input::placeholder { color: var(--text-secondary); opacity: 0.5; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; }
        
        .term-send-btn { background: transparent; border: none; color: var(--luminous-teal); cursor: pointer; padding: 10px; transition: transform 0.2s; display: flex; align-items: center; justify-content: center; }
        .term-send-btn:hover { transform: scale(1.1); text-shadow: 0 0 10px var(--luminous-teal); }
        .term-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Kinetic Typing Indicator */
        .typing-indicator { display: flex; gap: 4px; padding: 10px 15px; background: rgba(0, 0, 0, 0.4); border-left: 2px solid var(--luminous-teal); border-radius: 6px; border-top-left-radius: 0; width: fit-content; margin-top: 10px;}
        .typing-dot { width: 4px; height: 4px; background: var(--luminous-teal); border-radius: 50%; animation: typingPulse 1.4s infinite ease-in-out both; }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typingPulse { 0%, 80%, 100% { transform: scale(0); opacity: 0.3; } 40% { transform: scale(1); opacity: 1; } }

        @media (max-width: 600px) {
            .nexus-terminal { width: calc(100vw - 40px); right: -10px; bottom: 70px; height: 75vh; }
        }
    </style>

    <div class="nexus-trigger-btn" @click="isNexusOpen = !isNexusOpen" x-show="!isNexusOpen" x-transition:enter="transition ease-out duration-300 delay-200" x-transition:enter-start="opacity-0 scale-50" x-transition:enter-end="opacity-100 scale-100">
        <span class="trigger-icon">>_</span>
    </div>

    <div class="nexus-terminal" 
         x-show="isNexusOpen" 
         x-transition:enter="transition ease-out duration-400"
         x-transition:enter-start="opacity-0 scale-90 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-90 translate-y-10"
         style="display: none;">
        
        <div class="term-header">
            <div class="term-title">
                <span class="term-status"></span>
                PRAXIS AI [NEXUS]
            </div>
            <div class="term-close" @click="isNexusOpen = false">×</div>
        </div>

        <div class="term-body" x-ref="chatContainer">
            @foreach($messages as $index => $msg)
                <div class="msg-block {{ $msg['role'] === 'user' ? 'user' : 'ai' }}" wire:key="msg-{{ $index }}">
                    <span class="msg-label">{{ $msg['role'] === 'user' ? 'GUEST_NODE' : 'PRAXIS_MAINFRAME' }}</span>
                    <div class="msg-bubble">{!! $this->renderMarkdown($msg['content']) !!}</div>
                </div>
            @endforeach

            <div class="msg-block ai" wire:loading wire:target="fetchAiResponse">
                <span class="msg-label">PRAXIS_MAINFRAME</span>
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        </div>

        <div class="term-footer">
            <form wire:submit.prevent="submitUserMessage">
                <div class="term-input-wrapper">
                    <input type="text" class="term-input" wire:model="userInput" placeholder="Query the architecture..." required autocomplete="off">
                    <button type="submit" class="term-send-btn" wire:loading.attr="disabled" wire:target="submitUserMessage">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>