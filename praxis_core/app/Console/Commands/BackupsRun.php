<?php

namespace App\Console\Commands;

use App\Support\Backups\Backups;
use Illuminate\Console\Command;

/**
 * The nightly server backup (docs/BACKUPS.md): every database the cPanel account owns, dumped, restore-tested on
 * Sundays, encrypted with the owner's public key and uploaded to Drive. The scheduler runs it at 02:00 Lagos time.
 */
class BackupsRun extends Command
{
    protected $signature = 'backups:run
        {--site= : One site only (praxis, lpc, nfelia, hodlc, smartls)}
        {--restore-test : Run the restore test tonight whatever the day}
        {--no-restore-test : Skip the restore test even on its day}
        {--no-upload : Encrypt and keep on the server only}';

    protected $description = 'Back up every database: dump, restore test (weekly), encrypt, upload to Drive, prune';

    public function handle(Backups $backups): int
    {
        $options = array_filter([
            'site' => $this->option('site') ?: null,
            'restore_test' => $this->option('restore-test') ? true : ($this->option('no-restore-test') ? false : null),
            'upload' => $this->option('no-upload') ? false : null,
        ], fn ($v) => $v !== null);
        $this->line(now('Africa/Lagos')->format('Y-m-d H:i') . ' (Lagos) backups:run');
        foreach ($backups->databases($options) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
