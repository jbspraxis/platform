@php($key = $purpose . ($project !== '' ? '' : '_generic'))
<x-mail.layout :preheader="__('emails.signing.' . $key . '_preheader', ['project' => $project])" :headline="__('emails.signing.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.signing.' . $key . '_intro', ['project' => $project]) }} {{ __('emails.signing.' . $purpose . '_next') }}</x-mail.p>
    <x-mail.code :code="$code" :minutes="$minutes" />
    @if ($signer !== '')
        <x-mail.p>{{ __('emails.signing.signer', ['signer' => $signer]) }}</x-mail.p>
    @endif
    <x-mail.small>{{ __('emails.signing.not_expected') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.signing.reason') }}</x-slot:reason>
</x-mail.layout>
