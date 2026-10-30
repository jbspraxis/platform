<?php

use Illuminate\Support\Facades\Route;

/*
| PR 3 (writing) owns this file: composer uploads, signature images, anything the composer needs.
| Inside /mail, `can:use-mail`, HQ host. Name routes hq.mail.compose.*
|
|   /mail/outbox                 your scheduled, unsent and unfinished messages (failure notifications link here)
|   /mail/compose/editor.js      the composer's editor (no npm build: a plain file, versioned by ?v=)
*/

Route::get('/outbox', \App\Livewire\Hq\Mail\ComposeOutbox::class)->name('hq.mail.compose.outbox');

Route::get('/compose/editor.js', fn () => response()->file(resource_path('mail/compose.js'), [
    'Content-Type' => 'text/javascript; charset=utf-8',
    'Cache-Control' => 'private, max-age=31536000, immutable',
]))->name('hq.mail.compose.script');
