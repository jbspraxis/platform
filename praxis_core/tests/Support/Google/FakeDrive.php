<?php

namespace Tests\Support\Google;

use App\Support\Google\GoogleWorkspace;
use App\Support\Integrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Google Drive for tests (docs/BACKUPS.md 9): HTTP fakes that answer like Drive's v3 API, so the real GoogleWorkspace
 * runs end to end and nothing leaves the machine. Folders, resumable uploads (308 until the last chunk, a Range
 * header, the status query), md5Checksum, copies and the bin, all in memory.
 *
 *   $drive = FakeDrive::install();                 // a Google connection with the drive.file scope
 *   FakeDrive::install(withDrive: false);          // connected for meetings, Drive not allowed yet
 *   $drive->failChunks = 2;                        // the next two chunks answer 503 (the upload must resume)
 *   $drive->failOnly = 'lpc_db';                   // ...only for uploads of that name
 *   $drive->allowDrive(false);                     // the owner hasn't ticked Drive (yet)
 *   $drive->corrupt = fn ($name) => true;          // Drive reports a wrong md5Checksum
 *   $drive->paths();                               // ['JBS Praxis – Server backups/praxis/daily/praxis_db_….enc', …]
 */
final class FakeDrive
{
    /** @var array<string, array{id: string, name: string, mimeType: string, parents: list<string>, trashed: bool, bytes: string, appProperties: array, createdTime: string}> */
    public array $files = [];

    public int $failChunks = 0;

    /** Only uploads whose name starts with this fail their chunks (null: any upload) */
    public ?string $failOnly = null;

    public ?\Closure $corrupt = null;

    /** @var list<string> "METHOD url [Content-Range]" of every Drive call */
    public array $calls = [];

    private array $sessions = [];

    private int $sequence = 0;

    public static function install(bool $withDrive = true): self
    {
        Integrations::set('google.client_id', '123456789-fakeclient.apps.googleusercontent.com');
        Integrations::set('google.client_secret', 'fake-client-secret');
        Integrations::set('google.refresh_token', '1//fake-refresh');
        Integrations::set('google.account_email', 'praxisjbs@gmail.com');
        Integrations::set('google.status', 'connected');
        $drive = new self;
        $drive->allowDrive($withDrive);
        Http::fake(fn (Request $request) => $drive->handle($request));

        return $drive;
    }

    /** Whether the connection has the drive.file scope (Integrations › Reconnect Google, with Drive ticked) */
    public function allowDrive(bool $allowed = true): self
    {
        Integrations::set('google.scopes', implode(' ', array_filter(GoogleWorkspace::SCOPES, fn ($s) => $allowed || $s !== GoogleWorkspace::DRIVE_SCOPE)));
        Integrations::flush();

        return $this;
    }

    /** A file already in a folder (by path under the root, e.g. 'praxis/daily'), as an earlier night left it */
    public function seed(string $folderPath, string $name, string $bytes = 'old'): string
    {
        $parent = 'root';
        foreach (array_merge([(string) config('backups.drive.root')], explode('/', $folderPath)) as $part) {
            $parent = $this->folderNamed($part, $parent) ?? $this->create($part, GoogleWorkspace::FOLDER, [$parent]);
        }

        return $this->create($name, 'application/octet-stream', [$parent], $bytes);
    }

    /** @return list<string> every file (not folder) not in the bin, by path */
    public function paths(): array
    {
        $out = [];
        foreach ($this->files as $file) {
            if (! $file['trashed'] && $file['mimeType'] !== GoogleWorkspace::FOLDER) {
                $out[] = $this->path($file['id']);
            }
        }
        sort($out);

        return $out;
    }

    public function bytes(string $path): ?string
    {
        foreach ($this->files as $file) {
            if (! $file['trashed'] && $this->path($file['id']) === $path) {
                return $file['bytes'];
            }
        }

        return null;
    }

    /** @return list<string> names in the bin */
    public function trashed(): array
    {
        return array_values(array_map(fn ($f) => $f['name'], array_filter($this->files, fn ($f) => $f['trashed'])));
    }

    public function handle(Request $request)
    {
        $url = $request->url();
        $method = $request->method();
        $range = $request->header('Content-Range')[0] ?? null;
        if (str_starts_with($url, 'https://oauth2.googleapis.com/token')) {
            return Http::response(['access_token' => 'ya29.fake-drive', 'expires_in' => 3599, 'token_type' => 'Bearer']);
        }
        $this->calls[] = trim("{$method} " . strtok($url, '?') . ' ' . ($range ?? ''));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        // Resumable upload: start a session, then chunks
        if ($path === '/upload/drive/v3/files' && $method === 'POST') {
            $id = 'session-' . (++$this->sequence);
            $this->sessions[$id] = ['meta' => $request->data(), 'size' => (int) ($request->header('X-Upload-Content-Length')[0] ?? 0), 'bytes' => '', 'file' => null];

            return Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&upload_id=' . $id]);
        }
        if ($path === '/upload/drive/v3/files' && $method === 'PUT' && isset($this->sessions[$query['upload_id'] ?? ''])) {
            return $this->chunk($query['upload_id'], (string) $range, $request->body());
        }

        if ($path === '/drive/v3/files' && $method === 'GET') {
            return Http::response(['files' => array_values(array_map(fn ($f) => $this->resource($f), $this->search((string) ($query['q'] ?? ''))))]);
        }
        if ($path === '/drive/v3/files' && $method === 'POST') {
            $data = $request->data();

            return Http::response(['id' => $this->create($data['name'], $data['mimeType'] ?? 'application/octet-stream', $data['parents'] ?? ['root'])]);
        }
        if (preg_match('#^/drive/v3/files/([^/]+)/copy$#', $path, $m) && $method === 'POST' && isset($this->files[$m[1]])) {
            $data = $request->data();
            $id = $this->create($data['name'], $this->files[$m[1]]['mimeType'], $data['parents'], $this->files[$m[1]]['bytes']);

            return Http::response($this->resource($this->files[$id]));
        }
        if (preg_match('#^/drive/v3/files/([^/]+)$#', $path, $m) && $method === 'PATCH' && isset($this->files[$m[1]])) {
            $this->files[$m[1]]['trashed'] = (bool) ($request->data()['trashed'] ?? false);

            return Http::response(['id' => $m[1]]);
        }

        return Http::response(['error' => ['code' => 404, 'message' => 'Not found (FakeDrive)', 'errors' => [['reason' => 'notFound']]]], 404);
    }

    private function chunk(string $session, string $range, string $body)
    {
        $s = &$this->sessions[$session];
        $received = fn () => strlen($s['bytes']) > 0 ? ['Range' => 'bytes=0-' . (strlen($s['bytes']) - 1)] : [];
        if ($s['file'] !== null) {
            return Http::response($this->resource($this->files[$s['file']]));
        }
        if (str_starts_with($range, 'bytes */')) {
            return Http::response('', 308, $received());
        }
        if ($this->failChunks > 0 && ($this->failOnly === null || str_starts_with((string) ($s['meta']['name'] ?? ''), $this->failOnly))) {
            $this->failChunks--;

            return Http::response(['error' => ['code' => 503, 'message' => 'Backend error (FakeDrive)']], 503);
        }
        if (! preg_match('#^bytes (\d+)-(\d+)/(\d+)$#', $range, $m) || (int) $m[1] !== strlen($s['bytes'])) {
            return Http::response('', 308, $received());
        }
        $s['bytes'] .= $body;
        if (strlen($s['bytes']) < $s['size']) {
            return Http::response('', 308, $received());
        }
        $s['file'] = $this->create($s['meta']['name'], $s['meta']['mimeType'] ?? 'application/octet-stream', $s['meta']['parents'] ?? ['root'], $s['bytes'], (array) ($s['meta']['appProperties'] ?? []));

        return Http::response($this->resource($this->files[$s['file']]));
    }

    private function create(string $name, string $mime, array $parents, string $bytes = '', array $appProperties = []): string
    {
        $id = 'file-' . (++$this->sequence);
        $this->files[$id] = ['id' => $id, 'name' => $name, 'mimeType' => $mime, 'parents' => array_values($parents), 'trashed' => false,
            'bytes' => $bytes, 'appProperties' => $appProperties, 'createdTime' => sprintf('2026-01-01T00:00:%02d.%03dZ', intdiv($this->sequence, 1000) % 60, $this->sequence % 1000)];

        return $id;
    }

    private function resource(array $file): array
    {
        $md5 = md5($file['bytes']);
        if ($this->corrupt && $file['mimeType'] !== GoogleWorkspace::FOLDER && ($this->corrupt)($file['name'])) {
            $md5 = md5('corrupted' . $file['bytes']);
        }

        return ['id' => $file['id'], 'name' => $file['name'], 'mimeType' => $file['mimeType'], 'createdTime' => $file['createdTime'],
            'size' => (string) strlen($file['bytes']), 'md5Checksum' => $md5] + ($file['appProperties'] ? ['appProperties' => $file['appProperties']] : []);
    }

    /** The few query forms HQ sends: "'<id>' in parents and trashed = false [and name = '…'] [and mimeType = '…']" */
    private function search(string $q): array
    {
        preg_match("/'((?:[^'\\\\]|\\\\.)*)' in parents/", $q, $parent);
        preg_match("/name = '((?:[^'\\\\]|\\\\.)*)'/", $q, $name);
        preg_match("/mimeType = '((?:[^'\\\\]|\\\\.)*)'/", $q, $mime);
        $unquote = fn ($v) => stripslashes((string) $v);

        return array_filter($this->files, fn ($f) => ! $f['trashed']
            && (! $parent || in_array($unquote($parent[1]), $f['parents'], true))
            && (! $name || $f['name'] === $unquote($name[1]))
            && (! $mime || $f['mimeType'] === $unquote($mime[1])));
    }

    private function folderNamed(string $name, string $parent): ?string
    {
        foreach ($this->files as $f) {
            if (! $f['trashed'] && $f['mimeType'] === GoogleWorkspace::FOLDER && $f['name'] === $name && in_array($parent, $f['parents'], true)) {
                return $f['id'];
            }
        }

        return null;
    }

    private function path(string $id): string
    {
        $parts = [];
        for ($file = $this->files[$id] ?? null; $file !== null; $file = $this->files[$file['parents'][0] ?? ''] ?? null) {
            array_unshift($parts, $file['name']);
        }

        return implode('/', $parts);
    }
}
