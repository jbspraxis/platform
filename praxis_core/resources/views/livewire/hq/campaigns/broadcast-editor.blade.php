@php
    use App\Support\Campaigns\Blocks;
    use App\Support\Campaigns\Layouts;
    use App\Support\Campaigns\Audience;
    use App\Models\Campaigns\Broadcast;
    use App\Support\Campaigns\Broadcasts;

    // Text formats: Lucide paths drawn inline (the built icon set has no text-format icons; no npm build for this script)
    $formats = [
        ['bold', 'Bold', 'Ctrl+B', '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/>'],
        ['italic', 'Italic', 'Ctrl+I', '<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>'],
        ['underline', 'Underline', 'Ctrl+U', '<path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" x2="20" y1="20" y2="20"/>'],
        ['insertUnorderedList', 'Bulleted list', null, '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>'],
        ['insertOrderedList', 'Numbered list', null, '<path d="M10 12h11"/><path d="M10 18h11"/><path d="M10 6h11"/><path d="M4 10h2"/><path d="M4 6h1v4"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>'],
        ['heading:h2', 'Heading', null, '<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M21 18h-4c0-4 4-3 4-6 0-1.5-2-2.5-4-1"/>'],
        ['heading:h3', 'Small heading', null, '<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17.5 10.5c1.7-1 3.5 0 3.5 1.5a2 2 0 0 1-2 2"/><path d="M17 17.5c2 1.5 4 .3 4-1.5a2 2 0 0 0-2-2"/>'],
        ['link', 'Link', 'Ctrl+K', '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>'],
        ['removeFormat', 'Clear formatting', null, '<path d="M4 7V4h16v3"/><path d="M5 20h6"/><path d="M13 4 8 20"/><path d="m15 15 5 5"/><path d="m20 15-5 5"/>'],
    ];
    // Layouts and blocks name their icons in PHP (Layouts::ALL, Blocks::TYPES). scripts/icons.mjs bundles only icons named
    // in the views, so they are listed here: data-lucide="megaphone" data-lucide="lightbulb" data-lucide="calendar-days"
    // data-lucide="briefcase-business" data-lucide="pen-line" data-lucide="heading-2" data-lucide="pilcrow" data-lucide="image"
    // data-lucide="mouse-pointer-click" data-lucide="quote" data-lucide="chart-no-axes-column" data-lucide="list" data-lucide="minus"
    $locked = ! $canEdit;
    $locales = $broadcast->locales();
    $langName = ['en' => 'English', 'fr' => 'French'];
    $other = $tab === 'en' ? 'fr' : 'en';
    $kinds = ['client' => 'Client', 'lead' => 'Lead', 'kaizen' => 'Kaizen writer', 'subscriber' => 'Subscriber'];
    $p = "content.{$tab}";
@endphp
<div class="bce">
    @assets
        <script src="{{ route('hq.campaigns.script', ['v' => filemtime(resource_path('campaigns/editor.js'))]) }}"></script>
    @endassets
    <style>
        /* Broadcast editor (docs/CAMPAIGNS.md 4.4): only what the shared component classes don't cover. Brand tokens only. */
        .bce-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 0.875rem; color: var(--color-deep-teal); text-decoration: none; font-weight: 500; }
        .bce-back svg { width: 16px; height: 16px; }
        .bce-saved { font-size: 0.8125rem; color: var(--color-muted); }
        .bce-steps { margin-bottom: 24px; }
        .bce-steps .tab { display: inline-flex; align-items: center; }
        .bce-steps .tab span { display: inline-grid; place-items: center; width: 20px; height: 20px; margin-right: 6px; border-radius: 50%; background: var(--color-mist); font-size: 0.75rem; }
        .bce-steps .tab.is-active span { background: var(--color-deep-teal); color: #fff; }
        .bce-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, 480px); gap: 24px; align-items: start; }
        .bce-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .bce-section-title { margin: 0 0 12px; font-size: 0.9375rem; line-height: 1.375rem; font-weight: 600; color: var(--color-ink); }
        .bce-layouts { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
        .bce-layout { display: flex; flex-direction: column; gap: 4px; padding: 12px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); text-align: left; cursor: pointer; font: inherit; color: var(--color-slate); }
        .bce-layout strong { display: flex; align-items: center; gap: 8px; color: var(--color-ink); font-size: 0.875rem; font-weight: 600; }
        .bce-layout strong svg { width: 16px; height: 16px; color: var(--color-deep-teal); }
        .bce-layout small { font-size: 0.75rem; line-height: 1.0625rem; color: var(--color-muted); }
        .bce-layout[aria-pressed="true"] { border-color: var(--color-deep-teal); background: var(--color-teal-tint); box-shadow: inset 0 0 0 1px var(--color-deep-teal); }
        .bce-layout:disabled { cursor: default; }
        .bce-row { display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: flex-end; }
        .bce-row .field { flex: 1 1 200px; min-width: 0; }
        .bce-check { display: flex; align-items: center; gap: 8px; min-height: 40px; font-size: 0.875rem; color: var(--color-ink); }
        .bce-langbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
        .bce-langbar .tabs { margin-bottom: 0; flex: 1; }
        .bce-ai { padding: 16px; border-radius: var(--radius-card); background: var(--color-teal-tint); display: flex; flex-direction: column; gap: 10px; }
        .bce-ai .textarea { min-height: 88px; background: var(--color-paper); }
        .bce-fields { display: flex; flex-direction: column; gap: 14px; }
        .bce-block { border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); }
        .bce-block-head { display: flex; align-items: center; gap: 8px; padding: 8px 8px 8px 14px; border-bottom: 1px solid var(--color-line); font-size: 0.8125rem; font-weight: 600; color: var(--color-ink); }
        .bce-block-head > svg { width: 16px; height: 16px; color: var(--color-deep-teal); flex-shrink: 0; }
        .bce-block-head .bce-grow { flex: 1; }
        .bce-block-body { padding: 14px; display: flex; flex-direction: column; gap: 12px; }
        .bce-icon { display: inline-grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: var(--radius-control); background: transparent; color: var(--color-slate); cursor: pointer; flex-shrink: 0; }
        .bce-icon:hover:not(:disabled) { background: var(--color-mist); color: var(--color-ink); }
        .bce-icon:disabled { opacity: 0.35; cursor: default; }
        .bce-icon:focus-visible, .bce-layout:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 1px; }
        .bce-icon svg { width: 16px; height: 16px; }
        .bce-toolbar { display: flex; flex-wrap: wrap; gap: 2px; padding: 4px; border: 1px solid var(--color-line-strong); border-bottom: 0; border-radius: var(--radius-control) var(--radius-control) 0 0; background: var(--color-mist); }
        .bce-editor { min-height: 120px; padding: 10px 12px; border: 1px solid var(--color-line-strong); border-radius: 0 0 var(--radius-control) var(--radius-control); outline: none; font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-ink); overflow-wrap: anywhere; background: var(--color-paper); }
        .bce-editor:focus { border-color: var(--color-deep-teal); box-shadow: 0 0 0 1px var(--color-deep-teal); }
        .bce-editor:empty::before { content: attr(data-placeholder); color: var(--color-muted); pointer-events: none; }
        .bce-editor p { margin: 0 0 10px; }
        .bce-editor h2 { margin: 12px 0 8px; font-family: var(--font-serif); font-weight: 400; font-size: 1.25rem; line-height: 1.75rem; }
        .bce-editor h3 { margin: 10px 0 6px; font-size: 1rem; font-weight: 600; }
        .bce-editor ul, .bce-editor ol { margin: 0 0 10px; padding-left: 24px; }
        .bce-editor a { color: var(--color-fg-link); }
        .bce-linkbar { display: flex; gap: 8px; align-items: center; padding: 8px; border: 1px solid var(--color-line-strong); border-bottom: 0; background: var(--color-paper); }
        .bce-linkbar .input { flex: 1; }
        .bce-pairs { display: flex; flex-direction: column; gap: 8px; }
        .bce-pair { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) 32px; gap: 8px; align-items: center; }
        .bce-thumb { display: block; max-width: 100%; max-height: 220px; border-radius: var(--radius-control); border: 1px solid var(--color-line); }
        .bce-add { display: flex; flex-wrap: wrap; gap: 8px; }
        .bce-add .btn svg { width: 16px; height: 16px; }
        .bce-preview { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 12px; }
        .bce-preview-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .bce-frame-wrap { position: relative; width: 100%; height: calc(100vh - 220px); min-height: 480px; overflow: hidden; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: #F2F5F7; }
        .bce-frame-wrap iframe { position: absolute; top: 0; left: 50%; border: 0; background: #F2F5F7; transform-origin: top center; }
        .bce-summary { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
        .bce-segments { display: flex; flex-direction: column; gap: 8px; }
        .bce-segment { display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); cursor: pointer; }
        .bce-segment[aria-checked="true"] { border-color: var(--color-deep-teal); background: var(--color-teal-tint); box-shadow: inset 0 0 0 1px var(--color-deep-teal); }
        .bce-segment strong { display: block; color: var(--color-ink); font-size: 0.875rem; }
        .bce-segment small { display: block; color: var(--color-muted); font-size: 0.8125rem; line-height: 1.125rem; }
        .bce-segment .bce-grow { flex: 1; min-width: 0; }
        .bce-problems { margin: 0; padding-left: 20px; font-size: 0.875rem; line-height: 1.5rem; color: var(--color-slate); }
        .bce-send { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .bce-send .card { display: flex; flex-direction: column; gap: 12px; }
        .bce-send .card .btn { align-self: flex-start; }
        @media (max-width: 1100px) {
            .bce-grid { grid-template-columns: minmax(0, 1fr); }
            .bce-preview { position: static; }
            .bce-frame-wrap { height: 640px; }
        }
        @media (max-width: 600px) {
            .bce-pair { grid-template-columns: minmax(0, 1fr) 32px; }
            .bce-pair .input:first-child { grid-column: 1 / -1; }
            .bce-send .card .btn { align-self: stretch; }
            .bce-steps .tab { padding: 10px 8px; }
            .bce-steps .tab span { margin-right: 4px; }
        }
    </style>

    <div class="page-header">
        <div>
            <a href="{{ route('hq.campaigns.broadcasts') }}" class="bce-back" wire:navigate><i data-lucide="arrow-left"></i> All broadcasts</a>
            <h1 class="page-title">{{ $broadcast->title() }}</h1>
            <p class="page-subtitle">
                @if ($broadcast->status === 'Scheduled')
                    <span class="pill pill-info">Scheduled</span> {{ Broadcasts::wat($broadcast->scheduled_at) }}
                @else
                    <span class="pill pill-neutral">Draft</span>
                    @if ($savedAt) <span class="bce-saved" role="status">Saved at {{ $savedAt }} WAT</span> @else <span class="bce-saved">Changes are saved as you type.</span> @endif
                @endif
            </p>
        </div>
    </div>


    @if ($broadcast->status === 'Scheduled')
        <div class="alert alert-info mb-6" role="status">
            <i data-lucide="calendar-clock"></i>
            <div>
                <p class="m-0">It starts sending at {{ Broadcasts::wat($broadcast->scheduled_at) }}. To change anything, unschedule it first: it goes back to being a draft.</p>
                @if ($canSend)
                    <button type="button" class="btn btn-secondary btn-sm mt-3" wire:click="unschedule">Unschedule</button>
                @endif
            </div>
        </div>
    @elseif (! $canEdit)
        <div class="alert alert-info mb-6" role="status"><i data-lucide="info"></i><span>You can read this draft. Writing broadcasts needs the “Create broadcasts” permission: ask an administrator.</span></div>
    @endif

    <nav class="tabs bce-steps" aria-label="Steps">
        @foreach (['write' => 'Write', 'audience' => 'Audience', 'send' => 'Test and send'] as $key => $label)
            <button type="button" class="tab {{ $step === $key ? 'is-active' : '' }}" aria-current="{{ $step === $key ? 'step' : 'false' }}" wire:click="$set('step', '{{ $key }}')"><span>{{ $loop->iteration }}</span>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($step === 'write')
        <div class="bce-grid">
            <div class="bce-main">
                <section class="card" aria-labelledby="bce-layout-title">
                    <h2 class="bce-section-title" id="bce-layout-title">Layout</h2>
                    <div class="bce-layouts" role="group" aria-labelledby="bce-layout-title">
                        @foreach (Layouts::ALL as $key => $l)
                            <button type="button" class="bce-layout" aria-pressed="{{ $layout === $key ? 'true' : 'false' }}" wire:click="setLayout('{{ $key }}')" @disabled($locked)>
                                <strong><i data-lucide="{{ $l['icon'] }}"></i> {{ $l['label'] }}</strong>
                                <small>{{ $l['hint'] }}</small>
                            </button>
                        @endforeach
                    </div>
                    <div class="bce-row mt-4">
                        <div class="field">
                            <label class="label" for="bce-language">Languages</label>
                            <select id="bce-language" class="select" wire:model.live="language" @disabled($locked)>
                                @foreach (Broadcast::LANGUAGES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="help">{{ $language === 'both' ? 'Each person gets their own language: from their preference, else their organisation’s, else their country.' : 'Everyone gets this version, whatever their language.' }}</span>
                        </div>
                        <label class="bce-check"><input type="checkbox" class="checkbox" wire:model.live="greeting" @disabled($locked)> Greet each person (“Hello Ada,” / « Bonjour, »)</label>
                    </div>
                </section>

                <section class="card bce-fields" aria-label="The email">
                    <div class="bce-langbar">
                        @if (count($locales) > 1)
                            <div class="tabs" role="tablist" aria-label="Version">
                                @foreach ($locales as $l)
                                    <button type="button" role="tab" class="tab {{ $tab === $l ? 'is-active' : '' }}" aria-selected="{{ $tab === $l ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $l }}')">{{ $langName[$l] }}</button>
                                @endforeach
                            </div>
                        @else
                            <h2 class="bce-section-title m-0">{{ $langName[$tab] }} version</h2>
                        @endif
                        @if (! $locked)
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="$toggle('aiOpen')" aria-expanded="{{ $aiOpen ? 'true' : 'false' }}"><i data-lucide="sparkles"></i> Write with AI</button>
                                @if (count($locales) > 1)
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="translateFrom('{{ $other }}')" wire:confirm="Replace the {{ $langName[$tab] }} version with an AI translation of the {{ $langName[$other] }} one?"><i data-lucide="languages"></i> Translate from {{ $langName[$other] }}</button>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($aiRunId)
                        <div class="alert alert-info" role="status" wire:poll.2s="checkAi">
                            <i data-lucide="loader-circle"></i>
                            <span>{{ $aiKind === 'translate' ? 'Translating' : 'Writing a draft' }} with Claude. This takes up to a minute; you can keep working on another step.</span>
                        </div>
                    @elseif ($aiOpen && ! $locked)
                        <div class="bce-ai">
                            <label class="label" for="bce-brief">What should the {{ $langName[$tab] }} email say?</label>
                            <textarea id="bce-brief" class="textarea" wire:model="brief" maxlength="3000" placeholder="e.g. Invite finance directors in Lagos to a breakfast on 20 November, 8am, at our Lekki office, about seeing cash every morning. Ask them to reply to reserve a place."></textarea>
                            <span class="help">Give the facts, figures, names and dates: the AI uses only what you write here. It replaces the subject and blocks of this version; pictures are kept out.</span>
                            @error('brief') <span class="field-error">{{ $message }}</span> @enderror
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-primary btn-sm" wire:click="writeWithAi" wire:loading.attr="disabled" wire:target="writeWithAi"><i data-lucide="sparkles"></i> Write the draft</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('aiOpen', false)">Close</button>
                            </div>
                        </div>
                    @endif

                    @if (count($locales) > 1 && ! $locked && collect($version['blocks'])->every(fn ($b) => in_array($b['type'], ['button', 'divider'], true) || ($b['type'] === 'text' && \App\Support\Campaigns\RichText::isBlank($b['html']))) && $version['subject'] === '')
                        <p class="help m-0">This version is empty. <button type="button" class="btn btn-ghost btn-sm" wire:click="copyVersion('{{ $other }}', '{{ $tab }}')"><i data-lucide="copy"></i> Copy the {{ $langName[$other] }} version</button> to rewrite it by hand, or translate it with AI.</p>
                    @endif

                    <div class="field">
                        <label class="label" for="bce-subject">Subject</label>
                        <input id="bce-subject" type="text" class="input" maxlength="150" wire:model.live.debounce.500ms="{{ $p }}.subject" @disabled($locked) placeholder="{{ $tab === 'fr' ? 'p. ex. Trois vérifications avant votre clôture mensuelle' : 'e.g. Three checks before your month-end close' }}">
                    </div>
                    <div class="bce-row">
                        <div class="field">
                            <label class="label" for="bce-preheader">Preview text <span class="help">(optional)</span></label>
                            <input id="bce-preheader" type="text" class="input" maxlength="160" wire:model.live.debounce.500ms="{{ $p }}.preheader" @disabled($locked)>
                            <span class="help">The grey line inboxes show after the subject. Empty: the first words of the email.</span>
                        </div>
                        <div class="field">
                            <label class="label" for="bce-headline">Headline <span class="help">(optional)</span></label>
                            <input id="bce-headline" type="text" class="input" maxlength="150" wire:model.live.debounce.500ms="{{ $p }}.headline" @disabled($locked)>
                            <span class="help">The large title in the email. Empty: the subject.</span>
                        </div>
                    </div>
                </section>

                @foreach ($version['blocks'] as $i => $block)
                    @php $type = Blocks::TYPES[$block['type']] ?? null; $bp = "{$p}.blocks.{$i}"; @endphp
                    @continue(! $type)
                    <section class="bce-block" wire:key="block-{{ $tab }}-{{ $block['id'] }}" aria-label="{{ $type['label'] }}">
                        <div class="bce-block-head">
                            <i data-lucide="{{ $type['icon'] }}"></i>
                            <span class="bce-grow">{{ $type['label'] }}</span>
                            @unless ($locked)
                                <button type="button" class="bce-icon" wire:click="moveBlock({{ $i }}, -1)" @disabled($i === 0) aria-label="Move {{ mb_strtolower($type['label']) }} up" title="Move up"><i data-lucide="arrow-up"></i></button>
                                <button type="button" class="bce-icon" wire:click="moveBlock({{ $i }}, 1)" @disabled($loop->last) aria-label="Move {{ mb_strtolower($type['label']) }} down" title="Move down"><i data-lucide="arrow-down"></i></button>
                                <button type="button" class="bce-icon" wire:click="removeBlock({{ $i }})" wire:confirm="Remove this {{ mb_strtolower($type['label']) }} block from the {{ $langName[$tab] }} version?" aria-label="Remove {{ mb_strtolower($type['label']) }}" title="Remove"><i data-lucide="trash"></i></button>
                            @endunless
                        </div>
                        <div class="bce-block-body">
                            @switch($block['type'])
                                @case('heading')
                                    <label class="sr-only" for="b-{{ $block['id'] }}">Heading</label>
                                    <input id="b-{{ $block['id'] }}" type="text" class="input" maxlength="200" wire:model.live.debounce.500ms="{{ $bp }}.text" @disabled($locked)>
                                    @break
                                @case('text')
                                    <div wire:ignore x-data="campaignText({ id: @js($block['id']), locale: @js($tab), html: @js($block['html']) })">
                                        @unless ($locked)
                                            <div class="bce-toolbar" role="toolbar" aria-label="Text format">
                                                @foreach ($formats as [$command, $label, $keys, $paths])
                                                    @php
                                                        $click = match (true) {
                                                            $command === 'link' => 'openLink()',
                                                            str_starts_with($command, 'heading:') => "heading('" . substr($command, 8) . "')",
                                                            default => "cmd('{$command}')",
                                                        };
                                                    @endphp
                                                    <button type="button" class="bce-icon" @mousedown.prevent @click="{{ $click }}" aria-label="{{ $label }}" title="{{ $label }}{{ $keys ? " ({$keys})" : '' }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
                                                    </button>
                                                @endforeach
                                            </div>
                                            <div class="bce-linkbar" x-show="linkOpen" x-cloak>
                                                <label class="sr-only" :for="'link-' + @js($block['id'])">Link address</label>
                                                <input type="url" class="input" x-ref="link" :id="'link-' + @js($block['id'])" x-model="linkUrl" @keydown.enter.prevent="applyLink()" @keydown.escape.prevent="cancelLink()" placeholder="https://">
                                                <button type="button" class="btn btn-primary btn-sm" @click="applyLink()">Add link</button>
                                                <button type="button" class="btn btn-secondary btn-sm" @click="cancelLink()">Cancel</button>
                                            </div>
                                        @endunless
                                        <div class="bce-editor" x-ref="ed" contenteditable="{{ $locked ? 'false' : 'true' }}" role="textbox" aria-multiline="true" aria-label="Text"
                                             data-placeholder="{{ $tab === 'fr' ? 'Écrivez ici' : 'Write here' }}" spellcheck="true" lang="{{ $tab }}"></div>
                                    </div>
                                    <span class="help">You can write @{{ first_name }} or @{{ company_name }}: each person sees their own.</span>
                                    @break
                                @case('image')
                                    @if ($block['src'] !== '')
                                        <img src="{{ $block['src'] }}" alt="" class="bce-thumb">
                                    @endif
                                    @unless ($locked)
                                        <div>
                                            <label class="btn btn-secondary btn-sm" for="up-{{ $block['id'] }}"><i data-lucide="upload"></i> {{ $block['src'] !== '' ? 'Replace the picture' : 'Upload a picture' }}</label>
                                            <input id="up-{{ $block['id'] }}" type="file" class="sr-only" accept="image/png,image/jpeg"
                                                   x-on:change="const f = $event.target.files[0]; if (f) { $wire.set('uploadFor', @js($block['id'])).then(() => $wire.upload('upload', f)) }">
                                            <span class="help" wire:loading wire:target="upload">Uploading…</span>
                                            @if ($uploadFor === $block['id']) @error('upload') <span class="field-error">{{ $message }}</span> @enderror @endif
                                        </div>
                                        <span class="help">JPG or PNG, under 2 MB, ideally 1200 pixels wide. Never put the only copy of a message in a picture: many inboxes hide pictures at first.</span>
                                    @endunless
                                    <div class="bce-row">
                                        <div class="field"><label class="label" for="alt-{{ $block['id'] }}">Description for people who can’t see it</label>
                                            <input id="alt-{{ $block['id'] }}" type="text" class="input" maxlength="200" wire:model.live.debounce.500ms="{{ $bp }}.alt" @disabled($locked)></div>
                                        <div class="field"><label class="label" for="ln-{{ $block['id'] }}">Link <span class="help">(optional)</span></label>
                                            <input id="ln-{{ $block['id'] }}" type="url" class="input" wire:model.live.debounce.500ms="{{ $bp }}.link" @disabled($locked) placeholder="https://"></div>
                                    </div>
                                    @break
                                @case('button')
                                    <div class="bce-row">
                                        <div class="field"><label class="label" for="bl-{{ $block['id'] }}">Label</label>
                                            <input id="bl-{{ $block['id'] }}" type="text" class="input" maxlength="60" wire:model.live.debounce.500ms="{{ $bp }}.label" @disabled($locked)></div>
                                        <div class="field"><label class="label" for="bu-{{ $block['id'] }}">Link</label>
                                            <input id="bu-{{ $block['id'] }}" type="url" class="input" wire:model.live.debounce.500ms="{{ $bp }}.url" @disabled($locked) placeholder="https://"></div>
                                    </div>
                                    <span class="help">Start the label with a verb: “Book a free audit and demo”, “Reserve your place”. One button per email reads best.</span>
                                    @break
                                @case('quote')
                                    <div class="field"><label class="label" for="q-{{ $block['id'] }}">Quote</label>
                                        <textarea id="q-{{ $block['id'] }}" class="textarea" rows="3" maxlength="600" wire:model.live.debounce.500ms="{{ $bp }}.text" @disabled($locked)></textarea></div>
                                    <div class="field"><label class="label" for="qs-{{ $block['id'] }}">Who said it</label>
                                        <input id="qs-{{ $block['id'] }}" type="text" class="input" maxlength="120" wire:model.live.debounce.500ms="{{ $bp }}.source" @disabled($locked) placeholder="{{ $tab === 'fr' ? 'p. ex. Directeur financier, un grand industriel' : 'e.g. Finance director, a leading manufacturer' }}"></div>
                                    @break
                                @case('figures')
                                @case('details')
                                    @php
                                        $field = $block['type'] === 'figures' ? 'items' : 'rows';
                                        [$first, $second] = $block['type'] === 'figures' ? ['value', 'label'] : ['label', 'value'];
                                        $names = $block['type'] === 'figures' ? ['Figure', 'What it measures'] : ['Label', 'Detail'];
                                        $max = $block['type'] === 'figures' ? 3 : 6;
                                    @endphp
                                    <div class="bce-pairs">
                                        @foreach ($block[$field] as $r => $row)
                                            <div class="bce-pair" wire:key="row-{{ $block['id'] }}-{{ $r }}">
                                                <input type="text" class="input" aria-label="{{ $names[0] }} {{ $r + 1 }}" wire:model.live.debounce.500ms="{{ $bp }}.{{ $field }}.{{ $r }}.{{ $first }}" @disabled($locked) placeholder="{{ $block['type'] === 'figures' ? '41%' : ($tab === 'fr' ? 'Date' : 'Date') }}">
                                                <input type="text" class="input" aria-label="{{ $names[1] }} {{ $r + 1 }}" wire:model.live.debounce.500ms="{{ $bp }}.{{ $field }}.{{ $r }}.{{ $second }}" @disabled($locked) placeholder="{{ $block['type'] === 'figures' ? ($tab === 'fr' ? 'd’approbations en retard en moins' : 'fewer late approvals') : '' }}">
                                                @unless ($locked)
                                                    <button type="button" class="bce-icon" wire:click="removeRow({{ $i }}, {{ $r }})" @disabled(count($block[$field]) <= 1) aria-label="Remove row {{ $r + 1 }}" title="Remove"><i data-lucide="x"></i></button>
                                                @endunless
                                            </div>
                                        @endforeach
                                    </div>
                                    @if (! $locked && count($block[$field]) < $max)
                                        <button type="button" class="btn btn-ghost btn-sm" style="align-self: flex-start" wire:click="addRow({{ $i }})"><i data-lucide="plus"></i> Add {{ $block['type'] === 'figures' ? 'a figure' : 'a detail' }}</button>
                                    @endif
                                    @if ($block['type'] === 'figures')
                                        <span class="help">Only figures you can stand behind: the email states them as fact.</span>
                                    @endif
                                    @break
                                @case('divider')
                                    <span class="help">A thin line between two parts of the email.</span>
                                    @break
                            @endswitch
                        </div>
                    </section>
                @endforeach

                @unless ($locked)
                    <section class="card" aria-labelledby="bce-add-title">
                        <h2 class="bce-section-title" id="bce-add-title">Add a block</h2>
                        <div class="bce-add">
                            @foreach (Blocks::TYPES as $key => $t)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="addBlock('{{ $key }}')"><i data-lucide="{{ $t['icon'] }}"></i> {{ $t['label'] }}</button>
                            @endforeach
                        </div>
                    </section>
                @endunless
            </div>

            <aside class="bce-preview" aria-labelledby="bce-preview-title">
                <h2 class="bce-section-title m-0" id="bce-preview-title">Preview, {{ $langName[$tab] }}</h2>
                {{-- Drawn once, then kept up to date by the 'broadcast-preview' event (editor.js), so it never flickers or loses its scroll --}}
                <div wire:ignore x-data="campaignPreview()" data-html="{{ $previewHtml }}" @broadcast-preview.window="show($event.detail.html)" class="flex flex-col gap-3">
                    <div class="segmented" role="group" aria-label="Screen">
                        <button type="button" class="segmented-item" aria-pressed="true" :aria-pressed="(device === 'desktop').toString()" @click="device = 'desktop'"><i data-lucide="monitor"></i> Computer</button>
                        <button type="button" class="segmented-item" aria-pressed="false" :aria-pressed="(device === 'phone').toString()" @click="device = 'phone'"><i data-lucide="smartphone"></i> Phone</button>
                    </div>
                    <div class="bce-frame-wrap" x-ref="wrap">
                        <iframe x-ref="frame" title="The email as one person would see it" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" :style="frameStyle()"></iframe>
                    </div>
                </div>
                <p class="help m-0">Shown for Ada Okafor at Acme Ltd (demo). Links here are not counted.</p>
            </aside>
        </div>

    @elseif ($step === 'audience')
        <div class="bce-main">
            <section class="card" aria-labelledby="bce-who">
                <div class="card-header">
                    <h2 class="card-title" id="bce-who">Who gets it</h2>
                    @can('broadcasts.create')
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="newSegment"><i data-lucide="plus"></i> New segment</button>
                    @endcan
                </div>
                @if ($segments->isEmpty())
                    <div class="empty-state">
                        <p>No segments yet. A segment is a saved audience, such as “Clients in Cameroon” or “Banking leads”, that you can send to again.</p>
                        @can('broadcasts.create')
                            <button type="button" class="btn btn-primary mt-3" wire:click="newSegment"><i data-lucide="plus"></i> Create a segment</button>
                        @endcan
                    </div>
                @else
                    <div class="bce-segments" role="radiogroup" aria-labelledby="bce-who">
                        @foreach ($segments as $s)
                            <div class="bce-segment" role="radio" tabindex="0" aria-checked="{{ $segmentId === $s->id ? 'true' : 'false' }}" wire:key="seg-{{ $s->id }}"
                                 @unless ($locked) wire:click="useSegment('{{ $s->id }}')" @keydown.enter.prevent="$wire.useSegment('{{ $s->id }}')" @keydown.space.prevent="$wire.useSegment('{{ $s->id }}')" @endunless>
                                <div class="bce-grow">
                                    <strong>{{ $s->name }}</strong>
                                    <small>{{ $s->describe() }}</small>
                                </div>
                                @can('broadcasts.create')
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click.stop="editSegment('{{ $s->id }}')">Edit</button>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($segment && $summary)
                <section class="card" aria-labelledby="bce-count">
                    <h2 class="card-title mb-4" id="bce-count">{{ $segment->name }}: {{ $summary['total'] }} {{ $summary['total'] === 1 ? 'person' : 'people' }} today</h2>
                    <div class="bce-summary">
                        @foreach ($kinds as $k => $label)
                            @if (($summary['by_kind'][$k] ?? 0) > 0)
                                <div><div class="stat-label">{{ $label }}s</div><div class="stat-value">{{ $summary['by_kind'][$k] }}</div></div>
                            @endif
                        @endforeach
                        <div><div class="stat-label">In English</div><div class="stat-value">{{ $summary['by_language']['en'] }}</div></div>
                        <div><div class="stat-label">In French</div><div class="stat-value">{{ $summary['by_language']['fr'] }}</div></div>
                    </div>
                    <p class="help mt-4 mb-0">
                        @if ($summary['left_out'] > 0) {{ $summary['left_out'] }} {{ $summary['left_out'] === 1 ? 'address is' : 'addresses are' }} left out because {{ $summary['left_out'] === 1 ? 'it is' : 'they are' }} on the do-not-email list. @endif
                        The count is worked out again when sending starts, so it can change by then.
                    </p>
                    @if ($language !== 'both' && $summary['by_language'][$language === 'en' ? 'fr' : 'en'] > 0)
                        <div class="alert alert-warning mt-4" role="status"><i data-lucide="triangle-alert"></i>
                            <span>{{ $summary['by_language'][$language === 'en' ? 'fr' : 'en'] }} of them read {{ $language === 'en' ? 'French' : 'English' }}, but this broadcast is {{ mb_strtolower(Broadcast::LANGUAGES[$language]) }}: they will get the {{ $langName[$language] }} version.</span></div>
                    @endif
                </section>

                <section class="card" aria-labelledby="bce-people">
                    <h2 class="card-title mb-4" id="bce-people">Who is in it{{ $summary['total'] > 25 ? ' (the first 25)' : '' }}</h2>
                    @if ($recipients->isEmpty())
                        <p class="empty-state">Nobody matches this segment today. Edit it to widen who is in it.</p>
                    @else
                        <div class="table-wrap">
                            <table class="table">
                                <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Organisation</th><th scope="col">Group</th><th scope="col">Language</th></tr></thead>
                                <tbody>
                                    @foreach ($recipients as $r)
                                        <tr wire:key="r-{{ $r['email'] }}">
                                            <td>{{ trim($r['first_name'] . ' ' . $r['last_name']) ?: '—' }}</td>
                                            <td>{{ $r['email'] }}</td>
                                            <td>{{ $r['company'] ?: '—' }}</td>
                                            <td>{{ $kinds[$r['kind']] }}</td>
                                            <td>{{ $langName[$broadcast->localeFor($r['locale'])] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @elseif ($segments->isNotEmpty())
                <p class="help">Choose a segment above to see who is in it.</p>
            @endif
        </div>

    @else
        <div class="bce-main">
            <section class="card" aria-labelledby="bce-checks">
                <h2 class="card-title mb-4" id="bce-checks">Checks</h2>
                @if ($problems)
                    <div class="alert alert-warning" role="status"><i data-lucide="triangle-alert"></i>
                        <div><p class="m-0 mb-2">Before it can go out:</p><ul class="bce-problems">@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul></div>
                    </div>
                @else
                    <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>Ready to send{{ $summary ? ': ' . $summary['total'] . ' ' . ($summary['total'] === 1 ? 'person' : 'people') . ' in “' . $segment->name . '”' : '' }}.</span></div>
                @endif
            </section>

            <div class="bce-send">
                @can('broadcasts.create')
                    <section class="card" aria-labelledby="bce-test">
                        <h2 class="card-title" id="bce-test">Send a test</h2>
                        <p class="help m-0">{{ count($locales) > 1 ? 'Both versions go' : 'The email goes' }} to {{ auth()->user()->email }}, marked “Test”. Nothing is counted and its unsubscribe link does nothing.</p>
                        <button type="button" class="btn btn-secondary" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest"><i data-lucide="send"></i> Send a test to me</button>
                    </section>
                @endcan

                @if ($canSend && $broadcast->status === 'Draft')
                    <section class="card" aria-labelledby="bce-now">
                        <h2 class="card-title" id="bce-now">Send now</h2>
                        <p class="help m-0">The server sends 30 emails every 5 minutes{{ $summary && $summary['total'] > 30 ? ': about ' . (int) ceil($summary['total'] / 30) * 5 . ' minutes for everyone' : '' }}.</p>
                        <button type="button" class="btn btn-primary" wire:click="sendNow" @disabled($problems)
                                wire:confirm="Send “{{ $broadcast->title() }}” to {{ $summary['total'] ?? 0 }} {{ ($summary['total'] ?? 0) === 1 ? 'person' : 'people' }} now? This can’t be undone.">
                            <i data-lucide="send"></i> Send now
                        </button>
                    </section>
                    <section class="card" aria-labelledby="bce-later">
                        <h2 class="card-title" id="bce-later">Schedule</h2>
                        <div class="bce-row">
                            <div class="field"><label class="label" for="bce-date">Day</label>
                                <input id="bce-date" type="date" class="input" wire:model="scheduleDate" min="{{ now('Africa/Lagos')->format('Y-m-d') }}"></div>
                            <div class="field"><label class="label" for="bce-time">Time (WAT)</label>
                                <input id="bce-time" type="time" class="input" wire:model="scheduleTime" step="300"></div>
                        </div>
                        @error('scheduleDate') <span class="field-error">{{ $message }}</span> @enderror
                        @error('scheduleTime') <span class="field-error">{{ $message }}</span> @enderror
                        <p class="help m-0">Sending starts within 5 minutes of that time. Weekday mornings read best.</p>
                        <button type="button" class="btn btn-secondary" wire:click="schedule" @disabled($problems)><i data-lucide="calendar-clock"></i> Schedule</button>
                    </section>
                @elseif (! $canSend)
                    <section class="card">
                        <h2 class="card-title">Sending</h2>
                        <p class="help m-0">Sending to clients needs the “Approve broadcasts” permission. Ask someone who has it to send this draft.</p>
                    </section>
                @endif
            </div>
        </div>
    @endif

    @include('livewire.hq.campaigns.partials.segment-builder', ['onSave' => 'saveSegmentAndUse'])
</div>
