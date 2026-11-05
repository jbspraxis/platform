{{--
    An agreement sent for signature, and the signed copy that follows (docs/DOCUMENTS.md section 12).
    The one-time code never travels in this email: it is asked for on the signing page and sent on its own.
--}}
<x-mail.layout :preheader="__('emails.agreement.' . $kind . '_preheader', ['title' => $title])" :headline="__('emails.agreement.' . $kind . '_headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.agreement.' . $kind . '_intro', ['title' => $title]) }}</x-mail.p>
    <x-mail.facts :rows="$facts" />

    @if ($kind === 'request')
        <x-mail.p>{{ __('emails.agreement.request_how') }}</x-mail.p>
        @if ($signingUrl)
            <x-mail.button :url="$signingUrl" :label="__('emails.agreement.request_button')" />
            <x-mail.small>{{ $expires ? __('emails.agreement.request_expires_on', ['date' => $expires]) : __('emails.agreement.request_expires', ['days' => $days]) }} {{ __('emails.agreement.request_private') }}</x-mail.small>
        @endif
        @if ($hasPdf)
            <x-mail.small>{{ __('emails.agreement.request_pdf') }}</x-mail.small>
        @endif
    @else
        <x-mail.p>{{ __('emails.agreement.signed_record') }}</x-mail.p>
        @if ($hasPdf)
            <x-mail.small>{{ __('emails.agreement.signed_pdf') }}</x-mail.small>
        @endif
    @endif

    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.agreement.' . $kind . '_reason') }}</x-slot:reason>
</x-mail.layout>
