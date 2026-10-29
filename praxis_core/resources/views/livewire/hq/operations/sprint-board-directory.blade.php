<div style="min-height: 85vh; padding-bottom: 50px;">
    <style>
        .directory-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 20px; }
        
        .directory-card { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px; display: flex; flex-direction: column; transition: 0.3s; text-decoration: none; cursor: pointer; position: relative; overflow: hidden; }
        .directory-card:hover { border-color: var(--erp-teal); box-shadow: 0 10px 30px rgba(0,0,0,0.5); transform: translateY(-2px); background: rgba(0, 229, 255, 0.02); }
        
        .client-name { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-gold); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; }
        .project-title { font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: var(--erp-white); font-weight: bold; margin-bottom: 15px; line-height: 1.3; }
        
        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; border: 1px solid transparent; width: fit-content; }
        .pill-active { background: rgba(0, 255, 102, 0.1); color: #00FF66; border-color: #00FF66; }
        .pill-locked { background: rgba(255, 51, 102, 0.1); color: var(--erp-red); border-color: var(--erp-red); }

        .enter-action { margin-top: auto; padding-top: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); display: flex; align-items: center; justify-content: space-between; font-weight: bold; border-top: 1px dashed rgba(255,255,255,0.05); }
        .directory-card:hover .enter-action { color: var(--erp-white); }
        .directory-card:hover .enter-icon { transform: translateX(5px); color: var(--erp-teal); }
        .enter-icon { transition: transform 0.3s; }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
        <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">SPRINT BOARDS DIRECTORY</h1>
        <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ SELECT BATTLESPACE TO ENTER ]</p>
    </div>

    <div class="directory-grid">
        @forelse($projects as $p)
            <a href="{{ route('hq.ops.board', $p->id) }}" class="directory-card" wire:navigate>
                <div class="client-name">> {{ $p->client_name }}</div>
                <div class="project-title">{{ $p->title }}</div>
                
                @if(strtolower($p->status) === 'locked')
                    <span class="status-pill pill-locked"><i data-lucide="lock" style="width: 10px; height: 10px;"></i> GUILLOTINE LOCKED</span>
                @else
                    <span class="status-pill pill-active"><i data-lucide="activity" style="width: 10px; height: 10px;"></i> LIVE EXECUTION</span>
                @endif

                <div class="enter-action">
                    <span>INITIATE LINK</span>
                    <i data-lucide="arrow-right" class="enter-icon" style="width: 16px; height: 16px;"></i>
                </div>
            </a>
        @empty
            <div style="grid-column: 1 / -1; background: rgba(0,0,0,0.4); border: 1px dashed var(--erp-border-dark); padding: 60px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">
                NO ACTIVE BATTLESPACES ASSIGNED TO YOUR NODE.
            </div>
        @endforelse
    </div>

    <script> document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); </script>
</div>