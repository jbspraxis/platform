<div x-data="{ open: false }" style="position: relative; display: flex; align-items: center;">
    
    <div class="notification-bell" @click="open = !open" style="cursor: pointer; position: relative; display: flex; align-items: center; justify-content: center; width: 35px; height: 35px; min-width: 35px; min-height: 35px; border-radius: 4px; transition: 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">
        
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $unreadCount > 0 ? '#00e5ff' : '#8A9BB2' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>

        @if($unreadCount > 0)
            <div class="alert-dot" style="position: absolute; top: 4px; right: 6px; width: 8px; height: 8px; background-color: #FF3366; border-radius: 50%; box-shadow: 0 0 10px #FF3366; animation: blink 1.5s infinite;"></div>
        @endif
    </div>

    <div x-show="open" x-transition @click.away="open = false" style="display: none; position: absolute; right: 0; top: 50px; width: 340px; background: rgba(10, 17, 40, 0.95); backdrop-filter: blur(20px); border: 1px solid var(--erp-border-dark); border-top: 3px solid var(--erp-teal); border-radius: 6px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999;">
        <div style="padding: 15px 20px; border-bottom: 1px solid var(--erp-border-dark); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); display: flex; justify-content: space-between;">
            <span>> ALERTS ({{ $unreadCount }})</span>
        </div>
        
        <div style="max-height: 350px; overflow-y: auto;">
            @forelse($notifications as $note)
                <div wire:click="markAndRedirect('{{ $note->id }}', '{{ $note->link_url ?? '#' }}')" 
                     style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02); cursor: pointer; transition: 0.3s; background: {{ $note->is_read ? 'transparent' : 'rgba(0, 229, 255, 0.05)' }};" 
                     onmouseover="this.style.background='rgba(0, 229, 255, 0.08)'" 
                     onmouseout="this.style.background='{{ $note->is_read ? 'transparent' : 'rgba(0, 229, 255, 0.05)' }}'">
                    
                    <div style="font-size: 0.65rem; color: {{ ($note->type ?? 'System') === 'Financial' ? '#00FF66' : (($note->type ?? 'System') === 'System' ? '#FF3366' : '#00e5ff') }}; font-family: 'Roboto Mono', monospace; margin-bottom: 5px; font-weight: bold;">
                        [{{ strtoupper($note->type ?? 'SYS') }}]
                    </div>
                    <div style="font-size: 0.85rem; color: #FFF; font-weight: 600; margin-bottom: 5px;">{{ $note->title }}</div>
                    <div style="font-size: 0.75rem; color: #8A9BB2; line-height: 1.4;">{{ $note->message }}</div>
                    <div style="font-size: 0.65rem; color: #5a6b82; margin-top: 8px; font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</div>
                </div>
            @empty
                <div style="padding: 30px; text-align: center; color: #5a6b82; font-size: 0.75rem; font-family: 'Roboto Mono', monospace;">
                    > NO SYSTEM ALERTS.
                </div>
            @endforelse
        </div>
    </div>
</div>