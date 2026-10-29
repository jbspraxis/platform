{{-- System Health emails to the team (internal, English only): what happened, the monitor's detail with its line breaks, and the way into HQ. --}}
<x-mail.layout :preheader="$preheader" :headline="$headline" :internal="true">
    @foreach ($paragraphs as $paragraph)
        <x-mail.p>{{ $paragraph }}</x-mail.p>
    @endforeach
    @if ($facts)
        <x-mail.facts :rows="$facts" />
    @endif
    @if ($detailsTitle)
        <x-mail.p><strong style="font-weight: 600; color: #0C142B;">{{ $detailsTitle }}</strong></x-mail.p>
    @endif
    @if (filled($details))
        <x-mail.quote>{{ $details }}</x-mail.quote>
    @endif
    @if ($outro)
        <x-mail.p>{{ $outro }}</x-mail.p>
    @endif
    <x-mail.button :url="$healthUrl" :label="__('emails.system.button')" />
    @if ($note)
        <x-mail.small>{{ $note }}</x-mail.small>
    @endif
    <x-slot:reason>{{ __('emails.common.reason_internal') }}</x-slot:reason>
</x-mail.layout>
