@extends('layouts.site')

@section('title', __('site.kaizen.write.title'))

@section('content')
    <div class="narrow stack">
        <p><a class="btn btn-ghost" href="{{ route('kaizen') }}"><i data-lucide="arrow-left"></i>{{ __('site.kaizen.back') }}</a></p>
        <x-page-head :title="__('site.kaizen.write.title')" :lead="__('site.kaizen.write.intro')" />

        @if (session('sent') === 'kaizen')
            <x-success :title="__('site.kaizen.write.sent_title')" :again="route('kaizen.write')" :again-label="__('site.kaizen.write.send_another')">
                <p>{{ __('site.kaizen.write.sent_body') }}</p>
            </x-success>
        @else
            <form class="card form" method="POST" action="{{ route('kaizen.store') }}" novalidate>
                @csrf
                <x-form-guard form="kaizen" />
                <x-form-status />

                <fieldset class="fieldset">
                    <legend>{{ __('site.kaizen.write.about_you') }}</legend>
                    <div class="form-row">
                        <x-field name="full_name" :label="__('site.kaizen.write.full_name')" autocomplete="name" maxlength="100" />
                        <x-field name="title" :label="__('site.kaizen.write.title_field')" autocomplete="organization-title" maxlength="100" />
                    </div>
                    <div class="form-row">
                        <x-field name="email" type="email" :label="__('site.kaizen.write.email')" autocomplete="email" maxlength="100" />
                        <x-field name="linkedin_url" type="url" :label="__('site.kaizen.write.linkedin_url')" :hint="__('site.kaizen.write.linkedin_hint')" maxlength="255" />
                    </div>
                </fieldset>

                <fieldset class="fieldset">
                    <legend>{{ __('site.kaizen.write.article') }}</legend>
                    <x-select name="category" :label="__('site.kaizen.write.category')" :options="__('site.kaizen.categories')" />
                    <x-field name="article_title" :label="__('site.kaizen.write.article_title')" maxlength="255" />
                    <x-textarea name="exec_summary" :label="__('site.kaizen.write.exec_summary')" :hint="__('site.kaizen.write.exec_summary_hint')" rows="3" />
                    <x-textarea name="vulnerability" :label="__('site.kaizen.write.vulnerability')" :hint="__('site.kaizen.write.vulnerability_hint')" maxlength="10000" rows="8" />
                    <x-textarea name="solution" :label="__('site.kaizen.write.solution')" :hint="__('site.kaizen.write.solution_hint')" maxlength="10000" rows="8" />
                    <x-field name="roi" :label="__('site.kaizen.write.roi')" :hint="__('site.kaizen.write.roi_hint')" maxlength="255" />
                </fieldset>

                <x-turnstile />
                <div class="actions">
                    <button class="btn btn-primary" type="submit"><i data-lucide="send"></i>{{ __('site.kaizen.write.send') }}</button>
                </div>
            </form>
        @endif
    </div>
@endsection
