<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
use App\Livewire\ClientPortal;
use App\Http\Controllers\SitemapController;

// =========================================================
// ZONE 1: PUBLIC ARCHITECTURE (The Marketing Layer)
// =========================================================
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

// Old placeholder portal (Optional: You might want to remove this eventually)
Route::get('/client-portal', ClientPortal::class)->name('client.portal');


// =========================================================
// ZONE 2: PUBLIC GATEWAYS & ESCROW (No Auth Required)
// =========================================================
// 2.1 The SOW / Proposal Binding Engine
Route::get('/portal/proposal/{token}', \App\Livewire\Portal\ProposalGateway::class)->name('portal.proposal');

// 2.2 The Project Signoff & Handover Gateway
Route::get('/signoff/gateway/{token}', \App\Livewire\Portal\SignoffGateway::class)->name('portal.signoff.gateway');

// 2.3 The Public PDF Verification Radar (Immutable Audits)
Route::get('/verify/contract/{hash}', \App\Livewire\Portal\ContractVerification::class)->name('portal.verify.contract');

// 2.4 The COPQ Executive Dossier
Route::get('/dossier/{token}', function ($token) {
    $assessment = DB::table('copq_assessments')->where('report_token', $token)->first();
    if (!$assessment) abort(404, 'Dossier not found.');
    $isExecMode = request()->query('exec') === 'true';
    return view('copq-report', ['assessment' => $assessment, 'isExecMode' => $isExecMode]);
})->name('dossier.show');

// 2.5 Client OS Public Entry
Route::get('/client/login', \App\Livewire\ClientOs\LoginMatrix::class)->name('client.login');
Route::get('/client/setup/{token}', \App\Livewire\ClientOs\SetupAccount::class)->name('client.setup');

// 2.6 The Client Project Tracker (Mobile-First Portal)
Route::get('/portal/tracker/{token}', \App\Livewire\Portal\ProjectTracker::class)->name('portal.tracker');


// =========================================================
// ZONE 3: SECURE CLIENT OS (Auth Required)
// =========================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/client/dashboard', \App\Livewire\ClientOs\Dashboard::class)->name('client.dashboard');

    // Private uploads (receipts, payment proofs, QA evidence, CVs), checked per file
    Route::get('/files/{path}', [\App\Http\Controllers\SecureFileController::class, 'show'])->where('path', '.*')->name('secure.file');
});


// =========================================================
// ZONE 4: EXTERNAL TELEMETRY & TRACKING PIXELS
// =========================================================
Route::get('/tracking/open/{campaign_id}/{user_id}', function ($campaign_id, $user_id) {
    $ip = request()->ip(); $geo = 'Unknown Location';
    try {
        $geoData = Illuminate\Support\Facades\Http::timeout(3)->get("http://ip-api.com/json/{$ip}")->json();
        if ($geoData && $geoData['status'] === 'success') { $geo = $geoData['city'] . ', ' . $geoData['country']; }
    } catch (\Exception $e) { /* Fail silently */ }

    DB::table('broadcast_logs')->where('campaign_id', $campaign_id)->where('user_id', $user_id)->update([
        'open_count' => DB::raw('open_count + 1'), 'last_opened_at' => now(), 'last_open_ip' => $ip, 'last_open_geo' => $geo
    ]);
        
    $log = DB::table('broadcast_logs')->where('campaign_id', $campaign_id)->where('user_id', $user_id)->first();
    if ($log && $log->open_count == 1) { DB::table('broadcast_campaigns')->where('id', $campaign_id)->increment('opened_count'); }

    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    return response($pixel)->header('Content-Type', 'image/png');
})->name('tracking.open');
    
Route::get('/tracking/unsubscribe/{user_id}', function ($user_id) {
    DB::table('users')->where('id', $user_id)->update(['is_subscribed' => 0]);
    return "You have been successfully unsubscribed from JBS Praxis communications. You may close this window.";
})->name('tracking.unsubscribe');


// =========================================================
// ZONE 5: THE COMMAND CENTER (HQ MATRIX)
// =========================================================
Route::prefix('hq')->group(function () {
    
    // HQ Auth Routes
    Route::get('/login', \App\Livewire\Hq\Auth\LoginTerminal::class)->name('login');
    Route::get('/logout', function () {
        Auth::logout(); request()->session()->invalidate(); request()->session()->regenerateToken();
        return redirect()->route('login'); 
    })->name('hq.logout');

    // Protected HQ Routes: staff only. 'hq.staff:4' marks screens for clearance 4+ (finance, payroll, HR, master data, CRM, roles).
    Route::middleware(['auth', 'hq.staff'])->group(function () {

        // 1. COMMAND OVERVIEW
        Route::get('/dashboard/admin', \App\Livewire\Hq\Dashboards\GlobalTelemetry::class)->middleware('hq.staff:4')->name('hq.dash.admin');
        Route::get('/dashboard/workspace', \App\Livewire\Hq\Dashboards\MyWorkspace::class)->name('hq.dash.workspace');
        Route::get('/dashboard/investor', \App\Livewire\Hq\Dashboards\InvestorTerminal::class)->middleware('hq.staff:4')->name('hq.dash.investor');
        Route::get('/dashboard/audit', \App\Livewire\Hq\Dashboards\AuditTerminal::class)->middleware('hq.staff:4')->name('hq.dash.audit');

        // 2. MASTER DATA MANAGEMENT (MDM)
        Route::prefix('mdm')->middleware('hq.staff:4')->group(function () {
            // Financial Sub-Module
            Route::get('/dictionary', \App\Livewire\Hq\Mdm\FinancialDictionary::class)->name('hq.mdm.dictionary');
            Route::get('/copq-matrices', \App\Livewire\Hq\Mdm\CopqMatrices::class)->name('hq.mdm.copq');
            Route::get('/tax-compliance', \App\Livewire\Hq\Mdm\TaxCompliance::class)->name('hq.mdm.tax');
            Route::get('/remittance', \App\Livewire\Hq\Mdm\RemittanceAccounts::class)->name('hq.mdm.remittance');
            
            // Corporate Entities
            Route::get('/legal-entities', \App\Livewire\Hq\Mdm\LegalEntityRegistry::class)->name('hq.mdm.legal');
            Route::get('/employees', \App\Livewire\Hq\Mdm\EmployeeRegistry::class)->name('hq.mdm.employees');
        });

        // HUMAN CAPITAL (Newly Expanded HR Routes)
        Route::prefix('hr')->middleware('hq.staff:4')->group(function () {
            Route::get('/time-attendance', \App\Livewire\Hq\Hr\TimeAttendance::class)->name('hq.hr.time');
            Route::get('/compliance-contracts', \App\Livewire\Hq\Hr\ContractsAppraisals::class)->name('hq.hr.compliance');
        });

        // 3. FINANCIAL DISPATCH (Treasury, Ledger, Tax & Accounting)
        Route::prefix('finance')->group(function () {
            // Treasury & Expenses (any staff member can submit a cash request)
            Route::get('/cash-request', \App\Livewire\Hq\Finance\CashRequestSubmitter::class)->name('hq.finance.request');

            Route::middleware('hq.staff:4')->group(function () {
                Route::get('/cash-manager', \App\Livewire\Hq\Finance\CashRequestManager::class)->name('hq.finance.manager');
                Route::get('/budget-matrix', \App\Livewire\Hq\Finance\ProjectBudgetManager::class)->name('hq.finance.budgets');

                // Invoicing & Escrow
                Route::get('/invoice-ledger', \App\Livewire\Hq\Finance\InvoiceLedger::class)->name('hq.finance.invoices');
                Route::get('/escrow-verification', \App\Livewire\Hq\Finance\EscrowVerification::class)->name('hq.finance.escrow');

                // Core IFRS Accounting
                Route::get('/chart-of-accounts', \App\Livewire\Hq\Finance\ChartOfAccountsManager::class)->name('hq.finance.coa');
                Route::get('/general-ledger', \App\Livewire\Hq\Finance\GeneralLedgerManager::class)->name('hq.finance.gl');

                // Payroll & Tax Engine (Newly Added Endpoints)
                Route::get('/payroll', \App\Livewire\Hq\Finance\PayrollManager::class)->name('hq.finance.payroll');
                Route::get('/tax-configurator', \App\Livewire\Hq\Finance\TaxConfigurator::class)->name('hq.finance.tax-config');
                Route::get('/variable-compensation', \App\Livewire\Hq\Finance\VariableCompensation::class)->name('hq.finance.variables');
                Route::get('/compliance-matrix', \App\Livewire\Hq\Finance\ComplianceMatrix::class)->name('hq.finance.matrix');
                Route::get('/payslips', \App\Livewire\Hq\Finance\PayslipInspector::class)->name('hq.finance.payslips');

                // Executive BI (The Financial Matrix)
                Route::get('/executive-bi', \App\Livewire\Hq\Finance\ExecutiveBiDashboard::class)->name('hq.finance.bi');
            });
        });
        
        // 4. INTELLIGENCE DESK (CRM / Lead Management)
        Route::prefix('intelligence')->middleware('hq.staff:4')->group(function () {
            Route::get('/leads', \App\Livewire\Hq\Intelligence\LeadsRegister::class)->name('hq.crm.leads');
            Route::get('/consultation', \App\Livewire\Hq\Intelligence\LiveConsultation::class)->name('hq.crm.consult');
            Route::get('/proposal-builder/{id?}', \App\Livewire\Hq\Intelligence\ProposalBuilder::class)->name('hq.crm.proposal');
            Route::get('/broadcast-ledger', \App\Livewire\Hq\Intelligence\BroadcastLedger::class)->name('hq.crm.broadcast');
        });
        
        // 5. MARKETING & ROAS
        Route::prefix('marketing')->middleware('hq.staff:4')->group(function () {
            Route::get('/campaigns', \App\Livewire\Hq\Marketing\MarketingSync::class)->name('hq.marketing.campaigns');
        });

        // 6. OPERATIONS & DEPLOYMENT MATRIX
        Route::prefix('operations')->group(function () {
            Route::get('/active-deployments', \App\Livewire\Hq\Operations\ActiveDeployments::class)->name('hq.ops.deployments');
            Route::get('/scrum-master/{id}', \App\Livewire\Hq\Operations\ScrumMaster::class)->name('hq.ops.scrum');
            Route::get('/sprint-boards', \App\Livewire\Hq\Operations\SprintBoardDirectory::class)->name('hq.ops.boards.directory');
            Route::get('/sprint-board/{id}', \App\Livewire\Hq\Operations\SprintBoard::class)->name('hq.ops.board');
            Route::get('/qa-hub', \App\Livewire\Hq\Operations\QaHub::class)->name('hq.ops.qa');
            Route::get('/silent-timers', \App\Livewire\Hq\Operations\SilentTimers::class)->name('hq.ops.timers');
            Route::get('/project-signoffs', \App\Livewire\Hq\Operations\SignoffManager::class)->name('hq.ops.signoffs');
        });
        
        // 7. SYSTEM ADMINISTRATION (Identity & Access)
        Route::prefix('system')->middleware('hq.staff:4')->group(function () {
            Route::get('/role-management', \App\Livewire\Hq\System\RoleManagement::class)->name('hq.system.roles');
        });

    });
});

// =========================================================
// STANDALONE TRACKER TASK FORCE (Isolated Authentication)
// =========================================================
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