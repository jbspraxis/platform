<?php

namespace App\Support\Backups;

use App\Support\Google\GoogleWorkspace;

/**
 * The one tidy Drive folder (owner's decision 5), in praxisjbs@gmail.com's My Drive:
 *
 *   JBS Praxis – Server backups/
 *   ├── READ ME FIRST – how to restore.pdf
 *   ├── praxis/   daily/ (last 30)   monthly/ (last 12)   files/ (weekly, last 8)
 *   ├── lpc/ nfelia/ hodlc/ smartls/   (the same three folders each)
 *   └── other/<database>/   daily/ monthly/   (only if the account has a database no site claims)
 *
 * Folders are found by name each run (HQ sees only what it created) and made when missing, so a folder the owner
 * deletes comes back the next night. Every upload is checked against Drive's md5Checksum.
 */
class DriveStore
{
    /** @var array<string, string> path => folder id, for this run */
    private array $folders = [];

    public function __construct(private GoogleWorkspace $google) {}

    public function ready(): bool
    {
        return $this->google->canUseDrive();
    }

    public function root(): string
    {
        return $this->folders[''] ??= $this->google->driveFolder((string) config('backups.drive.root'), 'root');
    }

    /** daily | monthly | files of a site ('praxis', or 'other/smartqaq_wp1') */
    public function folder(string $site, string $kind): string
    {
        $path = $site . '/' . $kind;
        if (! isset($this->folders[$path])) {
            $parent = $this->root();
            foreach (explode('/', $path) as $i => $part) {
                $key = implode('/', array_slice(explode('/', $path), 0, $i + 1));
                $parent = $this->folders[$key] ??= $this->google->driveFolder($part, $parent);
            }
        }

        return $this->folders[$path];
    }

    /** The five sites' folders, made up front so the tree is complete from the first night */
    public function prepare(): void
    {
        foreach (Sites::keys() as $site) {
            foreach (['daily', 'monthly', 'files'] as $kind) {
                $this->folder($site, $kind);
            }
        }
    }

    /**
     * Uploads, checks Drive's checksum (a mismatch is sent once more, then fails), and replaces an older file of the
     * same name in that folder (a second run on the same day).
     *
     * @return array{id: string, size: int}
     */
    public function put(string $localPath, string $name, string $folderId, string $md5, array $properties = []): array
    {
        for ($attempt = 1; ; $attempt++) {
            $file = $this->google->driveUpload($localPath, $name, $folderId, $properties);
            if (hash_equals($md5, strtolower((string) ($file['md5Checksum'] ?? '')))) {
                break;
            }
            $this->google->driveTrash((string) $file['id']);
            if ($attempt === 2) {
                throw new BackupFailed('verify', "Drive’s copy of {$name} does not match the file sent (checksum " . ($file['md5Checksum'] ?? 'missing') . ", expected {$md5}), twice. The copy on the server is kept.");
            }
        }
        foreach ($this->google->driveList($folderId, $name) as $old) {
            if ($old['id'] !== $file['id']) {
                $this->google->driveTrash((string) $old['id']);
            }
        }

        return ['id' => (string) $file['id'], 'size' => (int) ($file['size'] ?? 0)];
    }

    /**
     * The first backup of a month is also copied into monthly/ (once per month and database). Returns the copy's id,
     * or null when this month already has one.
     */
    public function monthly(string $site, string $fileId, string $name, string $md5): ?string
    {
        $date = (string) Sites::dateIn($name);
        $series = str_replace($date, '', $name);
        $folder = $this->folder($site, 'monthly');
        foreach ($this->google->driveList($folder) as $file) {
            $other = (string) Sites::dateIn($file['name']);
            if ($other !== '' && substr($other, 0, 7) === substr($date, 0, 7) && str_replace($other, '', $file['name']) === $series) {
                return null;
            }
        }
        $copy = $this->google->driveCopy($fileId, $name, $folder);
        if (! hash_equals($md5, strtolower((string) ($copy['md5Checksum'] ?? '')))) {
            $this->google->driveTrash((string) $copy['id']);

            throw new BackupFailed('verify', "Drive’s monthly copy of {$name} does not match (checksum).");
        }

        return (string) $copy['id'];
    }

    /**
     * Keeps the newest $keep dates in a folder (a date may hold several databases of one site) and moves older
     * backups to Drive's bin. Monthly folders keep months: their files have one date each.
     *
     * @return list<string> the names moved to the bin
     */
    public function prune(string $folderId, int $keep): array
    {
        $files = array_filter($this->google->driveList($folderId), fn ($f) => ($f['mimeType'] ?? '') !== GoogleWorkspace::FOLDER && Sites::dateIn($f['name']) !== null);
        $dates = array_unique(array_map(fn ($f) => Sites::dateIn($f['name']), $files));
        rsort($dates);
        $kept = array_slice($dates, 0, max(1, $keep));
        $removed = [];
        foreach ($files as $file) {
            if (! in_array(Sites::dateIn($file['name']), $kept, true)) {
                $this->google->driveTrash((string) $file['id']);
                $removed[] = $file['name'];
            }
        }

        return $removed;
    }

    /** The restore guide at the top of the folder; replaced only when its text changes */
    public function readme(Readme $readme): void
    {
        $name = (string) config('backups.drive.readme');
        $version = $readme->version();
        $existing = $this->google->driveList($this->root(), $name);
        if (collect($existing)->contains(fn ($f) => ($f['appProperties']['version'] ?? null) === $version)) {
            return;
        }
        $path = $readme->writePdf();
        try {
            $this->put($path, $name, $this->root(), md5_file($path), ['version' => $version]);
        } finally {
            @unlink($path);
        }
    }
}
