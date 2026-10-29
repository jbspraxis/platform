<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Email preferences | JBS Praxis</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #F5F7FA; color: #0C142B; }
        main { max-width: 480px; margin: 12vh auto; padding: 32px 24px; background: #FFFFFF; border: 1px solid #E4E7EC; border-radius: 12px; }
        h1 { font-size: 1.375rem; margin: 0 0 12px; }
        p { line-height: 1.6; color: #475467; margin: 0 0 24px; }
        button { font: inherit; font-weight: 600; padding: 12px 20px; border: 0; border-radius: 8px; background: #08707C; color: #FFFFFF; cursor: pointer; }
        button:focus-visible { outline: 2px solid #0C142B; outline-offset: 2px; }
        a { color: #08707C; }
    </style>
</head>
<body>
<main>
    @if ($done)
        <h1>You're unsubscribed</h1>
        <p>You will no longer receive JBS Praxis emails. You can close this window.</p>
        <a href="{{ url('/') }}">Go to jbspraxis.com</a>
    @else
        <h1>Unsubscribe from JBS Praxis emails?</h1>
        <p>Confirm below and we'll stop sending you our emails.</p>
        <form method="POST" action="{{ route('tracking.unsubscribe.confirm', ['user_id' => $userId]) }}">
            @csrf
            <button type="submit">Unsubscribe</button>
        </form>
    @endif
</main>
</body>
</html>
