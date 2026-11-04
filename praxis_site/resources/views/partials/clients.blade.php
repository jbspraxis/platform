{{-- lucide: pause play --}}
{{-- "Organisations we have worked with" (redesign PR 3): the owner's logos (config site.clients), muted to one tone,
     drifting slowly left to right. No names are shown; each logo's name is its accessible name. The list is drawn twice
     so the drift loops without a gap; the second copy is hidden from screen readers. It stops on hover and focus,
     the button pauses it (WCAG 2.2.2), and with reduced motion it is a still, wrapped row. --}}
{{-- A logo listed before its file is added is simply left out (public/img/clients/{file}.webp) --}}
@php($clients = array_values(array_filter((array) config('site.clients', []), fn ($client) => is_file(public_path('img/clients/' . $client['file'] . '.webp')))))
@if ($clients)
    <section class="clients" aria-labelledby="clients-title" data-clients>
        <div class="container clients-head">
            <h2 class="clients-title" id="clients-title">{{ __('site.proof.clients_title') }}</h2>
            <button class="clients-toggle" type="button" data-clients-toggle hidden
                    data-pause="{{ __('site.proof.clients_pause') }}" data-play="{{ __('site.proof.clients_play') }}" aria-label="{{ __('site.proof.clients_pause') }}">
                <i data-lucide="pause"></i>
            </button>
        </div>
        <div class="clients-track">
            <div class="clients-reel">
                @foreach ([false, true] as $copy)
                    <ul class="clients-list" @if ($copy) aria-hidden="true" @endif>
                        @foreach ($clients as $client)
                            <li><img src="{{ asset('img/clients/' . $client['file'] . '.webp') }}" alt="{{ $copy ? '' : $client['name'] }}" height="48" loading="lazy" decoding="async"></li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
    </section>
@endif
