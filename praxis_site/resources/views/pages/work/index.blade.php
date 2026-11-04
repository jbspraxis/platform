@extends('layouts.site')

@section('page_title', __('work.index.title'))
@section('description', __('work.index.description'))

@section('full')
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('work.index.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('work.index.h1') }}</h1>
            <p class="lead">{{ __('work.index.lead') }}</p>
        </div>
    </section>

    {{-- The platforms we run (redesign, 4 Nov 2026): each client system's sign-in screen, linking to its case study --}}
    @php($screens = array_values(array_filter(array_keys(\App\Support\Work::CASES), fn ($slug) => is_file(public_path('img/screens/' . $slug . '-800.webp')))))
    @if ($screens)
        <section class="band" aria-labelledby="platforms-title">
            <div class="container">
                <x-section-head id="platforms-title" :title="__('work.screen.platforms_title')" :lead="__('work.screen.platforms_lead')" rule />
                <ul class="platforms">
                    @foreach ($screens as $slug)
                        <li class="platform">
                            <a href="{{ route('work.show', ['slug' => $slug]) }}">
                                <span class="platform-frame"><x-screen :slug="$slug" :alt="__('work.screen.alt', ['name' => __('work.cases.' . $slug . '.name')])" sizes="(min-width: 1000px) 360px, (min-width: 640px) 45vw, 100vw" /></span>
                                <span class="platform-name">{{ __('work.cases.' . $slug . '.name') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="band band-paper" aria-labelledby="clients-title">
        <div class="container">
            <h2 class="band-title" id="clients-title">{{ __('work.index.clients_title') }}</h2>
            @include('partials.work-register', ['cases' => $clients])
        </div>
    </section>

    <section class="band" id="products" aria-labelledby="products-title">
        <div class="container">
            <h2 class="band-title" id="products-title">{{ __('work.index.products_title') }}</h2>
            <p class="band-text">{{ __('work.index.products_text') }}</p>
            @include('partials.work-register', ['cases' => $products])
            <p class="band-link"><a href="{{ route('company') }}#products">{{ __('work.index.products_more') }}</a></p>
        </div>
    </section>

    {{-- Work described by sector only (App\Support\Work::OTHER): no page of its own, an anchor /industries links to --}}
    <section class="band band-paper" id="other" aria-labelledby="other-title">
        <div class="container">
            <h2 class="band-title" id="other-title">{{ __('work.index.other_title') }}</h2>
            <p class="band-text">{{ __('work.index.other_text') }}</p>
            <ul class="domains domains-pairs">
                @foreach (\App\Support\Work::OTHER as $key => $entry)
                    <li class="domain" id="{{ $key }}">
                        <p class="eyebrow"><a href="{{ route('industries') }}#{{ $entry['sector'] }}">{{ __('work.sectors.' . $entry['sector'] . '.name') }}</a></p>
                        <h3>{{ __('work.other.' . $key . '.title') }}</h3>
                        <p>{{ __('work.other.' . $key . '.text') }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="band" aria-labelledby="industries-title">
        <div class="container split">
            <h2 class="band-title" id="industries-title">{{ __('work.index.industries_title') }}</h2>
            <div class="stack-sm">
                <p class="band-text">{{ __('work.index.industries_text') }}</p>
                <p><a class="btn btn-secondary" href="{{ route('industries') }}">{{ __('work.index.industries_link') }}</a></p>
            </div>
        </div>
    </section>

    @include('partials.final-cta', ['title' => __('work.final_title'), 'text' => __('work.final_text')])
@endsection
