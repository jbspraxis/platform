@php($names = ['name' => $fullName, 'company' => $company])
<x-mail.layout :preheader="__('emails.enquiry.alert.preheader', $names, 'en')" :headline="__('emails.enquiry.alert.headline', $names, 'en')" :internal="true">
    <x-mail.p>{{ __('emails.enquiry.alert.intro', $names, 'en') }}</x-mail.p>
    <x-mail.facts :rows="$facts" />
    <x-mail.small>{{ __('emails.enquiry.alert.message_label', [], 'en') }}</x-mail.small>
    <x-mail.quote>{{ $enquiry }}</x-mail.quote>
    <x-mail.p>{{ __('emails.enquiry.alert.reply', $names, 'en') }}</x-mail.p>
    <x-mail.button :url="$leadsUrl" :label="__('emails.enquiry.alert.button', [], 'en')" />
    <x-slot:reason>{{ __('emails.common.reason_internal') }}</x-slot:reason>
</x-mail.layout>
