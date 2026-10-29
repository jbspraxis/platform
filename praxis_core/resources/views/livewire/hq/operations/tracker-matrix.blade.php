<div class="system-matrix-wrapper" 
     x-data="trackerMatrixEngine()"
     @modal-close.window="closeModal($event.detail[0].modal)"
     @system-alert.window="addAlert($event.detail[0])"
     @message-transmitted.window="scrollChatToBottom()"
     style="height: 100vh; width: 100vw; overflow: hidden; background-color: #010308; color: #FFFFFF; font-family: 'Inter', sans-serif; position: relative; display: flex; flex-direction: column;">
    
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600&family=Inter:wght@300;400;500;600&family=Montserrat:wght@400;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <div style="position: absolute; inset: 0; background-image: url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2000&auto=format&fit=crop'); background-size: cover; background-position: center; z-index: 0; filter: grayscale(80%) contrast(1.2) brightness(0.15) hue-rotate(200deg);"></div>
    <div style="position: absolute; inset: 0; background: radial-gradient(circle at center, rgba(1,3,8,0.4) 0%, #010308 100%); z-index: 1;"></div>

    <style>
        /* ==========================================================================
           MATRIX THEME CORE
           ========================================================================== */
        :root {
            --matrix-cyan: #00C3FF;
            --matrix-red: #FF3366;
            --matrix-bg: rgba(2, 6, 15, 0.85);
            --matrix-border: rgba(0, 195, 255, 0.2);
            --font-mono: 'Fira Code', monospace;
            --font-display: 'Montserrat', sans-serif;
            --font-body: 'Inter', sans-serif;
            /* FIX 5: Define previously missing status colour variables */
            --status-done: #00FF88;
            --status-progress: #FFB800;
        }

        .glass-panel {
            background: linear-gradient(135deg, rgba(2, 6, 15, 0.85), rgba(0, 195, 255, 0.05));
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--matrix-border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5), inset 0 0 15px rgba(0, 195, 255, 0.05);
        }

        /* Forms & Inputs */
        .minimal-input {
            width: 100%; box-sizing: border-box; background: rgba(0, 0, 0, 0.6); border: 1px solid rgba(255, 255, 255, 0.15); 
            color: #FFF; font-family: var(--font-body); font-size: 0.85rem; padding: 12px 16px; 
            border-radius: 4px; outline: none; transition: all 0.3s ease; color-scheme: dark;
        }
        .minimal-input:focus { border-color: var(--matrix-cyan); background: rgba(0, 195, 255, 0.05); box-shadow: 0 0 15px rgba(0, 195, 255, 0.1) inset; }
        .input-error { border-color: var(--matrix-red) !important; }
        
        /* High Contrast Dropdown Fix */
        select.minimal-input { appearance: auto; cursor: pointer; }
        select.minimal-input option { background-color: #010308; color: #FFFFFF; font-weight: 500; }
        select.minimal-input:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-glass {
            background: rgba(0, 195, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(0, 195, 255, 0.3); 
            color: var(--matrix-cyan); font-family: var(--font-mono); font-size: 0.75rem; font-weight: 600; 
            text-transform: uppercase; letter-spacing: 1px; padding: 10px 16px; border-radius: 4px; 
            cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center;
        }
        .btn-glass:hover:not(:disabled) { background: rgba(0, 195, 255, 0.2); border-color: var(--matrix-cyan); color: #FFF; box-shadow: 0 0 20px rgba(0, 195, 255, 0.4); }
        .btn-glass:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-danger { color: var(--matrix-red); border-color: rgba(255, 51, 102, 0.3); background: rgba(255, 51, 102, 0.05); }
        .btn-danger:hover:not(:disabled) { background: rgba(255, 51, 102, 0.2); border-color: var(--matrix-red); color: #FFF; box-shadow: 0 0 20px rgba(255, 51, 102, 0.4); }

        /* KANBAN ARCHITECTURE */
        .kanban-board { display: flex; gap: 25px; overflow-x: auto; padding: 20px 40px; height: 100%; align-items: flex-start; scroll-behavior: smooth; }
        .kanban-board::-webkit-scrollbar { height: 8px; }
        .kanban-board::-webkit-scrollbar-thumb { background: rgba(0, 195, 255, 0.3); border-radius: 4px; }
        
        .kanban-column { min-width: 360px; width: 360px; max-height: 100%; display: flex; flex-direction: column; border-radius: 8px; }
        .kanban-header { padding: 15px 20px; border-bottom: 1px solid var(--matrix-border); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.6); border-radius: 8px 8px 0 0; }
        .kanban-cards { padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; flex-grow: 1; }
        .kanban-cards::-webkit-scrollbar { width: 4px; }
        .kanban-cards::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); }

        .k-card { background: rgba(10, 15, 25, 0.85); border: 1px solid rgba(255,255,255,0.08); padding: 16px; border-radius: 6px; transition: 0.3s; position: relative; display: flex; flex-direction: column; gap: 12px; }
        .k-card:hover { border-color: var(--matrix-cyan); background: rgba(0, 195, 255, 0.05); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        
        .progress-bar-bg { height: 6px; background: rgba(255,255,255,0.1); width: 250px; border-radius: 3px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: var(--matrix-cyan); box-shadow: 0 0 10px var(--matrix-cyan); transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* ALERT SYSTEM */
        .toast-container { position: fixed; top: 30px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; align-items: center; gap: 10px; pointer-events: none; }
.toast { padding: 12px 20px; border-radius: 4px; font-family: var(--font-mono); font-size: 0.75rem; display: flex; align-items: center; gap: 12px; backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid; animation: slideIn 0.3s ease forwards; pointer-events: auto; min-width: 300px; max-width: 480px; }
.toast.success { background: rgba(0, 255, 136, 0.1); border-color: #00FF88; color: #00FF88; }
.toast.error { background: rgba(255, 51, 102, 0.1); border-color: var(--matrix-red); color: var(--matrix-red); }
.toast-close { margin-left: auto; background: none; border: none; cursor: pointer; color: inherit; opacity: 0.6; font-size: 0.85rem; padding: 0; line-height: 1; transition: opacity 0.2s; flex-shrink: 0; }
.toast-close:hover { opacity: 1; }
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .modal-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    inset: 0;
    background: rgba(1, 3, 8, 0.85);
    backdrop-filter: blur(8px);
    z-index: 1000;
}

        /* FLOATING CHAT BUTTON & DRAWER */
        .fab-container { position: fixed; bottom: 30px; right: 40px; z-index: 90; }
        .fab-btn { width: 64px; height: 64px; border-radius: 50%; background: rgba(0, 195, 255, 0.15); border: 1px solid var(--matrix-cyan); color: var(--matrix-cyan); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; cursor: pointer; backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.5), 0 0 20px rgba(0, 195, 255, 0.2); transition: 0.3s; }
        .fab-btn:hover { transform: scale(1.05); background: var(--matrix-cyan); color: #010308; }
        
        .chat-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: 450px; background: rgba(2, 6, 15, 0.95); backdrop-filter: blur(30px); border-left: 1px solid var(--matrix-border); z-index: 100; display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: -10px 0 50px rgba(0,0,0,0.5); }
        .chat-drawer.open { transform: translateX(0); }
        .chat-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 95; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .chat-backdrop.open { opacity: 1; pointer-events: auto; }

        /* Chat Bubbles */
        .chat-bubble { max-width: 85%; padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; line-height: 1.5; margin-bottom: 15px; position: relative; }
        .chat-staff { align-self: flex-end; background: rgba(0, 195, 255, 0.15); border: 1px solid rgba(0, 195, 255, 0.3); border-bottom-right-radius: 0; }
        .chat-client { align-self: flex-start; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); border-bottom-left-radius: 0; }
        .chat-image { max-width: 100%; border-radius: 4px; margin-top: 5px; border: 1px solid rgba(255,255,255,0.1); }
    </style>

    <div class="toast-container">
    <template x-for="alert in alerts" :key="alert.id">
        <div class="toast" :class="alert.type">
            <i class="fa-solid" :class="alert.type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check'"></i>
            <span x-text="alert.message" style="flex: 1;"></span>
            <button class="toast-close" @click="dismissAlert(alert.id)" title="Dismiss">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </template>
</div>

    <header class="glass-panel" style="position: relative; z-index: 20; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-radius: 0; border-top: none; border-left: none; border-right: none;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <img src="https://i.ibb.co/9mX9TgfJ/JBS-LOGO-ON-GREY.png" alt="JBS Praxis" style="height: 40px; filter: brightness(0) invert(1) opacity(0.9);">
            <div style="border-left: 1px solid rgba(255,255,255,0.2); padding-left: 20px;">
                <div style="font-family: var(--font-mono); font-size: 0.7rem; color: var(--matrix-cyan); letter-spacing: 2px; margin-bottom: 5px;">// HQ : TRACKER_MATRIX</div>
                <h1 style="font-family: var(--font-display); font-size: 1.4rem; font-weight: 800; text-transform: uppercase; margin: 0;">{{ $activeTracker->project_name }}</h1>
            </div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 40px;">
            <div style="text-align: right;">
                <div style="font-family: var(--font-mono); font-size: 0.7rem; color: rgba(255,255,255,0.6); margin-bottom: 8px;">SYSTEM COMPLETION: <span style="color: var(--matrix-cyan); font-weight: bold;">{{ $activeTracker->progress_percentage }}%</span></div>
                <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: {{ $activeTracker->progress_percentage }}%;"></div></div>
            </div>
            
            @if(auth('tracker')->user()->role !== 'consultant')
                <button class="btn-glass" @click="openModal('milestone')">
                    <i class="fa-solid fa-plus" style="margin-right: 8px;"></i> INJECT MILESTONE
                </button>
            @endif
        </div>
    </header>

    <div style="display: flex; flex-grow: 1; overflow: hidden; position: relative; z-index: 10;">
        
        <div class="kanban-board">
            @forelse($milestones as $milestone)
                <div class="kanban-column glass-panel">
                    <div class="kanban-header">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <h3 style="font-family: var(--font-display); font-size: 0.95rem; font-weight: 700; text-transform: uppercase; margin: 0; letter-spacing: 1px; color: #FFF;">{{ $milestone->title }}</h3>
                        </div>
                        
                        @if(auth('tracker')->user()->role !== 'consultant')
                            <div style="display: flex; gap: 8px;">
                                <button @click="$wire.set('editingMilestoneId', '{{ $milestone->id }}'); $wire.set('editingMilestoneTitle', '{{ addslashes($milestone->title) }}'); openModal('editMilestone');" 
                                        style="background: transparent; border: none; color: rgba(255,255,255,0.5); cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='rgba(255,255,255,0.5)'" title="Edit Milestone">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button wire:click="deleteMilestone('{{ $milestone->id }}')" wire:confirm="WARNING: This will permanently purge this milestone and all child tasks. Execute?"
                                        style="background: transparent; border: none; color: rgba(255,255,255,0.5); cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='#FF3366'" onmouseout="this.style.color='rgba(255,255,255,0.5)'" title="Purge Milestone">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <button @click="activeMilestoneId = '{{ $milestone->id }}'; $wire.set('newTaskTitle', ''); $wire.set('newTaskAssignee', ''); $wire.set('newTaskDueDate', ''); openModal('task');" 
                                        style="background: rgba(0,195,255,0.1); border: 1px solid rgba(0,195,255,0.3); width: 26px; height: 26px; border-radius: 4px; color: var(--matrix-cyan); cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#00C3FF'; this.style.color='#010308'" onmouseout="this.style.background='rgba(0,195,255,0.1)'; this.style.color='#00C3FF'" title="Inject Task">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="kanban-cards">
                        @forelse($milestone->tasks as $task)
                            <div class="k-card">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                                    <div style="font-size: 0.9rem; font-weight: 500; line-height: 1.4; flex-grow: 1;">{{ $task->title }}</div>
                                    
                                    @if(auth('tracker')->user()->role !== 'consultant')
                                        <div style="display: flex; gap: 8px; flex-shrink: 0;">
                                            <button @click="$wire.loadTaskForEditing('{{ $task->id }}')" title="Edit Task Parameters" style="background: transparent; border: none; color: rgba(255,255,255,0.3); cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='#00C3FF'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
                                                <i class="fa-solid fa-sliders"></i>
                                            </button>
                                            <button wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Purge task node?" title="Purge Task" style="background: transparent; border: none; color: rgba(255,255,255,0.3); cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='#FF3366'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 24px; height: 24px; border-radius: 50%; background: rgba(0,195,255,0.1); border: 1px solid var(--matrix-cyan); display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: var(--matrix-cyan); font-family: var(--font-mono); font-weight: bold;">
                                            {{ $task->assignee ? substr($task->assignee->name, 0, 1) : '?' }}
                                        </div>
                                        <span style="font-family: var(--font-mono); font-size: 0.65rem; color: rgba(255,255,255,0.6);">
                                            {{ $task->assignee ? strtoupper($task->assignee->name) : 'UNASSIGNED' }}
                                        </span>
                                    </div>
                                    @if($task->due_date)
                                        <div style="font-family: var(--font-mono); font-size: 0.65rem; color: rgba(255,255,255,0.5);">
                                            <i class="fa-regular fa-calendar" style="margin-right: 3px;"></i> {{ \Carbon\Carbon::parse($task->due_date)->format('M d') }}
                                        </div>
                                    @endif
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <select class="minimal-input" wire:change="updateTaskStatus('{{ $task->id }}', $event.target.value)" 
                                            style="width: 140px; padding: 6px 10px; font-size: 0.7rem; font-family: var(--font-mono); border-color: {{ $task->status === 'done' ? 'var(--status-done)' : ($task->status === 'in_progress' ? 'var(--status-progress)' : 'rgba(255,255,255,0.2)') }}; color: {{ $task->status === 'done' ? 'var(--status-done)' : ($task->status === 'in_progress' ? 'var(--status-progress)' : '#FFF') }};" 
                                            @if(auth('tracker')->user()->role === 'consultant' && $task->assigned_to !== auth('tracker')->id()) disabled @endif>
                                        <option value="todo" @if($task->status == 'todo') selected @endif>TODO</option>
                                        <option value="in_progress" @if($task->status == 'in_progress') selected @endif>IN PROGRESS</option>
                                        <option value="done" @if($task->status == 'done') selected @endif>DONE</option>
                                    </select>

                                    @if(auth('tracker')->user()->role !== 'consultant')
                                        <button @click="$wire.set('activeTaskId', '{{ $task->id }}'); openModal('reassign');" title="Transfer Operative" style="background: transparent; border: none; color: rgba(255,255,255,0.4); cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='#00C3FF'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">
                                            <i class="fa-solid fa-user-group"></i> Transfer
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div style="text-align: center; color: rgba(255,255,255,0.2); font-family: var(--font-mono); font-size: 0.7rem; padding: 20px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 6px;">[ EMPTY NODE ]</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div style="margin: auto; text-align: center; color: rgba(255,255,255,0.3); font-family: var(--font-mono);">
                    <i class="fa-solid fa-satellite-dish" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i><br>
                    AWAITING ARCHITECTURE INJECTION
                </div>
            @endforelse
        </div>
    </div>

    <div class="fab-container">
        <button class="fab-btn" @click="modals.chat = true; scrollChatToBottom()">
            <i class="fa-brands fa-whatsapp"></i>
        </button>
    </div>

    <div class="chat-backdrop" :class="{ 'open': modals.chat }" @click="modals.chat = false"></div>
    <div class="chat-drawer" :class="{ 'open': modals.chat }">
        <div style="padding: 20px 25px; border-bottom: 1px solid var(--matrix-border); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.5);">
            <div>
                <div style="font-family: var(--font-display); font-weight: 700; font-size: 1.1rem; color: #FFF;"><i class="fa-brands fa-whatsapp" style="color: #25D366; margin-right: 8px;"></i> CLIENT COMMS</div>
                <div style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--matrix-cyan); margin-top: 5px;">SECURE CHANNEL // ACTIVE</div>
            </div>
            <button @click="modals.chat = false" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); width: 36px; height: 36px; border-radius: 50%; color: #FFF; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='rgba(255,51,102,0.2)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="flex-grow: 1; padding: 20px 25px; overflow-y: auto; display: flex; flex-direction: column;" id="chat-scroller">
            @foreach($messages as $msg)
                <div class="chat-bubble {{ $msg->sender_type === 'staff' ? 'chat-staff' : 'chat-client' }}">
                    <div style="font-family: var(--font-mono); font-size: 0.6rem; color: {{ $msg->sender_type === 'staff' ? 'var(--matrix-cyan)' : 'rgba(255,255,255,0.5)' }}; margin-bottom: 5px; display: flex; justify-content: space-between;">
                        <span>{{ $msg->sender_type === 'staff' ? strtoupper($msg->sender->name ?? 'HQ') : 'CLIENT' }}</span>
                        <span>{{ $msg->created_at->format('H:i') }}</span>
                    </div>
                    
                    @if($msg->task_id)
                        <div style="font-family: var(--font-mono); font-size: 0.65rem; color: #FFF; background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px; margin-bottom: 8px; border-left: 2px solid var(--matrix-cyan);">
                            Re: {{ $msg->task->title ?? 'Task' }}
                        </div>
                    @endif
                    
                    @if($msg->media_type === 'image')
                        <a href="{{ asset('storage/' . $msg->media_path) }}" target="_blank">
                            <img src="{{ asset('storage/' . $msg->media_path) }}" class="chat-image" alt="Attachment">
                        </a>
                    @endif
                    
                    @if($msg->body)
                        <div style="white-space: pre-wrap; margin-top: 5px; color: #FFF;">{{ $msg->body }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        <form wire:submit.prevent="transmitMessage" style="padding: 20px 25px; border-top: 1px solid var(--matrix-border); background: rgba(0,0,0,0.5);">
            @error('chatMessage') <div class="error-msg" style="margin-bottom: 10px;">{{ $message }}</div> @enderror
            @error('mediaUpload') <div class="error-msg" style="margin-bottom: 10px;">{{ $message }}</div> @enderror

            @if($mediaUpload)
                <div style="margin-bottom: 10px; font-family: var(--font-mono); font-size: 0.7rem; color: var(--matrix-cyan); display: flex; align-items: center; justify-content: space-between; background: rgba(0,195,255,0.1); padding: 8px 12px; border-radius: 4px;">
                    <span><i class="fa-solid fa-image"></i> Data Pack Ready</span>
                    <button type="button" wire:click="$set('mediaUpload', null)" style="background:none; border:none; color: var(--matrix-red); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
            @endif

            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <input type="file" id="mediaUploadInput" wire:model="mediaUpload" accept="image/*" style="display: none;">
                <button type="button" onclick="document.getElementById('mediaUploadInput').click()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); color: #FFF; width: 44px; height: 44px; border-radius: 4px; cursor: pointer; transition: 0.2s; flex-shrink: 0;" title="Attach Image" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                    <i class="fa-solid fa-paperclip"></i>
                </button>

                <textarea wire:model.defer="chatMessage" class="minimal-input" placeholder="Transmit response..." style="min-height: 44px; max-height: 120px; resize: none; border-radius: 4px;"></textarea>
                
                <button type="submit" wire:loading.attr="disabled" style="background: var(--matrix-cyan); border: none; color: #010308; width: 44px; height: 44px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; flex-shrink: 0;" onmouseover="this.style.filter='brightness(1.2)'" onmouseout="this.style.filter='brightness(1)'">
                    <i class="fa-solid fa-paper-plane" wire:loading.remove wire:target="transmitMessage, mediaUpload"></i>
                    <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="transmitMessage, mediaUpload"></i>
                </button>
            </div>
        </form>
    </div>

    {{-- MODAL: INJECT MILESTONE --}}
    {{-- FIX 2: Removed redundant :style binding that conflicted with x-show --}}
    <div x-show="modals.milestone" class="modal-overlay" x-transition>
        <div class="glass-panel" style="width: 400px; padding: 30px; border-radius: 8px;" @click.away="closeModal('milestone')">
            <div style="font-family: var(--font-mono); color: var(--matrix-cyan); margin-bottom: 20px; font-size: 0.85rem; font-weight: 600;">> INJECT_MILESTONE</div>
            <div style="margin-bottom: 20px;">
                <input type="text" class="minimal-input @error('newMilestoneTitle') input-error @enderror" wire:model.defer="newMilestoneTitle" placeholder="Milestone Designation..." @keydown.enter="$wire.injectMilestone()">
                @error('newMilestoneTitle') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn-glass" style="flex: 1; background: rgba(0,195,255,0.2);" wire:click="injectMilestone" wire:loading.attr="disabled" wire:target="injectMilestone">
                    <span wire:loading.remove wire:target="injectMilestone">EXECUTE</span>
                    <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="injectMilestone"></i>
                </button>
                <button class="btn-glass btn-danger" style="flex: 1;" @click="closeModal('milestone')">ABORT</button>
            </div>
        </div>
    </div>

    {{-- MODAL: EDIT MILESTONE --}}
    {{-- FIX 2: Removed redundant :style binding that conflicted with x-show --}}
    {{-- FIX 3: Removed invalid $wire.errors setTimeout hack; modal close is now handled by the server-side dispatch --}}
    <div x-show="modals.editMilestone" class="modal-overlay" x-transition>
        <div class="glass-panel" style="width: 400px; padding: 30px; border-radius: 8px;" @click.away="closeModal('editMilestone')">
            <div style="font-family: var(--font-mono); color: var(--matrix-cyan); margin-bottom: 20px; font-size: 0.85rem; font-weight: 600;">> UPDATE_MILESTONE</div>
            <div style="margin-bottom: 20px;">
                <input type="text" class="minimal-input @error('editingMilestoneTitle') input-error @enderror" wire:model.defer="editingMilestoneTitle" placeholder="New Designation..." @keydown.enter="$wire.updateMilestoneTitle()">
                @error('editingMilestoneTitle') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn-glass" style="flex: 1; background: rgba(0,195,255,0.2);" wire:click="updateMilestoneTitle">
                    <span>UPDATE</span>
                </button>
                <button class="btn-glass btn-danger" style="flex: 1;" @click="closeModal('editMilestone')">ABORT</button>
            </div>
        </div>
    </div>

    {{-- MODAL: INJECT TASK --}}
    {{-- FIX 2: Removed redundant :style binding that conflicted with x-show --}}
    {{-- FIX 4: Changed wire:click="injectTask(activeMilestoneId)" to @click="$wire.injectTask(activeMilestoneId)" for safe Alpine→Livewire interop --}}
    <div x-show="modals.task" class="modal-overlay" x-transition>
        <div class="glass-panel" style="width: 450px; padding: 30px; border-radius: 8px;" @click.away="closeModal('task')">
            <div style="font-family: var(--font-mono); color: var(--matrix-cyan); margin-bottom: 20px; font-size: 0.85rem; font-weight: 600;">> DEPLOY_TASK_NODE</div>
            
            <div style="margin-bottom: 15px;">
                <input type="text" class="minimal-input @error('newTaskTitle') input-error @enderror" wire:model.defer="newTaskTitle" placeholder="Task Designation...">
                @error('newTaskTitle') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div style="margin-bottom: 15px;">
                <select class="minimal-input @error('newTaskAssignee') input-error @enderror" wire:model.defer="newTaskAssignee">
                    <option value="">[ ASSIGN OPERATIVE (OPTIONAL) ]</option>
                    @foreach($consultants as $user)
                        <option value="{{ $user->id }}">{{ strtoupper($user->name) }} ({{ strtoupper($user->role) }})</option>
                    @endforeach
                </select>
                @error('newTaskAssignee') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-family: var(--font-mono); font-size: 0.65rem; color: rgba(255,255,255,0.6); margin-bottom: 6px; display: block;">TARGET DEADLINE (OPTIONAL)</label>
                <input type="date" class="minimal-input @error('newTaskDueDate') input-error @enderror" wire:model.defer="newTaskDueDate">
                @error('newTaskDueDate') <span class="error-msg">{{ $message }}</span> @enderror
            </div>

            <div style="display: flex; gap: 10px;">
                <button class="btn-glass" style="flex: 1; background: rgba(0,195,255,0.2);" @click="$wire.injectTask(activeMilestoneId)" wire:loading.attr="disabled" wire:target="injectTask">
                    <span wire:loading.remove wire:target="injectTask">EXECUTE</span>
                    <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="injectTask"></i>
                </button>
                <button class="btn-glass btn-danger" style="flex: 1;" @click="closeModal('task')">ABORT</button>
            </div>
        </div>
    </div>

    {{-- MODAL: EDIT TASK --}}
    {{-- FIX 2: Removed redundant :style binding that conflicted with x-show --}}
    <div x-show="modals.editTask" class="modal-overlay" x-transition>
        <div class="glass-panel" style="width: 450px; padding: 30px; border-radius: 8px;" @click.away="closeModal('editTask')">
            <div style="font-family: var(--font-mono); color: var(--matrix-cyan); margin-bottom: 20px; font-size: 0.85rem; font-weight: 600;">> UPDATE_TASK_PARAMETERS</div>
            
            <div style="margin-bottom: 15px;">
                <input type="text" class="minimal-input @error('editTaskTitle') input-error @enderror" wire:model.defer="editTaskTitle" placeholder="Task Designation...">
                @error('editTaskTitle') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div style="margin-bottom: 15px;">
                <select class="minimal-input @error('editTaskAssignee') input-error @enderror" wire:model.defer="editTaskAssignee">
                    <option value="">[ ASSIGN OPERATIVE (OPTIONAL) ]</option>
                    @foreach($consultants as $user)
                        <option value="{{ $user->id }}">{{ strtoupper($user->name) }}</option>
                    @endforeach
                </select>
                @error('editTaskAssignee') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-family: var(--font-mono); font-size: 0.65rem; color: rgba(255,255,255,0.6); margin-bottom: 6px; display: block;">TARGET DEADLINE</label>
                <input type="date" class="minimal-input @error('editTaskDueDate') input-error @enderror" wire:model.defer="editTaskDueDate">
                @error('editTaskDueDate') <span class="error-msg">{{ $message }}</span> @enderror
            </div>

            <div style="display: flex; gap: 10px;">
                <button class="btn-glass" style="flex: 1; background: rgba(0,195,255,0.2);" wire:click="updateTaskDetails" wire:loading.attr="disabled" wire:target="updateTaskDetails">
                    <span wire:loading.remove wire:target="updateTaskDetails">UPDATE</span>
                    <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="updateTaskDetails"></i>
                </button>
                <button class="btn-glass btn-danger" style="flex: 1;" @click="closeModal('editTask')">ABORT</button>
            </div>
        </div>
    </div>

    {{-- MODAL: REASSIGN / TRANSFER OPERATIVE --}}
    {{-- FIX 2: Removed redundant :style binding that conflicted with x-show --}}
    <div x-show="modals.reassign" class="modal-overlay" x-transition>
        <div class="glass-panel" style="width: 400px; padding: 30px; border-radius: 8px;" @click.away="closeModal('reassign')">
            <div style="font-family: var(--font-mono); color: var(--matrix-cyan); margin-bottom: 20px; font-size: 0.85rem; font-weight: 600;">> TRANSFER_OPERATIVE</div>
            <div style="margin-bottom: 20px;">
                <select class="minimal-input @error('newAssigneeId') input-error @enderror" wire:model.defer="newAssigneeId">
                    <option value="">[ SELECT NEW OPERATIVE ]</option>
                    @foreach($consultants as $user)
                        <option value="{{ $user->id }}">{{ strtoupper($user->name) }}</option>
                    @endforeach
                </select>
                @error('newAssigneeId') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn-glass" style="flex: 1; background: rgba(0,195,255,0.2);" wire:click="reassignTask" wire:loading.attr="disabled" wire:target="reassignTask">
                    <span wire:loading.remove wire:target="reassignTask">TRANSFER</span>
                    <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="reassignTask"></i>
                </button>
                <button class="btn-glass btn-danger" style="flex: 1;" @click="closeModal('reassign')">ABORT</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('trackerMatrixEngine', () => ({
                isOnline: navigator.onLine,
                alerts: [],
                activeMilestoneId: null,
                modals: {
                    milestone: false,
                    editMilestone: false,
                    task: false,
                    editTask: false,
                    reassign: false,
                    chat: false
                },

                // FIX 1: Renamed from initEngine() to init() so Alpine auto-invokes it on boot
                init() {
                    window.addEventListener('online', () => { this.isOnline = true; });
                    window.addEventListener('offline', () => { this.isOnline = false; });
                    
                    // Listen for Livewire asking to open a specific modal (like editTask)
                    Livewire.on('open-modal', (data) => {
                        this.openModal(data[0].modal);
                    });
                },

                addAlert(alertData) {
    const id = Date.now();
    this.alerts.push({ id, ...alertData });
    setTimeout(() => {
        this.dismissAlert(id);
    }, 4000);
},

dismissAlert(id) {
    this.alerts = this.alerts.filter(a => a.id !== id);
},

                openModal(name) {
                    // Close all first, then open target to prevent stacking bugs
                    Object.keys(this.modals).forEach(k => this.modals[k] = false);
                    this.modals[name] = true;
                },

                closeModal(name) {
                    if(this.modals.hasOwnProperty(name)) {
                        this.modals[name] = false;
                    }
                },

                scrollChatToBottom() {
                    setTimeout(() => {
                        const el = document.getElementById('chat-scroller');
                        if(el) el.scrollTop = el.scrollHeight;
                    }, 150);
                }
            }));
        });
    </script>
</div>