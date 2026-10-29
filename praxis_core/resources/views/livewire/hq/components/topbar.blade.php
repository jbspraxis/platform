<div class="top-controls">
    <!-- AlpineJS Live Clock & Timer -->
    <div class="clock-pill" x-data="systemClock()">
        <span class="mono-font" x-text="currentTime">00:00:00</span>
        <button class="btn-clock" :class="{'clocked-in': isClockedIn}" @click="toggleClock">
            <i data-lucide="fingerprint" class="w-4 h-4"></i> 
            <span x-text="isClockedIn ? 'CLOCKED IN' : 'CLOCK IN'">CLOCK IN</span>
        </button>
    </div>

    <!-- Notification Dropdown -->
    <div x-data="{ open: false }" style="position: relative;">
        <!-- The Bell -->
        <div class="notification-bell" @click="open = !open" style="cursor: pointer; position: relative;">
            <i data-lucide="bell" class="w-5 h-5" style="color: {{ $unreadCount > 0 ? 'var(--erp-teal)' : 'var(--erp-muted)' }};"></i>
            @if($unreadCount > 0)
                <div class="alert-dot" style="position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background-color: var(--erp-red); border-radius: 50%; box-shadow: 0 0 10px var(--erp-red); animation: blink 1.5s infinite;"></div>
            @endif
        </div>

        <!-- The Dropdown Panel -->
        <div x-show="open" @click.away="open = false" style="display: none; position: absolute; right: -50px; top: 40px; width: 340px; background: rgba(10, 17, 40, 0.95); backdrop-filter: blur(20px); border: 1px solid var(--erp-border); border-top: 3px solid var(--erp-teal); border-radius: 6px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 1000;">
            <div style="padding: 15px 20px; border-bottom: 1px solid var(--erp-border-dark); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); display: flex; justify-content: space-between;">
                <span>> ALERTS ({{ $unreadCount }})</span>
            </div>
            
            <div style="max-height: 350px; overflow-y: auto;">
                @forelse($notifications as $note)
                    <div wire:click="markAsRead('{{ $note->id }}')" style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02); cursor: pointer; transition: 0.3s; background: {{ $note->is_read ? 'transparent' : 'rgba(0, 229, 255, 0.05)' }};" onmouseover="this.style.background='rgba(0, 229, 255, 0.08)'" onmouseout="this.style.background='{{ $note->is_read ? 'transparent' : 'rgba(0, 229, 255, 0.05)' }}'">
                        <div style="font-size: 0.65rem; color: {{ $note->type === 'Financial' ? '#00FF66' : ($note->type === 'System' ? 'var(--erp-red)' : 'var(--erp-teal)') }}; font-family: 'Roboto Mono', monospace; margin-bottom: 5px; font-weight: bold;">
                            [{{ strtoupper($note->type) }}]
                        </div>
                        <div style="font-size: 0.85rem; color: var(--erp-white); font-weight: 600; margin-bottom: 5px;">{{ $note->title }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary); line-height: 1.4;">{{ $note->message }}</div>
                        <div style="font-size: 0.65rem; color: var(--erp-muted); margin-top: 8px; font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</div>
                    </div>
                @empty
                    <div style="padding: 30px; text-align: center; color: var(--erp-muted); font-size: 0.75rem; font-family: 'Roboto Mono', monospace;">
                        > NO SYSTEM ALERTS.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Admin Profile Dropdown (Hover to trigger logout) -->
    <a href="{{ route('hq.logout') }}" class="admin-profile" title="Click to Terminate Session">
        <div class="admin-details">
            <span class="admin-name">{{ auth()->user()->name ?? 'System Admin' }}</span>
            <span class="admin-role">{{ auth()->user()?->role_names ?: 'Staff' }}</span>
        </div>
        <div class="admin-avatar">
            {{ strtoupper(substr(auth()->user()->first_name ?? 'S', 0, 1) . substr(auth()->user()->last_name ?? 'A', 0, 1)) }}
        </div>
    </a>
</div>