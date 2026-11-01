{{--
    A search-as-you-type picker over a Livewire list. The person types part of a name, the list narrows (the
    screen filters it through $model), arrow keys move, Enter or a click chooses. One choice shows as a card with
    "Change"; several (multiple) show as chips with a remove button, and the input stays to add more.

    $options: iterable of ['id', 'label', 'meta' => null, 'user' => null (for the avatar)]
    $selected: one option (single) or an iterable of them (multiple), or null / empty
    $choose / $remove: Livewire methods taking the id ($remove without an id clears a single choice)
    $footer: slot for a last row (e.g. "Add … as a new organisation"); give it role="option" to reach it with the keys
--}}
@props([
    'id', 'label', 'model', 'options' => [], 'selected' => null, 'multiple' => false, 'choose', 'remove' => null,
    'placeholder' => '', 'help' => null, 'error' => null, 'empty' => null, 'term' => '', 'change' => null, 'removeLabel' => null,
    'avatars' => true, 'autofocus' => false,
])
@once
    <style>
        .pk { position: relative; }
        .pk-wrap { position: relative; }
        .pk-wrap .input { padding-right: 36px; }
        .pk-icon { position: absolute; right: 10px; top: 50%; width: 16px; height: 16px; margin-top: -8px; color: var(--color-muted); pointer-events: none; }
        .pk-list { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 60; max-height: 280px; overflow-y: auto; margin: 0; padding: 4px; list-style: none; background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); box-shadow: var(--shadow-float); }
        .pk-opt { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 10px; border: 0; border-radius: var(--radius-control); background: transparent; color: var(--color-ink); font: inherit; font-size: 0.875rem; line-height: 1.25rem; text-align: left; cursor: pointer; }
        .pk-opt.is-active, .pk-opt:hover { background: var(--color-mist); }
        .pk-opt.is-active { box-shadow: inset 0 0 0 1px var(--color-deep-teal); }
        .pk-opt-text { display: flex; flex-direction: column; min-width: 0; }
        .pk-opt-text > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pk-meta { font-size: 0.8125rem; color: var(--color-muted); }
        .pk-add { color: var(--color-deep-teal); font-weight: 600; }
        .pk-add svg { width: 16px; height: 16px; flex-shrink: 0; }
        .pk-none { padding: 10px; font-size: 0.8125rem; color: var(--color-muted); }
        .pk-card { display: flex; align-items: center; gap: 10px; min-height: 42px; padding: 6px 6px 6px 10px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-mist); font-size: 0.875rem; color: var(--color-ink); }
        .pk-card .pk-opt-text { flex: 1; }
        .pk-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 6px; padding: 0; list-style: none; }
        .pk-chips:empty { display: none; }
        .pk-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 4px 3px 4px; border: 1px solid var(--color-line); border-radius: 999px; background: var(--color-paper); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-ink); }
        .pk-chip .avatar { width: 22px; height: 22px; font-size: 9px; }
        .pk-chip-x { display: grid; place-items: center; width: 22px; height: 22px; border: 0; border-radius: 50%; background: transparent; color: var(--color-muted); cursor: pointer; }
        .pk-chip-x:hover { background: var(--color-mist); color: var(--color-danger); }
        .pk-chip-x svg { width: 14px; height: 14px; }
        .pk .avatar { width: 28px; height: 28px; font-size: 11px; }
        @media (prefers-reduced-motion: no-preference) { .pk-list { animation: pk-in var(--motion-fade) ease-out; } }
        @keyframes pk-in { from { opacity: 0; transform: translateY(-2px); } to { opacity: 1; transform: none; } }
    </style>
@endonce
@php
    $list = $multiple ? collect($selected ?? []) : collect();
    $one = $multiple ? null : $selected;
    $describedBy = $error ? "{$id}-error" : ($help ? "{$id}-help" : null);
    $hasOptions = count($options) > 0;
    $itemUser = fn ($o) => $o['user'] ?? ['id' => $o['id'], 'name' => $o['label']];
@endphp
<div {{ $attributes->class(['field', 'pk']) }} role="group" aria-labelledby="{{ $id }}-label"
     x-data="{
        open: false, active: -1,
        items() { return this.$refs.list ? Array.from(this.$refs.list.querySelectorAll('[role=option]')) : [] },
        move(d) { const n = this.items().length; if (!n) { this.open = true; return } this.open = true; this.active = (this.active + d + n) % n; this.items()[this.active].scrollIntoView({ block: 'nearest' }) },
        pick() { const all = this.items(); const el = all[this.active] ?? (all.length === 1 ? all[0] : null); if (el) el.click() },
        close() { this.open = false; this.active = -1 },
        escape(e) { if (this.open) { e.stopPropagation(); this.close() } },
     }"
     x-on:click.outside="close()">
    <span class="label" id="{{ $id }}-label">{{ $label }}</span>

    @if ($multiple)
        <ul class="pk-chips" aria-label="{{ $label }}">
            @foreach ($list as $o)
                <li class="pk-chip" wire:key="{{ $id }}-chip-{{ $o['id'] }}">
                    @if ($avatars) <x-hq.avatar :user="$itemUser($o)" /> @endif
                    <span>{{ $o['label'] }}</span>
                    @if ($remove)
                        <button type="button" class="pk-chip-x" wire:click="{{ $remove }}('{{ $o['id'] }}')" aria-label="{{ str_replace(':name', $o['label'], $removeLabel ?? 'Remove :name') }}"><i data-lucide="x"></i></button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($one)
        <div class="pk-card" wire:key="{{ $id }}-chosen-{{ $one['id'] }}">
            @if ($avatars) <x-hq.avatar :user="$itemUser($one)" /> @endif
            <span class="pk-opt-text"><span>{{ $one['label'] }}</span>@if (! empty($one['meta']))<span class="pk-meta">{{ $one['meta'] }}</span>@endif</span>
            @if ($remove)
                <button type="button" class="btn btn-ghost btn-sm" wire:click="{{ $remove }}" x-on:click="$nextTick(() => $refs.input?.focus())">{{ $change ?? 'Change' }}</button>
            @endif
        </div>
    @else
        <div class="pk-wrap">
            <input id="{{ $id }}" type="text" class="input" role="combobox" autocomplete="off" spellcheck="false" x-ref="input"
                   wire:model.live.debounce.150ms="{{ $model }}" placeholder="{{ $placeholder }}" @if ($autofocus) autofocus @endif
                   aria-autocomplete="list" aria-controls="{{ $id }}-list" aria-labelledby="{{ $id }}-label"
                   :aria-expanded="open ? 'true' : 'false'" :aria-activedescendant="active >= 0 ? '{{ $id }}-opt-' + active : null"
                   @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif
                   x-on:focus="open = true" x-on:click="open = true" x-on:input="open = true; active = -1"
                   x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter.prevent="pick()"
                   x-on:keydown.escape="escape($event)" x-on:keydown.tab="close()">
            <i data-lucide="search" class="pk-icon"></i>
            <ul class="pk-list" id="{{ $id }}-list" role="listbox" aria-labelledby="{{ $id }}-label" x-ref="list" x-show="open" x-cloak
                @if ($multiple) aria-multiselectable="true" @endif>
                @foreach ($options as $i => $o)
                    <li role="presentation" wire:key="{{ $id }}-opt-{{ $o['id'] }}">
                        <button type="button" class="pk-opt" role="option" id="{{ $id }}-opt-{{ $i }}" tabindex="-1"
                                :class="{ 'is-active': active === {{ $i }} }" :aria-selected="active === {{ $i }} ? 'true' : 'false'"
                                x-on:mouseenter="active = {{ $i }}" wire:click="{{ $choose }}('{{ $o['id'] }}')"
                                x-on:click="{{ $multiple ? 'active = -1; $refs.input.focus()' : 'close()' }}">
                            @if ($avatars) <x-hq.avatar :user="$itemUser($o)" /> @endif
                            <span class="pk-opt-text"><span>{{ $o['label'] }}</span>@if (! empty($o['meta']))<span class="pk-meta">{{ $o['meta'] }}</span>@endif</span>
                        </button>
                    </li>
                @endforeach
                @if (! $hasOptions && $empty)
                    <li class="pk-none" role="presentation">{{ str_replace(':term', trim($term), $empty) }}</li>
                @endif
                @if (isset($footer) && trim($footer) !== '')
                    <li role="presentation" wire:key="{{ $id }}-footer">{{ $footer }}</li>
                @endif
            </ul>
        </div>
    @endif

    @if ($error)
        <span class="field-error" id="{{ $id }}-error">{{ $error }}</span>
    @elseif ($help)
        <span class="help" id="{{ $id }}-help">{{ $help }}</span>
    @endif
</div>
