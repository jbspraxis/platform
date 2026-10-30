{{-- Incoming and outgoing server settings for one form. $model: the Livewire array property (connectServer, sharedServer, passwordServer); $id: a unique prefix --}}
@php
    $security = ['ssl' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => 'None (not recommended)'];
@endphp
<div class="mb-grid mb-server">
    <div class="field">
        <label class="label" for="{{ $id }}-imap-host">Incoming server (IMAP)</label>
        <input id="{{ $id }}-imap-host" type="text" class="input" wire:model="{{ $model }}.imap_host" autocomplete="off" spellcheck="false"
               @error($model . '.imap_host') aria-invalid="true" aria-describedby="{{ $id }}-imap-host-error" @enderror>
        @error($model . '.imap_host') <p class="field-error" id="{{ $id }}-imap-host-error">{{ $message }}</p> @enderror
    </div>
    <div class="field">
        <label class="label" for="{{ $id }}-imap-port">Port</label>
        <input id="{{ $id }}-imap-port" type="number" inputmode="numeric" class="input" wire:model="{{ $model }}.imap_port">
    </div>
    <div class="field">
        <label class="label" for="{{ $id }}-imap-enc">Security</label>
        <select id="{{ $id }}-imap-enc" class="select" wire:model="{{ $model }}.imap_encryption">
            @foreach ($security as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
    </div>
    <div class="field">
        <label class="label" for="{{ $id }}-smtp-host">Outgoing server (SMTP)</label>
        <input id="{{ $id }}-smtp-host" type="text" class="input" wire:model="{{ $model }}.smtp_host" autocomplete="off" spellcheck="false"
               @error($model . '.smtp_host') aria-invalid="true" aria-describedby="{{ $id }}-smtp-host-error" @enderror>
        @error($model . '.smtp_host') <p class="field-error" id="{{ $id }}-smtp-host-error">{{ $message }}</p> @enderror
    </div>
    <div class="field">
        <label class="label" for="{{ $id }}-smtp-port">Port</label>
        <input id="{{ $id }}-smtp-port" type="number" inputmode="numeric" class="input" wire:model="{{ $model }}.smtp_port">
    </div>
    <div class="field">
        <label class="label" for="{{ $id }}-smtp-enc">Security</label>
        <select id="{{ $id }}-smtp-enc" class="select" wire:model="{{ $model }}.smtp_encryption">
            @foreach ($security as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
    </div>
</div>
<p class="help mt-1">For jbspraxis.com addresses: mail.jbspraxis.com, IMAP 993 and SMTP 465, both SSL/TLS (cPanel, Email accounts, Connect devices).</p>
