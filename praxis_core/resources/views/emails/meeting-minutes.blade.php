<x-mail.layout :preheader="__('emails.minutes.preheader', ['title' => $meeting->title])" :headline="__('emails.minutes.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ __('emails.minutes.intro', ['title' => $meeting->title, 'date' => $meetingDate]) }}</x-mail.p>
    @foreach ($blocks as $block)
        @if (isset($block['items']))
            @if ($block['heading'])
                <p style="margin: 8px 0 8px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 24px; font-weight: 600; color: #0C142B;">{{ $block['heading'] }}</p>
            @endif
            <ul style="margin: 0 0 16px; padding-left: 22px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;">
                @foreach ($block['items'] as $item)
                    <li style="margin: 0 0 4px;">{{ $item }}</li>
                @endforeach
            </ul>
        @else
            <x-mail.p>{!! nl2br(e($block['text'])) !!}</x-mail.p>
        @endif
    @endforeach
    @if ($portalUrl)
        <x-mail.button :url="$portalUrl" :label="__('emails.minutes.button')" />
    @endif
    <x-mail.small>{{ __('emails.minutes.corrections') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.minutes.reason', ['company' => $company]) }}</x-slot:reason>
</x-mail.layout>
