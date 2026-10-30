@extends('layouts.site')

@section('page_title', __('pages-extra.how.title'))
@section('description', __('pages-extra.how.description'))

@section('full')
    {{-- lucide: check calendar-days file-text --}}
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('pages-extra.how.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('pages-extra.how.h1') }}</h1>
            <p class="lead">{{ __('pages-extra.how.lead') }}</p>
            <p class="formerly">{{ __('pages-extra.how.formerly') }}</p>
        </div>
    </section>

    <section class="band band-paper" id="method" aria-labelledby="stages-title">
        <div class="container">
            <h2 class="sr-only" id="stages-title">{{ __('pages-extra.how.eyebrow') }}</h2>
            @include('partials.method', ['steps' => __('pages-extra.how.stages')])
        </div>
    </section>

    <section class="band" aria-labelledby="expect-title">
        <div class="container split">
            <h2 class="band-title" id="expect-title">{{ __('pages-extra.how.expect_title') }}</h2>
            <ul class="ticks ticks-large">
                @foreach (__('pages-extra.how.expect') as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    @include('partials.final-cta', ['title' => __('pages-extra.how.audit_title'), 'text' => __('pages-extra.how.audit_text')])

    <section class="band band-paper" id="guide" aria-labelledby="guide-title">
        <div class="container narrow">
            @include('partials.guide-form', ['title' => __('pages-extra.how.guide_title'), 'text' => null, 'send' => __('pages.services.guide_send')])
        </div>
    </section>
@endsection
