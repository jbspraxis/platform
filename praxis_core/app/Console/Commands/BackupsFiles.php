<?php

namespace App\Console\Commands;

use App\Support\Backups\Backups;
use Illuminate\Console\Command;

/**
 * The weekly backup of each site's uploaded files (docs/BACKUPS.md): a tar of its upload folders, encrypted and
 * uploaded to its files/ folder on Drive. The scheduler runs it on Sundays at 03:00 Lagos time.
 */
class BackupsFiles extends Command
{
    protected $signature = 'backups:files
        {--site= : One site only}
        {--no-upload : Encrypt and keep on the server only}';

    protected $description = 'Back up each site\'s uploaded files: tar, encrypt, upload to Drive, prune';

    public function handle(Backups $backups): int
    {
        $options = array_filter(['site' => $this->option('site') ?: null, 'upload' => $this->option('no-upload') ? false : null], fn ($v) => $v !== null);
        $this->line(now('Africa/Lagos')->format('Y-m-d H:i') . ' (Lagos) backups:files');
        foreach ($backups->files($options) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
