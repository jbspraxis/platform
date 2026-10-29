<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Support\Hosts;

// Public Livewire components
use App\Livewire\ConversionHub;
use App\Livewire\ScheduleConsultation;
use App\Livewire\ContactHub;
use App\Livewire\TheFirm;
use App\Livewire\Portfolio;
use App\Livewire\Services;
use App\Livewire\Careers;
use App\Livewire\Kaizen;
use App\Livewire\TelemetryNode;
use App\Http\Controllers\SitemapController;

/*
| Three hosts, one app (config/app.php 'domains'):
|   jbspraxis.com          the public site, website forms, broadcast tracking
|   hq.jbspraxis.com       HQ for staff, and the task force tracker
|   portal.jbspraxis.com   the client portal and every token link sent to clients
| Each host keeps its own login cookie. HQ and the portal answer only their own routes;
| old single-host addresses (/hq/..., /client/..., /portal/...) redirect to their new home.
*/

// Everything a host doesn't serve itself, except Livewire's endpoints, signed storage links and /up
$notOwnRoute = '^(?!livewire-|storage/|up$).*';
$legacy = fn (Request $request) => Hosts::legacyRedirect($request);

// =========================================================
// HQ (hq.jbspraxis.com): staff and the task force
// =========================================================
Route::domain(Hosts::hq())->group(function () use ($notOwnRoute, $legacy) {

    Route::get('/', fn () => redirect()->route(Auth::guard('web')->check() ? 'hq.dash.workspace' : 'login'))->name('hq.home');

    // HQ Auth Routes
    Route::get('/login', \App\Livewire\Hq\Auth\LoginTerminal::class)->name('login');
    Route::get('/logout', function () {
        Auth::logout(); request()->session()->invalidate(); request()->session()->regenerateToken();
        return redirect()->route('login');
    })->name('hq.logout');
    // Setup link for a newly invited staff member (rate-limited so tokens can't be guessed at speed)
    Route::get('/setup/{token}', \App\Livewire\Hq\Auth\SetupAccount::class)->middleware('throttle:30,1')->name('hq.setup');

    // Protected HQ Routes: staff only. Each screen needs its module's view permission
    // (can:<module>.view, see config/access.php); actions inside are checked per component.
    Route::middleware(['auth', 'hq.staff'])->group(function () {

        // 1. COMMAND OVERVIEW (the workspace is open to every staff member)
        Route::get('/dashboard/admin', \App\Livewire\Hq\Dashboards\GlobalTelemetry::class)->middleware('can:dashboard.view')->name('hq.dash.admin');
        Route::get('/dashboard/workspace', \App\Livewire\Hq\Dashboards\MyWorkspace::class)->name('hq.dash.workspace');
        Route::get('/dashboard/investor', \App\Livewire\Hq\Dashboards\InvestorTerminal::class)->middleware('can:dashboard.view')->name('hq.dash.investor');
        Route::get('/dashboard/audit', \App\Livewire\Hq\Dashboards\AuditTerminal::class)->middleware('can:audit.view')->name('hq.dash.audit');

        // 2. MASTER DATA MANAGEMENT (MDM)
        Route::prefix('mdm')->group(function () {
            Route::middleware('can:mdm.view')->group(function () {
                // Financial Sub-Module
                Route::get('/dictionary', \App\Livewire\Hq\Mdm\FinancialDictionary::class)->name('hq.mdm.dictionary');
                Route::get('/copq-matrices', \App\Livewire\Hq\Mdm\CopqMatrices::class)->name('hq.mdm.copq');
                Route::get('/tax-compliance', \App\Livewire\Hq\Mdm\TaxCompliance::class)->name('hq.mdm.tax');
                Route::get('/remittance', \App\Livewire\Hq\Mdm\RemittanceAccounts::class)->name('hq.mdm.remittance');

                // Corporate Entities
                Route::get('/legal-entities', \App\Livewire\Hq\Mdm\LegalEntityRegistry::class)->name('hq.mdm.legal');
            });
            // People records belong to HR
            Route::get('/employees', \App\Livewire\Hq\Mdm\EmployeeRegistry::class)->middleware('can:hr.view')->name('hq.mdm.employees');
        });

        // HUMAN CAPITAL
        Route::prefix('hr')->middleware('can:hr.view')->group(function () {
            Route::get('/time-attendance', \App\Livewire\Hq\Hr\TimeAttendance::class)->name('hq.hr.time');
            Route::get('/compliance-contracts', \App\Livewire\Hq\Hr\ContractsAppraisals::class)->name('hq.hr.compliance');
        });

        // 3. FINANCIAL DISPATCH (Treasury, Ledger, Tax & Accounting)
        Route::prefix('finance')->group(function () {
            // Treasury & Expenses
            Route::get('/cash-request', \App\Livewire\Hq\Finance\CashRequestSubmitter::class)->middleware('can:expenses.create')->name('hq.finance.request');
            Route::get('/cash-manager', \App\Livewire\Hq\Finance\CashRequestManager::class)->middleware('can:expenses.view')->name('hq.finance.manager');

            Route::middleware('can:finance.view')->group(function () {
                Route::get('/budget-matrix', \App\Livewire\Hq\Finance\ProjectBudgetManager::class)->name('hq.finance.budgets');

                // Invoicing & Escrow
                Route::get('/invoice-ledger', \App\Livewire\Hq\Finance\InvoiceLedger::class)->name('hq.finance.invoices');
                Route::get('/escrow-verification', \App\Livewire\Hq\Finance\EscrowVerification::class)->name('hq.finance.escrow');

                // Core IFRS Accounting
                Route::get('/chart-of-accounts', \App\Livewire\Hq\Finance\ChartOfAccountsManager::class)->name('hq.finance.coa');
                Route::get('/general-ledger', \App\Livewire\Hq\Finance\GeneralLedgerManager::class)->name('hq.finance.gl');

                // Executive BI (The Financial Matrix)
                Route::get('/executive-bi', \App\Livewire\Hq\Finance\ExecutiveBiDashboard::class)->name('hq.finance.bi');
            });

            // Payroll & Tax Engine
            Route::middleware('can:payroll.view')->group(function () {
                Route::get('/payroll', \App\Livewire\Hq\Finance\PayrollManager::class)->name('hq.finance.payroll');
                Route::get('/tax-configurator', \App\Livewire\Hq\Finance\TaxConfigurator::class)->name('hq.finance.tax-config');
                Route::get('/variable-compensation', \App\Livewire\Hq\Finance\VariableCompensation::class)->name('hq.finance.variables');
                Route::get('/compliance-matrix', \App\Livewire\Hq\Finance\ComplianceMatrix::class)->name('hq.finance.matrix');
                Route::get('/payslips', \App\Livewire\Hq\Finance\PayslipInspector::class)->name('hq.finance.payslips');
            });
        });

        // 4. INTELLIGENCE DESK (CRM / Lead Management)
        Route::prefix('intelligence')->group(function () {
            Route::middleware('can:crm.view')->group(function () {
                Route::get('/leads', \App\Livewire\Hq\Intelligence\LeadsRegister::class)->name('hq.crm.leads');
                Route::get('/consultation', \App\Livewire\Hq\Intelligence\LiveConsultation::class)->name('hq.crm.consult');
                Route::get('/proposal-builder/{id?}', \App\Livewire\Hq\Intelligence\ProposalBuilder::class)->name('hq.crm.proposal');
            });
            Route::get('/broadcast-ledger', \App\Livewire\Hq\Intelligence\BroadcastLedger::class)->middleware('can:broadcasts.view')->name('hq.crm.broadcast');
        });

        // 5. MARKETING & ROAS
        Route::prefix('marketing')->middleware('can:marketing.view')->group(function () {
            Route::get('/campaigns', \App\Livewire\Hq\Marketing\MarketingSync::class)->name('hq.marketing.campaigns');
        });

        // 6. OPERATIONS & DEPLOYMENT MATRIX
        Route::prefix('operations')->group(function () {
            Route::middleware('can:projects.view')->group(function () {
                Route::get('/active-deployments', \App\Livewire\Hq\Operations\ActiveDeployments::class)->name('hq.ops.deployments');
                Route::get('/scrum-master/{id}', \App\Livewire\Hq\Operations\ScrumMaster::class)->name('hq.ops.scrum');
                Route::get('/sprint-boards', \App\Livewire\Hq\Operations\SprintBoardDirectory::class)->name('hq.ops.boards.directory');
                Route::get('/sprint-board/{id}', \App\Livewire\Hq\Operations\SprintBoard::class)->name('hq.ops.board');
            });
            Route::get('/qa-hub', \App\Livewire\Hq\Operations\QaHub::class)->middleware('can:qa.view')->name('hq.ops.qa');
            Route::get('/silent-timers', \App\Livewire\Hq\Operations\SilentTimers::class)->middleware('can:timesheets.view')->name('hq.ops.timers');
            // Sign-offs and Kaizen moderation share this screen; each part checks its own module
            Route::get('/project-signoffs', \App\Livewire\Hq\Operations\SignoffManager::class)->middleware('can:signoffs.view')->name('hq.ops.signoffs');
        });

        // 7. SYSTEM ADMINISTRATION (Identity & Access)
        Route::prefix('system')->group(function () {
            Route::get('/role-management', \App\Livewire\Hq\System\RoleManagement::class)->middleware('can:access.view')->name('hq.system.roles');
            Route::get('/roles-permissions', \App\Livewire\Hq\System\RolesPermissions::class)->middleware('can:access.view')->name('hq.system.permissions');
            Route::get('/health', \App\Livewire\Hq\System\SystemHealth::class)->middleware('can:system.view')->name('hq.system.health');
        });

        // Private uploads (receipts, payment proofs, QA evidence, CVs), checked per file
        Route::get('/files/{path}', [\App\Http\Controllers\SecureFileController::class, 'show'])->where('path', '.*')->name('hq.file');
    });

    // STANDALONE TRACKER TASK FORCE (Isolated Authentication)
    Route::prefix('taskforce')->group(function () {

        // The Dedicated Login Portal for Consultants & JBS Staff
        Route::get('/login', \App\Livewire\Tracker\Auth\Login::class)->name('taskforce.login');

        // Dedicated Logout
        Route::get('/logout', function () {
            Auth::guard('tracker')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect()->route('taskforce.login');
        })->name('taskforce.logout');

        // The Backend Dashboard (Protected by the 'tracker' guard)
        Route::middleware(['auth:tracker'])->group(function () {
            Route::get('/matrix/{id}', \App\Livewire\Hq\Operations\TrackerMatrix::class)->name('taskforce.matrix');
        });
    });

    // Old /hq/... links (stored notifications, bookmarks); everything else here is 404
    Route::any('{old}', $legacy)->where('old', 'hq(/.*)?');
    Route::any('{any}', fn () => abort(404))->where('any', $notOwnRoute);
});

// =========================================================
// CLIENT PORTAL (portal.jbspraxis.com): clients and every token link
// =========================================================
Route::domain(Hosts::portal())->group(function () use ($notOwnRoute, $legacy) {

    Route::get('/login', \App\Livewire\ClientOs\LoginMatrix::class)->name('client.login');

    // Token links are rate-limited (30 requests a minute per IP) so tokens cannot be guessed at speed.
    Route::middleware('throttle:30,1')->group(function () {
        // Setup link for an invited client account
        Route::get('/setup/{token}', \App\Livewire\ClientOs\SetupAccount::class)->name('client.setup');

        // The SOW / Proposal Binding Engine
        Route::get('/proposal/{token}', \App\Livewire\Portal\ProposalGateway::class)->name('portal.proposal');

        // The Project Signoff & Handover Gateway
        Route::get('/signoff/{token}', \App\Livewire\Portal\SignoffGateway::class)->name('portal.signoff.gateway');

        // The Public PDF Verification Radar (Immutable Audits)
        Route::get('/verify/{hash}', \App\Livewire\Portal\ContractVerification::class)->name('portal.verify.contract');

        // The COPQ Executive Dossier
        Route::get('/dossier/{token}', function ($token) {
            $assessment = DB::table('copq_assessments')->where('report_token', $token)->first();
            if (!$assessment) abort(404, 'Dossier not found.');
            $isExecMode = request()->query('exec') === 'true';
            return view('copq-report', ['assessment' => $assessment, 'isExecMode' => $isExecMode]);
        })->name('dossier.show');

        // The Client Project Tracker (Mobile-First Portal)
        Route::get('/tracker/{token}', \App\Livewire\Portal\ProjectTracker::class)->name('portal.tracker');
    });

    // Client accounts only (guard 'client', table client_users)
    Route::middleware(['auth:client', 'client.active'])->group(function () {
        Route::get('/', \App\Livewire\ClientOs\Dashboard::class)->name('client.dashboard');

        // QA evidence on the client's own projects, checked per file
        Route::get('/files/{path}', [\App\Http\Controllers\SecureFileController::class, 'show'])->where('path', '.*')->name('portal.file');
    });

    // Old /client/..., /portal/... links; everything else here is 404
    Route::any('{old}', $legacy)->where('old', '(client-portal|client|portal|signoff/gateway|verify/contract|hq|taskforce)(/.*)?');
    Route::any('{any}', fn () => abort(404))->where('any', $notOwnRoute);
});

// =========================================================
// PUBLIC SITE (jbspraxis.com; www redirects to it)
// =========================================================
Route::domain('www.' . Hosts::site())->group(function () {
    Route::any('{any?}', function (Request $request) {
        $query = $request->getQueryString();
        return redirect()->away(Hosts::url(Hosts::site(), $request->path()) . ($query ? '?' . $query : ''), 301);
    })->where('any', '.*');
});

Route::domain(Hosts::site())->group(function () use ($legacy) {

    // ZONE 1: PUBLIC ARCHITECTURE (The Marketing Layer)
    Route::get('/', ConversionHub::class)->name('home');
    Route::get('/schedule', ScheduleConsultation::class)->name('schedule');
    Route::get('/contact', ContactHub::class)->name('contact');
    Route::get('/about', TheFirm::class)->name('about');
    Route::get('/portfolio', Portfolio::class)->name('portfolio');
    Route::get('/services', Services::class)->name('services');
    Route::get('/careers', Careers::class)->name('careers');
    Route::get('/kaizen', Kaizen::class)->name('kaizen');
    Route::get('/privacy', \App\Livewire\Privacy::class)->name('privacy');
    Route::get('/trust-center', \App\Livewire\TrustCenter::class)->name('trust-center');
    Route::get('/feedback', TelemetryNode::class)->name('feedback');
    Route::view('/framework', 'framework')->name('framework');
    Route::get('/sitemap.xml', [SitemapController::class, 'index']);
    Route::get('/kaizen/{slug}', \App\Livewire\KaizenArticleView::class)->name('kaizen.show');

    // ZONE 4: EXTERNAL TELEMETRY & TRACKING PIXELS (broadcast emails)
    Route::get('/tracking/open/{campaign_id}/{user_id}', function ($campaign_id, $user_id) {
        // Country comes from Cloudflare's header once the site is proxied; visitor IPs are no
        // longer sent in plain text to a third-party geolocation service.
        $ip = request()->ip();
        $geo = request()->header('CF-IPCountry') ?: 'Unknown Location';

        DB::table('broadcast_logs')->where('campaign_id', $campaign_id)->where('user_id', $user_id)->update([
            'open_count' => DB::raw('open_count + 1'), 'last_opened_at' => now(), 'last_open_ip' => $ip, 'last_open_geo' => $geo
        ]);

        $log = DB::table('broadcast_logs')->where('campaign_id', $campaign_id)->where('user_id', $user_id)->first();
        if ($log && $log->open_count == 1) { DB::table('broadcast_campaigns')->where('id', $campaign_id)->increment('opened_count'); }

        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return response($pixel)->header('Content-Type', 'image/png');
    })->middleware('throttle:60,1')->name('tracking.open');

    // Unsubscribe needs a click: email security scanners open links automatically, which used to unsubscribe people.
    Route::get('/tracking/unsubscribe/{user_id}', function ($user_id) {
        return view('unsubscribe', ['userId' => $user_id, 'done' => false]);
    })->middleware('throttle:30,1')->name('tracking.unsubscribe');

    Route::post('/tracking/unsubscribe/{user_id}', function ($user_id) {
        DB::table('client_users')->where('id', $user_id)->update(['is_subscribed' => 0, 'updated_at' => now()]);
        return view('unsubscribe', ['userId' => $user_id, 'done' => true]);
    })->middleware('throttle:30,1')->name('tracking.unsubscribe.confirm');

    // Old addresses from before HQ and the portal had their own hosts (emails already sent, bookmarks)
    Route::any('{old}', $legacy)->where('old', Hosts::LEGACY_PATTERN);
});
