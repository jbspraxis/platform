@extends('layouts.site')

@section('page_title', __('work.industries.title'))
@section('description', __('work.industries.description'))

@use('App\Support\Work')
@php($domains = __('pages.domains'))

@section('full')
    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('work.industries.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('work.industries.h1') }}</h1>
            <p class="lead">{{ __('work.industries.lead') }}</p>
        </div>
    </section>

    {{-- The page's one picture: each sector against the five domains, marked only where the work covers it --}}
    <section class="band band-paper" aria-labelledby="coverage-title">
        <div class="container">
            <h2 class="band-title" id="coverage-title">{{ __('work.industries.coverage_title') }}</h2>
            <p class="band-text">{{ __('work.industries.coverage_text') }}</p>
            <div class="coverage-scroll" role="region" aria-labelledby="coverage-title" tabindex="0">
                <table class="coverage">
                    <caption class="sr-only">{{ __('work.industries.coverage_caption') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('work.labels.sector') }}</th>
                            @foreach (Work::DOMAINS as $domain)
                                <th scope="col">{{ $domains[Work::domainIndex($domain)]['name'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sectors as $sector)
                            @php($covered = Work::coverage($sector))
                            <tr>
                                <th scope="row"><a href="#{{ $sector }}">{{ __('work.sectors.' . $sector . '.name') }}</a></th>
                                @foreach (Work::DOMAINS as $domain)
                                    @if (in_array($domain, $covered, true))
                                        <td><span class="mark" aria-hidden="true"></span><span class="sr-only">{{ __('work.industries.covered') }}</span></td>
                                    @else
                                        <td><span class="mark-none" aria-hidden="true"></span><span class="sr-only">{{ __('work.industries.not_covered') }}</span></td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="band" aria-label="{{ __('work.industries.eyebrow') }}">
        <div class="container">
            <ul class="sectors">
                @foreach ($sectors as $sector)
                    @php($work = Work::inSector($sector))
                    <li class="sector" id="{{ $sector }}">
                        <div>
                            <h2 class="sector-name">{{ __('work.sectors.' . $sector . '.name') }}</h2>
                            <p>{{ __('work.sectors.' . $sector . '.line') }}</p>
                        </div>
                        <ul class="sector-work">
                            @foreach ($work['cases'] as $slug)
                                <li>
                                    <a href="{{ route('work.show', ['slug' => $slug]) }}">{{ __('work.cases.' . $slug . '.name') }}</a>
                                    <span>{{ __('work.cases.' . $slug . '.summary') }}</span>
                                </li>
                            @endforeach
                            @foreach ($work['other'] as $other)
                                <li>
                                    <a href="{{ route('work') }}#{{ $other }}">{{ __('work.other.' . $other . '.title') }}</a>
                                    <span>{{ __('work.other.' . $other . '.text') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @include('partials.final-cta', ['title' => __('work.final_title'), 'text' => __('work.final_text')])
@endsection
