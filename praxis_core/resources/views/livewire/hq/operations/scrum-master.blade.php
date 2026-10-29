<div style="position: relative; overflow-y: auto; min-height: 100vh;">
    <style>
        .split-layout { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
        .pane { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 30px; border-radius: 4px; max-height: 80vh; overflow-y: auto; }
        
        /* Custom Scrollbar for Panes */
        .pane::-webkit-scrollbar { width: 6px; }
        .pane::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .pane::-webkit-scrollbar-thumb { background: var(--erp-teal); border-radius: 3px; }

        .btn-action { background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; border-radius: 2px; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-action:hover { border-color: var(--erp-teal); color: var(--erp-teal); }
        
        .btn-ai { background: rgba(212, 175, 55, 0.1); color: var(--erp-gold); border: 1px solid var(--erp-gold); padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px; }
        .btn-ai:hover:not(:disabled) { background: var(--erp-gold); color: var(--erp-bg-main); box-shadow: 0 0 15px rgba(212,175,55,0.4); }
        .btn-ai:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-primary { background: var(--erp-teal); color: var(--erp-bg-main); border: 1px solid var(--erp-teal); padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px; }
        .btn-primary:hover { background: var(--erp-white); box-shadow: 0 0 15px rgba(0,229,255,0.4); }

        .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 15px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; line-height: 1.6; resize: vertical; }
        .cyber-textarea:focus { border-color: var(--erp-teal); background: rgba(0,229,255,0.02); }
        
        .cyber-input { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 8px 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .cyber-input:focus { border-color: var(--erp-teal); }

        .sprint-block { border: 1px solid var(--erp-border-dark); margin-bottom: 20px; background: rgba(0,0,0,0.2); }
        .sprint-header { background: rgba(255,255,255,0.05); padding: 10px 15px; font-family: 'Montserrat', sans-serif; font-weight: bold; color: var(--erp-teal); border-bottom: 1px solid var(--erp-border-dark); font-size: 0.9rem; display: flex; justify-content: space-between; }
        
        .task-row { padding: 15px; border-bottom: 1px dashed rgba(255,255,255,0.05); display: flex; gap: 15px; align-items: flex-start; transition: 0.3s; }
        .task-row:hover { background: rgba(0,229,255,0.02); }
        .task-row:last-child { border-bottom: none; }

        .fibonacci-box { background: rgba(0,0,0,0.5); border: 1px solid var(--erp-teal); color: var(--erp-teal); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-family: 'Roboto Mono', monospace; font-size: 1.2rem; font-weight: bold; flex-shrink: 0; text-align: center; }
        
        .form-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; display: block; }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">AI SCRUM MASTER STAGING</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-gold); margin-top: 5px;">[ PROJECT: {{ strtoupper($project->title) }} ]</p>
        </div>
        <a href="{{ route('hq.ops.deployments') }}" class="btn-action" wire:navigate>
            <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i> RETURN TO MATRIX
        </a>
    </div>

    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('error') }}</div>
    @endif

    <div class="split-layout">
        
        <div class="pane">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal); margin-bottom: 15px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">> SCOPE OF WORK CONTEXT</div>
            
            <p style="font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 15px; line-height: 1.5;">
                The AI will read the text below to engineer the backlog. You may edit this text to remove irrelevant information or add specific technical requirements before generating.
            </p>

            <textarea wire:model="sowContext" class="cyber-textarea" style="min-height: 400px;"></textarea>

            <button wire:click="generateScrumBacklog" class="btn-ai" wire:loading.attr="disabled" wire:target="generateScrumBacklog">
                <span wire:loading.remove wire:target="generateScrumBacklog">
                    <i data-lucide="cpu" style="width: 18px; height: 18px;"></i> ENGINEER BATTLESPACE
                </span>
                <span wire:loading wire:target="generateScrumBacklog">
                    <i data-lucide="loader" class="animate-spin" style="width: 18px; height: 18px; display: inline-block;"></i> PARSING LOGIC...
                </span>
            </button>
        </div>

        <div class="pane">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal); margin-bottom: 15px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">> GENERATED BACKLOG (HUMAN REVIEW)</div>

            @if(empty($generatedBacklog))
                <div style="border: 1px dashed var(--erp-border-dark); padding: 60px 20px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; background: rgba(0,0,0,0.2);">
                    <i data-lucide="layout-template" style="width: 48px; height: 48px; color: var(--erp-border-dark); margin: 0 auto 15px auto;"></i><br>
                    AWAITING AI ENGINEERING.<br>
                    <span style="font-size: 0.65rem; color: #555;">(Generated Sprints and Tasks will appear here for editing)</span>
                </div>
            @else
                
                <div style="margin-bottom: 15px; font-size: 0.8rem; color: var(--erp-gold); font-family: 'Roboto Mono', monospace;">
                    > WARNING: Review all Fibonacci Points and Acceptance Criteria. Edit fields directly before committing.
                </div>

                @foreach($generatedBacklog as $sIndex => $sprint)
                    <div class="sprint-block">
                        <div class="sprint-header">
                            <span><input type="text" wire:model="generatedBacklog.{{ $sIndex }}.sprint_title" class="cyber-input" style="width: 300px; padding: 4px 8px; border-color: transparent; background: transparent; color: var(--erp-teal); font-weight: bold; font-family: 'Montserrat', sans-serif;"></span>
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">{{ count($sprint['tasks']) }} TASKS</span>
                        </div>

                        @foreach($sprint['tasks'] as $tIndex => $task)
                            <div class="task-row">
                                <div>
                                    <label class="form-label">PTS</label>
                                    <input type="number" wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.story_points" class="fibonacci-box cyber-input" style="padding: 0; outline: none;">
                                </div>
                                
                                <div style="flex: 1;">
                                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                        <div style="flex: 1;">
                                            <label class="form-label">PHASE</label>
                                            <input type="text" wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.phase_title" class="cyber-input" style="padding: 6px 10px; font-size: 0.75rem; color: var(--erp-gold);">
                                        </div>
                                        <div style="flex: 2;">
                                            <label class="form-label">TASK TITLE</label>
                                            <input type="text" wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.title" class="cyber-input" style="padding: 6px 10px; font-size: 0.8rem; font-weight: bold;">
                                        </div>
                                    </div>

                                    <label class="form-label">ACCEPTANCE CRITERIA (HTML UL/LI)</label>
                                    <textarea wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.description" class="cyber-textarea" style="min-height: 80px; padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem;"></textarea>
                                </div>

                                <div style="padding-top: 20px;">
                                    <button wire:click="removeTask({{ $sIndex }}, {{ $tIndex }})" style="background: transparent; border: none; color: var(--erp-red); cursor: pointer; opacity: 0.5; transition: 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.5'" title="Remove Task">
                                        <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <button wire:click="commitBacklogToDatabase" class="btn-primary" wire:loading.attr="disabled" wire:target="commitBacklogToDatabase">
                    <span wire:loading.remove wire:target="commitBacklogToDatabase">
                        <i data-lucide="database" style="width: 18px; height: 18px;"></i> COMMIT BATTLESPACE TO DATABASE
                    </span>
                    <span wire:loading wire:target="commitBacklogToDatabase">
                        ENCRYPTING & SAVING MATRICES...
                    </span>
                </button>
            @endif
        </div>

    </div>

    <script> document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); </script>
</div>