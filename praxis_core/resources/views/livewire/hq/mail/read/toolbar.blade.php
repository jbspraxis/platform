@php
    $flagged = collect($messages)->contains(fn ($m) => $m['model']->is_flagged);
    $iconBtn = fn (string $icon) => view('livewire.hq.mail.read.icon', ['name' => $icon])->render();
@endphp
<div class="mr-toolbar" role="toolbar" aria-label="Conversation actions" wire:key="toolbar-{{ $openThread->id }}">
    <button type="button" class="mr-icon-btn mr-back" wire:click="closeThread" aria-label="Back to the list" title="Back (u)">{!! $iconBtn('arrow-left') !!}</button>
    @if ($canOrganise)
        @if (! in_array($role, ['archive', 'trash', 'spam'], true))
            <button type="button" class="mr-icon-btn" wire:click="act('archive')" title="Archive (e)" aria-label="Archive">{!! $iconBtn('archive') !!}</button>
        @endif
        @if ($role === 'spam')
            <button type="button" class="btn btn-ghost btn-sm" wire:click="act('inbox')">{!! $iconBtn('inbox') !!} Not spam</button>
        @else
            <button type="button" class="mr-icon-btn" wire:click="act('spam')" title="Spam (!)" aria-label="Move to Spam">{!! $iconBtn('shield-alert') !!}</button>
        @endif
        @if (in_array($role, ['trash', 'archive'], true))
            <button type="button" class="mr-icon-btn" wire:click="act('inbox')" title="Move to Inbox" aria-label="Move to Inbox">{!! $iconBtn('inbox') !!}</button>
        @endif
        @if (in_array($role, ['trash', 'spam'], true))
            <button type="button" class="btn btn-ghost btn-sm" wire:click="askConfirm('delete')" title="Delete for good (#)">{!! $iconBtn('trash') !!} Delete for good</button>
        @else
            <button type="button" class="mr-icon-btn" wire:click="act('trash')" title="Trash (#)" aria-label="Move to Trash">{!! $iconBtn('trash') !!}</button>
        @endif
        <label class="sr-only" for="mr-move">Move to folder</label>
        <select id="mr-move" class="select" x-on:change="if ($event.target.value) { $wire.act('move:' + $event.target.value); $event.target.value = '' }">
            <option value="">Move to…</option>
            @foreach ($folders as $f)
                @if (! $currentFolder || $f->id !== $currentFolder->id)
                    <option value="{{ $f->id }}">{{ $f->label() }}</option>
                @endif
            @endforeach
        </select>
        <span class="mr-sep" aria-hidden="true"></span>
    @endif
    <button type="button" class="mr-icon-btn" wire:click="act('unread')" title="Mark as unread (Shift+U)" aria-label="Mark as unread">{!! $iconBtn('mail') !!}</button>
    @if ($canOrganise)
        <button type="button" class="mr-icon-btn {{ $flagged ? 'is-on' : '' }}" wire:click="act('{{ $flagged ? 'unflag' : 'flag' }}')" title="{{ $flagged ? 'Remove the flag (s)' : 'Flag (s)' }}" aria-label="{{ $flagged ? 'Remove the flag' : 'Flag' }}" aria-pressed="{{ $flagged ? 'true' : 'false' }}">{!! $iconBtn('flag') !!}</button>
    @endif
    <span class="mr-grow"></span>
    @if ($canSend && $latest)
        @if ($latest['is_draft'])
            <button type="button" class="btn btn-secondary btn-sm" @click="compose('draft', {{ $latest['id'] }})">{!! $iconBtn('pen') !!} Edit draft</button>
        @else
            <button type="button" class="mr-icon-btn" @click="compose('reply', {{ $latest['id'] }})" title="Reply (r)" aria-label="Reply">{!! $iconBtn('reply') !!}</button>
            <button type="button" class="mr-icon-btn" @click="compose('reply_all', {{ $latest['id'] }})" title="Reply all (a)" aria-label="Reply all">{!! $iconBtn('reply-all') !!}</button>
            <button type="button" class="mr-icon-btn" @click="compose('forward', {{ $latest['id'] }})" title="Forward (f)" aria-label="Forward">{!! $iconBtn('forward') !!}</button>
        @endif
    @endif
</div>
