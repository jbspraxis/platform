@php($key = $forClient ? 'client' : 'staff')
<x-mail.layout :preheader="__('emails.password_reset.' . $key . '_preheader', ['minutes' => $minutes])" :headline="__('emails.password_reset.' . $key . '_subject')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.password_reset.' . $key . '_intro') }}</x-mail.p>
    <x-mail.button :url="$resetUrl" :label="__('emails.password_reset.button')" />
    <x-mail.p>{{ __('emails.password_reset.expiry', ['minutes' => $minutes]) }}</x-mail.p>
    <x-mail.small>{{ __('emails.password_reset.not_requested') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.password_reset.' . $key . '_reason') }}</x-slot:reason>
</x-mail.layout>
