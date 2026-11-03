<?php

use App\Http\Controllers\EmailLinkController;
use Illuminate\Support\Facades\Route;

/*
| Links inside broadcasts (docs/CAMPAIGNS.md 4.5), on the portal host with every other link sent to clients. Loaded in
| routes/web.php's portal group. {token} is the recipient's own secret (broadcast_logs.token, 40 characters), so a
| link only ever acts for the person it was sent to; 'preview' is the editor's and test emails' harmless stand-in.
|
|   GET  /email/unsubscribe/{token}   the page: one button, in the person's language (scanners open links, so a GET never unsubscribes)
|   POST /email/unsubscribe/{token}   that button, and the inbox's own one-click unsubscribe (RFC 8058, no CSRF token)
|   GET  /email/open/{token}.png      the open-counting pixel
|   GET  /email/click/{token}/{link}  counts the click, then goes to the link's address (only addresses the broadcast holds)
|   GET  /email/img/{file}            pictures in broadcasts, for email clients
*/

Route::prefix('email')->group(function () {
    Route::get('/unsubscribe/{token}', [EmailLinkController::class, 'unsubscribePage'])->where('token', '[A-Za-z0-9]{7,40}')
        ->middleware('throttle:30,1')->name('email.unsubscribe');
    // Inboxes post one-click unsubscribes from a few servers: a higher limit than the page
    Route::post('/unsubscribe/{token}', [EmailLinkController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{7,40}')
        ->middleware('throttle:120,1')->name('email.unsubscribe.confirm');
    // Mail image proxies (Gmail, Outlook) fetch for many readers from a few addresses: a high limit
    Route::get('/open/{token}.png', [EmailLinkController::class, 'open'])->where('token', '[A-Za-z0-9]{40}')
        ->middleware('throttle:600,1')->name('email.open');
    Route::get('/click/{token}/{link}', [EmailLinkController::class, 'click'])->where(['token' => '[A-Za-z0-9]{40}', 'link' => '[0-9]{1,12}'])
        ->middleware('throttle:120,1')->name('email.click');
    Route::get('/img/{file}', [EmailLinkController::class, 'image'])->where('file', '[0-9a-f-]{36}\.(jpg|png)')
        ->middleware('throttle:600,1')->name('email.image');
});
