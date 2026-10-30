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
        @endif
    </div>
@endsection
