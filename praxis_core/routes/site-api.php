<?php

use App\Http\Controllers\SiteApi\BookingController;
use App\Http\Controllers\SiteApi\CalculatorController;
use App\Http\Controllers\SiteApi\ChatController;
use App\Http\Controllers\SiteApi\EnquiryController;
use App\Http\Controllers\SiteApi\FormsController;
use App\Http\Controllers\SiteApi\KaizenController;
use App\Http\Controllers\SiteApi\NotFoundController;
use App\Http\Controllers\SiteApi\SettingsController;
use App\Http\Controllers\SiteApi\TrackingController;
use App\Support\Hosts;
use Illuminate\Support\Facades\Route;

/*
| The private API for the marketing site (praxis_site): docs/SITE_API.md.
| Loaded by bootstrap/app.php with the stateless 'api' group and the prefix api/site/v1, before
| routes/web.php so HQ's catch-all does not answer first. HQ host only. Every route is signed
| (site.signed: read, write or chat; reads are limited there, forms and chat in their services). The routes are unnamed on purpose:
| named HQ GET routes are treated as staff screens by the HQ page checks.
*/

Route::domain(Hosts::hq())->group(function () {

    Route::middleware('site.signed:read')->group(function () {
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::get('/booking/slots', [BookingController::class, 'slots']);
        Route::get('/calculator/reference', [CalculatorController::class, 'reference']);
        Route::get('/reports/{token}', [CalculatorController::class, 'report'])->where('token', '[A-Za-z0-9-]{1,64}');
        Route::get('/kaizen/articles', [KaizenController::class, 'index']);
        Route::get('/kaizen/articles/{slug}', [KaizenController::class, 'show'])->where('slug', '[A-Za-z0-9-]{1,255}');
        Route::get('/portfolio', [FormsController::class, 'portfolio']);

        // Broadcast email bookkeeping (the site serves the pixel and the confirmation page)
        Route::get('/tracking/open/{campaign_id}/{user_id}', [TrackingController::class, 'open'])
            ->where(['campaign_id' => '[A-Za-z0-9-]{1,64}', 'user_id' => '[A-Za-z0-9-]{1,64}']);
        Route::post('/tracking/unsubscribe/{user_id}', [TrackingController::class, 'unsubscribe'])
            ->where('user_id', '[A-Za-z0-9-]{1,64}');
    });

    // Forms: limited in their shared services, with the old pages (SITE_API.md section 6.2)
    Route::middleware('site.signed:write')->group(function () {
        Route::post('/enquiries', [EnquiryController::class, 'store']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/calculator/assessments', [CalculatorController::class, 'store']);
        Route::post('/kaizen/submissions', [KaizenController::class, 'store']);
        Route::post('/feedback', [FormsController::class, 'feedback']);
        Route::post('/careers/applications', [FormsController::class, 'careers']);
        Route::post('/whitepaper', [FormsController::class, 'whitepaper']);
    });

    Route::post('/chat', [ChatController::class, 'store'])->middleware('site.signed:chat');

    // Last: any other path or method is 404 not_found here, never web.php (SITE_API.md section 6.3)
    Route::any('/', NotFoundController::class);
    Route::any('/{any}', NotFoundController::class)->where('any', '.*');
});
