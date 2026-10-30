@extends('layouts.site')

@section('title', __('site.report.title'))

@section('content')
    <div class="stack">
        <x-page-head :eyebrow="$report['company'] !== '' ? __('site.report.prepared_for', ['company' => $report['company']]) : null" :title="__('site.report.title')" />

        @if (session('report_new') && session('report_emailed', true))
            <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><p>{{ __('site.report.sent') }}</p></div>
        @elseif (session('report_new'))
            {{-- HQ answered "emailed": false: the report is stored and this link works, but no copy went out --}}
            <div class="alert alert-info" role="status"><i data-lucide="info"></i><p>{{ __('site.report.not_emailed') }}</p></div>
        @endif

        <div class="layout-aside">
            <div class="stack">
                <section class="card" aria-labelledby="headline">
                    @if ($report['total'])
                        <p class="muted small">{{ __('site.report.per_year_label') }}</p>
                        <p class="figure-big">{{ $report['total'] }}</p>
                        <h2 class="section-title" id="headline">
                            {{ $report['company'] !== ''
                                ? __('site.report.headline', ['company' => $report['company'], 'amount' => $report['total']])
                                : __('site.report.headline_plain', ['amount' => $report['total']]) }}
                        </h2>
                        @if ($report['monthly'])
                            <p>{{ __('site.report.per_month', ['amount' => $report['monthly']]) }}</p>
                        @endif
                    @else
                        <h2 class="section-title" id="headline">{{ __('site.report.title') }}</h2>
                    @endif
                    <p class="muted">{{ __('site.report.explain') }}</p>
                </section>

                @if ($report['parts'])
                    <section class="card" aria-labelledby="breakdown">
                        <h2 class="section-title" id="breakdown">{{ __('site.report.breakdown') }}</h2>
                        <ul class="parts">
                            @foreach ($report['parts'] as $part)
                                <li class="part">
                                    <div>
                                        <strong class="card-title">{{ $part['title'] }}</strong>
                                        <p class="muted small">{{ $part['description'] }}</p>
                                    </div>
                                    <span class="part-amount">{{ $part['amount'] }}</span>
                                    @if ($part['share'] !== null)
                                        <progress class="part-share" max="100" value="{{ $part['share'] }}" aria-label="{{ __('site.report.share', ['percent' => $part['share']]) }}">{{ $part['share'] }}%</progress>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($report['error_rate'])
                    <section class="card" aria-labelledby="quality">
                        <h2 class="section-title" id="quality">{{ __('site.report.quality') }}</h2>
                        <div class="compare">
                            <div><span class="muted small">{{ __('site.report.your_rate') }}</span><strong>{{ $report['error_rate'] }}</strong></div>
                            @if ($report['baseline'])
                                <div><span class="muted small">{{ __('site.report.baseline') }}</span><strong>{{ $report['baseline'] }}</strong></div>
                            @endif
                        </div>
                    </section>
                @endif

                @if ($report['analysis'] !== '')
                    <section class="card" aria-labelledby="analysis">
                        <h2 class="section-title" id="analysis">{{ __('site.report.analysis') }}</h2>
                        <div class="prose">{{ \App\Support\Articles::html($report['analysis']) }}</div>
                    </section>
                @endif

                <p class="muted small">{{ __('site.report.estimate_note') }} {{ __('site.report.share_note') }}</p>
            </div>

            <aside class="stack">
                <div class="card">
                    <h2 class="section-title">{{ __('site.report.facts_title') }}</h2>
                    <dl class="facts">
                        @foreach ($report['facts'] as $fact)
                            <div><dt>{{ $fact['label'] }}</dt><dd>{{ $fact['value'] }}</dd></div>
                        @endforeach
                        @if ($report['date'])
                            <div><dt>{{ __('site.report.facts_date') }}</dt><dd>{{ $report['date'] }}</dd></div>
                        @endif
                        @if ($report['reference'] !== '')
                            <div><dt>{{ __('site.report.facts_reference') }}</dt><dd>{{ $report['reference'] }}</dd></div>
                        @endif
                    </dl>
                </div>
                <div class="callout stack-sm">
                    <h2 class="card-title">{{ __('site.report.next') }}</h2>
                    <p>{{ __('site.report.next_body') }}</p>
                    <p><a class="btn btn-primary" href="{{ route('schedule') }}"><i data-lucide="calendar-days"></i>{{ __('site.cta') }}</a></p>
                </div>
            </aside>
        </div>
    </div>
@endsection
