<x-mail.layout :preheader="__('emails.insights_rejected.preheader')" :headline="__('emails.insights_rejected.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.insights_rejected.intro', ['title' => $articleTitle]) }}</x-mail.p>
    <x-mail.p>{{ __('emails.insights_rejected.note') }}</x-mail.p>
    <x-mail.quote>{{ $reason }}</x-mail.quote>
    <x-mail.p>{{ __('emails.insights_rejected.again') }}</x-mail.p>
    <x-mail.button :url="$writeUrl" :label="__('emails.insights_rejected.button')" />
    <x-mail.small>{{ __('emails.insights_rejected.reply') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.insights_rejected.reason') }}</x-slot:reason>
</x-mail.layout>
