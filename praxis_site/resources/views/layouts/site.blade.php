{{-- The one layout of the marketing site: header with the navigation and language switch, SEO tags, footer, cookie
     banner and Praxis AI. Content pages fill @section('full') with their own bands; form pages fill @section('content'),
     which sits in the page container. --}}
@php
    $locale = app()->getLocale();
    $routeName = (string) request()->route()?->getName();
    $here = \Illuminate\Support\Str::after($routeName, 'fr.');
    // hreflang and the switch: only for pages that exist in both languages
    $paired = $routeName !== '' && \Illuminate\Support\Facades\Route::has('fr.' . $here);
    $alternates = $paired ? \App\Http\Middleware\SiteLocale::alternates(request()) : null;
    $canonical = url()->current();
    $fullTitle = trim($__env->yieldContent('page_title'));
    $title = $fullTitle !== '' ? $fullTitle : (trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')) . ' | ' . __('site.title_suffix') : __('site.title_suffix'));
    $description = trim($__env->yieldContent('description')) ?: e(__('pages.home.description'));
    $ogImage = asset('img/og/jbs-praxis-og-' . $locale . '-1200x630.png');
    $nav = ['services' => 'services', 'how-we-work' => 'how', 'work' => 'work', 'company' => 'company', 'kaizen' => 'insights'];
    $ga4 = (string) config('site.ga4_id');
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            array_filter([
                '@type' => 'Organization',
                '@id' => url('/') . '#organization',
                'name' => $site->companyName,
                'legalName' => $site->legalName,
                'url' => url('/'),
                'logo' => asset('img/logo/jbs-praxis-stacked-colour.svg'),
                'email' => $site->email,
                'slogan' => __('site.tagline'),
                'sameAs' => array_values(array_map(fn ($link) => $link['url'], $site->social)),
            ]),
            [
                '@type' => 'WebSite',
                '@id' => url('/') . '#website',
                'url' => url('/'),
                'name' => 'JBS Praxis',
                'inLanguage' => ['en', 'fr'],
                'publisher' => ['@id' => url('/') . '#organization'],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('site.noindex'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    {{-- Sections are escaped when they are stored, so they are printed raw here (no double escaping) --}}
    <title>{!! $title !!}</title>
    <meta name="description" content="{!! $description !!}">
    <link rel="canonical" href="{{ $canonical }}">
    @if ($alternates)
        <link rel="alternate" hreflang="en" href="{{ $alternates['en'] }}">
        <link rel="alternate" hreflang="fr" href="{{ $alternates['fr'] }}">
        <link rel="alternate" hreflang="x-default" href="{{ $alternates['en'] }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="JBS Praxis">
    <meta property="og:title" content="{!! $title !!}">
    <meta property="og:description" content="{!! $description !!}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="{{ $locale === 'fr' ? 'fr_FR' : 'en_GB' }}">
    <meta property="og:locale:alternate" content="{{ $locale === 'fr' ? 'en_GB' : 'fr_FR' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@jbspraxis">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('img/icons/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('img/icons/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0C142B">
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($turnstile ?? false)
        {{-- Cloudflare Turnstile (App\Support\Turnstile): only when TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are set --}}
        <script src="{{ config('services.turnstile.script_url') }}" async defer></script>
    @endif
</head>
<body>
    <a class="skip-link" href="#main">{{ __('site.skip') }}</a>

    <header class="site-header">
        <div class="container site-header-inner">
            <div class="site-brand">
                <a class="site-logo" href="{{ route('home') }}">
                    <img src="{{ asset('img/logo/jbs-praxis-horizontal-colour.svg') }}" alt="{{ __('site.home_link') }}" width="161" height="32">
                </a>
                @if (config('site.noindex'))
                    <span class="tag tag-preview">{{ __('site.preview') }}</span>
                @endif
            </div>

            <nav class="site-nav" aria-label="{{ __('site.nav.label') }}">
                <ul>
                    @foreach ($nav as $name => $key)
                        <li><a href="{{ route($name) }}" @if ($here === $name || str_starts_with($here, $name . '.')) aria-current="page" @endif>{{ __('site.nav.' . $key) }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="site-tools">
                @if ($alternates)
                    <ul class="lang-switch" aria-label="{{ __('site.nav.language') }}">
                        @foreach (\App\Http\Middleware\SiteLocale::LOCALES as $code)
                            <li><a href="{{ $alternates[$code] }}" hreflang="{{ $code }}" lang="{{ $code }}" @if ($code === $locale) aria-current="true" @endif>{{ __('site.languages.' . $code) }}</a></li>
                        @endforeach
                    </ul>
                @endif
                <a class="client-login" href="{{ config('site.portal_url') }}/login">{{ __('site.nav.client_login') }}</a>
                <a class="btn btn-primary" href="{{ route('schedule') }}">
                    <span class="cta-full">{{ __('site.cta') }}</span><span class="cta-short">{{ __('site.cta_short') }}</span>
                </a>
                {{-- Small screens: the same links in a disclosure (works without JavaScript) --}}
                <details class="nav-menu">
                    <summary aria-label="{{ __('site.nav.menu') }}"><i data-lucide="menu"></i></summary>
                    <div class="nav-menu-panel">
                        <ul>
                            @foreach ($nav as $name => $key)
                                <li><a href="{{ route($name) }}" @if ($here === $name || str_starts_with($here, $name . '.')) aria-current="page" @endif>{{ __('site.nav.' . $key) }}</a></li>
                            @endforeach
                            <li><a href="{{ config('site.portal_url') }}/login">{{ __('site.nav.client_login') }}</a></li>
                        </ul>
                        @if ($alternates)
                            <ul class="lang-switch" aria-label="{{ __('site.nav.language') }}">
                                @foreach (\App\Http\Middleware\SiteLocale::LOCALES as $code)
                                    <li><a href="{{ $alternates[$code] }}" hreflang="{{ $code }}" lang="{{ $code }}" @if ($code === $locale) aria-current="true" @endif>{{ __('site.languages.' . $code) }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </details>
            </div>
        </div>
    </header>

    <main id="main" class="site-main @hasSection('full') site-main-full @endif">
        @hasSection('full')
            @yield('full')
        @else
            <div class="container">
                @yield('content')
            </div>
        @endif
    </main>

    @include('partials.footer')
    @include('partials.chat')
    @if ($ga4 !== '')
        @include('partials.consent', ['ga4' => $ga4])
    @endif
</body>
</html>
