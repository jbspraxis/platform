@extends('layouts.site')

@section('title', __('site.profile_link.title'))
@section('description', e(__('site.profile_link.description')))
@section('noindex', '1')

@php
    /**
     * /profile/{token} when the link no longer opens the profile (docs/COMPANY_PROFILE.md section 14).
     * Revoked, expired and never-a-link all read the same, so a stranger trying a token learns nothing from
     * the difference; only "too many" says something else, because the visitor can act on it.
     */
    $tooMany = ($state ?? '') === 'too_many';
@endphp

@section('content')
    <div class="stack">
        <x-page-head :eyebrow="__('site.profile_link.eyebrow')" :title="__('site.profile_link.title')" />

        <div class="prose">
            <p>{{ $tooMany ? __('site.profile_link.too_many') : __('site.profile_link.ended') }}</p>
            <p>{{ __('site.profile_link.ask', ['email' => $site->email]) }}</p>
        </div>

        <p>
            <a class="btn btn-primary" href="{{ route('contact') }}">{{ __('site.profile_link.cta') }}</a>
        </p>
    </div>
@endsection
