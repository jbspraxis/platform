<?php

use App\Http\Controllers\CompanyProfileController;
use Illuminate\Support\Facades\Route;

/*
| HQ › Company profile (docs/COMPANY_PROFILE.md). Loaded inside the HQ host's signed-in staff group in
| routes/web.php. The module carries the teams' credentials, so it never leaves HQ and every screen is behind
| company_profile.view; the actions inside check edit, approve and manage again.
|
|   /company-profile                                 the section editor, English and French side by side
|   /company-profile/versions                        every version, who wrote it, who approved it
|   /company-profile/versions/{version}/preview      a version exactly as the renderers read it
|   /company-profile/download                        the PDF (route name: company-profile.download)
*/

Route::prefix('company-profile')->middleware('can:company_profile.view')->group(function () {
    Route::get('/', \App\Livewire\Hq\CompanyProfile\ProfileEditor::class)->name('hq.company-profile.editor');
    Route::get('/versions', \App\Livewire\Hq\CompanyProfile\ProfileVersions::class)->name('hq.company-profile.versions');

    // The name the three company-profile PRs agreed on in company-profile/CONTRACT.md; it keeps the plain
    // name on purpose, so PR 2 and PR 3 link to it without knowing it is mounted under HQ.
    Route::get('/download', [CompanyProfileController::class, 'download'])->name('company-profile.download');

    Route::get('/versions/{version}/preview', \App\Livewire\Hq\CompanyProfile\ProfilePreview::class)
        ->whereUuid('version')->name('hq.company-profile.preview');
});
