@extends('layouts.site')

@section('page_title', __('legal.privacy.title'))
@section('description', __('legal.privacy.description'))

@section('full')
    @php
        // The policy's text is in lang/{en,fr}/legal.php; the facts in it come from Website settings and config/session.php
        $offices = array_map(fn (array $office) => $office['city'] . ($office['country'] !== '' ? ', ' . $office['country'] : ''), $site->offices);
        $fill = [
            ':company' => $site->legalName ?? $site->companyName,
            ':email' => $site->email,
            ':offices' => implode(__('legal.privacy.offices_separator'), $offices),
            ':session_cookie' => (string) config('session.cookie'),
            ':session_minutes' => (string) (int) config('session.lifetime'),
        ];
        $text = fn (string $line) => strtr($line, $fill);
        $sections = __('legal.privacy.sections');
        $anchor = fn (string $id) => str_replace('_', '-', $id);
    @endphp

    <section class="page-hero">
        <div class="container">
            <p class="eyebrow">{{ __('legal.privacy.eyebrow') }}</p>
            <h1 class="page-h1">{{ __('legal.privacy.h1') }}</h1>
            <p class="lead">{{ __('legal.privacy.lead') }}</p>
            <p class="policy-effective">{{ __('legal.privacy.effective') }}</p>
        </div>
    </section>

    <section class="band band-paper">
        <div class="container">
            <div class="prose policy">
                <div class="callout policy-summary">
                    <h2 id="summary">{{ __('legal.privacy.summary_title') }}</h2>
                    <ul class="ticks">
                        @foreach (__('legal.privacy.summary') as $line)
                            <li>{{ $text($line) }}</li>
                        @endforeach
                    </ul>
                </div>

                <nav class="policy-toc" aria-labelledby="contents-title">
                    <h2 id="contents-title">{{ __('legal.privacy.toc_title') }}</h2>
                    <ol>
                        @foreach ($sections as $id => $section)
                            <li><a href="#{{ $anchor($id) }}">{{ $section['title'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>

                {{-- A body block is a paragraph (string), a sub-heading, a list (items) or a two-column list (rows) --}}
                @foreach ($sections as $id => $section)
                    <h2 id="{{ $anchor($id) }}">{{ $loop->iteration }}. {{ $section['title'] }}</h2>
                    @foreach ($section['body'] as $block)
                        @if (is_string($block))
                            {{-- No offices in Website settings: the line that lists them is left out --}}
                            @unless ($fill[':offices'] === '' && str_contains($block, ':offices'))
                                <p>{{ $text($block) }}</p>
                            @endunless
                        @elseif (isset($block['heading']))
                            <h3>{{ $text($block['heading']) }}</h3>
                        @elseif (isset($block['items']))
                            <ul class="ticks">
                                @foreach ($block['items'] as $item)
                                    <li>{{ $text($item) }}</li>
                                @endforeach
                            </ul>
                        @elseif (isset($block['rows']))
                            <dl class="facts facts-wide">
                                @foreach ($block['rows'] as [$term, $detail])
                                    <div><dt>{{ $text($term) }}</dt><dd>{{ $text($detail) }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                    @endforeach

                    @if ($id === 'cookies' && (string) config('site.ga4_id') !== '')
                        {{-- Opens the cookie banner (partials/consent.blade.php), as the footer link does --}}
                        <p><button class="btn btn-secondary btn-sm" type="button" data-consent-open>{{ __('legal.privacy.cookie_button') }}</button></p>
                    @elseif ($id === 'rights')
                        <p><a class="btn btn-secondary" href="mailto:{{ $site->email }}"><i data-lucide="mail"></i>{{ $site->email }}</a></p>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endsection
