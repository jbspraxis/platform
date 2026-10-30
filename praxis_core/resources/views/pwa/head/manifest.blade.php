{{-- The portal's manifest is in the page's language (its name on the home screen) --}}
<link rel="manifest" href="/manifest.webmanifest{{ ($pwaApp ?? 'hq') === 'portal' ? '?lang=' . (app()->getLocale() === 'fr' ? 'fr' : 'en') : '' }}">
<meta name="theme-color" content="#0C142B">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ ($pwaApp ?? 'hq') === 'portal' ? __('portal.app_name') : 'HQ' }}">
