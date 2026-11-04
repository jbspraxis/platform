<?php

namespace App\Support\Backups;

use App\Support\Google\GoogleWorkspace;
use App\Support\Monitor\ErrorMonitor;
use App\Support\Monitor\HealthChecks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Proof the backups work (owner's decision 7): per site, the last database backup and the last files backup, with
 * the last restore test, for HQ › System health; and the hourly check that rings the owner when a site's last good
 * backup is older than 26 hours. A row still "running" six hours after it started counts as failed (the run died).
 */
final class Status
{
    /**
     * @return list<array{site: string, label: string, state: string, db: ?object, db_ok: ?object, files: ?object,
     *                     files_ok: ?object, restore: ?object, restore_result: ?array, notes: list<string>}>
     */
    public static function sites(): array
    {
        $sites = Sites::all();
        $others = DB::table('server_backups')->where('site', 'like', Sites::OTHER . '/%')->where('created_at', '>=', now()->subDays(40))
            ->distinct()->pluck('site')->all();
        foreach ($others as $other) {
            $sites[$other] = ['label' => $other, 'databases' => [], 'files' => [], 'exclude' => []];
        }
        $setUpAt = ErrorMonitor::state('backups.setup_at');
        $staleHours = (int) config('backups.stale_hours', 26);
        $filesDays = (int) config('backups.files_stale_days', 8);

        $out = [];
        foreach ($sites as $site => $config) {
            // The latest date's rows, the last per database: one of a site's two databases failing shows, even if the other worked
            $lastRun = function (string $kind) use ($site) {
                $date = DB::table('server_backups')->where('site', $site)->where('kind', $kind)->max('backup_date');
                $rows = $date ? DB::table('server_backups')->where('site', $site)->where('kind', $kind)->where('backup_date', $date)
                    ->orderByDesc('id')->get()->unique('source') : collect();

                return $rows->first(fn ($r) => self::failed($r)) ?? $rows->first();
            };
            $lastOk = fn (string $kind) => DB::table('server_backups')->where('site', $site)->where('kind', $kind)->where('status', 'ok')
                ->orderByDesc('finished_at')->orderByDesc('id')->first();
            $db = $lastRun('db');
            $dbOk = $lastOk('db');
            $files = $lastRun('files');
            $filesOk = $lastOk('files');
            $restore = DB::table('server_backups')->where('site', $site)->where('kind', 'db')->whereNotNull('restore_test')
                ->orderByDesc('started_at')->orderByDesc('id')->first();
            $restoreResult = $restore ? json_decode((string) $restore->restore_test, true) : null;

            $bad = [];
            $warn = [];
            if ($db && self::failed($db)) {
                $bad[] = 'Last database backup failed' . ($db->stage ? " ({$db->stage})" : '') . ': ' . mb_substr((string) ($db->error ?: 'it did not finish'), 0, 200);
            }
            if ($dbOk && Carbon::parse($dbOk->finished_at)->lt(now()->subHours($staleHours))) {
                $bad[] = 'The last good database backup is older than ' . $staleHours . ' hours.';
            } elseif (! $dbOk && $setUpAt && Carbon::parse($setUpAt)->lt(now()->subHours($staleHours)) && ! Sites::isOther($site)) {
                $bad[] = 'No database backup has worked yet.';
            }
            if ($restoreResult && ! ($restoreResult['ok'] ?? false)) {
                $bad[] = 'The last restore test failed: ' . implode('; ', array_slice((array) ($restoreResult['mismatches'] ?? []), 0, 2));
            } elseif ($dbOk && (! $restore || Carbon::parse($restore->started_at)->lt(now()->subDays(8)))) {
                $warn[] = 'No restore test in the last week.';
            }
            if (! Sites::isOther($site)) {
                if ($config['files'] === []) {
                    $warn[] = 'No upload folders set for the weekly files backup.';
                } elseif ($files && self::failed($files)) {
                    $bad[] = 'Last files backup failed' . ($files->stage ? " ({$files->stage})" : '') . ': ' . mb_substr((string) ($files->error ?: 'it did not finish'), 0, 200);
                } elseif ($filesOk && Carbon::parse($filesOk->finished_at)->lt(now()->subDays($filesDays))) {
                    $warn[] = 'The last files backup is older than ' . $filesDays . ' days.';
                } elseif (! $filesOk && $setUpAt && Carbon::parse($setUpAt)->lt(now()->subDays($filesDays))) {
                    $warn[] = 'No files backup has worked yet.';
                }
            }

            $out[] = [
                'site' => $site,
                'label' => $config['label'],
                'state' => $bad ? 'bad' : ($warn || ! $dbOk ? 'warn' : 'ok'),
                'db' => $db, 'db_ok' => $dbOk, 'files' => $files, 'files_ok' => $filesOk,
                'restore' => $restore, 'restore_result' => $restoreResult,
                'notes' => [...$bad, ...$warn],
            ];
        }

        return $out;
    }

    /** The "Server backups" check of HQ › System health */
    public static function check(): array
    {
        $label = 'Server backups (Drive)';
        if (! app(Backups::class)->setUp()) {
            return ['label' => $label, 'state' => 'warn', 'detail' => 'Not switched on yet: nothing on the server is backed up off-site.',
                'fix' => 'docs/BACKUPS.md, section 4: run backups:setup on the server, reconnect Google with Drive allowed, and add the owner’s public key.'];
        }
        if (Keys::publicKey() === null) {
            return ['label' => $label, 'state' => 'bad', 'detail' => 'There is no backup key on the server, so nothing can be encrypted or uploaded.',
                'fix' => 'The owner makes the key on the PC (deploy/backups/pc/New-BackupKey.ps1); its public half goes into resources/backups/owner-public-key.pem.'];
        }
        if (! app(GoogleWorkspace::class)->canUseDrive()) {
            return ['label' => $label, 'state' => 'bad', 'detail' => 'Drive backups are not allowed yet, so backups stay on the server only.',
                'fix' => 'HQ › Settings and access › Integrations → Reconnect Google, and allow Drive.'];
        }

        $sites = self::sites();
        $state = HealthChecks::worst(array_map(fn ($s) => ['state' => $s['state']], $sites));
        $good = count(array_filter($sites, fn ($s) => $s['state'] === 'ok'));
        $problems = array_values(array_filter($sites, fn ($s) => $s['state'] !== 'ok'));
        $last = DB::table('server_backups')->where('kind', 'db')->where('status', 'ok')->max('finished_at');
        $detail = $good . ' of ' . count($sites) . ' sites fully backed up.'
            . ($last ? ' Last database backup ' . HealthChecks::ago($last) . '.' : '')
            . ($problems ? ' ' . implode(' ', array_map(fn ($s) => $s['site'] . ': ' . ($s['notes'][0] ?? 'not backed up yet.'), array_slice($problems, 0, 3))) : '');

        return ['label' => $label, 'state' => $state, 'detail' => $detail,
            'fix' => $state === 'ok' ? null : 'See the Server backups table below, then docs/BACKUPS.md, section 8.'];
    }

    /**
     * backups:check, hourly: a site whose last good database backup is older than 26 hours rings the owner once a
     * day, a files backup older than 8 days once a week. Nothing rings before the setup.
     *
     * @return list<string>
     */
    public static function ringStale(): array
    {
        if (! app(Backups::class)->setUp()) {
            return ['Not set up yet: nothing to check.'];
        }
        $lines = [];
        foreach (self::sites() as $site) {
            $stale = collect($site['notes'])->first(fn ($n) => str_contains($n, 'older than ' . (int) config('backups.stale_hours', 26)) || str_starts_with($n, 'No database backup'));
            if ($stale && Bells::once('stale:' . $site['site'], 24, "{$site['label']}: no backup for over " . (int) config('backups.stale_hours', 26) . ' hours',
                $stale . ' ' . ($site['db_ok'] ? 'Last good one: ' . Carbon::parse($site['db_ok']->finished_at)->timezone('Africa/Lagos')->format('D j M, H:i') . ' (Lagos).' : '') . ' See System health › Server backups.')) {
                $lines[] = "{$site['site']}: rang the owner ({$stale})";
            }
            $files = collect($site['notes'])->first(fn ($n) => str_contains($n, 'files backup is older') || str_starts_with($n, 'No files backup'));
            if ($files && Bells::once('stale-files:' . $site['site'], 24 * 7, "{$site['label']}: no files backup for over " . (int) config('backups.files_stale_days', 8) . ' days',
                $files . ' See System health › Server backups.')) {
                $lines[] = "{$site['site']}: rang the owner ({$files})";
            }
        }

        return $lines ?: ['Every site is within its limits.'];
    }

    private static function failed(object $row): bool
    {
        return $row->status === 'failed' || ($row->status === 'running' && Carbon::parse($row->started_at)->lt(now()->subHours(6)));
    }
}
