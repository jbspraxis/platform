<?php

namespace App\Support\Campaigns;

use App\Support\Hosts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Pictures in broadcasts (docs/CAMPAIGNS.md 4.4): uploaded in the editor, kept in storage/app/campaign-images and
 * served to email clients from the portal host (GET /email/img/{file}, no sign-in), because inboxes load images
 * from a public address. Names are random, so nobody can list them. Wider than 1200 px, a picture is scaled down
 * when the server has GD; otherwise it is kept as uploaded (2 MB at most).
 */
final class Images
{
    public const DIR = 'campaign-images';

    public const MAX_KB = 2048;

    public const MAX_WIDTH = 1200;

    /** Stores the upload; returns its public address */
    public static function store(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension()) === 'png' || $file->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $name = Str::uuid() . '.' . $ext;
        $dir = storage_path('app/' . self::DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $target = $dir . '/' . $name;
        if (! self::scaleDown($file->getRealPath(), $target, $ext)) {
            copy($file->getRealPath(), $target);
        }

        return self::url($name);
    }

    public static function url(string $file): string
    {
        return route('email.image', ['file' => $file]);
    }

    /** The stored file behind a public address of ours, or null */
    public static function file(?string $url): ?string
    {
        $parts = parse_url((string) $url);
        if (! $parts || ($parts['host'] ?? '') !== Hosts::portal() || ! preg_match('~^/email/img/([0-9a-f-]{36}\.(jpg|png))$~', $parts['path'] ?? '', $m)) {
            return null;
        }
        $path = storage_path('app/' . self::DIR . '/' . $m[1]);

        return is_file($path) ? $path : null;
    }

    public static function isOurs(?string $url): bool
    {
        return self::file($url) !== null;
    }

    private static function scaleDown(string $source, string $target, string $ext): bool
    {
        if (! function_exists('imagecreatetruecolor') || ! ($size = @getimagesize($source)) || $size[0] <= self::MAX_WIDTH) {
            return false;
        }
        $image = $ext === 'png' ? @imagecreatefrompng($source) : @imagecreatefromjpeg($source);
        if (! $image) {
            return false;
        }
        $height = (int) round($size[1] * self::MAX_WIDTH / $size[0]);
        $scaled = imagecreatetruecolor(self::MAX_WIDTH, $height);
        if ($ext === 'png') {
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
        }
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, self::MAX_WIDTH, $height, $size[0], $size[1]);
        $ok = $ext === 'png' ? imagepng($scaled, $target, 8) : imagejpeg($scaled, $target, 85);
        imagedestroy($image);
        imagedestroy($scaled);

        return $ok;
    }
}
