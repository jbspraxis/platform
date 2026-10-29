@php($key = $forClient ? 'client' : 'staff')
<x-mail.layout :preheader="__('emails.invitation.' . $key . '_preheader')" :headline="__('emails.invitation.' . $key . '_subject')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.invitation.' . $key . '_intro') }}</x-mail.p>
    <x-mail.button :url="$setupUrl" :label="__('emails.invitation.button')" />
    <x-mail.small>{{ __('emails.invitation.not_expected') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.invitation.' . $key . '_reason') }}</x-slot:reason>
</x-mail.layout>
