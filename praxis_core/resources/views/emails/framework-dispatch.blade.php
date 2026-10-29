<x-mail.layout :preheader="__('emails.method.preheader')" :headline="__('emails.method.subject')">
    <x-mail.greeting />
    <x-mail.p>{{ __('emails.method.intro') }}</x-mail.p>
    <x-mail.p>{{ __('emails.method.book_intro') }}</x-mail.p>
    <x-mail.button :url="$bookUrl" :label="__('emails.method.button')" />
    <x-mail.small>{{ __('emails.method.not_requested') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.method.reason') }}</x-slot:reason>
</x-mail.layout>
