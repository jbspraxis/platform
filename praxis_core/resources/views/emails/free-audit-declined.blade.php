<x-mail.layout :preheader="__('emails.audit_declined.preheader')" :headline="__('emails.audit_declined.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.audit_declined.intro', ['company' => $companyName]) }}</x-mail.p>
    <x-mail.facts :rows="[
        __('emails.audit_declined.label_date') => $requestedDate,
        __('emails.audit_declined.label_time') => $requestedTime,
    ]" />
    <x-mail.p>{{ __('emails.audit_declined.another') }}</x-mail.p>
    <x-mail.button :url="$bookUrl" :label="__('emails.audit_declined.button')" />
    <x-mail.small>{{ __('emails.audit_declined.reply') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.audit_declined.reason', ['company' => $companyName]) }}</x-slot:reason>
</x-mail.layout>
