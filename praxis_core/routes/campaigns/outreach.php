<?php

use Illuminate\Support\Facades\Route;

/*
| HQ › Campaigns › Outreach (C2, docs/CAMPAIGNS.md section 10). Required from routes/campaigns.php, inside the HQ
| host's signed-in staff group. Every action inside a screen checks its permission again (outreach.view, .create,
| .approve). Unsubscribe links in outreach emails are on the portal host (routes/email-links.php).
|
|   /campaigns/outreach                       every campaign with its numbers and each sender's day; New campaign
|   /campaigns/outreach/limits                the sending limits (outreach.approve: the owner), senders and pauses
|   /campaigns/outreach/replies               C3 (section 12): replies to every campaign this person sorts
|   /campaigns/outreach/{campaign}            the prospects and their drafts: review, approve, send; Import prospects;
|                                             ?show=replies: the campaign's replies (&reply=<id> opens one)
*/

Route::prefix('campaigns/outreach')->middleware('can:outreach.view')->group(function () {
    Route::get('/', \App\Livewire\Hq\Campaigns\Outreach\OutreachList::class)->name('hq.campaigns.outreach');
    Route::get('/limits', \App\Livewire\Hq\Campaigns\Outreach\OutreachLimits::class)->middleware('can:outreach.approve')->name('hq.campaigns.outreach.limits');
    Route::get('/replies', \App\Livewire\Hq\Campaigns\Outreach\OutreachReplies::class)->name('hq.campaigns.outreach.replies');
    Route::get('/{campaign}', \App\Livewire\Hq\Campaigns\Outreach\OutreachCampaignPage::class)->whereUuid('campaign')->name('hq.campaigns.outreach.campaign');
    // The rich-text editor's script, the same file Broadcasts loads (people with Outreach may not have Broadcasts)
    Route::get('/editor.js', fn () => response()->file(resource_path('campaigns/editor.js'), [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'private, max-age=86400',
    ]))->name('hq.campaigns.outreach.script');
});
