<?php

use App\Http\Controllers\LetterheadController;
use Illuminate\Support\Facades\Route;

/*
| HQ › Documents (docs/DOCUMENTS.md). Loaded inside the HQ host's signed-in staff group in routes/web.php.
| Owner decision, 4 November 2026: the Owner role only, until the owner ticks the letters module for other
| roles on Roles and permissions. Every action inside a screen checks its own permission again.
|
|   /documents                      the register: every document issued, with its reference and status
|   /documents/signature            your own signature: draw it or upload it once
|   /documents/{document}           one document: its fields, the preview, signing, issuing
|   /documents/{document}/pdf       the PDF (the issued file once it is issued, else a preview of the draft)
|   /documents/blank/{entity}.pdf   a blank letterhead to print or write on
|   /documents/blank/{entity}.docx  the same letterhead as a Word document
*/

Route::prefix('documents')->middleware('can:letters.view')->group(function () {
    Route::get('/', \App\Livewire\Hq\Letters\DocumentRegister::class)->name('hq.letters.register');
    Route::get('/signature', \App\Livewire\Hq\Letters\MySignature::class)->name('hq.letters.signature');

    Route::get('/blank/{entity}.pdf', [LetterheadController::class, 'blankPdf'])->whereUuid('entity')->name('hq.letters.blank-pdf');
    Route::get('/blank/{entity}.docx', [LetterheadController::class, 'blankDocx'])->whereUuid('entity')->name('hq.letters.blank-docx');

    Route::get('/{document}', \App\Livewire\Hq\Letters\DocumentPage::class)->whereUuid('document')->name('hq.letters.document');
    Route::get('/{document}/pdf', [LetterheadController::class, 'documentPdf'])->whereUuid('document')->name('hq.letters.pdf');

    // The screens' script (the signature pad and the rich-text body); plain script, no build step. The views
    // add ?v=<file time>, so a changed file has a new address and each one can be kept for a year
    Route::get('/editor.js', fn () => response()->file(resource_path('letters/documents.js'), [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'private, max-age=31536000, immutable',
    ]))->name('hq.letters.script');
});
