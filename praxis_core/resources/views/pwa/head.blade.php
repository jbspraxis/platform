{{-- HQ and the Client Portal as installable apps (docs/PWA.md): the manifest and the iPhone and iPad app tags, the
     startup images (the first frame of the launch animation), and the app script (service worker, install and
     notification prompts). $pwaApp is 'hq' (default) or 'portal'. --}}
@php($pwaApp = ($pwaApp ?? 'hq') === 'portal' ? 'portal' : 'hq')
@include('pwa.head.manifest', ['pwaApp' => $pwaApp])
@include('pwa.head.startup-images', ['pwaApp' => $pwaApp])
@include('pwa.head.client', ['pwaApp' => $pwaApp])
