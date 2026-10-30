{{-- Cloudflare Turnstile, only when TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are set (App\Support\Turnstile).
     Cloudflare's script (loaded by the layout) fills the hidden "cf-turnstile-response" field inside the form. --}}
@if (\App\Support\Turnstile::enabled())
    <div class="turnstile">
        <div class="cf-turnstile" data-sitekey="{{ \App\Support\Turnstile::siteKey() }}" data-language="{{ app()->getLocale() }}" data-size="flexible" data-theme="light"></div>
        <p class="help">{{ __('site.form.challenge_hint') }}</p>
    </div>
@endif
