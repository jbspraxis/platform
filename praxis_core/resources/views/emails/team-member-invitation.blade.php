@php($words = ['name' => $inviterName, 'company' => $company])
<x-mail.layout :preheader="__('emails.team_invitation.preheader')" :headline="__('emails.team_invitation.headline', $words)">
    <x-mail.greeting :first-name="$firstName" :last-name="$lastName" />
    <x-mail.p>{{ __('emails.team_invitation.intro', $words) }}</x-mail.p>
    <x-mail.p>{{ __('emails.team_invitation.about') }}</x-mail.p>
    <x-mail.button :url="$setupUrl" :label="__('emails.team_invitation.button')" />
    <x-mail.small>{{ __('emails.team_invitation.not_expected') }}</x-mail.small>
    <x-mail.signoff />
    <x-slot:reason>{{ __('emails.team_invitation.reason', $words) }}</x-slot:reason>
</x-mail.layout>
