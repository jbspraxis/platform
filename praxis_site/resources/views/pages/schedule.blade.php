@extends('layouts.site')

@section('title', __('site.schedule.title'))

@section('content')
    <div class="stack">
        <x-page-head :title="__('site.schedule.title')" :lead="__('site.schedule.intro')" />

        @if (session('sent') === 'schedule')
            <div class="narrow">
                <x-success :title="__('site.schedule.sent_title')" :again="route('schedule')" :again-label="__('site.schedule.book_another')">
                    @if (session('booking_when'))
                        <p><strong>{{ __('site.schedule.sent_time', ['when' => session('booking_when')]) }}</strong></p>
                    @endif
                    <p>{{ __('site.schedule.sent_body') }}</p>
                </x-success>
            </div>
        @else
            <div class="layout-aside">
                <form class="card form" method="POST" action="{{ route('schedule.store') }}" novalidate>
                    @csrf
                    <x-form-guard form="schedule" />
                    <x-form-status />

                    <section aria-labelledby="step-day">
                        <h2 class="section-title" id="step-day">1. {{ __('site.schedule.step_day') }}</h2>
                        <div class="calendar-head">
                            @if ($previous)
                                <a class="btn btn-secondary btn-sm" href="{{ route('schedule', ['month' => $previous]) }}"><i data-lucide="chevron-left"></i><span>{{ __('site.schedule.previous_month') }}</span></a>
                            @else
                                <span></span>
                            @endif
                            <h3>{{ $monthLabel }}</h3>
                            @if ($next)
                                <a class="btn btn-secondary btn-sm" href="{{ route('schedule', ['month' => $next]) }}"><span>{{ __('site.schedule.next_month') }}</span><i data-lucide="chevron-right"></i></a>
                            @else
                                <span></span>
                            @endif
                        </div>
                        <table class="calendar">
                            <caption class="sr-only">{{ $monthLabel }}</caption>
                            <thead>
                                <tr>
                                    @foreach (__('site.schedule.weekdays') as $short => $long)
                                        <th scope="col"><abbr title="{{ $long }}">{{ $short }}</abbr></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($weeks as $week)
                                    <tr>
                                        @foreach ($week as $day)
                                            <td>
                                                @if ($day === null)
                                                @elseif ($day['slots'] > 0)
                                                    <a class="cal-day {{ $day['selected'] ? 'is-selected' : '' }}"
                                                       href="{{ route('schedule', ['month' => $month->format('Y-m'), 'date' => $day['date']]) }}#step-time"
                                                       aria-label="{{ trans_choice('site.schedule.day_free', $day['slots'], ['date' => $day['label'], 'count' => $day['slots']]) }}"
                                                       @if ($day['selected']) aria-current="date" @endif>{{ $day['day'] }}<span class="cal-dot"></span></a>
                                                @else
                                                    <span class="cal-day is-closed" aria-label="{{ __('site.schedule.day_full', ['date' => $day['label']]) }}">{{ $day['day'] }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @unless ($hasFreeDays)
                            <p class="muted small">{{ __('site.schedule.no_days', ['email' => $site->email]) }}</p>
                        @endunless
                    </section>

                    <section aria-labelledby="step-time" class="stack-sm">
                        <h2 class="section-title" id="step-time">2. {{ __('site.schedule.step_time') }}</h2>
                        @if ($selected === '')
                            <p class="muted">{{ __('site.schedule.pick_day') }}</p>
                            @error('time')
                                <p class="field-error"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></p>
                            @enderror
                        @else
                            <input type="hidden" name="date" value="{{ $selected }}">
                            <fieldset class="fieldset" @error('time') aria-describedby="time-error" @enderror>
                                <legend class="label">{{ __('site.schedule.times_for', ['date' => $selectedLabel]) }}</legend>
                                <div class="times">
                                    @foreach ($times as $time)
                                        <label class="time-option">
                                            <input type="radio" name="time" value="{{ $time }}" @checked(old('time') === $time)>
                                            <span>{{ $time }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('time')
                                    <p class="field-error" id="time-error"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></p>
                                @enderror
                            </fieldset>
                        @endif
                        @error('date')
                            <p class="field-error"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></p>
                        @enderror
                    </section>

                    <fieldset class="fieldset">
                        <legend>3. {{ __('site.schedule.step_details') }}</legend>
                        <div class="form-row">
                            <x-field name="full_name" :label="__('site.schedule.full_name')" autocomplete="name" maxlength="100" />
                            <x-field name="title" :label="__('site.schedule.title_field')" optional autocomplete="organization-title" maxlength="100" />
                        </div>
                        <div class="form-row">
                            <x-field name="email" type="email" :label="__('site.schedule.email')" autocomplete="email" maxlength="100" />
                            <x-field name="phone" type="tel" :label="__('site.schedule.phone')" :hint="__('site.schedule.phone_hint')" autocomplete="tel" maxlength="30" />
                        </div>
                        <x-field name="enterprise_name" :label="__('site.schedule.enterprise_name')" autocomplete="organization" maxlength="150" />
                        <x-select name="focus" :label="__('site.schedule.focus')" :options="__('site.schedule.focuses')" />
                        <div data-show-when="focus=Other">
                            <x-field name="custom_focus" :label="__('site.schedule.custom_focus')" :hint="__('site.schedule.custom_focus_hint')" maxlength="40" />
                        </div>
                    </fieldset>

                    <x-turnstile />
                    <div class="actions">
                        <button class="btn btn-primary" type="submit"><i data-lucide="calendar-check"></i>{{ __('site.schedule.send') }}</button>
                    </div>
                </form>

                <aside class="callout small">
                    <h2 class="card-title">{{ __('site.schedule.next_title') }}</h2>
                    <p>{{ __('site.schedule.next_body') }}</p>
                </aside>
            </div>
        @endif
    </div>
@endsection
