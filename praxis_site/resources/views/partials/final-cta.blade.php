{{-- The closing Ink band: booking, then phone and WhatsApp from Website settings (hidden when empty). --}}
<section class="band band-ink" aria-labelledby="final-title">
    <div class="container final">
        <h2 class="band-title" id="final-title">{{ $title }}</h2>
        <p class="band-text">{{ $text }}</p>
        <div class="actions">
            <a class="btn btn-on-dark btn-lg" href="{{ route('schedule') }}">{{ __('site.cta') }}</a>
            @if ($site->phones || $site->whatsapp)
                <p class="final-contact">
                    {{ __('pages.home.or_call') }}
                    @foreach ($site->phones as $phone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone['number']) }}"><i data-lucide="phone"></i>{{ $phone['number'] }}</a>
                    @endforeach
                    @foreach ($site->whatsapp as $number)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $number['number']) }}" rel="noopener"><i data-lucide="message-circle"></i>WhatsApp</a>
                    @endforeach
                </p>
            @endif
        </div>
    </div>
</section>
