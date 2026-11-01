<x-mail.layout :preheader="__('emails.sow.preheader', ['title' => $sow->title])" :headline="__('emails.sow.headline')">
    <x-mail.greeting :first-name="$firstName" />
    <x-mail.p>{{ $sow->version > 1 ? __('emails.sow.intro_version', ['title' => $sow->title, 'n' => $sow->version]) : __('emails.sow.intro', ['title' => $sow->title]) }}</x-mail.p>
    @if ($changes)
        <p style="margin: 8px 0 8px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 24px; font-weight: 600; color: #0C142B;">{{ __('emails.sow.changes', ['n' => $sow->version - 1]) }}</p>
        @foreach ($changes as $block)
            @if (isset($block['items']))
                @if ($block['heading'])
                    <x-mail.p>{{ $block['heading'] }}</x-mail.p>
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
    @endif
    <x-mail.facts :rows="$facts" />
    @if ($portalUrl)
        <x-mail.button :url="$portalUrl" :label="__('emails.sow.button')" />
    @endif
    <x-mail.small>{{ $hasPdf ? __('emails.sow.pdf') . ' ' : '' }}{{ __('emails.sow.how') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.sow.reason', ['company' => $company]) }}</x-slot:reason>
</x-mail.layout>
