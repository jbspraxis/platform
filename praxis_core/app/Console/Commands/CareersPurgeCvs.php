<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Careers retention, as the privacy policy on jbspraxis.com promises: a CV is deleted, and its application
 * anonymised, config('praxis.cv_retention_months') (12) months after it was received.
 *
 * - CVs (media_assets with a talent_id, files under vault/dossiers on the private disk) received before the
 *   cutoff: the file and its row go.
 * - Applications (talent_pool) received before the cutoff with no newer CV (a repeat application adds a CV
 *   and starts the period again): name, email, phone, city, links and answer are replaced, and the row is
 *   hidden (deleted_at). The row stays so counts by discipline and date still add up, and nothing that
 *   points at it is cascaded away.
 * - Stray files in vault/dossiers that no row points at (an upload whose save failed), older than the cutoff.
 *
 * Left alone: applications of people who joined us (status Drafted, or an employment contract that points at
 * the application), since they are part of a staff record now; files of other kinds (media_assets with no
 * talent_id). Runs nightly only when CV_RETENTION_PURGE is on (routes/console.php); --dry-run changes nothing.
 */
class CareersPurgeCvs extends Command
{
    /** An anonymised application's email: .invalid can never be a real address (RFC 2606), and stays unique */
    public const ANONYMISED_DOMAIN = 'anonymised.invalid';

    public const ANONYMISED_NAME = 'Anonymised applicant';

    private const FOLDER = 'vault/dossiers';

    protected $signature = 'careers:purge-cvs
        {--dry-run : Count what would be removed, change nothing}';

    protected $description = 'Careers: delete CVs and anonymise applications older than the retention period (privacy policy)';

    public function handle(): int
    {
        $months = max(1, (int) config('praxis.cv_retention_months', 12));
        $cutoff = now()->subMonths($months);
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('local');

        $cvs = $this->oldCvs($cutoff);
        $applications = $this->oldApplications($cutoff);
        $strays = $this->strayFiles($cutoff);

        if ($dry) {
            $this->line(sprintf(
                'careers:purge-cvs (dry run, %d months, received before %s): would delete %d CV(s), anonymise %d application(s) and remove %d stray file(s). Nothing was changed.',
                $months, $cutoff->toDateString(), $cvs->count(), $applications->count(), count($strays)
            ));

            return self::SUCCESS;
        }

        // Files first: a row left without its file is harmless, a file left without its row is found next time
        $files = 0;
        foreach ($cvs as $cv) {
            $path = self::safePath($cv->file_path);
            if ($path !== null && $disk->exists($path) && $disk->delete($path)) {
                $files++;
            }
        }
        DB::table('media_assets')->whereIn('id', $cvs->pluck('id'))->delete();

        foreach ($applications as $application) {
            DB::table('talent_pool')->where('id', $application->id)->update([
                'full_name' => self::ANONYMISED_NAME,
                'email' => 'anonymised-' . $application->id . '@' . self::ANONYMISED_DOMAIN,
                'phone' => '',
                'city' => '',
                'secondary_skillset' => null,
                'linkedin_url' => null,
                'portfolio_url' => null,
                'praxis_answer' => '',
                'updated_by' => null,
                'updated_at' => now(),
                'deleted_at' => $application->deleted_at ?? now(),
            ]);
        }

        foreach ($strays as $path) {
            $disk->delete($path);
        }

        $summary = sprintf(
            'Careers retention (%d months, received before %s): %d CV(s) deleted (%d file(s)), %d application(s) anonymised, %d stray file(s) removed.',
            $months, $cutoff->toDateString(), $cvs->count(), $files, $applications->count(), count($strays)
        );
        if ($cvs->isNotEmpty() || $applications->isNotEmpty() || $strays !== []) {
            DB::table('system_audit_logs')->insert([
                'user_id' => null,
                'action' => 'CV_RETENTION_PURGE',
                'description' => $summary,
                'target_table' => 'talent_pool',
                'created_at' => now(),
            ]);
        }
        $this->line('careers:purge-cvs: ' . $summary);

        return self::SUCCESS;
    }

    /** CVs received before the cutoff, except those of people who joined us */
    private function oldCvs(Carbon $cutoff)
    {
        return DB::table('media_assets')
            ->whereNotNull('talent_id')
            ->whereRaw('COALESCE(created_at, updated_at) < ?', [$cutoff])
            ->whereNotIn('talent_id', $this->joined())
            ->get(['id', 'file_path']);
    }

    /** Applications received before the cutoff with no CV received since, not yet anonymised */
    private function oldApplications(Carbon $cutoff)
    {
        return DB::table('talent_pool')
            ->whereRaw('COALESCE(created_at, updated_at) < ?', [$cutoff])
            ->where('email', 'not like', '%@' . self::ANONYMISED_DOMAIN)
            ->whereNotIn('id', $this->joined())
            ->whereNotExists(fn ($q) => $q->from('media_assets')
                ->whereColumn('media_assets.talent_id', 'talent_pool.id')
                ->whereRaw('COALESCE(media_assets.created_at, media_assets.updated_at) >= ?', [$cutoff]))
            ->get(['id', 'deleted_at']);
    }

    /** Applications of people who joined us: Drafted, or pointed at by an employment contract */
    private function joined()
    {
        return DB::table('talent_pool')->select('id')
            ->where('status', 'Drafted')
            ->orWhereIn('id', DB::table('employment_contracts')->select('mdm_employee_id'));
    }

    /** Files in vault/dossiers older than the cutoff that no row points at */
    private function strayFiles(Carbon $cutoff): array
    {
        $disk = Storage::disk('local');
        $known = DB::table('media_assets')->whereNotNull('file_path')->pluck('file_path')
            ->map(fn ($path) => self::safePath($path))->filter()->flip();

        $out = [];
        foreach ($disk->files(self::FOLDER) as $path) {
            if (!isset($known[$path]) && $disk->lastModified($path) < $cutoff->getTimestamp()) {
                $out[] = $path;
            }
        }

        return $out;
    }

    /** A stored path as the private disk knows it, or null when it could leave the disk */
    private static function safePath(?string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');

        return $path === '' || str_contains($path, '..') ? null : $path;
    }
}
