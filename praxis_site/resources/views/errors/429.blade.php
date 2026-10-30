@extends('layouts.site')

@section('title', __('site.errors.throttled_title'))

@section('content')
    <div class="narrow card stack-sm">
        <h1 class="page-h1">{{ __('site.errors.throttled_title') }}</h1>
        <p>{{ __('site.errors.throttled_body') }}</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('home') }}">{{ __('site.errors.go_home') }}</a>
        </div>
    </div>
@endsection
