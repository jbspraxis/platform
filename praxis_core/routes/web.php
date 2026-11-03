<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Support\Hosts;

/*
| Three hosts, one app (config/app.php 'domains'):
|   jbspraxis.com          served by praxis_site; here only the page names for links, broadcast tracking and old redirects
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
    // Password reset link from "Forgot your password?" or Accounts (docs/SIGN_IN.md section 8), rate-limited the same way
    Route::get('/reset/{token}', \App\Livewire\Hq\Auth\ResetPassword::class)->middleware('throttle:30,1')->name('hq.password.reset');

    // Protected HQ Routes: staff only. Each screen needs its module's view permission
    // (can:<module>.view, see config/access.php); actions inside are checked per component.
    Route::middleware(['auth', 'hq.staff'])->group(function () {

        // 1. COMMAND OVERVIEW (the workspace is open to every staff member)
        Route::get('/dashboard/admin', \App\Livewire\Hq\Dashboards\GlobalTelemetry::class)->middleware('can:dashboard.view')->name('hq.dash.admin');
        Route::get('/dashboard/workspace', \App\Livewire\Hq\Dashboards\MyWorkspace::class)->name('hq.dash.workspace');
        // Each person's own profile (the avatar menu): phone numbers and password; open to every staff member
        Route::get('/profile', \App\Livewire\Hq\Account\MyProfile::class)->name('hq.profile');
        // People's photos (uploaded on My profile), for signed-in staff only
        Route::get('/avatar/{user}', fn (string $user) => \App\Support\Avatars::response($user))
            ->where('user', '[0-9a-f-]{36}')->name('hq.avatar');
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
                Route::get('/invoice-ledger/{invoice}/pdf', [\App\Http\Controllers\InvoicePdfController::class, 'hq'])->whereUuid('invoice')->name('hq.finance.invoice-pdf');
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
                // Leads (docs/LEADS.md): the list, Add a lead, and one page per lead with its tabs
                Route::get('/leads', \App\Livewire\Hq\Intelligence\LeadsRegister::class)->name('hq.crm.leads');
                Route::get('/leads/new', \App\Livewire\Hq\Leads\AddLead::class)->middleware('can:crm.create')->name('hq.crm.leads.new');
                Route::get('/leads/{organization}', \App\Livewire\Hq\Leads\LeadPage::class)->whereUuid('organization')->name('hq.crm.lead');
                Route::get('/consultation', \App\Livewire\Hq\Intelligence\LiveConsultation::class)->name('hq.crm.consult');
                Route::get('/proposal-builder/{id?}', \App\Livewire\Hq\Intelligence\ProposalBuilder::class)->name('hq.crm.proposal');
            });
            Route::get('/broadcast-ledger', \App\Livewire\Hq\Intelligence\BroadcastLedger::class)->middleware('can:broadcasts.view')->name('hq.crm.broadcast');
        });

        // Opportunities (docs/OPPORTUNITIES.md): one record per piece of work with a client, under Leads and proposals
        // Who may open these is decided in each screen (OpportunityAccess): the permission, or being on the opportunity's team
        Route::prefix('opportunities')->group(function () {
            Route::get('/', \App\Livewire\Hq\Opportunities\OpportunityBoard::class)->name('hq.crm.opportunities');
            Route::get('/{opportunity}', \App\Livewire\Hq\Opportunities\OpportunityPage::class)->whereUuid('opportunity')->name('hq.crm.opportunity');
            Route::get('/{opportunity}/proposals/{proposal}/pdf', [\App\Http\Controllers\ProposalPdfController::class, 'hq'])->whereUuid(['opportunity', 'proposal'])->name('hq.crm.proposal-pdf');
            Route::get('/{opportunity}/sow/{sow}/pdf', [\App\Http\Controllers\SowPdfController::class, 'hq'])->whereUuid(['opportunity', 'sow'])->name('hq.crm.sow-pdf');
            Route::get('/{opportunity}/transcripts/{transcript}/{what}.md', [\App\Http\Controllers\MeetingNotesController::class, 'download'])
                ->whereUuid(['opportunity', 'transcript'])->whereIn('what', ['notes', 'transcript'])->name('hq.crm.meeting-notes');
        });

        // 5. MARKETING & ROAS
        // Each screen checks its own module, so someone with only website.view can open Website settings
        Route::prefix('marketing')->group(function () {
            Route::get('/campaigns', \App\Livewire\Hq\Marketing\MarketingSync::class)->middleware('can:marketing.view')->name('hq.marketing.campaigns');
            Route::get('/website-settings', \App\Livewire\Hq\Marketing\WebsiteSettings::class)->middleware('can:website.view')->name('hq.marketing.website');
        });

        // 6. OPERATIONS & DEPLOYMENT MATRIX
        Route::prefix('operations')->group(function () {
            Route::middleware('can:projects.view')->group(function () {
                Route::get('/active-deployments', \App\Livewire\Hq\Operations\ActiveDeployments::class)->name('hq.ops.deployments');
                Route::get('/scrum-master/{id}', \App\Livewire\Hq\Operations\ScrumMaster::class)->name('hq.ops.scrum');
                // One page per project: milestones, their boards, backlog, QA, meetings and money (docs/PROJECTS.md).
                // Sprints were folded into milestones (1 Nov 2026): the old sprint board addresses lead to the new pages.
                Route::get('/projects/{project}', \App\Livewire\Hq\Projects\ProjectPage::class)->whereUuid('project')->name('hq.ops.project');
                Route::get('/milestone-boards', \App\Livewire\Hq\Operations\SprintBoardDirectory::class)->name('hq.ops.boards.directory');
                Route::redirect('/sprint-boards', '/operations/milestone-boards');
                Route::get('/sprint-board/{id}', fn (string $id) => redirect()->route('hq.ops.project', ['project' => $id, 'tab' => 'board']))->name('hq.ops.board');
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
            // Google connection, Claude key and AI budget (docs/OPPORTUNITIES.md 6)
            Route::get('/integrations', \App\Livewire\Hq\System\Integrations::class)->middleware('can:integrations.manage')->name('hq.system.integrations');
        });

        // Google OAuth for praxisjbs@gmail.com. The callback path is registered on the OAuth client in Google Cloud
        // (https://hq.jbspraxis.com/integrations/google/callback): don't change it without changing it there.
        Route::prefix('integrations/google')->middleware('can:integrations.manage')->group(function () {
            Route::get('/connect', [\App\Http\Controllers\Integrations\GoogleOAuthController::class, 'connect'])->name('hq.integrations.google.connect');
            Route::get('/callback', [\App\Http\Controllers\Integrations\GoogleOAuthController::class, 'callback'])->name('hq.integrations.google.callback');
        });

        // 8. MAIL (docs/MAIL.md): own and shared mailboxes; routes/mail.php
        require __DIR__ . '/mail.php';

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

        // Password reset link from "Forgot your password?" or HQ's Accounts (docs/SIGN_IN.md section 8)
        Route::get('/reset/{token}', \App\Livewire\ClientOs\ResetPassword::class)->name('client.password.reset');

        // The SOW / Proposal Binding Engine
        Route::get('/proposal/{token}', \App\Livewire\Portal\ProposalGateway::class)->name('portal.proposal');

        // The Project Signoff & Handover Gateway
        Route::get('/signoff/{token}', \App\Livewire\Portal\SignoffGateway::class)->name('portal.signoff.gateway');

        // The Public PDF Verification Radar (Immutable Audits)
        Route::get('/verify/{hash}', \App\Livewire\Portal\ContractVerification::class)->name('portal.verify.contract');

        // The COPQ Executive Dossier
        Route::get('/dossier/{token}', function (Request $request, $token) {
            $isExecMode = $request->query('exec') === 'true';
            // Visitors' links (cost report emails sent before 31 Oct 2026) open the report on the site; HQ's own links add ?exec=true
            if (!$isExecMode) {
                return redirect()->away(Hosts::url(Hosts::site(), 'dossier/' . rawurlencode($token), $request), 301);
            }
            $assessment = DB::table('copq_assessments')->where('report_token', $token)->first();
            if (!$assessment) abort(404, 'Dossier not found.');
            return view('copq-report', ['assessment' => $assessment, 'isExecMode' => $isExecMode]);
        })->name('dossier.show');

        // The Client Project Tracker (Mobile-First Portal)
        Route::get('/tracker/{token}', \App\Livewire\Portal\ProjectTracker::class)->name('portal.tracker');

        // A client's meetings for one opportunity: upcoming, minutes, book a follow-up (docs/OPPORTUNITIES.md, PR 2).
        // Signed, expiring links from HQ's emails (App\Support\Opportunities\PortalLinks); the signature covers path and query
        Route::get('/meetings/{opportunity}', \App\Livewire\Portal\MeetingsGateway::class)->whereUuid('opportunity')
            ->middleware('signed:relative')->name('portal.meetings');

        // One version of an opportunity's proposal: read it, comment by section, approve it or ask for changes; and its PDF (PR 4)
        Route::get('/proposals/{proposal}', \App\Livewire\Portal\ProposalReview::class)->whereUuid('proposal')
            ->middleware('signed:relative')->name('portal.proposals.review');
        Route::get('/proposals/{proposal}/pdf', [\App\Http\Controllers\ProposalPdfController::class, 'portal'])->whereUuid('proposal')
            ->middleware('signed:relative')->name('portal.proposals.pdf');

        // One version of an opportunity's statement of work: read it, sign it with the one-time code or ask for changes; and its PDF (PR 5)
        Route::get('/sow/{sow}', \App\Livewire\Portal\SowReview::class)->whereUuid('sow')
            ->middleware('signed:relative')->name('portal.sow');
        Route::get('/sow/{sow}/pdf', [\App\Http\Controllers\SowPdfController::class, 'portal'])->whereUuid('sow')
            ->middleware('signed:relative')->name('portal.sow.pdf');
    });

    // Client accounts only (guard 'client', table client_users)
    Route::middleware(['auth:client', 'client.active'])->group(function () {
        Route::get('/', \App\Livewire\ClientOs\Dashboard::class)->name('client.dashboard');
        // An invoice's PDF, for an account that sees invoices on that project (docs/QA-PORTAL.md D11)
        Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\InvoicePdfController::class, 'portal'])->whereUuid('invoice')->name('portal.invoice.pdf');

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

    // The public pages are praxis_site's (jbspraxis.com has its own docroot since 30 Oct 2026). The names
    // stay so emails and HQ keep building the same addresses with route(); this app never serves them.
    $notHere = fn () => abort(404);
    Route::get('/', $notHere)->name('home');
    Route::get('/schedule', $notHere)->name('schedule');
    Route::get('/contact', $notHere)->name('contact');
    Route::get('/about', $notHere)->name('about');
    Route::get('/portfolio', $notHere)->name('portfolio');
    Route::get('/services', $notHere)->name('services');
    Route::get('/careers', $notHere)->name('careers');
    Route::get('/kaizen', $notHere)->name('kaizen');
    Route::get('/privacy', $notHere)->name('privacy');
    Route::get('/trust-center', $notHere)->name('trust-center');
    Route::get('/feedback', $notHere)->name('feedback');
    Route::get('/framework', $notHere)->name('framework');
    Route::get('/kaizen/{slug}', $notHere)->name('kaizen.show');

    // ZONE 4: EXTERNAL TELEMETRY & TRACKING PIXELS (broadcast emails)
    // Broadcast links carry a token from now on (?t=…, App\Services\Site\TrackingService); links in emails
    // sent before keep working without it. The same rules as the site API.
    Route::get('/tracking/open/{campaign_id}/{user_id}', function ($campaign_id, $user_id) {
        // Country comes from Cloudflare's header once the site is proxied; visitor IPs are no
        // longer sent in plain text to a third-party geolocation service.
        $ip = request()->ip();
        $geo = request()->header('CF-IPCountry') ?: 'Unknown Location';
        $token = request()->query('t');

        app(\App\Services\Site\TrackingService::class)->open((string) $campaign_id, (string) $user_id, $ip, mb_substr((string) $geo, 0, 64), is_string($token) ? $token : null);

        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return response($pixel)->header('Content-Type', 'image/png');
    })->middleware('throttle:60,1')->name('tracking.open');

    // Unsubscribe needs a click: email security scanners open links automatically, which used to unsubscribe people.
    Route::get('/tracking/unsubscribe/{user_id}', function ($user_id) {
        $campaign = request()->query('campaign');
        $token = request()->query('t');
        return view('unsubscribe', ['userId' => $user_id, 'done' => false, 'invalid' => false,
            'campaign' => is_string($campaign) ? $campaign : null, 'token' => is_string($token) ? $token : null]);
    })->middleware('throttle:30,1')->name('tracking.unsubscribe');

    Route::post('/tracking/unsubscribe/{user_id}', function ($user_id) {
        $campaign = request()->input('campaign');
        $token = request()->input('t');
        $done = app(\App\Services\Site\TrackingService::class)->unsubscribe((string) $user_id, is_string($campaign) ? $campaign : null, is_string($token) ? $token : null);
        return view('unsubscribe', ['userId' => $user_id, 'done' => $done, 'invalid' => !$done, 'campaign' => null, 'token' => null]);
    })->middleware('throttle:30,1')->name('tracking.unsubscribe.confirm');

    // Old addresses from before HQ and the portal had their own hosts (emails already sent, bookmarks)
    Route::any('{old}', $legacy)->where('old', Hosts::LEGACY_PATTERN);
});
