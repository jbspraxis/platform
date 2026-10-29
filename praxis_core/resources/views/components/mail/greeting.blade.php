{{-- "Hello Ada," in English. French never uses the first name alone: "Bonjour Madame Okafor," when the title is known, otherwise "Bonjour,". --}}
@props(['firstName' => null, 'lastName' => null, 'title' => null])
@php
    $fr = app()->getLocale() === 'fr';
    $firstName = trim((string) $firstName);
    $lastName = trim((string) $lastName);
    $title = in_array($title, ['Madame', 'Monsieur'], true) ? $title : null;
    $text = match (true) {
        $fr && $title && $lastName !== '' => __('emails.common.greeting_titled', ['title' => $title, 'last_name' => $lastName]),
        !$fr && $firstName !== '' => __('emails.common.greeting_named', ['first_name' => $firstName]),
        default => __('emails.common.greeting_plain'),
    };
@endphp
<p style="margin: 0 0 16px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;">{{ $text }}</p>
