@extends('layouts.site')

@section('page_title', __('pages-extra.security.title'))
@section('description', __('pages-extra.security.description'))

@section('full')
    {{-- lucide: key-round scroll-text split lock shield-check user-round-check mail --}}
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('pages-extra.security.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('pages-extra.security.h1') }}</h1>
            <p class="lead">{{ __('pages-extra.security.lead') }}</p>
        </div>
    </section>

    <section class="band band-paper" aria-label="{{ __('pages-extra.security.eyebrow') }}">
        <div class="container">
            <ul class="domains domains-three">
                @foreach (__('pages-extra.security.blocks') as $block)
                    <li class="domain">
                        <i data-lucide="{{ $block['icon'] }}"></i>
                        <h2 class="domain-title">{{ $block['title'] }}</h2>
                        <p>{{ $block['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="band" aria-labelledby="questions-title">
        <div class="container split">
            <h2 class="band-title" id="questions-title">{{ __('pages-extra.security.contact_title') }}</h2>
            <div class="stack-sm">
                <p class="band-text">{{ __('pages-extra.security.contact_text') }}</p>
                <p><a class="btn btn-secondary" href="mailto:{{ $site->email }}"><i data-lucide="mail"></i>{{ $site->email }}</a></p>
                <p><a href="{{ route('privacy') }}">{{ __('pages-extra.security.privacy_link') }}</a></p>
            </div>
        </div>
    </section>
@endsection
