@extends('layouts.site')

@section('title', __('site.careers.title'))

@section('content')
    <div class="narrow stack">
        <x-page-head :title="__('site.careers.title')" :lead="__('site.careers.intro')" />

        @if (session('sent') === 'careers')
            <x-success :title="__('site.careers.sent_title')" :again="route('careers')" :again-label="__('site.careers.send_another')">
                <p>{{ __('site.careers.sent_body') }}</p>
            </x-success>
        @else
            <form class="card form" method="POST" action="{{ route('careers.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <x-form-guard form="careers" />
                <x-form-status />

                <fieldset class="fieldset">
                    <legend>{{ __('site.careers.about_you') }}</legend>
                    <div class="form-row">
                        <x-field name="full_name" :label="__('site.careers.full_name')" autocomplete="name" maxlength="100" />
                        <x-field name="email" type="email" :label="__('site.careers.email')" autocomplete="email" maxlength="100" />
                    </div>
                    <div class="form-row form-row-phone">
                        <x-select name="country_code" :label="__('site.careers.country_code')" :options="__('site.dial_codes')" value="+234" :placeholder="false" />
                        <x-field name="phone" type="tel" :label="__('site.careers.phone')" :hint="__('site.form.phone_hint')" autocomplete="tel-national" maxlength="20" />
                    </div>
                    <x-field name="city" :label="__('site.careers.city')" autocomplete="address-level2" maxlength="255" />
                </fieldset>

                <fieldset class="fieldset">
                    <legend>{{ __('site.careers.skills') }}</legend>
                    <x-select name="primary_skill" :label="__('site.careers.primary_skill')" :options="__('site.careers.primary_skills')" />
                    <div data-show-when="primary_skill=other">
                        <x-field name="other_skill" :label="__('site.careers.other_skill')" maxlength="100" />
                    </div>
                    <x-field name="secondary_skillset" :label="__('site.careers.secondary_skillset')" :hint="__('site.careers.secondary_skillset_hint')" maxlength="255" />
                    <div class="form-row">
                        <x-field name="linkedin_url" type="url" :label="__('site.careers.linkedin_url')" :hint="__('site.form.url_hint')" maxlength="255" />
                        <x-field name="portfolio_url" type="url" :label="__('site.careers.portfolio_url')" optional :hint="__('site.form.url_hint')" maxlength="255" />
                    </div>
                    <x-textarea name="praxis_answer" :label="__('site.careers.praxis_answer')" :hint="__('site.careers.praxis_answer_hint')" maxlength="1000" rows="6" />
                    <x-field name="cv" type="file" :label="__('site.careers.cv')" :hint="$errors->any() ? __('site.careers.cv_hint') . ' ' . __('site.careers.cv_again') : __('site.careers.cv_hint')" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" />
                </fieldset>

                <x-turnstile />
                <div class="actions">
                    <button class="btn btn-primary" type="submit"><i data-lucide="send"></i>{{ __('site.careers.send') }}</button>
                </div>
            </form>
        @endif
    </div>
@endsection
