<x-mail.layout :preheader="__('emails.insights_published.preheader')" :headline="__('emails.insights_published.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.insights_published.intro', ['title' => $articleTitle]) }}</x-mail.p>
    <x-mail.button :url="$articleUrl" :label="__('emails.insights_published.button')" />
    <x-mail.p>{{ __('emails.insights_published.credit') }}</x-mail.p>
    <x-mail.small>{{ __('emails.insights_published.reply') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.insights_published.reason') }}</x-slot:reason>
</x-mail.layout>
