@php($k = 'emails.client_meeting.' . $variant)
<x-mail.layout :preheader="__($k . '.preheader', ['company' => $company])" :headline="__($k . '.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __($k . '.intro', ['company' => $company, 'date' => $meetingDate]) }}</x-mail.p>
    <x-mail.facts :rows="[
        __('emails.client_meeting.label_meeting') => $meeting->title,
        __('emails.meeting.label_date') => $meetingDate,
        __('emails.meeting.label_time') => $meetingTime,
        __('emails.meeting.label_format') => $variant === 'cancelled' ? null : __('emails.client_meeting.format'),
    ]" />
    @if ($variant !== 'cancelled' && filled($meeting->agenda))
        <x-mail.small>{{ __('emails.client_meeting.agenda') }}</x-mail.small>
        <x-mail.quote>{{ $meeting->agenda }}</x-mail.quote>
    @endif
    @if ($variant !== 'cancelled' && $meeting->meet_link)
        <x-mail.button :url="$meeting->meet_link" :label="__('emails.meeting.button')" />
        <x-mail.small>{{ __('emails.client_meeting.notice') }}</x-mail.small>
        @if ($bookingUrl)
            <x-mail.small>{{ __('emails.client_meeting.another_time') }} <a href="{{ $bookingUrl }}" style="color: #08707C;">{{ __('emails.client_meeting.book_link') }}</a></x-mail.small>
        @endif
    @elseif ($bookingUrl)
        <x-mail.button :url="$bookingUrl" :label="__('emails.client_meeting.book_button')" />
    @endif
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.client_meeting.reason', ['company' => $company]) }}</x-slot:reason>
</x-mail.layout>
