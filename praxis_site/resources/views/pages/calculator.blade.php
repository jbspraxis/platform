@extends('layouts.site')

@section('title', __('site.calculator.title'))

@section('content')
    <div class="narrow stack">
        <x-page-head :title="__('site.calculator.title')" :lead="__('site.calculator.intro')" />
        <x-stale :show="$stale" />

        {{-- The same calculator as a conversation, for anyone who would rather answer that way. The button
             only does anything with JavaScript; the form below it is unchanged and remains the way in. --}}
        <p class="calculator-switch">
            <button class="btn btn-secondary" type="button" data-calc-open hidden data-calc-reveal>
                <i data-lucide="message-square-text"></i>{{ __('site.calculator.chat.open') }}
            </button>
        </p>

        <form class="card form" method="POST" action="{{ route('calculator.store') }}" novalidate>
            @csrf
            <x-form-guard form="calculator" />
            <x-form-status />

            <fieldset class="fieldset">
                <legend>{{ __('site.calculator.about_org') }}</legend>
                <div class="form-row">
                    <x-select name="industry" :label="__('site.calculator.industry')" optional :options="$options['industries']" />
                    <x-select name="tool" :label="__('site.calculator.tool')" optional :options="$options['systems']" />
                </div>
                <div class="form-row">
                    <x-select name="bottleneck" :label="__('site.calculator.bottleneck')" optional :options="$options['bottlenecks']" />
                    <x-select name="currency" :label="__('site.calculator.currency')" optional :options="$options['currencies']" />
                </div>
            </fieldset>

            <fieldset class="fieldset">
                <legend>{{ __('site.calculator.figures') }}</legend>
                <p class="fieldset-hint">{{ __('site.calculator.figures_hint') }}</p>
                <div class="form-row">
                    <x-field name="headcount" :label="__('site.calculator.headcount')" optional inputmode="numeric" maxlength="12" />
                    <x-field name="mgmt_nodes" :label="__('site.calculator.mgmt_nodes')" optional :hint="__('site.calculator.mgmt_nodes_hint')" inputmode="numeric" maxlength="12" />
                </div>
                <div class="form-row">
                    <x-field name="turnover" :label="__('site.calculator.turnover')" optional inputmode="decimal" maxlength="24" />
                    <x-field name="hourly_cost" :label="__('site.calculator.hourly_cost')" optional inputmode="decimal" maxlength="16" />
                </div>
                <div class="form-row">
                    <x-field name="manual_hrs" :label="__('site.calculator.manual_hrs')" optional inputmode="decimal" maxlength="6" />
                    <x-field name="error_rate" :label="__('site.calculator.error_rate')" optional :hint="__('site.calculator.error_rate_hint')" inputmode="decimal" maxlength="6" />
                </div>
                <x-field name="acv" :label="__('site.calculator.acv')" optional :hint="__('site.calculator.acv_hint')" inputmode="decimal" maxlength="20" />
            </fieldset>

            <fieldset class="fieldset">
                <legend>{{ __('site.calculator.about_you') }}</legend>
                <x-field name="email" type="email" :label="__('site.calculator.email')" :hint="__('site.calculator.email_hint')" autocomplete="email" maxlength="150" />
                <div class="form-row">
                    <x-field name="name" :label="__('site.calculator.name')" optional autocomplete="name" maxlength="100" />
                    <x-field name="company" :label="__('site.calculator.company')" optional autocomplete="organization" maxlength="150" />
                </div>
            </fieldset>

            <x-turnstile />
            <div class="actions">
                <button class="btn btn-primary" type="submit"><i data-lucide="calculator"></i>{{ __('site.calculator.send') }}</button>
            </div>
        </form>
    </div>
    @include('partials.calculator-dialog')
@endsection