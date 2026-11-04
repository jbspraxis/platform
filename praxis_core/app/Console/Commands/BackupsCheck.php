<?php

namespace App\Console\Commands;

use App\Support\Backups\Status;
use Illuminate\Console\Command;

/**
 * Hourly (docs/BACKUPS.md 8): rings the owner when a site's last good backup is older than 26 hours (once a day),
 * or its last files backup older than 8 days (once a week).
 */
class BackupsCheck extends Command
{
    protected $signature = 'backups:check';

    protected $description = 'Ring the owner about sites whose last good backup is too old';

    public function handle(): int
    {
        foreach (Status::ringStale() as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
