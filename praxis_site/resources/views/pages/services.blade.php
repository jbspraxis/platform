@extends('layouts.site')

@section('page_title', __('pages.services.title'))
@section('description', __('pages.services.description'))

@section('full')
    {{-- lucide: landmark users truck shield-check message-square-text check file-text --}}
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('pages.services.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('pages.services.h1') }}</h1>
            <p class="lead">{{ __('pages.services.lead') }}</p>
        </div>
    </section>

    <section class="band band-paper" aria-labelledby="domains-title">
        <div class="container">
            <h2 class="band-title" id="domains-title">{{ __('pages.services.domains_title') }}</h2>
            <ul class="domains domains-detailed">
                @foreach (__('pages.domains') as $domain)
                    <li class="domain">
                        <i data-lucide="{{ $domain['icon'] }}"></i>
                        <h3>{{ $domain['name'] }}</h3>
                        <p>{{ $domain['line'] }}</p>
                        <ul class="ticks">
                            @foreach ($domain['examples'] as $example)
                                <li>{{ $example }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="band" id="how-we-work" aria-labelledby="method-title">
        <div class="container">
            <h2 class="band-title" id="method-title">{{ __('pages.services.method_title') }}</h2>
            @include('partials.method', ['steps' => __('pages.method')])
            <div class="expect">
                <h3>{{ __('pages.services.expect_title') }}</h3>
                <ul class="ticks ticks-row">
                    @foreach (__('pages.services.expect') as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <p class="band-link"><a href="{{ route('how-we-work') }}#method">{{ __('pages.home.method_link') }}</a></p>
        </div>
    </section>

    <section class="band band-paper" id="guide" aria-labelledby="guide-title">
        <div class="container narrow">
            @include('partials.guide-form', ['title' => __('pages.services.guide_title'), 'text' => __('pages.services.guide_text'), 'send' => __('pages.services.guide_send')])
        </div>
    </section>

    @include('partials.final-cta', ['title' => __('pages.home.final_title'), 'text' => __('pages.home.final_text')])
@endsection
