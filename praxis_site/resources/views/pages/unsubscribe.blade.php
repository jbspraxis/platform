@extends('layouts.site')

@section('title', __('site.unsubscribe.title'))

@section('content')
    <div class="narrow">
        @if ($done)
            <x-success :title="__('site.unsubscribe.done_heading')">
                <p>{{ __('site.unsubscribe.done_body') }}</p>
                <p><a href="{{ route('home') }}">{{ __('site.errors.go_home') }}</a></p>
            </x-success>
        @else
            <div class="card stack-sm">
                <h1 class="page-h1">{{ __('site.unsubscribe.heading') }}</h1>
                <p>{{ __('site.unsubscribe.body') }}</p>
                <x-form-status />
                <form class="stack-sm" method="POST" action="{{ route('unsubscribe.store', ['user_id' => $userId]) }}">
                    @csrf
                    <x-form-guard form="unsubscribe" />
                    {{-- The email link's campaign and token (HQ checks them for campaigns sent after PR2b) --}}
                    @if ($campaign !== null)
                        <input type="hidden" name="campaign" value="{{ $campaign }}">
                    @endif
                    @if ($token !== null)
                        <input type="hidden" name="t" value="{{ $token }}">
                    @endif
                    <x-turnstile />
                    <div class="actions">
                        <button class="btn btn-primary" type="submit">{{ __('site.unsubscribe.button') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection
