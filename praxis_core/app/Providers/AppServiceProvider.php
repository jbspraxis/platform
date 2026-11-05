<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The live web PHP has no fileinfo extension: a stand-in for finfo (App\Support\Polyfill\Finfo) keeps file
        // type checks, uploads and storage working everywhere; App\Support\FilesystemManager stays as a second guard
        if (! extension_loaded('fileinfo')) {
            require_once app_path('Support/Polyfill/fileinfo.php');
        }
        $this->app->singleton('filesystem', fn ($app) => new \App\Support\FilesystemManager($app));

        // Company profile (docs/COMPANY_PROFILE.md). Two seams the other two company-profile PRs replace:
        // PR 3 binds the logo library over DirectoryClientLogos. PR 2's designed renderer is bound here.
        $this->app->bind(\App\Support\CompanyProfile\ClientLogos::class, \App\Support\CompanyProfile\DirectoryClientLogos::class);
        $this->app->bind(\App\Support\CompanyProfile\CompanyProfileRenderer::class, \App\Support\CompanyProfile\PdfRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Re-run the HQ staff and active-client checks on Livewire update requests, not only on page loads.
        // (Livewire already re-runs 'auth' and 'can:' route middleware.)
        \Livewire\Livewire::addPersistentMiddleware([
            \App\Http\Middleware\HqStaff::class,
            \App\Http\Middleware\ClientActive::class,
        ]);

        // Permissions from config/access.php: $user->can('finance.view'), @can, can: middleware.
        // Anything that isn't a catalogue permission falls through to normal gates/policies.
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            return \App\Support\Access::isPermission($ability) ? \App\Support\Access::allows($user, $ability) : null;
        });

        // Settings and access › Key vault (docs/KEY_VAULT.md): the protected Owner role only. Deliberately not a
        // permission in config/access.php, so it is not on Roles and permissions and no role's "*" can reach it.
        \Illuminate\Support\Facades\Gate::define('key-vault', fn ($user) => \App\Support\KeyVault\KeyVault::allows($user));

        // What an AI run is about (ai_runs.subject_type): short names, not class names (docs/OPPORTUNITIES.md 8.3)
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'opportunity' => \App\Models\Opportunity::class,
            'meeting' => \App\Models\Meeting::class,
            'meeting_transcript' => \App\Models\MeetingTranscript::class,
            'questionnaire' => \App\Models\Questionnaire::class,
            'proposal' => \App\Models\Proposal::class,
            'statement_of_work' => \App\Models\StatementOfWork::class,
            'project_draft' => \App\Models\ProjectDraft::class,
            'milestone_plan' => \App\Models\MilestonePlan::class,
            'company_document_version' => \App\Models\CompanyDocumentVersion::class,
        ]);

        // Uploads wait in Livewire's temporary folder before a screen keeps them: up to 20 MB (Company documents takes
        // scans that large; Livewire's own default is 12 MB). Each screen still checks its own limit and file types.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.\App\Support\CompanyDocuments\Documents::MAX_KB]]);
    }
}
