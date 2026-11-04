@extends('layouts.site')

@php
    // One case study (App\Support\Work::CASES[$slug]); its words are in lang work.cases.<slug>
    $key = 'work.cases.' . $slug;
    $ours = $case['group'] === 'products';
    $method = __('pages.method');
    $role = __($key . '.role');
    $stages = [];
    foreach (['see', 'design', 'build', 'hold'] as $i => $stage) {
        $stages[] = ['name' => $method[$i]['name'], 'text' => $role[$stage]];
    }
    $changedTitle = $ours ? 'changed_product' : ($case['stage'] === 'build' ? 'changed_build' : 'changed');
    $related = array_intersect_key(\App\Support\Work::CASES, array_flip($case['related']));
@endphp

@section('page_title', __($key . '.title'))
@section('description', __($key . '.description'))

@section('full')
    <section class="page-hero">
        <div class="container case-hero-inner">
            <div>
                <p class="eyebrow">{{ __($ours ? 'work.labels.our_product' : 'work.labels.case_study') }}</p>
                <h1 class="page-h1">{{ __($key . '.h1') }}</h1>
                <p class="lead">{{ __($key . '.lead') }}</p>
            </div>
            <dl class="facts facts-wide case-facts">
                <div><dt>{{ __($ours ? 'work.labels.product' : 'work.labels.client') }}</dt><dd>{{ __($key . '.name') }}</dd></div>
                @if (\Illuminate\Support\Facades\Lang::has($key . '.partner'))
                    <div><dt>{{ __('work.labels.partner') }}</dt><dd>{{ __($key . '.partner') }}</dd></div>
                @endif
                <div><dt>{{ __('work.labels.sector') }}</dt><dd><a href="{{ route('industries') }}#{{ $case['sector'] }}">{{ __('work.sectors.' . $case['sector'] . '.name') }}</a></dd></div>
                <div><dt>{{ __('work.labels.country') }}</dt><dd>{{ __($key . '.country') }}</dd></div>
                @if (\Illuminate\Support\Facades\Lang::has($key . '.system'))
                    <div><dt>{{ __('work.labels.system') }}</dt><dd>{{ __($key . '.system') }}</dd></div>
                @endif
                <div><dt>{{ __('work.labels.stage') }}</dt><dd>{{ __('work.stages.' . $case['stage']) }}</dd></div>
            </dl>
        </div>
    </section>

    @if (is_file(public_path('img/screens/' . $slug . '-1280.webp')))
        {{-- The platform itself: its sign-in screen, as its team sees it --}}
        <section class="band band-tight case-screen" aria-label="{{ __('work.screen.label', ['name' => __($key . '.name')]) }}">
            <div class="container">
                <x-frame :caption="__('work.screen.caption', ['name' => __($key . '.name')])">
                    <x-screen :slug="$slug" :alt="__('work.screen.alt', ['name' => __($key . '.name')])" sizes="(min-width: 1200px) 1136px, calc(100vw - 32px)" />
                </x-frame>
            </div>
        </section>
    @endif

    <section class="band band-paper" aria-labelledby="needed-title">
        <div class="container split">
            <h2 class="band-title" id="needed-title">{{ __($ours ? 'work.labels.needed_product' : 'work.labels.needed') }}</h2>
            <div class="stack-sm">
                @foreach (__($key . '.needed') as $paragraph)
                    <p class="band-text">{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
    </section>

    {{-- The page's one picture: what we built as a map of its areas, standing on what holds them together --}}
    <section class="band" aria-labelledby="built-title">
        <div class="container">
            <h2 class="band-title" id="built-title">{{ __('work.labels.built') }}</h2>
            <p class="band-text">{{ __($key . '.map.intro') }}</p>
            <ul class="system-groups">
                @foreach (__($key . '.map.groups') as $group)
                    <li class="system-group">
                        <h3>{{ $group['name'] }}</h3>
                        <ul>
                            @foreach ($group['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
            <div class="system-spine">
                <p class="eyebrow">{{ __('work.labels.spine') }}</p>
                <p class="system-spine-text">{{ __($key . '.map.spine') }}</p>
            </div>
        </div>
    </section>

    <section class="band band-paper" aria-labelledby="day-title">
        <div class="container split">
            <div>
                <p class="eyebrow">{{ __('work.labels.day') }}</p>
                <h2 class="band-title" id="day-title">{{ __($key . '.day_title') }}</h2>
            </div>
            <ol class="steps" role="list">
                @foreach (__($key . '.day') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>
    </section>

    @php($results = (array) config('site.results.' . $slug, []))
    @if ($results)
        {{-- Confirmed figures only (config site.results, from the client in writing) --}}
        <section class="band band-paper" aria-labelledby="results-title">
            <div class="container">
                <x-section-head id="results-title" :title="__('site.proof.results_title')" rule />
                <dl class="results">
                    @foreach ($results as $result)
                        <div class="result">
                            <dt>{{ $result['label'][app()->getLocale()] ?? $result['label']['en'] }}</dt>
                            <dd class="result-value">{{ $result['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="results-note">{{ __('site.proof.results_note') }}</p>
            </div>
        </section>
    @endif

    <section class="band" aria-labelledby="changed-title">
        <div class="container">
            <h2 class="band-title" id="changed-title">{{ __('work.labels.' . $changedTitle) }}</h2>
            <ul class="shifts">
                @foreach (__($key . '.changed') as $shift)
                    <li>
                        <h3>{{ $shift['title'] }}</h3>
                        <p>{{ $shift['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="band band-paper" aria-labelledby="role-title">
        <div class="container">
            <h2 class="band-title" id="role-title">{{ __($ours ? 'work.labels.role_product' : 'work.labels.role') }}</h2>
            @include('partials.method', ['steps' => $stages])
            <p class="band-link"><a href="{{ route('how-we-work') }}#method">{{ __('pages.home.method_link') }}</a></p>
        </div>
    </section>

    <section class="band" aria-labelledby="related-title">
        <div class="container">
            <h2 class="band-title" id="related-title">{{ __('work.labels.related') }}</h2>
            @include('partials.work-register', ['cases' => $related])
            <p class="band-link"><a href="{{ route('work') }}">{{ __('work.labels.all_work') }}</a></p>
        </div>
    </section>

    @include('partials.calculator-band')

    @include('partials.final-cta', ['title' => __('work.final_title'), 'text' => __('work.final_text')])
@endsection
