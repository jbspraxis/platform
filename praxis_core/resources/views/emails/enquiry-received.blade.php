<x-mail.layout :preheader="__('emails.enquiry.preheader')" :headline="__('emails.enquiry.subject')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.enquiry.intro') }}</x-mail.p>
    <x-mail.p>{{ __('emails.enquiry.book_intro') }}</x-mail.p>
    <x-mail.button :url="$bookUrl" :label="__('emails.enquiry.button')" />
    <x-mail.small>{{ __('emails.enquiry.not_sent') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.common.reason_contact') }}</x-slot:reason>
</x-mail.layout>
