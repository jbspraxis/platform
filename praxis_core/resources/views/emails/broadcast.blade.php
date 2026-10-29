{{-- An editorial broadcast: the campaign's own HTML from HQ → Broadcasts, in the branded layout. The open-tracking pixel stays last. --}}
<x-mail.layout :preheader="$preheader" :headline="$editorialSubject" :unsubscribe-url="$unsubscribeUrl">
    {!! $body !!}
    <x-mail.signoff />
    @if ($trackingPixelUrl)
        <img src="{{ $trackingPixelUrl }}" width="1" height="1" style="display:none;" alt="">
    @endif
    <x-slot:reason>{{ __('emails.broadcast.reason') }}</x-slot:reason>
</x-mail.layout>
