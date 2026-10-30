@extends('layouts.site')

@section('title', __('site.kaizen.title'))

@section('content')
    <div class="stack">
        <x-page-head :title="__('site.kaizen.title')" :lead="__('site.kaizen.intro')" />
        <x-stale :show="$stale" />

        <div class="actions">
            <nav class="segmented" aria-label="{{ __('site.kaizen.sections') }}">
                <a class="segmented-item" href="{{ route('kaizen') }}" @if ($category === null) aria-current="page" @endif>{{ __('site.kaizen.all') }}</a>
                @foreach (__('site.kaizen.categories') as $value => $label)
                    <a class="segmented-item" href="{{ route('kaizen', ['category' => $value]) }}" @if ($category === $value) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <a class="btn btn-secondary" href="{{ route('kaizen.write') }}"><i data-lucide="pen-line"></i>{{ __('site.kaizen.write_cta') }}</a>
        </div>

        @if ($list['items'])
            <ul class="article-list">
                @foreach ($list['items'] as $article)
                    <li class="card">
                        <div class="article-meta">
                            @if ($article['category'] !== '')
                                <span>{{ __('site.kaizen.categories')[$article['category']] ?? $article['category'] }}</span>
                            @endif
                            @if ($article['minutes'])
                                <span>{{ __('site.kaizen.minutes', ['minutes' => $article['minutes']]) }}</span>
                            @endif
                            @if ($article['date'])
                                <span>{{ $article['date'] }}</span>
                            @endif
                        </div>
                        <h2 class="article-title"><a href="{{ route('kaizen.show', $article['slug']) }}">{{ $article['title'] }}</a></h2>
                        @if ($article['summary'] !== '')
                            <p>{{ $article['summary'] }}</p>
                        @endif
                        <p class="muted small">{{ __('site.kaizen.by', ['name' => $article['byline']]) }}</p>
                    </li>
                @endforeach
            </ul>

            @if ($list['page'] > 1 || $list['has_more'])
                <nav class="pager" aria-label="{{ $list['pages'] ? __('site.kaizen.page_of', ['page' => $list['page'], 'pages' => $list['pages']]) : __('site.kaizen.page', ['page' => $list['page']]) }}">
                    @if ($list['page'] > 1)
                        <a class="btn btn-secondary" href="{{ route('kaizen', array_filter(['category' => $category, 'page' => $list['page'] - 1])) }}"><i data-lucide="chevron-left"></i>{{ __('site.kaizen.newer') }}</a>
                    @else
                        <span></span>
                    @endif
                    <span class="muted small">{{ $list['pages'] ? __('site.kaizen.page_of', ['page' => $list['page'], 'pages' => $list['pages']]) : __('site.kaizen.page', ['page' => $list['page']]) }}</span>
                    @if ($list['has_more'])
                        <a class="btn btn-secondary" href="{{ route('kaizen', array_filter(['category' => $category, 'page' => $list['page'] + 1])) }}">{{ __('site.kaizen.older') }}<i data-lucide="chevron-right"></i></a>
                    @else
                        <span></span>
                    @endif
                </nav>
            @endif
        @else
            <div class="card empty-state">
                <strong>{{ $category === null ? __('site.kaizen.empty_title') : __('site.kaizen.empty_section') }}</strong>
                <p>{{ __('site.kaizen.empty_body') }}</p>
                <a class="btn btn-primary" href="{{ route('kaizen.write') }}"><i data-lucide="pen-line"></i>{{ __('site.kaizen.write_cta') }}</a>
            </div>
        @endif
    </div>
@endsection
