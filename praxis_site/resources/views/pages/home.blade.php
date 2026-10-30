@extends('layouts.site')

@section('page_title', __('pages.home.title'))
@section('description', __('pages.home.description'))

@push('head')
    <link rel="preload" as="image" href="{{ asset('media/ask-your-company-anything-poster.jpg') }}">
@endpush

@section('full')
    {{-- lucide: landmark users truck shield-check message-square-text pause play arrow-down calculator phone message-circle --}}
    <section class="hero" aria-labelledby="hero-title">
        <div class="container hero-inner">
            <div class="hero-text">
                <h1 class="display" id="hero-title">{{ __('pages.home.h1') }}</h1>
                <p class="hero-lead">{{ __('pages.home.lead') }}</p>
                <div class="actions">
                    <a class="btn btn-primary btn-lg" href="{{ route('schedule') }}">{{ __('site.cta') }}</a>
                    <a class="btn btn-ghost" href="#answers"><i data-lucide="arrow-down"></i>{{ __('pages.home.see_answers') }}</a>
                </div>
            </div>
            {{-- The 20-second interface loop (brand/kit/social/posts; it draws its own window). Muted, no autoplay attribute: app.js plays it
                 unless the visitor asked for reduced motion, and the button pauses it (WCAG 2.2.2) --}}
            <figure class="screen" id="answers">
                <video class="screen-video" data-loop muted loop playsinline preload="metadata" width="1920" height="1080"
                       poster="{{ asset('media/ask-your-company-anything-poster.jpg') }}" aria-label="{{ __('pages.home.video_label') }}">
                    <source src="{{ asset('media/ask-your-company-anything-16x9.mp4') }}" type="video/mp4">
                </video>
                <button class="screen-toggle" type="button" data-loop-toggle hidden
                        data-pause="{{ __('pages.home.pause') }}" data-play="{{ __('pages.home.play') }}" aria-label="{{ __('pages.home.pause') }}">
                    <i data-lucide="pause"></i>
                </button>
            </figure>
        </div>
    </section>

    <section class="band" aria-labelledby="problem-title">
        <div class="container split">
            <h2 class="band-title" id="problem-title">{{ __('pages.home.problem_title') }}</h2>
            <p class="band-text">{{ __('pages.home.problem_text') }}</p>
        </div>
    </section>

    <section class="band band-paper" aria-labelledby="build-title">
        <div class="container">
            <hr class="praxis-rule rule-short">
            <h2 class="band-title" id="build-title">{{ __('pages.home.build_title') }}</h2>
            <ul class="domains">
                @foreach (__('pages.domains') as $domain)
                    <li class="domain">
                        <i data-lucide="{{ $domain['icon'] }}"></i>
                        <h3>{{ $domain['name'] }}</h3>
                        <p>{{ $domain['line'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="band" aria-labelledby="method-title">
        <div class="container">
            <h2 class="band-title" id="method-title">{{ __('pages.home.method_title') }}</h2>
            @include('partials.method', ['steps' => __('pages.method')])
            <p class="band-link"><a href="{{ route('how-we-work') }}#method">{{ __('pages.home.method_link') }}</a></p>
        </div>
    </section>

    <section class="band band-paper" id="work" aria-labelledby="work-title">
        <div class="container split">
            <h2 class="band-title" id="work-title">{{ __('pages.home.work_title') }}</h2>
            <ul class="names">
                @foreach (__('pages.home.work') as $name)
                    <li>{{ $name }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="band" aria-labelledby="calculator-title">
        <div class="container">
            <div class="feature">
                <div>
                    <h2 class="band-title" id="calculator-title">{{ __('pages.home.calculator_title') }}</h2>
                    <p class="band-text">{{ __('pages.home.calculator_text') }}</p>
                </div>
                <a class="btn btn-secondary btn-lg" href="{{ route('calculator') }}"><i data-lucide="calculator"></i>{{ __('pages.home.calculator_cta') }}</a>
            </div>
        </div>
    </section>

    @include('partials.final-cta', ['title' => __('pages.home.final_title'), 'text' => __('pages.home.final_text')])
@endsection
