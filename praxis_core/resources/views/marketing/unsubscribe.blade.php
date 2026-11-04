{{-- The page behind a broadcast's unsubscribe link (EmailLinkController, docs/CAMPAIGNS.md 4.5), in the language the email
     was sent in. States: ask (one button), done, invalid (unknown link), preview (the editor's and test emails' link).
     Outreach emails (section 10) use it too: their own words ($copy, falling back to the broadcast's) and routes. --}}
@php
    $copy ??= 'unsubscribe';
    $pageRoute ??= 'email.unsubscribe';
    $confirmRoute ??= 'email.unsubscribe.confirm';
    $t = fn (string $key, array $with = []) => \Illuminate\Support\Facades\Lang::has("marketing.{$copy}.{$key}") ? __("marketing.{$copy}.{$key}", $with) : __('marketing.unsubscribe.' . $key, $with);
    $contact = config('mail.brand.reply_to.address');
    $mailto = '<a href="mailto:' . e($contact) . '">' . e($contact) . '</a>';
    $other = app()->getLocale() === 'fr' ? 'en' : 'fr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $t('title') }} | JBS Praxis</title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css'])
    <style>
        .unsub h1 { margin: 0 0 12px; font-family: var(--font-serif); font-weight: 400; font-size: 1.75rem; line-height: 2.25rem; color: var(--color-ink); }
        .unsub p { margin: 0 0 12px; font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-slate); }
        .unsub .btn { width: 100%; margin-top: 8px; }
        .unsub-lang { margin-top: 16px; text-align: center; font-size: 0.875rem; }
    </style>
</head>
<body>
<main class="auth-page">
    <div class="auth-card unsub">
        <img class="auth-logo" src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" width="210" height="40">
        @if ($state === 'done')
            <h1>{{ $t('done_heading') }}</h1>
            <p>{{ $email ? $t('done_body', ['email' => $email]) : $t('done_body_plain') }}</p>
            <p>{!! str_replace('@@', $mailto, e($t('mistake', ['contact' => '@@']))) !!}</p>
        @elseif ($state === 'invalid')
            <h1>{{ $t('invalid_heading') }}</h1>
            <p>{!! str_replace('@@', $mailto, e($t('invalid_body', ['contact' => '@@']))) !!}</p>
        @else
            <h1>{{ $t('heading') }}</h1>
            @if ($state === 'preview')
                <div class="alert alert-info mb-4" role="status"><span>{{ $t('preview') }}</span></div>
            @endif
            <p>{{ $email ? $t('body', ['email' => $email]) : $t('body_plain') }}</p>
            <p>{{ $t('keeps') }}</p>
            <form method="POST" action="{{ route($confirmRoute, ['token' => $token]) }}">
                <button type="submit" class="btn btn-primary" @disabled($state === 'preview')>{{ $t('button') }}</button>
            </form>
        @endif
    </div>
    <p class="unsub-lang"><a href="{{ route($pageRoute, ['token' => $token, 'lang' => $other]) }}" lang="{{ $other }}">{{ $t('other_language') }}</a></p>
</main>
</body>
</html>
