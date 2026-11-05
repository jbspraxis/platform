@extends('layouts.site')

@section('title', __('site.feedback.title'))

@section('content')
    <div class="narrow stack">
        <x-page-head :title="__('site.feedback.title')" :lead="__('site.feedback.intro')" />

        @if (session('sent') === 'feedback')
            <x-success :title="__('site.feedback.sent_title')" :again="route('feedback')" :again-label="__('site.feedback.send_another')">
                <p>{{ __('site.feedback.sent_body') }}</p>
            </x-success>
        @else
            @php
                // The shared chrome plus this form's own words (lang/{en,fr}/site.php)
                $agent = array_replace_recursive(__('site.agent.common'), __('site.agent.feedback'));
            @endphp
            {{-- The same form as a conversation, for anyone who would rather answer that way. The button only
                 does anything with JavaScript; the form below it is unchanged and remains the way in. --}}
            <p class="agent-switch">
                <button class="btn btn-secondary" type="button" data-agent-open="feedback" data-agent-reveal hidden>
                    <i data-lucide="message-square-text"></i>{{ $agent['open'] }}
                </button>
            </p>

            <form class="card form" method="POST" action="{{ route('feedback.store') }}" novalidate>
                @csrf
                <x-form-guard form="feedback" />
                <x-form-status />
                <div class="form-row">
                    <x-field name="identity" :label="__('site.feedback.identity')" optional maxlength="255" />
                    <x-field name="email" type="email" :label="__('site.feedback.email')" optional :hint="__('site.feedback.email_hint')" autocomplete="email" maxlength="255" />
                </div>
                <div class="form-row">
                    <x-select name="category" :label="__('site.feedback.category')" :options="__('site.feedback.categories')" />
                    <x-select name="severity" :label="__('site.feedback.severity')" :options="__('site.feedback.severities')" value="1" :placeholder="false" />
                </div>
                <x-textarea name="description" :label="__('site.feedback.description')" :hint="__('site.feedback.description_hint')" maxlength="5000" rows="6" />
                <x-turnstile />
                <div class="actions">
                    <button class="btn btn-primary" type="submit"><i data-lucide="send"></i>{{ __('site.feedback.send') }}</button>
                </div>
            </form>

            <x-agent-dialog
                form="feedback"
                :action="route('feedback.store')"
                :strings="$agent"
                :lists="['categories' => __('site.feedback.categories'), 'severities' => __('site.feedback.severities')]"
                :gate="['name' => 'description', 'kind' => 'longtext', 'label' => __('site.feedback.description'), 'hint' => __('site.feedback.description_hint'), 'max' => 5000]" />
        @endif
    </div>
@endsection
