<?php

use App\Http\Controllers\Mail\AttachmentController;
use Illuminate\Support\Facades\Route;

/*
| PR 2 (reading) owns this file: attachment downloads, inline pictures (cid:), remote-image proxy if any.
| Inside /mail, `can:use-mail`, HQ host. Name routes hq.mail.read.*
|
| Each file is read from the mail server when asked for (HQ keeps no copy); MailFetch checks the person can
| read the mailbox. Remote pictures load straight from the sender's server once the person allows them.
*/

Route::prefix('read')->group(function () {
    Route::get('/attachment/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('hq.mail.read.attachment');
    Route::get('/inline/{message}/{cid}', [AttachmentController::class, 'inline'])->whereNumber('message')->where('cid', '.+')->name('hq.mail.read.inline');
    Route::get('/older/{folder}/{uid}/{section}', [AttachmentController::class, 'live'])->whereNumber(['folder', 'uid'])->name('hq.mail.read.live');
});
