<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The logos in the strip "Organisations we have worked with" (docs/COMPANY_PROFILE.md section 4).
 *
 * They come from the client logo library in HQ, so a logo added there and ticked for the website appears here
 * without anyone touching this code. The list is a cached read (10 minutes, last good copy for a day); each
 * file is fetched once per version and then kept on this server, so a page never waits on an image.
 *
 * **It never breaks.** If HQ cannot be reached and nothing is cached, the strip falls back to the fifteen
 * logos shipped with the site (config site.clients and public/img/clients), which is exactly what it showed
 * before HQ took the list over.
 */
final class Clients
{
    /** Where a fetched logo is kept on this server */
    private const FOLDER = 'clients';

    /** How much of a logo's SHA-256 the address carries, and the file is named after */
    private const VERSION_LENGTH = 12;

    /**
     * The strip, in order: the name only as an accessible name, and the address of the file to show.
     *
     * @return list<array{key: string, name: string, url: string}>
     */
    public static function strip(): array
    {
        $rows = self::fromHq();

        return $rows === null ? self::shipped() : $rows;
    }

    /**
     * The list HQ keeps, or null when HQ has never answered. A logo whose file this server has not got yet
     * still appears: the address points at this site's own route, which fetches it on the first request.
     *
     * @return list<array{key: string, name: string, url: string}>|null
     */
    private static function fromHq(): ?array
    {
        try {
            $data = app(HqApi::class)->clients();
        } catch (Throwable) {
            // HQ has never answered on this server, or is unreachable with nothing saved: the shipped list stands in
            return null;
        }

        $out = [];
        foreach ((array) ($data['clients'] ?? []) as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($key === '' || ! preg_match('/^[a-z0-9-]{1,80}$/', $key)) {
                continue;
            }
            $version = substr((string) ($row['sha256'] ?? ''), 0, self::VERSION_LENGTH);
            $out[] = [
                'key' => $key,
                'name' => $name !== '' ? $name : $key,
                'url' => route('clients.logo', array_filter(['key' => $key, 'v' => $version])),
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * The logos shipped with the site: what the strip showed before HQ kept the list, and what it falls back
     * to when HQ has never answered on this server.
     *
     * @return list<array{key: string, name: string, url: string}>
     */
    public static function shipped(): array
    {
        $out = [];
        foreach ((array) config('site.clients', []) as $client) {
            $file = (string) ($client['file'] ?? '');
            if ($file === '' || ! is_file(public_path('img/clients/' . $file . '.webp'))) {
                continue;
            }
            $out[] = [
                'key' => $file,
                'name' => (string) ($client['name'] ?? $file),
                'url' => asset('img/clients/' . $file . '.webp'),
            ];
        }

        return $out;
    }

    /**
     * One logo's bytes for this site's own /img/client-logos/{key}.webp.
     *
     * **This runs on a public, cookieless route, so a visitor controls both `$key` and `$version`.** Neither
     * may be allowed to make this server call HQ or write a file, or a bot could fill the disk one made-up
     * version at a time and drain the Site API allowance the contact form, the chat and the verify page share.
     * So HQ is asked for a logo only when **the cached list names that key** and the caller asked for exactly
     * **the version the list names**, and the bytes are kept only when their SHA-256 is the one the list gave.
     * Everything else is answered from what is already here: the stored copy, then the file shipped with the
     * site, then 404 — without a single call to HQ.
     */
    public static function logo(string $key, string $version = ''): ?string
    {
        if (! preg_match('/^[a-z0-9-]{1,80}$/', $key)) {
            return null;
        }
        if (! preg_match('/^[a-f0-9]{0,12}$/', $version)) {
            $version = '';
        }

        $disk = Storage::disk('local');
        $sha = self::sha($key);
        $current = $sha === '' ? '' : substr($sha, 0, self::VERSION_LENGTH);

        // The version the list names, when we already have it: almost every request ends here
        if ($current !== '' && $disk->exists(self::path($key, $current))) {
            return (string) $disk->get(self::path($key, $current));
        }

        // The only path that reaches HQ: a key the list names, at the version the list names
        if ($current !== '' && $version === $current) {
            try {
                $bytes = app(HqApi::class)->clientLogo($key);
                // A logo whose bytes are not the ones the list vouched for is never kept, nor served
                if ($bytes !== '' && hash('sha256', $bytes) === $sha) {
                    self::keep($disk, $key, $current, $bytes);

                    return $bytes;
                }
            } catch (Throwable) {
                // HQ is unreachable or rate-limiting us: fall through to what is already here
            }
        }

        // Whatever version of this key we kept last, so a logo keeps showing while HQ cannot be reached,
        // and a made-up version is answered without asking HQ anything
        $kept = self::lastKept($disk, $key);
        if ($kept !== null) {
            return $kept;
        }

        $shipped = public_path('img/clients/' . $key . '.webp');

        return is_file($shipped) ? (string) file_get_contents($shipped) : null;
    }

    /** True when `$version` is the version the cached list names, so the answer may be cached for a year */
    public static function isCurrentVersion(string $key, string $version): bool
    {
        $sha = self::sha($key);

        return $sha !== '' && $version === substr($sha, 0, self::VERSION_LENGTH);
    }

    /** The SHA-256 the cached list gives for one key, or '' when HQ has never answered or does not know it */
    private static function sha(string $key): string
    {
        try {
            $data = app(HqApi::class)->clients();
        } catch (Throwable) {
            return '';
        }

        foreach ((array) ($data['clients'] ?? []) as $row) {
            if (($row['key'] ?? null) === $key) {
                $sha = (string) ($row['sha256'] ?? '');

                return preg_match('/^[a-f0-9]{64}$/', $sha) ? $sha : '';
            }
        }

        return '';
    }

    /**
     * Whatever version of one key this server kept last, or null when it has none. Reads only; it never calls
     * HQ, so it is what a made-up version and an HQ outage are both answered from.
     */
    private static function lastKept($disk, string $key): ?string
    {
        $pattern = '#^' . self::FOLDER . '/' . preg_quote($key, '#') . '-[a-f0-9]{1,' . self::VERSION_LENGTH . '}\.webp$#';
        $mine = array_values(array_filter(
            $disk->files(self::FOLDER),
            fn (string $file) => preg_match($pattern, $file) === 1,
        ));

        return $mine === [] ? null : (string) $disk->get(end($mine));
    }

    /** Keep one file per key: the version the list names, and nothing it has superseded */
    private static function keep($disk, string $key, string $current, string $bytes): void
    {
        $path = self::path($key, $current);
        $disk->put($path, $bytes);

        foreach ($disk->files(self::FOLDER) as $file) {
            if ($file !== $path && str_starts_with($file, self::FOLDER . '/' . $key . '-')) {
                $disk->delete($file);
            }
        }
    }

    private static function path(string $key, string $version): string
    {
        return self::FOLDER . '/' . $key . '-' . $version . '.webp';
    }
}
