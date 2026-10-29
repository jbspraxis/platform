@php($names = ['name' => $fullName, 'company' => $company, 'when' => $when])
<x-mail.layout :preheader="__('emails.audit_request.alert.preheader', $names, 'en')" :headline="__('emails.audit_request.alert.headline', $names, 'en')" :internal="true">
    <x-mail.p>{{ __('emails.audit_request.alert.intro', $names, 'en') }}</x-mail.p>
    <x-mail.facts :rows="$facts" />
    <x-mail.p>{{ __('emails.audit_request.alert.approve', $names, 'en') }}</x-mail.p>
    <x-mail.button :url="$approveUrl" :label="__('emails.audit_request.alert.button', [], 'en')" />
    <x-slot:reason>{{ __('emails.common.reason_internal') }}</x-slot:reason>
</x-mail.layout>
