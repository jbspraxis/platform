{{-- One connected mailbox on the Mailboxes page. $account (with access_level), $state from Mailboxes::state(), $highlight --}}
@php
    $level = $account->access_level;
    $canManage = in_array($level, ['manage', 'owner'], true);
    $levelLabel = ['owner' => 'Your mailbox', 'manage' => 'Manager', 'send' => 'Read and send', 'read' => 'Read'][$level] ?? '';
@endphp
<div class="mb-row {{ $highlight ? 'is-highlight' : '' }}" wire:key="mailbox-{{ $account->id }}" id="mailbox-{{ $account->id }}">
    <div class="mb-row-main">
        <div class="mb-row-icon" aria-hidden="true"><i data-lucide="{{ $account->isShared() ? 'users' : 'mail' }}"></i></div>
        <div class="mb-row-text">
            <div class="mb-row-title">
                <strong>{{ $account->display_name }}</strong>
                <span class="text-muted">{{ $account->email }}</span>
            </div>
            <div class="mb-row-meta">
                <span class="pill pill-{{ $state['tone'] }}">{{ $state['label'] }}</span>
                @if ($account->isShared()) <span class="pill pill-neutral">{{ $levelLabel }}</span> @endif
                <span class="help">{{ $state['detail'] }}</span>
            </div>
        </div>
    </div>
    <div class="mb-row-actions">
        @if ($account->status === 'needs_reconnect' && $canManage)
            <button type="button" class="btn btn-primary btn-sm" wire:click="openModal('password', '{{ $account->id }}')">
                <i data-lucide="key-round"></i> Enter password
            </button>
        @else
            <button type="button" class="btn btn-secondary btn-sm" wire:click="refreshNow('{{ $account->id }}')" wire:loading.attr="disabled" wire:target="refreshNow('{{ $account->id }}')">
                <i data-lucide="refresh-cw"></i>
                <span wire:loading.remove wire:target="refreshNow('{{ $account->id }}')">Refresh now</span>
                <span wire:loading wire:target="refreshNow('{{ $account->id }}')">Updating…</span>
            </button>
        @endif
        @if ($canManage)
            @if ($account->isShared())
                <button type="button" class="btn btn-secondary btn-sm" wire:click="openModal('people', '{{ $account->id }}')"><i data-lucide="user-plus"></i> People</button>
                <button type="button" class="btn btn-ghost btn-sm" wire:click="openModal('rename', '{{ $account->id }}')">Rename</button>
            @endif
            @if ($account->status !== 'needs_reconnect')
                <button type="button" class="btn btn-ghost btn-sm" wire:click="openModal('password', '{{ $account->id }}')">Change password</button>
            @endif
            <button type="button" class="btn btn-ghost btn-sm mb-danger-text" wire:click="openModal('remove', '{{ $account->id }}')">
                {{ $account->isShared() ? 'Remove from HQ' : 'Disconnect' }}
            </button>
        @endif
    </div>
</div>
