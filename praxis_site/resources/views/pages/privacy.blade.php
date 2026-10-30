@extends('layouts.site')

@section('page_title', __('pages.privacy.title'))
@section('description', __('pages.privacy.description'))

@section('full')
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('pages.privacy.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('pages.privacy.h1') }}</h1>
            <p class="lead">{{ __('pages.privacy.updated') }}</p>
        </div>
    </section>

    <section class="band band-paper">
        <div class="container">
            <div class="prose policy">
                @foreach (__('pages.privacy.sections') as $id => $section)
                    <h2 id="{{ str_replace('_', '-', $id) }}">{{ $loop->iteration }}. {{ $section['title'] }}</h2>
                    @foreach ($section['body'] as $paragraph)
                        <p>{{ strtr($paragraph, [':company' => $site->legalName ?? $site->companyName, ':email' => $site->email]) }}</p>
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>
@endsection
