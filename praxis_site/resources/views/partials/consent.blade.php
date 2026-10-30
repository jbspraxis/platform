{{-- Cookie choice (only when GA4_ID is set). Nothing from Google loads before "Accept"; resources/js/app.js keeps the
     choice in the jbs_consent cookie and the footer's "Cookie settings" reopens this. Hidden until the script shows it. --}}
<section class="consent" data-consent data-ga="{{ $ga4 }}" aria-labelledby="consent-title" hidden>
    <h2 id="consent-title" class="sr-only">{{ __('site.consent.label') }}</h2>
    <p>{{ __('site.consent.text') }} <a href="{{ route('privacy') }}#cookies">{{ __('site.consent.more') }}</a></p>
    <div class="consent-actions">
        <button class="btn btn-primary btn-sm" type="button" data-consent-accept>{{ __('site.consent.accept') }}</button>
        <button class="btn btn-secondary btn-sm" type="button" data-consent-reject>{{ __('site.consent.reject') }}</button>
    </div>
</section>
