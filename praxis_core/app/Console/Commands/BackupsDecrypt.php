<?php

namespace App\Console\Commands;

use App\Support\Backups\BackupFailed;
use App\Support\Backups\Envelope;
use Illuminate\Console\Command;

/**
 * Opens a backup with the owner's private key, on a machine that has PHP and this repository (docs/BACKUPS.md 7).
 * Never on the server: the private key is never there. On the owner's Windows PC, Restore-Backup.ps1 does the same
 * without PHP.
 */
class BackupsDecrypt extends Command
{
    protected $signature = 'backups:decrypt {file : A .enc file from Drive} {--key= : The private key file (PEM)} {--out= : Where to write the opened file}';

    protected $description = 'Open (decrypt and check) a server backup with the private key';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $key = (string) $this->option('key');
        if (! is_file($file) || ! is_file($key)) {
            $this->error('Give an existing .enc file and --key=<private key file>.');

            return self::FAILURE;
        }
        $out = (string) ($this->option('out') ?: preg_replace('/\.enc$/', '', $file));
        try {
            $header = Envelope::decrypt($file, $out, (string) file_get_contents($key));
        } catch (BackupFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Opened {$out}: {$header['site']} {$header['kind']} of {$header['date']}, " . number_format((int) $header['size']) . ' bytes, checksum verified.');

        return self::SUCCESS;
    }
}
