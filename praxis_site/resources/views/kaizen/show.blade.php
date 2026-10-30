@extends('layouts.site')

@section('title', $article['title'])

@section('content')
    <article class="stack">
        <p><a class="btn btn-ghost" href="{{ route('kaizen') }}"><i data-lucide="arrow-left"></i>{{ __('site.kaizen.back') }}</a></p>
        <x-stale :show="$stale" />

        <header class="page-head">
            <div class="article-meta">
                @if ($article['category'] !== '')
                    <span>{{ __('site.kaizen.categories')[$article['category']] ?? $article['category'] }}</span>
                @endif
                @if ($article['minutes'])
                    <span>{{ __('site.kaizen.minutes', ['minutes' => $article['minutes']]) }}</span>
                @endif
            </div>
            <h1 class="page-h1">{{ $article['title'] }}</h1>
            <p class="muted">
                {{ __('site.kaizen.by', ['name' => $article['byline']]) }}@if ($article['date']) · {{ __('site.kaizen.published', ['date' => $article['date']]) }}@endif
            </p>
        </header>

        <hr class="praxis-rule">

        <div class="card prose">
            @if ($article['summary'] !== '')
                <h2>{{ __('site.kaizen.summary') }}</h2>
                {{ \App\Support\Articles::html($article['summary']) }}
            @endif
            @if ($article['problem'] !== '')
                <h2>{{ __('site.kaizen.problem') }}</h2>
                {{ \App\Support\Articles::html($article['problem']) }}
            @endif
            @if ($article['solution'] !== '')
                <h2>{{ __('site.kaizen.solution') }}</h2>
                {{ \App\Support\Articles::html($article['solution']) }}
            @endif
            @if ($article['result'] !== '')
                <h2>{{ __('site.kaizen.result') }}</h2>
                {{ \App\Support\Articles::html($article['result']) }}
            @endif
        </div>

        <p><a class="btn btn-primary" href="{{ route('schedule') }}"><i data-lucide="calendar-days"></i>{{ __('site.cta') }}</a></p>
    </article>
@endsection
