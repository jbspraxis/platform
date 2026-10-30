<?php

use App\Http\Controllers\Pwa\NotificationOpenController;
use App\Http\Controllers\Pwa\PushSubscriptionController;
use App\Http\Controllers\Pwa\PwaController;
use App\Support\Hosts;
use Illuminate\Support\Facades\Route;

/*
| HQ as an app (docs/PWA.md), on the HQ host only: the portal and the public site never answer these.
| The first group has no session and sets no cookies, so its answers can be cached; the second is for
| signed-in staff.
*/

Route::domain(Hosts::hq())->group(function () {
    Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
    Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');
    Route::get('/pwa/ping', [PwaController::class, 'ping'])->name('pwa.ping');
    Route::get('/pwa/v/{version}/{path}', [PwaController::class, 'asset'])
        ->where(['version' => '[a-f0-9]{10}', 'path' => '[A-Za-z0-9][A-Za-z0-9/_.-]*'])
        ->name('pwa.asset');

    Route::middleware(['web', 'auth', 'hq.staff'])->group(function () {
        Route::get('/pwa/go/{section}', [PwaController::class, 'go'])
            ->whereIn('section', ['workspace', 'leads', 'projects', 'finance'])
            ->name('pwa.go');
        Route::get('/pwa/open/{notification}', NotificationOpenController::class)->name('pwa.open');

        Route::post('/pwa/push/subscription', [PushSubscriptionController::class, 'store'])->middleware('throttle:30,1')->name('pwa.push.store');
        Route::post('/pwa/push/subscription/delete', [PushSubscriptionController::class, 'destroy'])->middleware('throttle:30,1')->name('pwa.push.destroy');
        Route::post('/pwa/push/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:6,1')->name('pwa.push.test');
    });
});
