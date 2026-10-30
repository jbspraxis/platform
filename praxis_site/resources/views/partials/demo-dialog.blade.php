{{-- The narrated 3-minute demo. app.js opens this dialog from [data-demo-open] and only then adds YouTube's player
     (youtube-nocookie.com, captions on, in the page's language); closing it removes the player, so nothing from
     YouTube loads before the visitor asks to watch. --}}
<dialog class="demo-dialog" data-demo-dialog aria-labelledby="demo-title" data-lang="{{ app()->getLocale() }}">
    <div class="demo-dialog-head">
        <h2 id="demo-title">{{ __('pages.home.demo_title') }}</h2>
        <button class="btn btn-ghost btn-sm" type="button" data-demo-close aria-label="{{ __('pages.home.demo_close') }}"><i data-lucide="x"></i></button>
    </div>
    <div class="demo-frame" data-demo-frame></div>
    <p class="demo-note">{{ __('pages.home.demo_note') }}</p>
</dialog>
