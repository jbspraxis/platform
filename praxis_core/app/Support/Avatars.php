<?php

namespace App\Support;

use App\Models\User;
use App\Support\Polyfill\Finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * People's photos (My profile; shown in HQ only, owner 30 Oct 2026). The browser crops the photo to a square;
 * here it is re-encoded as a 512 px JPEG (which also drops anything hidden in the file) and kept privately on the
 * local disk under avatars/. Served by the hq.avatar route to signed-in staff, with a new address per photo.
 *
 * HQ draws photos at 80 px or less (22 to 44 px in lists and the top bar), so the avatar component asks for a small
 * copy (SMALL px, sharp on screens with two pixels per point), made from the stored photo the first time it is asked
 * for and kept next to it (avatars/<name>-160.jpg). The 512 px photo stays the original.
 */
final class Avatars
{
    public const SIZE = 512;

    public const SMALL = 160;

    public static function store(User $user, UploadedFile $file): void
    {
        $bytes = (string) $file->get();
        $jpeg = self::normalise($bytes);
        if ($jpeg === null) {
            throw ValidationException::withMessages(['photo' => 'That file isn’t a photo HQ can read. Use a JPG, PNG or WebP photo.']);
        }

        $path = 'avatars/' . $user->id . '-' . Str::lower(Str::random(12)) . $jpeg['ext'];
        Storage::disk('local')->put($path, $jpeg['bytes']);

        $old = $user->avatar_path;
        $user->forceFill(['avatar_path' => $path])->save();
        if ($old && $old !== $path) {
            Storage::disk('local')->delete([$old, self::smallPath($old)]);
        }
    }

    public static function remove(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('local')->delete([$user->avatar_path, self::smallPath($user->avatar_path)]);
        }
        $user->forceFill(['avatar_path' => null])->save();
    }

    /** The photo for the hq.avatar route: the small copy when $size is SMALL, otherwise the stored photo */
    public static function response(string $userId, mixed $size = null)
    {
        $disk = Storage::disk('local');
        $path = DB::table('users')->where('id', $userId)->value('avatar_path');
        abort_unless($path && $disk->exists($path), 404);

        if (is_scalar($size) && (string) $size === (string) self::SMALL) {
            $path = self::small($path) ?? $path;
        }

        // Sent from the disk as it is (not read into memory first), with its Last-Modified date; private, so no shared cache keeps it
        $response = new BinaryFileResponse($disk->path($path), 200, [
            'Content-Type' => match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            // The address changes with every new photo
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ], false);
        $response->isNotModified(request());

        return $response;
    }

    /** Where a photo's small copy is kept: avatars/<name>-160.jpg */
    private static function smallPath(string $path): string
    {
        return preg_replace('/\.[A-Za-z0-9]+$/', '', $path) . '-' . self::SMALL . '.jpg';
    }

    /** The photo's small copy, made now if it isn't there yet; null when it can't be made (no GD), so the photo itself is sent */
    private static function small(string $path): ?string
    {
        $disk = Storage::disk('local');
        $small = self::smallPath($path);
        if ($disk->exists($small)) {
            return $small;
        }

        $jpeg = self::square((string) $disk->get($path), self::SMALL);
        if ($jpeg === null) {
            return null;
        }
        // Written under another name and then renamed, so a request reading it at the same moment never gets half a file
        $tmp = $small . '.' . Str::lower(Str::random(8)) . '.tmp';
        $disk->put($tmp, $jpeg);
        if (! $disk->move($tmp, $small)) {
            $disk->delete($tmp);
        }

        return $disk->exists($small) ? $small : null;
    }

    /**
     * A square JPEG of at most SIZE px, centre-cropped; without GD, the original if it is a JPEG, PNG or WebP.
     *
     * @return array{bytes: string, ext: string}|null
     */
    private static function normalise(string $bytes): ?array
    {
        $type = Finfo::detect(substr($bytes, 0, 65536), null, strlen($bytes));
        if (! in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        if (! function_exists('imagecreatefromstring')) {
            return $type === 'image/gif' ? null : ['bytes' => $bytes, 'ext' => match ($type) { 'image/png' => '.png', 'image/webp' => '.webp', default => '.jpg' }];
        }

        $out = self::square($bytes, self::SIZE);

        return $out !== null ? ['bytes' => $out, 'ext' => '.jpg'] : null;
    }

    /** The image as a square JPEG of at most $max px, centre-cropped (GD); null when GD is missing or can't read it */
    private static function square(string $bytes, int $max): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return null;
        }
        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);
        $size = min($max, $side);
        $square = imagecreatetruecolor($size, $size);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255)); // transparent PNGs on white
        imagecopyresampled($square, $image, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        ob_start();
        imagejpeg($square, null, 86);
        $out = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($square);

        return $out !== '' ? $out : null;
    }
}
