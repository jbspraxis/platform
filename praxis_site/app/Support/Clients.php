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
            $version = substr((string) ($row['sha256'] ?? ''), 0, 12);
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
     * One logo's bytes for this site's own /img/clients/{key}.webp: the copy kept here, else fetched from HQ
     * once and kept, else the file shipped with the site. Null when there is nothing to show at all.
     */
    public static function logo(string $key, string $version = ''): ?string
    {
        if (! preg_match('/^[a-z0-9-]{1,80}$/', $key)) {
            return null;
        }
        $version = preg_match('/^[a-f0-9]{0,12}$/', $version) ? $version : '';
        $path = self::FOLDER . '/' . $key . ($version !== '' ? '-' . $version : '') . '.webp';
        $disk = Storage::disk('local');

        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }

        try {
            $bytes = app(HqApi::class)->clientLogo($key);
            if ($bytes !== '') {
                $disk->put($path, $bytes);

                return $bytes;
            }
        } catch (Throwable) {
            // HQ is unreachable, rate-limiting us or does not know this key: fall through to what we shipped
        }

        $shipped = public_path('img/clients/' . $key . '.webp');

        return is_file($shipped) ? (string) file_get_contents($shipped) : null;
    }
}
