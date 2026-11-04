<?php

use Illuminate\Support\Facades\Route;

/*
| HQ › Campaigns (docs/CAMPAIGNS.md). Loaded inside the HQ host's signed-in staff group in routes/web.php.
| Two tools in one area: Broadcasts (C1, here) and Outreach (C2, which adds its own file routes/campaigns/outreach.php
| and its rail link). Every action inside a screen checks its permission again (broadcasts.view, .create, .approve).
|
|   /campaigns                              → Broadcasts
|   /campaigns/broadcasts                   the list
|   /campaigns/broadcasts/{broadcast}       a draft: write it, choose who gets it, test it, send or schedule it
|   /campaigns/broadcasts/{broadcast}/report  what happened: sent, opens, clicks, unsubscribes, each recipient
|   /campaigns/audiences                    saved segments, the subscriber list and the do-not-email list
*/

Route::prefix('campaigns')->middleware('can:broadcasts.view')->group(function () {
    Route::redirect('/', '/campaigns/broadcasts')->name('hq.campaigns');
    Route::get('/broadcasts', \App\Livewire\Hq\Campaigns\BroadcastList::class)->name('hq.campaigns.broadcasts');
    Route::get('/broadcasts/{broadcast}', \App\Livewire\Hq\Campaigns\BroadcastEditor::class)->whereUuid('broadcast')->name('hq.campaigns.broadcast');
    Route::get('/broadcasts/{broadcast}/report', \App\Livewire\Hq\Campaigns\BroadcastReport::class)->whereUuid('broadcast')->name('hq.campaigns.report');
    Route::get('/audiences', \App\Livewire\Hq\Campaigns\Audiences::class)->name('hq.campaigns.audiences');
    // The editor's script (blocks, rich text, live preview); plain script, no build step
    Route::get('/editor.js', fn () => response()->file(resource_path('campaigns/editor.js'), [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'private, max-age=86400',
    ]))->name('hq.campaigns.script');
});

// Outreach (C2): its own file and permission (outreach.view), so it opens for people without Broadcasts
require __DIR__ . '/campaigns/outreach.php';
