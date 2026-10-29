{{-- "Hello Ada," in English. French never uses the first name alone: "Bonjour Madame Okafor," when the title is known, otherwise "Bonjour,".
     A first word that is an honorific ("Dr", "Engr.", "Chief") is not a name, so it falls back to "Hello,". --}}
@props(['firstName' => null, 'lastName' => null, 'title' => null])
@php
    $fr = app()->getLocale() === 'fr';
    $firstName = trim((string) $firstName);
    if (preg_match('/^(mr|mrs|ms|miss|dr|prof|engr|chief|alhaji|alhaja|hon|rev|pastor|barr|arc|sir|madam|mme|mlle|m)\.?$/iu', $firstName)) {
        $firstName = '';
    } elseif ($firstName !== '' && $firstName === mb_strtolower($firstName)) {
        $firstName = mb_convert_case($firstName, MB_CASE_TITLE);
    }
    $lastName = trim((string) $lastName);
    $title = in_array($title, ['Madame', 'Monsieur'], true) ? $title : null;
    $text = match (true) {
        $fr && $title && $lastName !== '' => __('emails.common.greeting_titled', ['title' => $title, 'last_name' => $lastName]),
        !$fr && $firstName !== '' => __('emails.common.greeting_named', ['first_name' => $firstName]),
        default => __('emails.common.greeting_plain'),
    };
@endphp
<p style="margin: 0 0 16px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;">{{ $text }}</p>
