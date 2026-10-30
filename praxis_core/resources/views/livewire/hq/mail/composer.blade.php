@php
    // Format buttons: Lucide paths drawn inline (the built icon set has no text-format icons; no npm build here)
    $formats = [
        ['bold', 'Bold', 'Ctrl+B', '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/>'],
        ['italic', 'Italic', 'Ctrl+I', '<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>'],
        ['underline', 'Underline', 'Ctrl+U', '<path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" x2="20" y1="20" y2="20"/>'],
        ['insertUnorderedList', 'Bulleted list', null, '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>'],
        ['insertOrderedList', 'Numbered list', null, '<path d="M10 12h11"/><path d="M10 18h11"/><path d="M10 6h11"/><path d="M4 10h2"/><path d="M4 6h1v4"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>'],
        ['formatBlock:blockquote', 'Quote', null, '<path d="M17 6H3"/><path d="M21 12H8"/><path d="M21 18H8"/><path d="M3 12v6"/>'],
        ['removeFormat', 'Clear formatting', null, '<path d="M4 7V4h16v3"/><path d="M5 20h6"/><path d="M13 4 8 20"/><path d="m15 15 5 5"/><path d="m20 15-5 5"/>'],
    ];
    $fmtTime = fn (?string $iso) => $iso ? \Carbon\Carbon::parse($iso)->timezone($tz)->format('H:i') : null;
@endphp
<div class="mc">
    @assets
        <script src="{{ route('hq.mail.compose.script', ['v' => filemtime(resource_path('mail/compose.js'))]) }}"></script>
    @endassets
    <style>
        /* The composer (PR 3): a dialog on desktop, full screen on phones. Brand tokens only. */
        .mc-backdrop { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 24px; background: rgba(12, 20, 43, 0.5); }
        .mc-panel { width: min(780px, 100%); height: min(760px, calc(100vh - 48px)); display: flex; flex-direction: column; background: var(--color-paper); border-radius: var(--radius-dialog); box-shadow: var(--shadow-float); overflow: hidden; }
        .mc-head { display: flex; align-items: center; gap: 12px; padding: 12px 12px 12px 20px; border-bottom: 1px solid var(--color-line); }
        .mc-head h2 { margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mc-saved { font-size: 0.8125rem; color: var(--color-muted); white-space: nowrap; }
        .mc-icon { display: inline-grid; place-items: center; width: 36px; height: 36px; border: 0; border-radius: var(--radius-control); background: transparent; color: var(--color-slate); cursor: pointer; flex-shrink: 0; }
        .mc-icon:hover { background: var(--color-mist); color: var(--color-ink); }
        .mc-icon:focus-visible, .mc-chip button:focus-visible, .mc-link:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 1px; }
        .mc-icon svg { width: 18px; height: 18px; }
        .mc-icon.is-on { background: var(--color-teal-tint); color: var(--color-petrol); }
        .mc-fields { padding: 0 20px; border-bottom: 1px solid var(--color-line); }
        .mc-row { display: flex; align-items: flex-start; gap: 8px; border-bottom: 1px solid var(--color-line); min-height: 44px; }
        .mc-row:last-child { border-bottom: 0; }
        .mc-row > label, .mc-row > .mc-label { flex: 0 0 56px; padding: 11px 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-muted); }
        .mc-row .mc-from-text { padding: 11px 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); overflow-wrap: anywhere; }
        .mc-plain { flex: 1; min-width: 0; border: 0; padding: 11px 0; font: inherit; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); background: transparent; }
        .mc-plain:focus { outline: none; }
        .mc-row:focus-within { border-bottom-color: var(--color-deep-teal); }
        .mc-select { flex: 1; min-width: 0; border: 0; padding: 10px 0; font: inherit; font-size: 0.875rem; color: var(--color-ink); background: transparent; }
        .mc-chips { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 6px 0; position: relative; }
        .mc-chip { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 2px 4px 2px 10px; border-radius: 999px; background: var(--color-mist); color: var(--color-ink); font-size: 0.8125rem; line-height: 1.5rem; }
        .mc-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mc-chip button { display: inline-grid; place-items: center; width: 22px; height: 22px; border: 0; border-radius: 50%; background: transparent; color: var(--color-slate); cursor: pointer; }
        .mc-chip button:hover { background: var(--color-line); }
        .mc-chip svg { width: 14px; height: 14px; }
        .mc-chips input { flex: 1 0 140px; min-width: 120px; border: 0; padding: 5px 0; font: inherit; font-size: 0.875rem; color: var(--color-ink); background: transparent; }
        .mc-chips input:focus { outline: none; }
        .mc-suggest { position: absolute; top: 100%; left: 0; right: 0; z-index: 5; margin: 0; padding: 4px; list-style: none; background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-control); box-shadow: var(--shadow-float); max-height: 260px; overflow-y: auto; }
        .mc-suggest li { display: flex; flex-direction: column; padding: 8px 10px; border-radius: var(--radius-control); cursor: pointer; font-size: 0.875rem; line-height: 1.25rem; }
        .mc-suggest li small { color: var(--color-muted); font-size: 0.8125rem; }
        .mc-suggest li.is-active, .mc-suggest li:hover { background: var(--color-teal-tint); }
        .mc-field-error { padding: 0 0 8px 64px; font-size: 0.8125rem; color: var(--color-danger); }
        .mc-link { border: 0; background: none; padding: 11px 0 11px 4px; color: var(--color-fg-link); font: inherit; font-size: 0.875rem; font-weight: 500; cursor: pointer; white-space: nowrap; }
        .mc-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 2px; padding: 6px 12px; border-bottom: 1px solid var(--color-line); }
        .mc-sep { width: 1px; height: 20px; background: var(--color-line); margin: 0 6px; }
        .mc-linkbar { display: flex; gap: 8px; align-items: center; padding: 8px 20px; border-bottom: 1px solid var(--color-line); background: var(--color-mist); }
        .mc-linkbar .input { flex: 1; }
        .mc-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 20px 20px; }
        .mc-editor { min-height: 180px; outline: none; font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-ink); overflow-wrap: anywhere; }
        .mc-editor:empty::before { content: attr(data-placeholder); color: var(--color-muted); pointer-events: none; }
        .mc-editor p { margin: 0 0 12px; }
        .mc-editor blockquote { margin: 0 0 12px 4px; padding-left: 12px; border-left: 2px solid var(--color-line); color: var(--color-slate); }
        .mc-editor ul, .mc-editor ol { margin: 0 0 12px; padding-left: 24px; }
        .mc-editor a { color: var(--color-fg-link); }
        .mc-extra { margin-top: 20px; display: flex; flex-direction: column; gap: 12px; }
        .mc-box { border: 1px solid var(--color-line); border-radius: var(--radius-control); }
        .mc-box-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; font-size: 0.8125rem; color: var(--color-muted); }
        .mc-box-head .select { width: auto; padding: 4px 8px; font-size: 0.8125rem; }
        .mc-box-body { padding: 0 12px 12px; overflow-x: auto; }
        .mc-quote { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); max-height: 320px; overflow-y: auto; overflow-wrap: anywhere; }
        .mc-files { display: flex; flex-wrap: wrap; gap: 8px; }
        .mc-file { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; padding: 6px 6px 6px 10px; border: 1px solid var(--color-line); border-radius: var(--radius-control); font-size: 0.8125rem; color: var(--color-ink); }
        .mc-file svg { width: 16px; height: 16px; color: var(--color-slate); flex-shrink: 0; }
        .mc-file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mc-file small { color: var(--color-muted); white-space: nowrap; }
        .mc-ai { padding: 12px 20px; border-bottom: 1px solid var(--color-line); background: var(--color-teal-tint); display: flex; flex-direction: column; gap: 10px; }
        .mc-ai .textarea { min-height: 64px; }
        .mc-ai-draft { white-space: pre-wrap; background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-control); padding: 12px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); max-height: 220px; overflow-y: auto; }
        .mc-row-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .mc-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--color-line); position: relative; }
        .mc-foot .mc-grow { flex: 1; }
        .mc-send { display: inline-flex; }
        .mc-send .btn:first-child { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .mc-send .btn + .btn { border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: 1px solid rgba(255, 255, 255, 0.35); padding-left: 10px; padding-right: 10px; }
        .mc-schedule { position: absolute; left: 20px; bottom: calc(100% + 8px); z-index: 5; width: min(340px, calc(100% - 40px)); padding: 16px; background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); box-shadow: var(--shadow-float); display: flex; flex-direction: column; gap: 10px; }
        .mc-schedule h3 { margin: 0; font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .mc-schedule .btn { justify-content: flex-start; }
        .mc-confirm { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .mc-drop { outline: 2px dashed var(--color-teal); outline-offset: -8px; }
        .mc-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); z-index: 70; display: flex; align-items: center; gap: 12px; max-width: min(560px, calc(100vw - 32px)); padding: 10px 10px 10px 16px; border-radius: var(--radius-card); background: var(--color-ink); color: var(--color-fg-on-dark); box-shadow: var(--shadow-float); font-size: 0.875rem; line-height: 1.375rem; }
        .mc-toast .btn-ghost { color: var(--color-accent-on-dark); }
        .mc-toast .btn-ghost:hover { background: rgba(255, 255, 255, 0.08); }
        .mc-toast .mc-icon { color: var(--color-fg-on-dark-muted); width: 32px; height: 32px; }
        .mc-toast .mc-icon:hover { background: rgba(255, 255, 255, 0.08); color: var(--color-fg-on-dark); }
        .mc-toast.is-failed { background: var(--color-danger); }
        .mc-toast-text { flex: 1; min-width: 0; }
        @media (max-width: 640px) {
            .mc-backdrop { padding: 0; place-items: stretch; }
            .mc-panel { width: 100%; height: 100dvh; max-height: none; border-radius: 0; }
            .mc-head { padding-left: 16px; }
            .mc-fields, .mc-scroll, .mc-foot, .mc-ai, .mc-linkbar { padding-left: 16px; padding-right: 16px; }
            .mc-toolbar { padding-left: 8px; padding-right: 8px; }
            .mc-foot .help { display: none; }
            .mc-row > label, .mc-row > .mc-label { flex-basis: 48px; }
            .mc-field-error { padding-left: 56px; }
            .mc-saved { display: none; }
            .mc-schedule { left: 16px; }
            .mc-toast { bottom: 16px; }
        }
        @media (prefers-reduced-motion: no-preference) {
            .mc-panel { animation: mc-in 160ms ease-out; }
            @keyframes mc-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        }
    </style>

    @if ($open && $outboxId)
        <div class="mc-backdrop" wire:key="mc-{{ $outboxId }}"
             {{-- x-data must never change while the composer is open: a changed x-data makes Alpine start the editor again
                  (text reset to the last save, focus moved). The draft's text rides on the editor inside wire:ignore instead. --}}
             x-data="hqMailEditor({ autosaveMs: {{ (int) config('mail_compose.autosave_ms', 1500) }} })"
             x-on:keydown.escape.window="if (!$event.defaultPrevented) { $event.preventDefault(); $wire.close(html()) }"
             x-on:click.self="$wire.close(html())">
            <section class="mc-panel" role="dialog" aria-modal="true" aria-labelledby="mc-title"
                     :class="{ 'mc-drop': dragging }"
                     x-on:dragover.prevent="dragging = true" x-on:dragleave.self="dragging = false" x-on:drop.prevent="dragging = false; dropFiles($event)">
                <header class="mc-head">
                    <h2 id="mc-title">{{ $subject !== '' ? $subject : $title }}</h2>
                    @if ($savedAt)
                        <span class="mc-saved" wire:loading.remove wire:target="autosave">Saved {{ $fmtTime($savedAt) }}</span>
                        <span class="mc-saved" wire:loading wire:target="autosave">Saving…</span>
                    @endif
                    <button type="button" class="mc-icon" x-on:click="$wire.close(html())" title="Close and keep the draft" aria-label="Close and keep the draft"><i data-lucide="x"></i></button>
                </header>

                <div class="mc-fields">
                    <div class="mc-row">
                        <label for="mc-from">From</label>
                        @if (in_array($kind, ['reply', 'reply_all'], true) || $accounts->count() < 2)
                            <div class="mc-from-text" id="mc-from">{{ $account?->fromLine() }}</div>
                        @else
                            <select id="mc-from" class="mc-select" wire:model.live="accountId">
                                @foreach ($accounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->display_name ? $a->display_name . ' <' . $a->email . '>' : $a->email }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    @foreach (['to' => 'To', 'cc' => 'Cc', 'bcc' => 'Bcc'] as $field => $label)
                        @if ($field === 'to' || $showCc)
                            <div x-data="hqMailChips('{{ $field }}')" wire:key="mc-{{ $field }}">
                                <div class="mc-row">
                                    <label for="mc-{{ $field }}">{{ $label }}</label>
                                    <div class="mc-chips" x-on:click.self="$refs.input.focus()">
                                        <template x-for="(a, i) in list" :key="a.email">
                                            <span class="mc-chip" :title="a.name ? a.name + ' <' + a.email + '>' : a.email">
                                                <span x-text="label(a)"></span>
                                                <button type="button" x-on:click="remove(i)" :aria-label="'Remove ' + label(a)"><i data-lucide="x"></i></button>
                                            </span>
                                        </template>
                                        <input id="mc-{{ $field }}" x-ref="input" type="text" autocomplete="off" spellcheck="false"
                                               role="combobox" :aria-expanded="items.length > 0" aria-controls="mc-{{ $field }}-list"
                                               @if ($field === 'to' && ! in_array($kind, ['reply', 'reply_all'], true)) data-autofocus @endif
                                               x-model="query" x-on:input="typed()" x-on:keydown="key($event)" x-on:blur="blurred()" x-on:paste="pasted($event)">
                                        <ul class="mc-suggest" id="mc-{{ $field }}-list" role="listbox" x-show="items.length" x-cloak>
                                            <template x-for="(s, j) in items" :key="s.email">
                                                <li role="option" :class="{ 'is-active': j === active }" :aria-selected="j === active" x-on:mousedown.prevent="pick(s)">
                                                    <span x-text="s.name || s.email"></span>
                                                    <small x-text="s.name ? s.email : (s.kind === 'staff' ? 'HQ' : '')"></small>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    @if ($field === 'to' && ! $showCc)
                                        <button type="button" class="mc-link" wire:click="$set('showCc', true)">Cc and Bcc</button>
                                    @endif
                                </div>
                                <div class="mc-field-error" x-show="bad" x-text="bad" x-cloak></div>
                            </div>
                        @endif
                    @endforeach

                    <div class="mc-row">
                        <label for="mc-subject">Subject</label>
                        <input id="mc-subject" class="mc-plain" type="text" maxlength="250" wire:model.live.debounce.800ms="subject">
                    </div>
                </div>

                <div class="mc-toolbar" role="toolbar" aria-label="Formatting">
                    @foreach ($formats as [$command, $name, $shortcut, $paths])
                        @php [$cmd, $arg] = explode(':', $command) + [1 => null]; @endphp
                        <button type="button" class="mc-icon" title="{{ $name }}{{ $shortcut ? ' (' . $shortcut . ')' : '' }}" aria-label="{{ $name }}"
                                x-on:mousedown.prevent x-on:click="cmd('{{ $cmd }}'{!! $arg ? ", '" . $arg . "'" : '' !!})">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
                        </button>
                        @if ($loop->index === 2 || $loop->index === 5) <span class="mc-sep" aria-hidden="true"></span> @endif
                    @endforeach
                    <button type="button" class="mc-icon" title="Link (Ctrl+K)" aria-label="Link" x-on:mousedown.prevent x-on:click="openLink()"><i data-lucide="link"></i></button>
                    <span class="mc-sep" aria-hidden="true"></span>
                    <button type="button" class="mc-icon" title="Attach files" aria-label="Attach files" x-on:click="$refs.file.click()"><i data-lucide="paperclip"></i></button>
                    <input type="file" multiple x-ref="file" wire:model="uploads" hidden>
                    <button type="button" class="mc-icon {{ $aiOpen ? 'is-on' : '' }}" title="Write with AI" aria-label="Write with AI" aria-pressed="{{ $aiOpen ? 'true' : 'false' }}" wire:click="toggleAi"><i data-lucide="sparkles"></i></button>
                </div>

                <div class="mc-linkbar" x-show="linkOpen" x-cloak>
                    <label class="sr-only" for="mc-link-url">Link address</label>
                    <input id="mc-link-url" class="input" type="text" x-ref="linkInput" x-model="linkUrl" placeholder="https://"
                           x-on:keydown.enter.prevent="applyLink()" x-on:keydown.escape.prevent.stop="linkOpen = false">
                    <button type="button" class="btn btn-secondary btn-sm" x-on:click="applyLink()">Add link</button>
                    <button type="button" class="btn btn-ghost btn-sm" x-on:click="linkOpen = false">Cancel</button>
                </div>

                @if ($aiOpen)
                    <div class="mc-ai">
                        @if ($aiDraft === null)
                            <div class="field">
                                <label class="label" for="mc-ai-prompt">What should the message say?</label>
                                <textarea id="mc-ai-prompt" class="textarea" wire:model="aiPrompt" maxlength="2000"
                                          placeholder="For example: thank them and propose a call on Tuesday at 10:00"
                                          x-on:keydown.ctrl.enter.prevent="$wire.writeWithAi(html())" x-on:keydown.meta.enter.prevent="$wire.writeWithAi(html())"></textarea>
                                @error('aiPrompt') <span class="field-error">{{ $message }}</span> @enderror
                                <span class="help">Praxis AI reads {{ $kind === 'new' ? 'your draft' : 'this conversation and your draft' }} and writes a draft for you to check. Nothing is sent until you press Send.</span>
                            </div>
                            <div class="mc-row-actions">
                                <button type="button" class="btn btn-primary btn-sm" x-on:click="$wire.writeWithAi(html())" wire:loading.attr="disabled" wire:target="writeWithAi">
                                    <span wire:loading.remove wire:target="writeWithAi"><i data-lucide="sparkles"></i></span>
                                    <span wire:loading wire:target="writeWithAi"><i data-lucide="loader-circle"></i></span>
                                    Write draft
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="toggleAi">Close</button>
                            </div>
                        @else
                            <div class="mc-ai-draft" aria-live="polite">{{ $aiDraft }}</div>
                            <div class="mc-row-actions">
                                <button type="button" class="btn btn-primary btn-sm" wire:click="useAiDraft"><i data-lucide="check"></i> Use draft</button>
                                <button type="button" class="btn btn-secondary btn-sm" x-on:click="$wire.writeWithAi(html())" wire:loading.attr="disabled" wire:target="writeWithAi">
                                    <span wire:loading.remove wire:target="writeWithAi"><i data-lucide="rotate-ccw"></i></span>
                                    <span wire:loading wire:target="writeWithAi"><i data-lucide="loader-circle"></i></span>
                                    Try again
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="toggleAi">Close</button>
                            </div>
                            <span class="help">Use draft replaces what is in the message now.</span>
                        @endif
                    </div>
                @endif

                <div class="mc-scroll">
                    @if ($error)
                        <div class="alert alert-danger mb-4" role="alert"><i data-lucide="circle-alert"></i><span>{{ $error }}</span></div>
                    @endif

                    <div wire:ignore>
                        <div class="mc-editor" x-ref="editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Message"
                             data-placeholder="Write your message" data-html="{{ $editorHtml }}" spellcheck="true" @if (in_array($kind, ['reply', 'reply_all'], true)) data-autofocus @endif></div>
                    </div>

                    <div class="mc-extra">
                        @if ($attachments || $uploads)
                            <div class="mc-files" aria-label="Attachments">
                                @foreach ($attachments as $file)
                                    <span class="mc-file" wire:key="f-{{ $file['key'] }}">
                                        <i data-lucide="paperclip"></i>
                                        <span>{{ $file['name'] }}</span>
                                        <small>{{ \Illuminate\Support\Number::fileSize((int) $file['size'], precision: $file['size'] > 1048576 ? 1 : 0) }}</small>
                                        <button type="button" class="mc-icon" style="width: 28px; height: 28px;" wire:click="removeAttachment('{{ $file['key'] }}')" aria-label="Remove {{ $file['name'] }}" title="Remove"><i data-lucide="x"></i></button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <div class="help" wire:loading wire:target="uploads"><i data-lucide="loader-circle" style="width: 14px; height: 14px; vertical-align: -2px;"></i> Uploading…</div>
                        @error('uploads') <div class="field-error">{{ $message }}</div> @enderror
                        @error('uploads.*') <div class="field-error">{{ $message }}</div> @enderror

                        @if ($quoteHtml)
                            <div class="mc-box">
                                <div class="mc-box-head">
                                    @if ($kind === 'forward')
                                        <span>The forwarded message goes below yours.</span>
                                    @else
                                        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="quote"> Include the original message</label>
                                    @endif
                                    <button type="button" class="mc-link" style="padding: 0;" wire:click="$toggle('showQuote')">{{ $showQuote ? 'Hide it' : 'Show it' }}</button>
                                </div>
                                @if ($showQuote)
                                    <div class="mc-box-body"><div class="mc-quote">{!! $quoteHtml !!}</div></div>
                                @endif
                            </div>
                        @endif

                        <div class="mc-box">
                            <div class="mc-box-head">
                                <span>Signature, added when you send · <a href="{{ route('hq.mail.signature') }}" class="mc-link" style="padding: 0;" wire:navigate>Your details</a></span>
                                <label class="sr-only" for="mc-signature">Signature</label>
                                <select id="mc-signature" class="select" wire:model.live="signature">
                                    @foreach (\App\Support\Mail\Compose\Signatures::STYLES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if ($signatureHtml)
                                <div class="mc-box-body">{!! $signatureHtml !!}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <footer class="mc-foot">
                    <template x-if="!confirmDiscard">
                        <div class="mc-row-actions mc-grow">
                            <div class="mc-send">
                                <button type="button" class="btn btn-primary" x-on:click="send()" wire:loading.attr="disabled" wire:target="send" title="Send (Ctrl+Enter)">
                                    <i data-lucide="send"></i> Send
                                </button>
                                <button type="button" class="btn btn-primary" wire:click="$toggle('scheduleOpen')" aria-label="Schedule send" title="Schedule send" aria-expanded="{{ $scheduleOpen ? 'true' : 'false' }}"><i data-lucide="chevron-down"></i></button>
                            </div>
                            <span class="help">Files up to 12 MB each, {{ $maxMb }} MB in all</span>
                        </div>
                    </template>
                    <template x-if="!confirmDiscard">
                        <button type="button" class="mc-icon" title="Discard draft" aria-label="Discard draft" x-on:click="confirmDiscard = true"><i data-lucide="trash-2"></i></button>
                    </template>
                    <template x-if="confirmDiscard">
                        <div class="mc-confirm mc-grow" role="alert">
                            <span class="mc-grow">Discard this draft? It is removed from HQ and from Drafts.</span>
                            <button type="button" class="btn btn-danger btn-sm" wire:click="discard">Discard draft</button>
                            <button type="button" class="btn btn-secondary btn-sm" x-on:click="confirmDiscard = false">Keep writing</button>
                        </div>
                    </template>

                    @if ($scheduleOpen)
                        <div class="mc-schedule" role="dialog" aria-label="Schedule send" x-on:keydown.escape.stop.prevent="$wire.set('scheduleOpen', false)">
                            <h3>Schedule send</h3>
                            <button type="button" class="btn btn-secondary" x-on:click="$wire.schedule(html(), 'tomorrow')"><i data-lucide="clock"></i> Tomorrow at 08:00</button>
                            <button type="button" class="btn btn-secondary" x-on:click="$wire.schedule(html(), 'monday')"><i data-lucide="calendar"></i> Monday at 08:00</button>
                            <div class="field">
                                <label class="label" for="mc-when">Another time (Lagos time)</label>
                                <input id="mc-when" class="input" type="datetime-local" wire:model="scheduleAt"
                                       min="{{ now($tz)->addMinutes(2)->format('Y-m-d\TH:i') }}" max="{{ now($tz)->addYear()->format('Y-m-d\TH:i') }}">
                                @error('scheduleAt') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="mc-row-actions">
                                <button type="button" class="btn btn-primary btn-sm" x-on:click="$wire.schedule(html())"><i data-lucide="clock"></i> Schedule</button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('scheduleOpen', false)">Cancel</button>
                            </div>
                        </div>
                    @endif
                </footer>
            </section>
        </div>
    @endif

    @if ($toast)
        <div class="mc-toast {{ $toast['type'] === 'failed' ? 'is-failed' : '' }}" role="status" aria-live="polite"
             wire:key="toast-{{ $toast['type'] }}-{{ $toast['outboxId'] ?? 'x' }}-{{ md5($toast['text']) }}" x-data="hqMailToast(@js($toast))">
            <span class="mc-toast-text">
                {{ $toast['text'] }}
                @if ($toast['type'] === 'undo') <span x-text="left > 0 ? '(' + left + ')' : ''"></span> @endif
            </span>
            @if (in_array($toast['type'], ['undo', 'scheduled'], true))
                <button type="button" class="btn btn-ghost btn-sm" wire:click="undo('{{ $toast['outboxId'] }}')"><i data-lucide="rotate-ccw"></i> Undo</button>
            @elseif (in_array($toast['type'], ['failed', 'retry'], true) || ($toast['type'] === 'info' && ! empty($toast['outboxId'])))
                <button type="button" class="btn btn-ghost btn-sm" wire:click="reopen('{{ $toast['outboxId'] }}')"><i data-lucide="pencil"></i> Open</button>
            @endif
            @if ($toast['type'] !== 'undo')
                <button type="button" class="mc-icon" wire:click="dismissToast" aria-label="Close message"><i data-lucide="x"></i></button>
            @endif
        </div>
    @endif
</div>
