@extends('layouts.site')

@section('title', __('site.contact.title'))

@section('content')
    <div class="narrow stack">
        <x-page-head :title="__('site.contact.title')" :lead="__('site.contact.intro')" />

        @if (session('sent') === 'contact')
            <x-success :title="__('site.contact.sent_title')" :again="route('contact')" :again-label="__('site.contact.send_another')">
                <p>{{ __('site.contact.sent_body', ['email' => $site->email]) }}</p>
            </x-success>
        @else
            <form class="card form" method="POST" action="{{ route('contact.store') }}" novalidate>
                @csrf
                <x-form-guard form="contact" />
                <x-form-status />

                <fieldset class="fieldset">
                    <legend>{{ __('site.contact.about_you') }}</legend>
                    <div class="form-row">
                        <x-field name="full_name" :label="__('site.contact.full_name')" autocomplete="name" maxlength="100" />
                        <x-field name="email" type="email" :label="__('site.contact.email')" autocomplete="email" maxlength="100" />
                    </div>
                    <div class="form-row">
                        <x-field name="company_name" :label="__('site.contact.company_name')" autocomplete="organization" maxlength="150" />
                        <x-field name="title" :label="__('site.contact.title_field')" optional autocomplete="organization-title" maxlength="100" />
                    </div>
                    <div class="form-row form-row-phone">
                        <x-select name="country_code" :label="__('site.contact.country_code')" optional :options="__('site.dial_codes')" value="+234" :placeholder="false" />
                        <x-field name="phone" type="tel" :label="__('site.contact.phone')" optional :hint="__('site.form.phone_hint')" autocomplete="tel-national" maxlength="20" />
                    </div>
                    <div class="form-row">
                        <x-field name="city" :label="__('site.contact.city')" optional autocomplete="address-level2" maxlength="100" />
                        <x-select name="language" :label="__('site.contact.language')" optional :options="__('site.contact.languages')" :value="$replyLanguage" :placeholder="false" />
                    </div>
                    <x-textarea name="message" :label="__('site.contact.message')" :hint="__('site.contact.message_hint')" maxlength="2000" rows="6" />
                    <x-select name="start_timing" :label="__('site.contact.start_timing')" optional :options="__('site.contact.start_timings')" />
                </fieldset>

                <fieldset class="fieldset">
                    <legend>{{ __('site.contact.diagnostic') }}</legend>
                    <p class="fieldset-hint">{{ __('site.contact.diagnostic_hint') }}</p>
                    <div class="form-row">
                        <x-select name="stage" :label="__('site.contact.stage')" optional :options="__('site.contact.stages')" />
                        <x-select name="bottleneck" :label="__('site.contact.bottleneck')" optional :options="__('site.contact.bottlenecks')" />
                    </div>
                    <div data-show-when="bottleneck=other">
                        <x-field name="other_bottleneck" :label="__('site.contact.other_bottleneck')" maxlength="255" />
                    </div>
                    <div class="form-row">
                        <x-select name="tech_reality" :label="__('site.contact.tech_reality')" optional :options="__('site.contact.tech_realities')" />
                        <x-select name="gov_status" :label="__('site.contact.gov_status')" optional :options="__('site.contact.gov_statuses')" />
                    </div>
                </fieldset>

                <x-turnstile />
                <div class="actions">
                    <button class="btn btn-primary" type="submit"><i data-lucide="send"></i>{{ __('site.contact.send') }}</button>
                </div>
            </form>
        @endif
    </div>
@endsection
