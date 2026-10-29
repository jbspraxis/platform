<x-mail.layout :preheader="__('emails.audit_request.preheader')" :headline="__('emails.audit_request.subject')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.audit_request.intro') }}</x-mail.p>
    <x-mail.facts :rows="[
        __('emails.audit_request.requested_time') => $when,
        __('emails.audit_request.topic') => $topicLabel,
    ]" />
    <x-mail.p>{{ __('emails.audit_request.change') }}</x-mail.p>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.common.reason_contact') }}</x-slot:reason>
</x-mail.layout>
