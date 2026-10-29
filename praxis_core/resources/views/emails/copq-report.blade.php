<x-mail.layout :preheader="__('emails.copq.preheader')" :headline="__('emails.copq.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ $company === null ? __('emails.copq.intro_plain', ['amount' => $amount]) : __('emails.copq.intro', ['company' => $company, 'amount' => $amount]) }}</x-mail.p>
    <x-mail.p>{{ __('emails.copq.explain') }}</x-mail.p>
    <x-mail.facts :rows="[
        __('emails.copq.label_amount') => $amount,
        __('emails.copq.label_date') => $preparedOn,
    ]" />
    <x-mail.button :url="$reportUrl" :label="__('emails.copq.button')" />
    @if (__('emails.copq.report_language') !== '')
        <x-mail.small>{{ __('emails.copq.report_language') }}</x-mail.small>
    @endif
    <x-mail.small>{{ __('emails.copq.share') }}</x-mail.small>
    <x-mail.small>{{ __('emails.copq.estimate_note') }}</x-mail.small>
    @if ($company !== null)
        <x-mail.small>{{ __('emails.common.confidential', ['company' => $company]) }}</x-mail.small>
    @endif
    <x-mail.signoff />
    <x-slot:reason>{{ $fromHq ? __('emails.copq.reason_hq') : __('emails.common.reason_contact') }}</x-slot:reason>
</x-mail.layout>
