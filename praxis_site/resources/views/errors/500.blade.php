{{-- Standalone on purpose: no layout, no settings, nothing that could fail again --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('site.errors.server_title') }} | {{ __('site.title_suffix') }}</title>
    @if (is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body>
    <main class="site-main">
        <div class="container">
            <div class="narrow card stack-sm">
                <h1 class="page-h1">{{ __('site.errors.server_title') }}</h1>
                <p>{{ __('site.errors.server_body', ['email' => config('site.fallback_email')]) }}</p>
                <p><a class="btn btn-primary" href="{{ url('/') }}">{{ __('site.errors.go_home') }}</a></p>
            </div>
        </div>
    </main>
</body>
</html>
