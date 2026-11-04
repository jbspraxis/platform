<?php

namespace App\Console\Commands;

use App\Support\Backups\BackupFailed;
use App\Support\Backups\Setup;
use Illuminate\Console\Command;

/**
 * Server backups, once on the server (docs/BACKUPS.md 4): the backups folder, the read-only MySQL user (its password
 * goes straight into ~/.backup-my.cnf, never to the screen), the restore test's scratch database and the grants.
 * Safe to run again. --once does nothing after the first success, so a one-shot cron line can call it every minute.
 */
class BackupsSetup extends Command
{
    protected $signature = 'backups:setup
        {--dry-run : List what it would do, and the databases with their sites; change nothing}
        {--once : Do nothing if the setup already succeeded (for a one-shot cron line)}';

    protected $description = 'Set up the server backups: read-only MySQL user, scratch database and grants (through cPanel uapi)';

    public function handle(Setup $setup): int
    {
        if ($this->option('once') && $setup->done()) {
            return self::SUCCESS;
        }
        $this->line(now('Africa/Lagos')->format('Y-m-d H:i') . ' (Lagos) backups:setup' . ($this->option('dry-run') ? ' --dry-run' : ''));
        try {
            foreach ($setup->run((bool) $this->option('dry-run')) as $line) {
                $this->line($line);
            }
        } catch (BackupFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($this->option('dry-run') ? 'Dry run: nothing was changed.' : 'Set up. The first backup runs tonight at 02:00 Lagos time.');

        return self::SUCCESS;
    }
}
