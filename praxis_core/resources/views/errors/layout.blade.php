{{-- Error pages for HQ and the portal (docs/QA-PORTAL.md): branded, in the visitor's language, with the way back.
     Self-contained (no Vite build), so they still show when the app itself is failing. Each page passes $key
     (lang/<locale>/errors.php). --}}
@php
    $request = request();
    $locale = session('locale') ?: (str_starts_with((string) $request->getPreferredLanguage(['en', 'fr']), 'fr') ? 'fr' : 'en');
    $t = fn (string $line) => __('errors.' . $line, [], $locale);
    $onPortal = \App\Support\Hosts::isPortal($request);
    $onHq = \App\Support\Hosts::isHq($request);
    // A message the app gave in abort() is shown when it was written for people (never Laravel's own defaults)
    $detail = isset($exception) ? trim((string) $exception->getMessage()) : '';
    if (in_array($detail, ['', 'Forbidden', 'Not Found', 'This action is unauthorized.', 'Invalid signature.', 'Page Expired', 'Too Many Attempts.', 'Server Error', 'Service Unavailable'], true) || ($code ?? 500) >= 500) {
        $detail = '';
    }
    [$back, $backLabel] = $onPortal ? [route('client.login'), $t('portal_sign_in')] : ($onHq ? [url('/'), $t('hq_home')] : [url('/'), $t('home')]);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $t($key . '.title') }} · JBS Praxis</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; background: #F4F6F8; color: #0C142B;
               font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.5; }
        header { background: #0C142B; padding: 16px 24px; }
        header img { display: block; height: 28px; width: auto; }
        main { flex: 1; display: flex; align-items: flex-start; justify-content: center; padding: 48px 16px; }
        .card { width: 100%; max-width: 520px; background: #fff; border: 1px solid #DDE3EA; border-radius: 12px; padding: 32px; }
        .code { margin: 0 0 8px; color: #5B6577; font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
        h1 { margin: 0 0 12px; font-size: 1.5rem; line-height: 2rem; font-weight: 600; }
        p { margin: 0 0 16px; color: #334155; }
        .detail { padding: 12px 16px; border-radius: 8px; background: #EEF6F7; color: #0C142B; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        a.btn { display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 8px; background: #08707C; color: #fff; font-weight: 500; text-decoration: none; }
        a.btn:focus-visible, a.link:focus-visible { outline: 3px solid #0E9AA7; outline-offset: 2px; }
        a.link { color: #08707C; font-weight: 500; }
    </style>
</head>
<body>
    <header><img src="/assets/img/logo/jbs-praxis-horizontal-reversed.svg" alt="JBS Praxis" width="147" height="28"></header>
    <main>
        <div class="card" role="main">
            <p class="code">{{ __('errors.code', ['code' => $code ?? 500], $locale) }}</p>
            <h1>{{ $t($key . '.title') }}</h1>
            <p>{{ $t($key . '.body') }}</p>
            @if ($detail !== '')
                <p class="detail">{{ $detail }}</p>
            @endif
            <div class="actions">
                <a class="btn" href="{{ $back }}">{{ $backLabel }}</a>
                <a class="link" href="mailto:info@jbspraxis.com">{{ $t('contact') }}</a>
            </div>
        </div>
    </main>
</body>
</html>
