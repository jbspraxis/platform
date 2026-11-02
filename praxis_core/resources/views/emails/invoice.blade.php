<x-mail.layout :preheader="__('emails.invoice.preheader', ['number' => $invoice->invoice_number])" :headline="__('emails.invoice.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.invoice.intro', ['project' => $invoice->project_title ?? '']) }}</x-mail.p>
    <x-mail.facts :rows="$facts" />
    <x-mail.button :url="$portalUrl" :label="__('emails.invoice.button')" />
    <x-mail.small>{{ $hasPdf ? __('emails.invoice.pdf') . ' ' : '' }}{{ __('emails.invoice.how', ['number' => $invoice->invoice_number]) }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.invoice.reason', ['company' => $company]) }}</x-slot:reason>
</x-mail.layout>
