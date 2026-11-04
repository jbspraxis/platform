{{-- The closing Ink band: one call to action. Phone numbers live in the footer, under each regional desk (owner, 31 Oct 2026). --}}
<section class="band band-ink" aria-labelledby="final-title">
    <div class="container final">
        <h2 class="band-title" id="final-title">{{ $title }}</h2>
        <p class="band-text">{{ $text }}</p>
        <div class="actions">
            <a class="btn btn-on-dark btn-lg" href="{{ route('schedule') }}">{{ __('site.cta') }}</a>
        </div>
        <p class="final-note">{{ __('site.cta_note') }}</p>
    </div>
</section>
