<x-mail.layout :preheader="__('emails.receipt.preheader', ['amount' => $amount, 'invoice' => $receipt->invoice_number])" :headline="__('emails.receipt.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.receipt.intro', ['project' => $receipt->project_title ?? '']) }}</x-mail.p>
    <x-mail.facts :rows="$facts" />
    @if ($left <= 0)
        <x-mail.p>{{ __('emails.receipt.fully_paid', ['invoice' => $receipt->invoice_number]) }}</x-mail.p>
    @endif
    <x-mail.button :url="$portalUrl" :label="__('emails.receipt.button')" />
    <x-mail.small>{{ __('emails.receipt.portal') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.receipt.reason', ['company' => $company]) }}</x-slot:reason>
</x-mail.layout>
