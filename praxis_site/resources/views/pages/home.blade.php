@extends('layouts.site')

@section('page_title', __('pages.home.title'))
@section('description', __('pages.home.description'))

@php
    // The narrated 3-minute demo on YouTube, in the page's language (config site.demo_video)
    $demoId = (string) (config('site.demo_video.' . app()->getLocale()) ?: config('site.demo_video.en'));
@endphp

@push('head')
    {{-- The poster is the largest thing on the first screen (LCP): fetched first --}}
    <link rel="preload" as="image" fetchpriority="high" href="{{ asset('media/hero-60s-poster-1280.webp') }}">
@endpush

@section('full')
    {{-- lucide: landmark users truck shield-check message-square-text pause play circle-play arrow-down calculator phone message-circle x --}}
    <section class="hero" aria-labelledby="hero-title">
        <div class="container hero-inner">
            <div class="hero-text">
                <h1 class="display" id="hero-title">{{ __('pages.home.h1') }}</h1>
                <p class="hero-lead">{{ __('pages.home.lead') }}</p>
                <div class="actions">
                    <a class="btn btn-primary btn-lg" href="{{ route('schedule') }}">{{ __('site.cta') }}</a>
                    @if ($demoId !== '')
                        {{-- Without JavaScript this opens the video on YouTube; with it, a player on the page (partials.demo-dialog) --}}
                        <a class="btn btn-ghost" href="https://www.youtube.com/watch?v={{ $demoId }}" rel="noopener" data-demo-open="{{ $demoId }}">
                            <i data-lucide="circle-play"></i>{{ __('pages.home.watch_demo') }}
                        </a>
                    @else
                        <a class="btn btn-ghost" href="#answers"><i data-lucide="arrow-down"></i>{{ __('pages.home.see_answers') }}</a>
                    @endif
                </div>
            </div>
            {{-- The 60-second demo, silent, captions in the picture (demo/hero-60s-loop.mp4). Muted, no autoplay attribute, preload none:
                 app.js plays it only on wide screens with a fast connection and no reduced motion; the button plays or pauses it (WCAG 2.2.2) --}}
            <figure class="screen" id="answers">
                <video class="screen-video" data-loop muted loop playsinline preload="none" width="1920" height="1080"
                       poster="{{ asset('media/hero-60s-poster-1280.webp') }}" aria-label="{{ __('pages.home.video_label') }}">
                    <source src="{{ asset('media/hero-60s-loop.mp4') }}" type="video/mp4">
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
            <x-section-head id="build-title" :title="__('pages.home.build_title')" rule />
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

    {{-- Who we work with: each name opens its case study (App\Support\Work, lang work.cases.*.list_name) --}}
    <section class="band band-paper" id="work" aria-labelledby="work-title">
        <div class="container split">
            <h2 class="band-title" id="work-title">{{ __('pages.home.work_title') }}</h2>
            <div>
                <ul class="names">
                    @foreach (array_keys(\App\Support\Work::CASES) as $slug)
                        <li><a href="{{ route('work.show', ['slug' => $slug]) }}">{{ __('work.cases.' . $slug . '.list_name') }}</a></li>
                    @endforeach
                </ul>
                <p class="band-link"><a href="{{ route('work') }}">{{ __('work.home_link') }}</a></p>
            </div>
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

    @if ($demoId !== '')
        @include('partials.demo-dialog')
    @endif
@endsection
