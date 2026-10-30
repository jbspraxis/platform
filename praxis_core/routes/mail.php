<?php

use Illuminate\Support\Facades\Route;

/*
| HQ Mail (docs/MAIL.md). Loaded inside the HQ host's signed-in staff group in routes/web.php.
| `use-mail`: the mail.view permission, and Mail open to the person (owner-only until the owner opens it).
| Each mailbox, folder, thread and message is checked again per request by App\Support\Mail\MailAccess.
|
| Pages (PR 1 sets the addresses; each PR owns its own screen):
|   /mail             Inbox (PR 2): ?box=<account id>&folder=<folder id or role>&thread=<thread id>
|   /mail/mailboxes   Mailboxes (PR 1): connect, shared mailboxes, people, passwords, opening Mail to everyone
|   /mail/signature   Signature (PR 3)
| Each PR adds its own other routes in its own file under routes/mail/ (read.php PR 2, compose.php PR 3,
| team.php PR 4), so the parallel PRs never edit the same route file.
*/

Route::prefix('mail')->middleware('can:use-mail')->group(function () {
    Route::get('/', \App\Livewire\Hq\Mail\Inbox::class)->name('hq.mail.inbox');
    Route::get('/mailboxes', \App\Livewire\Hq\Mail\Mailboxes::class)->name('hq.mail.mailboxes');
    Route::get('/signature', \App\Livewire\Hq\Mail\Signature::class)->name('hq.mail.signature');

    foreach (['read', 'compose', 'team'] as $part) {
        require __DIR__ . "/mail/{$part}.php";
    }
});
