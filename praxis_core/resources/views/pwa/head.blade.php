{{-- HQ as an installable app (docs/PWA.md): the manifest and the iPhone and iPad app tags, the startup images
     (the first frame of the launch animation), and the app script (service worker, install and notification prompts) --}}
@include('pwa.head.manifest')
@include('pwa.head.startup-images')
@include('pwa.head.client')
