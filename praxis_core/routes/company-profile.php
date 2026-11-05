<?php

use App\Http\Controllers\CompanyProfile\LogoFileController;
use App\Http\Controllers\CompanyProfileController;
use Illuminate\Support\Facades\Route;

/*
| HQ › Company profile (company-profile/CONTRACT.md, docs/COMPANY_PROFILE.md). Loaded inside the HQ host's
| signed-in staff group in routes/web.php. The module carries the teams' credentials, so it never leaves HQ and
| every screen is behind company_profile.view; the actions inside check edit, approve and manage again.
|
|   /company-profile                                 the section editor, English and French side by side
|   /company-profile/versions                        every version, who wrote it, who approved it
|   /company-profile/versions/{version}/preview      a version exactly as the renderers read it
|   /company-profile/download                        the PDF (route name: company-profile.download)
|   /company-profile/logos                           the client logo library: upload, name, sector, order, where it shows
|   /company-profile/logos/{logo}/{kind}             the bytes of one logo (web, png or print), for HQ only
|   /company-profile/logos.js                        the logo screen's script; plain script, no build step
*/

Route::prefix('company-profile')->middleware('can:company_profile.view')->group(function () {
    Route::get('/', \App\Livewire\Hq\CompanyProfile\ProfileEditor::class)->name('hq.company-profile.editor');
    Route::get('/versions', \App\Livewire\Hq\CompanyProfile\ProfileVersions::class)->name('hq.company-profile.versions');

    // The name the three company-profile PRs agreed on in company-profile/CONTRACT.md; it keeps the plain
    // name on purpose, so PR 2 and PR 3 link to it without knowing it is mounted under HQ.
    Route::get('/download', [CompanyProfileController::class, 'download'])->name('company-profile.download');

    Route::get('/versions/{version}/preview', \App\Livewire\Hq\CompanyProfile\ProfilePreview::class)
        ->whereUuid('version')->name('hq.company-profile.preview');

    // The client logo library (PR 3): the one place a client logo is kept, read by the profile above, by the
    // deck, and by the strip on jbspraxis.com through the Site API.
    Route::get('/logos', \App\Livewire\Hq\CompanyProfile\LogoLibrary::class)->name('hq.company-profile.logos');

    Route::get('/logos/{logo}/{kind}', [LogoFileController::class, 'show'])
        ->whereUuid('logo')->where('kind', 'web|png|print')
        ->name('hq.company-profile.logo-file');

    Route::get('/logos.js', fn () => response()->file(resource_path('company-profile/logos.js'), [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'private, max-age=86400',
    ]))->name('hq.company-profile.script');
});
