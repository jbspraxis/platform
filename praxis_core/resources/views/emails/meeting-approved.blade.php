<x-mail.layout :preheader="__('emails.meeting.preheader')" :headline="__('emails.meeting.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.meeting.intro', ['company' => $companyName]) }}</x-mail.p>
    <x-mail.facts :rows="[
        __('emails.meeting.label_date') => $meetingDate,
        __('emails.meeting.label_time') => $meetingTime,
        __('emails.meeting.label_format') => __('emails.meeting.format_online'),
    ]" />
    <x-mail.button :url="$meetingLink" :label="__('emails.meeting.button')" />
    <x-mail.p>{{ __('emails.meeting.prepare') }}</x-mail.p>
    @if (str_contains((string) $meetingLink, 'meet.google.com'))
        {{-- Every client invite carries the transcription notice (docs/OPPORTUNITIES.md section 1) --}}
        <x-mail.small>{{ __('emails.client_meeting.notice') }}</x-mail.small>
    @endif
    <x-mail.small>{{ __('emails.meeting.reschedule') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.meeting.reason', ['company' => $companyName]) }}</x-slot:reason>
</x-mail.layout>
