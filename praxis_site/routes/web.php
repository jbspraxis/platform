<?php

use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\KaizenController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\WorkController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| The marketing site. Paths are today's public paths (docs/SITE_API.md section 5), so links in emails
| already sent keep working after the PR3 switch. Every POST: CSRF (web group), a light limit per visitor
| (IPv4 address or IPv6 /64), then FormGuard (honeypot, 3-second signed page-load time, Turnstile when
| its keys are set), Post/Redirect/Get.
*/

// No session or cookie for robots and the email pixel
$stateless = [StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, AddQueuedCookiesToResponse::class, EncryptCookies::class];

Route::get('/robots.txt', [PageController::class, 'robots'])->withoutMiddleware($stateless)->name('robots');

/*
| The pages, registered twice: English at / with the plain names, French under /fr with names prefixed "fr."
| (App\Http\Middleware\SiteLocale). Views name routes once; on a French page route() adds /fr itself.
*/
$pages = function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/how-we-work', [PageController::class, 'howWeWork'])->name('how-we-work');
    Route::get('/company', [PageController::class, 'company'])->name('company');
    Route::get('/security', [PageController::class, 'security'])->name('security');
    Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

    // The proof pages: the work, one page per case study (App\Support\Work), and the sectors behind them
    Route::get('/work', [WorkController::class, 'index'])->name('work');
    Route::get('/work/{slug}', [WorkController::class, 'show'])->where('slug', '[a-z0-9-]{1,100}')->name('work.show');
    Route::get('/industries', [WorkController::class, 'industries'])->name('industries');

    Route::get('/contact', [ContactController::class, 'show'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->middleware(['throttle:forms', 'guard:contact'])->name('contact.store');

    Route::get('/schedule', [ScheduleController::class, 'show'])->name('schedule');
    Route::post('/schedule', [ScheduleController::class, 'store'])->middleware(['throttle:forms', 'guard:schedule'])->name('schedule.store');

    Route::get('/cost-calculator', [CalculatorController::class, 'show'])->name('calculator');
    Route::post('/cost-calculator', [CalculatorController::class, 'store'])->middleware(['throttle:forms', 'guard:calculator'])->name('calculator.store');
    // The report: in the language of the calculator page that made it (email links open the English page)
    Route::get('/dossier/{token}', [ReportController::class, 'show'])->where('token', '[A-Za-z0-9-]{6,100}')->name('report');

    // The QR code on a formal document (docs/DOCUMENTS.md section 7): is it genuine, and does it still stand?
    Route::get('/verify/{token}', [VerifyController::class, 'show'])->where('token', '[A-Za-z0-9]{24,64}')->name('verify');
    Route::get('/verify/{token}/document.pdf', [VerifyController::class, 'file'])->where('token', '[A-Za-z0-9]{24,64}')->name('verify.file');

    Route::get('/kaizen', [KaizenController::class, 'index'])->name('kaizen');
    Route::get('/kaizen/write', [KaizenController::class, 'create'])->name('kaizen.write');
    Route::post('/kaizen/write', [KaizenController::class, 'store'])->middleware(['throttle:forms', 'guard:kaizen'])->name('kaizen.store');
    Route::get('/kaizen/{slug}', [KaizenController::class, 'show'])->where('slug', '[A-Za-z0-9-]{1,200}')->name('kaizen.show');

    Route::get('/services', [ServicesController::class, 'show'])->name('services');
    Route::post('/services', [ServicesController::class, 'store'])->middleware(['throttle:forms', 'guard:services'])->name('services.store');

    Route::get('/careers', [CareersController::class, 'show'])->name('careers');
    Route::post('/careers', [CareersController::class, 'store'])->middleware(['throttle:forms', 'guard:careers'])->name('careers.store');

    Route::get('/feedback', [FeedbackController::class, 'show'])->name('feedback');
    Route::post('/feedback', [FeedbackController::class, 'store'])->middleware(['throttle:forms', 'guard:feedback'])->name('feedback.store');

    Route::post('/chat', [ChatController::class, 'store'])->middleware(['throttle:chat', 'guard:chat'])->name('chat');
};

Route::group([], $pages);
Route::prefix('fr')->name('fr.')->group($pages);

// Old addresses of the previous site (search results, links in emails already sent)
Route::permanentRedirect('/about', '/company');
Route::permanentRedirect('/trust-center', '/security');
Route::permanentRedirect('/portfolio', '/work');
Route::permanentRedirect('/framework', '/how-we-work#method');
Route::permanentRedirect('/kaizen-hub', '/kaizen');
Route::permanentRedirect('/privacy-policy', '/privacy');

// Search engines and AI crawlers
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->withoutMiddleware($stateless)->name('sitemap');
Route::get('/llms.txt', [PageController::class, 'llms'])->withoutMiddleware($stateless)->name('llms');
// IndexNow's key file (php artisan site:indexnow): only the key in config site.indexnow is found
Route::get('/{key}.txt', [PageController::class, 'indexNowKey'])->where('key', '[A-Za-z0-9-]{8,128}')
    ->withoutMiddleware($stateless)->name('indexnow.key');

// Broadcast email links (same paths as today). The pixel counts its own limit: past it the image is still
// returned and only the call to HQ is skipped (TrackingController::open)
Route::get('/tracking/open/{campaign_id}/{user_id}', [TrackingController::class, 'open'])
    ->where(['campaign_id' => '[A-Za-z0-9-]{1,64}', 'user_id' => '[A-Za-z0-9-]{1,64}'])
    ->withoutMiddleware($stateless)->name('tracking.open');
Route::get('/tracking/unsubscribe/{user_id}', [TrackingController::class, 'confirm'])
    ->where('user_id', '[A-Za-z0-9-]{1,64}')->middleware('throttle:unsubscribe')->name('unsubscribe');
Route::post('/tracking/unsubscribe/{user_id}', [TrackingController::class, 'unsubscribe'])
    ->where('user_id', '[A-Za-z0-9-]{1,64}')->middleware(['throttle:unsubscribe', 'guard:unsubscribe'])->name('unsubscribe.store');

// HQ and the client portal used to live on this host (/hq, /client, /portal…): those addresses move to their own
// hosts exactly as praxis_core's App\Support\Hosts::legacyRedirect sends them (/dossier stays here: the report)
Route::any('{old}', [PageController::class, 'legacy'])
    ->where('old', '(hq|taskforce|files|client-portal|client|portal|signoff|verify)(/.*)?')
    ->withoutMiddleware($stateless);
