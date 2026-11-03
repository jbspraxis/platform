{{-- A broadcast from HQ › Campaigns (App\Mail\BroadcastEmail): the layout's label, the headline, an optional greeting, the blocks,
     the sign-off and why the person got it. The open-counting pixel stays last. --}}
<x-mail.layout :preheader="$preheader" :headline="$headline" :eyebrow="$eyebrow" :unsubscribe-url="$unsubscribeUrl">
    @if ($greeting)
        <x-mail.greeting :first-name="$person['first_name'] ?? null" />
    @endif
    {!! $body !!}
    <x-mail.signoff />
    @if ($pixelUrl)
        <img src="{{ $pixelUrl }}" width="1" height="1" style="display: block; width: 1px; height: 1px; border: 0;" alt="">
    @endif
    <x-slot:reason>{{ __('emails.broadcast.reasons.' . ($person['kind'] ?? 'lead')) }}</x-slot:reason>
</x-mail.layout>
