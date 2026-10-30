{{-- HQ could not be reached and there is no saved copy: a calm message with the contact email from the last good settings --}}
@extends('layouts.site')

@section('title', __('site.errors.unavailable_title'))

@section('content')
    <div class="narrow card stack-sm">
        <h1 class="page-h1">{{ __('site.errors.unavailable_title') }}</h1>
        <p>{{ $message ?? __('site.errors.unavailable_body', ['email' => $email ?? $site->email]) }}</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url()->current() }}"><i data-lucide="rotate-cw"></i>{{ __('site.errors.try_again') }}</a>
            <a class="btn btn-secondary" href="mailto:{{ $email ?? $site->email }}"><i data-lucide="mail"></i>{{ $email ?? $site->email }}</a>
        </div>
    </div>
@endsection
