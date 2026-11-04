{{-- The key vault's emails to the owner (internal, English only): the code that opens one item, or the notice that one was opened. --}}
<x-mail.layout :preheader="__('emails.key_vault.' . $kind . '_preheader', ['item' => $item])" :headline="__('emails.key_vault.' . $kind . '_headline')" :internal="true">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.key_vault.' . $kind . '_intro', ['item' => $item]) }}</x-mail.p>
    @if ($kind === 'code')
        <x-mail.code :code="$code" :minutes="$minutes" />
    @else
        <x-mail.facts :rows="$facts" />
        <x-mail.button :url="$vaultUrl" :label="__('emails.key_vault.button')" />
    @endif
    <x-mail.small>{{ __('emails.key_vault.' . $kind . '_not_you') }}</x-mail.small>
    <x-slot:reason>{{ __('emails.common.reason_internal') }}</x-slot:reason>
</x-mail.layout>
