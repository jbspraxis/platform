<?php

namespace App\Support\CompanyProfile;

use App\Models\CompanyProfile\ClientLogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The client logo library (docs/COMPANY_PROFILE.md section 2): the one place a client logo is kept, and what
 * the company profile, the deck and jbspraxis.com all read.
 *
 * It implements App\Support\CompanyProfile\ClientLogos, the profile engine's provider interface, and
 * App\Providers\AppServiceProvider binds it in place of PR 1's DirectoryClientLogos, so the profile's
 * `clients.logos` is this library and nothing else.
 *
 * Every logo is kept three times over, because each use can draw a different thing: an SVG or a JPEG for the
 * PDF (the live PHP has no gd, so dompdf cannot read a PNG), a transparent PNG for the deck, and a WebP for
 * the website. The browser makes all three when the file is chosen; nothing here converts an image.
 *
 * Files sit on the private disk. Nothing in the library is on a public path: the website is served the bytes
 * through the signed Site API, and HQ through a permission-checked route.
 */
final class LogoLibrary implements ClientLogos
{
    public const DISK = 'local';

    public const FOLDER = 'company-profile/logos';

    /** What a rendition is called and which media types may fill it */
    public const RENDITIONS = [
        'print' => ['image/svg+xml', 'image/jpeg'],
        'png' => ['image/png'],
        'web' => ['image/webp'],
    ];

    // ------------------------------------------------------------------ reading

    /** @return \Illuminate\Database\Eloquent\Collection<int, ClientLogo> */
    public static function live()
    {
        return ClientLogo::query()->live()->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ClientLogo> */
    public static function retired()
    {
        return ClientLogo::query()->retired()->get();
    }

    /**
     * The logos one use shows, in order. Used by the profile (through the interface below), by the deck and by
     * the Site API.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ClientLogo>
     */
    public static function shownOn(string $use)
    {
        return ClientLogo::query()->live()->shownOn($use)->get();
    }

    /**
     * The profile engine's provider (ClientLogos, company-profile/CONTRACT.md `clients.logos`): the logos
     * ticked for the profile, in order, as the engine wants them.
     *
     * @return list<array{name: string, path: string}>
     */
    public function all(): array
    {
        return array_map(
            fn (array $logo) => ['name' => $logo['name'], 'path' => $logo['path']],
            $this->forProfile('profile'),
        );
    }

    /**
     * The same logos with everything a renderer might want: the transparent PNG for a deck, the kind of the
     * print file, the key and the sector. $use is one of 'profile', 'deck', 'website'.
     *
     * A logo with no print file on disk is left out rather than breaking the render.
     *
     * @return list<array{name: string, path: string, png: string|null, kind: string, key: string, sector: string|null}>
     */
    public function forProfile(string $use = 'profile'): array
    {
        $out = [];
        foreach (self::shownOn($use) as $logo) {
            $path = self::absolute($logo->print_path);
            if ($path === null) {
                continue;
            }
            $out[] = [
                'name' => (string) $logo->name,
                'path' => $path,
                'png' => self::absolute($logo->png_path),
                'kind' => (string) $logo->print_kind,
                'key' => (string) $logo->key,
                'sector' => $logo->sector,
            ];
        }

        return $out;
    }

    /** The file on disk, or null when it is missing */
    public static function absolute(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $disk = Storage::disk(self::DISK);

        return $disk->exists($path) ? $disk->path($path) : null;
    }

    public static function bytes(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $disk = Storage::disk(self::DISK);

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    // ------------------------------------------------------------------ writing

    /**
     * Add a logo, or replace the files of one that is already there.
     *
     * $renditions is ['print' => 'data:image/jpeg;base64,...', 'png' => '...', 'web' => '...']; 'print' is
     * required, the other two are optional. The browser has already trimmed and sized them.
     */
    public static function save(array $attributes, array $renditions, ?User $by = null, ?ClientLogo $logo = null): ClientLogo
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('A logo needs the name of the organisation it belongs to.');
        }

        $logo ??= new ClientLogo;
        $key = $logo->exists ? (string) $logo->key : self::uniqueKey($name);

        $files = [];
        foreach (self::RENDITIONS as $slot => $allowed) {
            $uri = trim((string) ($renditions[$slot] ?? ''));
            if ($uri === '') {
                continue;
            }
            $files[$slot] = LogoFiles::decode($uri, $allowed);
        }

        if (! $logo->exists && ! isset($files['print'])) {
            throw new RuntimeException('A logo needs a file the profile can print.');
        }

        $written = [];
        foreach ($files as $slot => $file) {
            $path = self::FOLDER.'/'.$key.'.'.self::extension($file['media']);
            Storage::disk(self::DISK)->put($path, $file['bytes']);
            $written[$slot] = ['path' => $path, 'media' => $file['media'], 'bytes' => $file['bytes']];
        }

        $logo->fill([
            'key' => $key,
            'name' => $name,
            'sector' => self::blankToNull($attributes['sector'] ?? null),
            'on_profile' => (bool) ($attributes['on_profile'] ?? true),
            'on_deck' => (bool) ($attributes['on_deck'] ?? true),
            'on_website' => (bool) ($attributes['on_website'] ?? true),
            'updated_by' => $by?->id,
        ]);

        if (! $logo->exists) {
            $logo->position = (int) ClientLogo::query()->max('position') + 1;
            $logo->created_by = $by?->id;
        }

        if (isset($written['print'])) {
            // The old print file may have had the other extension; take it away so nothing stale is served
            if ($logo->print_path && $logo->print_path !== $written['print']['path']) {
                Storage::disk(self::DISK)->delete($logo->print_path);
            }
            $logo->print_path = $written['print']['path'];
            $logo->print_kind = $written['print']['media'] === 'image/svg+xml' ? 'svg' : 'jpeg';
        }
        if (isset($written['png'])) {
            $logo->png_path = $written['png']['path'];
        }
        if (isset($written['web'])) {
            $logo->web_path = $written['web']['path'];
            $logo->web_sha256 = hash('sha256', $written['web']['bytes']);
        }
        if (isset($attributes['width'])) {
            $logo->width = (int) $attributes['width'] ?: null;
        }
        if (isset($attributes['height'])) {
            $logo->height = (int) $attributes['height'] ?: null;
        }

        $wasThere = $logo->exists;
        $logo->save();

        self::audit($wasThere ? 'PROFILE_LOGO_UPDATED' : 'PROFILE_LOGO_ADDED', $logo->name, $logo);

        return $logo;
    }

    /** The order the wall and the strip show them in: the ids, in the order they should appear */
    public static function reorder(array $ids, ?User $by = null): void
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids, $by) {
            foreach ($ids as $position => $id) {
                ClientLogo::query()->whereKey($id)->update([
                    'position' => $position + 1,
                    'updated_by' => $by?->id,
                    'updated_at' => now(),
                ]);
            }
        });

        self::audit('PROFILE_LOGOS_REORDERED', count($ids).' logos put in a new order');
    }

    /** Move one logo one place up or down, which is how the screen reorders it without dragging */
    public static function move(ClientLogo $logo, int $by, ?User $user = null): void
    {
        $order = self::live()->pluck('id')->all();
        $at = array_search($logo->id, $order, true);
        if ($at === false) {
            return;
        }
        $to = max(0, min(count($order) - 1, $at + $by));
        if ($to === $at) {
            return;
        }

        array_splice($order, $at, 1);
        array_splice($order, $to, 0, [$logo->id]);
        self::reorder($order, $user);
    }

    /** Retired, never deleted: a profile version that carried it stays true */
    public static function retire(ClientLogo $logo, ?User $by = null): ClientLogo
    {
        if ($logo->isRetired()) {
            return $logo;
        }
        $logo->forceFill(['retired_at' => now(), 'updated_by' => $by?->id])->save();
        self::audit('PROFILE_LOGO_RETIRED', $logo->name, $logo);

        return $logo;
    }

    public static function restore(ClientLogo $logo, ?User $by = null): ClientLogo
    {
        if (! $logo->isRetired()) {
            return $logo;
        }
        $logo->forceFill([
            'retired_at' => null,
            'position' => (int) ClientLogo::query()->max('position') + 1,
            'updated_by' => $by?->id,
        ])->save();
        self::audit('PROFILE_LOGO_RESTORED', $logo->name, $logo);

        return $logo;
    }

    // ------------------------------------------------------------------ small helpers

    public static function extension(string $media): string
    {
        return match ($media) {
            'image/svg+xml' => 'svg',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }

    /** "Smart Logistics and Services" to "smart-logistics-and-services", never taken twice */
    public static function uniqueKey(string $name): string
    {
        $base = Str::limit(Str::slug($name), 60, '') ?: 'logo';
        $key = $base;
        $n = 2;
        while (ClientLogo::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$n++;
        }

        return $key;
    }

    private static function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function audit(string $action, string $description, ?ClientLogo $logo = null): void
    {
        rescue(fn () => DB::table('system_audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => Str::limit($description, 1900, ''),
            'target_table' => 'company_profile_logos',
            'target_id' => $logo?->id,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]), null, false);
    }
}
