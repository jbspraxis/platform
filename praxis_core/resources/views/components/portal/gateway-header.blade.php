@props(['kicker' => null, 'width' => 1040])
{{-- Header of the pages a client opens from an email link (proposal, sign-off, document check, project tracker):
     the logo, what the page is, and the English | Français switch. The switch calls switchLanguage() on the
     page's Livewire component (App\Livewire\Portal\Concerns\WithGatewayLanguage), which reloads the page. --}}
@php $current = app()->getLocale() === 'fr' ? 'fr' : 'en'; @endphp
{{-- Sent once per page (@assets): @once starts again on every Livewire update, which re-sent it each time --}}
@assets
<style>
    .gateway-header { background: var(--color-paper); border-bottom: 1px solid var(--color-line); }
    .gateway-header-inner { max-width: 1040px; margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .gateway-brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .gateway-logo { display: block; height: 28px; width: auto; flex-shrink: 0; }
    .gateway-kicker { padding-left: 12px; border-left: 1px solid var(--color-line); color: var(--color-muted); font-size: 0.875rem; line-height: 1.25rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gateway-lang { display: flex; align-items: center; gap: 4px; flex-shrink: 0; font-size: 0.875rem; line-height: 1.25rem; color: var(--color-muted); }
    .gateway-lang button { padding: 6px 6px; min-height: 32px; border: 0; background: none; border-radius: var(--radius-control); color: var(--color-deep-teal); font: inherit; font-weight: 500; cursor: pointer; }
    .gateway-lang button:hover { text-decoration: underline; }
    .gateway-lang button.is-active { color: var(--color-ink); font-weight: 600; cursor: default; text-decoration: none; }
    @media (min-width: 768px) { .gateway-header-inner { padding: 16px 32px; } }
    @media (max-width: 480px) { .gateway-kicker { display: none; } .gateway-logo { height: 24px; } }
    @media print { .gateway-header { display: none !important; } }
</style>
@endassets
<header class="gateway-header">
    <div class="gateway-header-inner" style="max-width: {{ (int) $width }}px;">
        <div class="gateway-brand">
            <img class="gateway-logo" src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" width="147" height="28">
            @if (filled($kicker))
                <span class="gateway-kicker">{{ $kicker }}</span>
            @endif
        </div>
        <nav class="gateway-lang" aria-label="{{ __('gateway.language.label') }}">
            <button type="button" wire:click="switchLanguage('en')" lang="en" @class(['is-active' => $current === 'en']) @if ($current === 'en') aria-current="true" @endif>English</button>
            <span aria-hidden="true">|</span>
            <button type="button" wire:click="switchLanguage('fr')" lang="fr" @class(['is-active' => $current === 'fr']) @if ($current === 'fr') aria-current="true" @endif>Français</button>
        </nav>
    </div>
</header>
