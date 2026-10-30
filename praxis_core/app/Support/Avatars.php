<?php

namespace App\Support;

use App\Models\User;
use App\Support\Polyfill\Finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * People's photos (My profile; shown in HQ only, owner 30 Oct 2026). The browser crops the photo to a square;
 * here it is re-encoded as a 512 px JPEG (which also drops anything hidden in the file) and kept privately on the
 * local disk under avatars/. Served by the hq.avatar route to signed-in staff, with a new address per photo.
 */
final class Avatars
{
    public const SIZE = 512;

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
            Storage::disk('local')->delete($old);
        }
    }

    public static function remove(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => null])->save();
    }

    /** The photo for the hq.avatar route */
    public static function response(string $userId)
    {
        $path = DB::table('users')->where('id', $userId)->value('avatar_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            // The address changes with every new photo
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return null;
        }
        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);
        $size = min(self::SIZE, $side);
        $square = imagecreatetruecolor($size, $size);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255)); // transparent PNGs on white
        imagecopyresampled($square, $image, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        ob_start();
        imagejpeg($square, null, 86);
        $out = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($square);

        return $out !== '' ? ['bytes' => $out, 'ext' => '.jpg'] : null;
    }
}
