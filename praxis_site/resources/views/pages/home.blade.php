@extends('layouts.site')

@section('page_title', __('pages.home.title'))
@section('description', __('pages.home.description'))

@php
    // The narrated 3-minute demo on YouTube, in the page's language (config site.demo_video)
    $demoId = (string) (config('site.demo_video.' . app()->getLocale()) ?: config('site.demo_video.en'));
@endphp



@section('full')
    {{-- lucide: landmark users truck shield-check message-square-text file-check-2 arrow-up circle-play arrow-down calculator phone message-circle x --}}
    {{-- The hero (redesign PR 2, docs/BRAND.md section 11 "The hero moment"): a framed answer from the demo company.
         Every final value is in the page from the start; scripts only stage it: the question fades in, the Praxis rule
         draws to the answer, the figure's last digits settle, the bars grow, then the question box turns into a live
         "Ask Praxis" field. Reduced motion or no JavaScript: the final frame, still. Behind it on wide screens, the quiet
         flow field (resources/js/motion/field.js); the drawing in .hero-field is its still fallback. --}}
    <section class="hero" aria-labelledby="hero-title" data-hero>
        <div class="hero-field" aria-hidden="true">
            <svg class="hero-field-still" viewBox="0 0 1200 600" preserveAspectRatio="none" focusable="false">
                @for ($i = 0; $i < 18; $i++)
                    <line x1="0" x2="1200" y1="{{ 40 + $i * 30 }}" y2="{{ 40 + $i * 30 + (($i % 3) - 1) * 6 }}" />
                @endfor
            </svg>
        </div>
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
                    @endif
                </div>
            </div>

            <figure class="answer" id="answers" data-answer aria-label="{{ __('pages.home.answer_label') }}">
                <div class="answer-bar">
                    <span class="answer-org"><span class="answer-mark" aria-hidden="true">N</span>{{ __('pages.home.answer_company') }}<span class="answer-place">{{ __('pages.home.answer_place') }}</span></span>
                    <span class="tag answer-demo">{{ __('pages.home.answer_demo') }}</span>
                </div>
                <div class="answer-body">
                    <p class="answer-question" data-answer-step="question"><i data-lucide="message-square-text"></i>{{ __('pages.home.answer_question') }}</p>
                    <div class="answer-rule" aria-hidden="true"><span data-answer-step="rule"></span></div>
                    <p class="answer-figure" data-answer-step="figure">
                        <span class="answer-number" data-settle>{{ __('pages.home.answer_figure') }}</span>
                        <span class="answer-figure-label">{{ __('pages.home.answer_figure_label') }}</span>
                    </p>
                    <table class="answer-table" data-answer-step="table">
                        <caption class="sr-only">{{ __('pages.home.answer_share_label') }}</caption>
                        <tbody>
                            @foreach (__('pages.home.answer_rows') as $row)
                                <tr>
                                    <th scope="row">{{ $row['name'] }}</th>
                                    <td class="answer-bar-cell" aria-hidden="true"><span class="answer-bar-track"><span class="answer-bar-fill" data-value="{{ $row['value'] }}"></span></span></td>
                                    <td class="answer-share">{{ $row['share'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="answer-sources" data-answer-step="sources"><i data-lucide="file-check-2"></i>{{ __('pages.home.answer_sources') }}</p>
                </div>
                {{-- The question box: a real form. It hands the question to Ask Praxis (partials.chat) and opens it --}}
                <form class="answer-ask" action="{{ route('chat') }}" method="POST" data-hero-ask>
                    @csrf
                    <x-form-guard form="hero-chat" />
                    <label class="sr-only" for="hero-ask">{{ __('pages.home.ask_label') }}</label>
                    <input class="answer-ask-input" id="hero-ask" name="chat_message" type="text" maxlength="1500" autocomplete="off"
                           placeholder="{{ __('pages.home.ask_placeholder') }}" data-question="{{ __('pages.home.answer_question') }}">
                    <button class="answer-ask-send" type="submit" aria-label="{{ __('pages.home.ask_button') }}"><i data-lucide="arrow-up"></i></button>
                </form>
            </figure>
        </div>
    </section>

    <section class="band" aria-labelledby="problem-title" data-chapter="statement">
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

    <section class="band" aria-labelledby="method-title" data-chapter="method">
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
