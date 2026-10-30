@extends('layouts.site')

@section('title', __('site.errors.expired_title'))

@section('content')
    <div class="narrow card stack-sm">
        <h1 class="page-h1">{{ __('site.errors.expired_title') }}</h1>
        <p>{{ __('site.errors.expired_body') }}</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url()->previous() }}">{{ __('site.errors.try_again') }}</a>
        </div>
    </div>
@endsection
