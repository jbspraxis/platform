<div x-data="{ open: false }" style="position: relative; display: flex; align-items: center; z-index: 9000;">
    
    <div @click="open = !open" style="cursor: pointer; position: relative; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--erp-border-dark); transition: 0.3s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
        
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $unreadCount > 0 ? '#00e5ff' : '#8A9BB2' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>

        @if($unreadCount > 0)
            <div style="position: absolute; top: -5px; right: -5px; background-color: var(--erp-red); color: white; font-family: 'Roboto Mono', monospace; font-size: 10px; font-weight: bold; min-width: 18px; height: 18px; padding: 0 4px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 2px solid var(--erp-bg-base); box-shadow: 0 0 10px rgba(255, 51, 102, 0.4);">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </div>
        @endif
    </div>

    <div x-show="open" x-transition @click.away="open = false" style="display: none; position: absolute; right: 0; top: 50px; width: 340px; background: rgba(10, 17, 40, 0.98); backdrop-filter: blur(20px); border: 1px solid var(--erp-border-dark); border-top: 3px solid var(--erp-teal); border-radius: 6px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); overflow: hidden;">
        <div style="padding: 15px 20px; border-bottom: 1px solid var(--erp-border-dark); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02);">
            <span>> ACTIVE DISPATCHES ({{ $unreadCount }})</span>
            <span style="font-size: 0.6rem; color: var(--erp-muted);">UTC: {{ now()->format('H:i') }}</span>
        </div>
        
        <div style="max-height: 400px; overflow-y: auto;">
            @forelse($notifications as $note)
                <div wire:click="markAndRedirect('{{ $note->id }}', '{{ $note->link_url ?? '#' }}')" 
                     style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02); cursor: pointer; transition: 0.3s; background: {{ $note->is_read ? 'transparent' : 'rgba(0, 229, 255, 0.03)' }};" 
                     onmouseover="this.style.background='rgba(0, 229, 255, 0.08)'" 
                     onmouseout="this.style.background='{{ $note->is_read ? 'transparent' : 'rgba(0, 229, 255, 0.03)' }}'">
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <div style="font-size: 0.65rem; color: {{ $note->type === 'Financial' ? '#00FF66' : ($note->type === 'System' ? '#FF3366' : '#00e5ff') }}; font-family: 'Roboto Mono', monospace; font-weight: bold;">
                            [{{ strtoupper($note->type ?? 'SYS') }}]
                        </div>
                        @if(!$note->is_read)
                            <div style="width: 6px; height: 6px; background: var(--erp-teal); border-radius: 50%;"></div>
                        @endif
                    </div>
                    <div style="font-size: 0.85rem; color: #FFF; font-weight: 600; margin-bottom: 3px;">{{ $note->title }}</div>
                    <div style="font-size: 0.75rem; color: var(--erp-muted); line-height: 1.4;">{{ $note->message }}</div>
                    <div style="font-size: 0.6rem; color: #5a6b82; margin-top: 8px; font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</div>
                </div>
            @empty
                <div style="padding: 40px 20px; text-align: center; color: #5a6b82; font-size: 0.75rem; font-family: 'Roboto Mono', monospace;">
                    > NO SYSTEM ALERTS IN BUFFER.
                </div>
            @endforelse
        </div>
    </div>
</div>