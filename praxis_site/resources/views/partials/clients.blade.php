{{-- lucide: pause play --}}
{{-- "Organisations we have worked with" (redesign PR 3): the owner's logos (config site.clients), muted to one tone,
     drifting slowly left to right. No names are shown; each logo's name is its accessible name. The list is drawn twice
     so the drift loops without a gap; the second copy is hidden from screen readers. It stops on hover and focus,
     the button pauses it (WCAG 2.2.2), and with reduced motion it is a still, wrapped row. --}}
{{-- The list comes from the client logo library in HQ (docs/COMPANY_PROFILE.md section 4), so a logo added
     there and ticked for the website appears here without a code change. When HQ has never answered on this
     server, App\Support\Clients falls back to the logos shipped with the site, and the strip is unchanged. --}}
@php($clients = \App\Support\Clients::strip())
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
                            <li><img src="{{ $client['url'] }}" alt="{{ $copy ? '' : $client['name'] }}" height="48" loading="lazy" decoding="async"></li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
    </section>
@endif
