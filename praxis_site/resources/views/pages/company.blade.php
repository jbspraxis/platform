@extends('layouts.site')

@section('page_title', __('pages.company.title'))
@section('description', __('pages.company.description'))

@section('full')
    {{-- lucide: shield-check briefcase external-link --}}
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('pages.company.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('pages.company.h1') }}</h1>
            <p class="lead">{{ __('pages.company.lead') }}</p>
        </div>
    </section>

    {{-- Facts from Website settings (HQ → Marketing → Website settings) --}}
    <section class="band band-paper" aria-labelledby="facts-title">
        <div class="container split">
            <h2 class="band-title" id="facts-title">{{ __('pages.company.facts_title') }}</h2>
            <dl class="facts facts-wide">
                @if ($site->legalName)
                    <div><dt>{{ __('pages.company.legal_name') }}</dt><dd>{{ $site->legalName }}</dd></div>
                @endif
                @if ($site->offices)
                    <div><dt>{{ __('pages.company.offices') }}</dt><dd>
                        @foreach ($site->offices as $office)
                            {{ $office['city'] . ($office['country'] !== '' ? ', ' . $office['country'] : '') }}
                            @if (! $loop->last)<br>@endif
                        @endforeach
                    </dd></div>
                @endif
                @foreach ($site->registrations as $registration)
                    <div><dt>{{ __('pages.company.registration') }}@if ($registration['country'] !== '') ({{ $registration['country'] }})@endif</dt>
                        <dd>{{ $registration['label'] }}: {{ $registration['number'] ?? __('site.footer.registration_pending') }}</dd></div>
                @endforeach
                <div><dt>{{ __('pages.company.contact') }}</dt><dd><a href="mailto:{{ $site->email }}">{{ $site->email }}</a></dd></div>
                @if ($site->social)
                    <div><dt>{{ __('pages.company.follow') }}</dt><dd>
                        @foreach ($site->social as $link)
                            <a href="{{ $link['url'] }}" rel="noopener">{{ $link['label'] }}</a>@if (! $loop->last) · @endif
                        @endforeach
                    </dd></div>
                @endif
            </dl>
        </div>
    </section>

    <section class="band" aria-labelledby="ourselves-title">
        <div class="container split">
            <h2 class="band-title" id="ourselves-title">{{ __('pages.company.ourselves_title') }}</h2>
            <p class="band-text">{{ __('pages.company.ourselves_text') }}</p>
        </div>
    </section>

    <section class="band band-paper" aria-labelledby="products-title">
        <div class="container split">
            <h2 class="band-title" id="products-title">{{ __('pages.company.products_title') }}</h2>
            <p class="band-text">{{ __('pages.company.nfelia') }}
                <a href="{{ config('site.nfelia_url') }}" rel="noopener">nfelia.com</a></p>
        </div>
    </section>

    <section class="band" id="security" aria-labelledby="security-title">
        <div class="container split">
            <h2 class="band-title" id="security-title">{{ __('pages.company.security_title') }}</h2>
            <div class="stack-sm">
                <p class="band-text">{{ __('pages.company.security_text') }}</p>
                <p class="actions">
                    <a class="btn btn-secondary" href="{{ route('security') }}"><i data-lucide="shield-check"></i>{{ __('pages.company.security_link') }}</a>
                    <a class="btn btn-ghost" href="{{ route('careers') }}"><i data-lucide="briefcase"></i>{{ __('pages.company.careers') }}</a>
                </p>
            </div>
        </div>
    </section>

    @include('partials.final-cta', ['title' => __('pages.home.final_title'), 'text' => __('pages.home.final_text')])
@endsection
