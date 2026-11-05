<?php

namespace App\Support\CompanyProfile\Deck;

/**
 * The size and the type of an image, read from its own bytes.
 *
 * `getimagesize()` is part of the standard library and not of gd, but the live web PHP has neither gd nor
 * imagick (docs/DOCUMENTS.md section 3), and the book already learnt not to trust an image function to be
 * there. Two headers are all the deck needs: a PNG states its size in the first chunk, and a JPEG states
 * it in the frame header. Anything else is refused rather than guessed at, which is the same rule the
 * logo wall keeps in the PDF.
 *
 * SVG is deliberately not read. PowerPoint will place an SVG only beside a raster copy of it, and nothing
 * on the server can rasterise one; a logo with no PNG is left off the wall instead.
 */
final class Images
{
    /** @return array{width: int, height: int, type: string}|null */
    public static function size(string $path): ?array
    {
        $bytes = @file_get_contents($path, false, null, 0, 65536);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return self::png($bytes) ?? self::jpeg($bytes);
    }

    /**
     * How large one logo is drawn on the wall.
     *
     * Logos arrive at every proportion, and matching them on height alone makes a wide wordmark shout and
     * a square mark whisper — the book learnt this first (App\Support\CompanyProfile\Assets::logoBox).
     * Matching them on **area** is what a designer does by eye. The box is then clamped so nothing breaks
     * its cell or its row.
     *
     * @return array{width: float, height: float}|null
     */
    public static function box(string $path, float $area, float $maxWidth, float $maxHeight): ?array
    {
        $size = self::size($path);
        if ($size === null || $size['width'] <= 0 || $size['height'] <= 0) {
            return null;
        }

        $ratio = $size['width'] / $size['height'];
        $width = sqrt($area * $ratio);
        $height = $width / $ratio;

        if ($width > $maxWidth) {
            $width = $maxWidth;
            $height = $width / $ratio;
        }
        if ($height > $maxHeight) {
            $height = $maxHeight;
            $width = $height * $ratio;
        }

        return ['width' => round($width, 2), 'height' => round($height, 2)];
    }

    /** The part extension a file goes into the package under, or null when it is not one we place. */
    public static function extension(string $path): ?string
    {
        $size = self::size($path);

        return $size === null ? null : ($size['type'] === 'png' ? 'png' : 'jpeg');
    }

    /** @return array{width: int, height: int, type: string}|null */
    private static function png(string $bytes): ?array
    {
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n" || substr($bytes, 12, 4) !== 'IHDR') {
            return null;
        }
        $head = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

        return ['width' => (int) $head['width'], 'height' => (int) $head['height'], 'type' => 'png'];
    }

    /** @return array{width: int, height: int, type: string}|null */
    private static function jpeg(string $bytes): ?array
    {
        if (substr($bytes, 0, 2) !== "\xFF\xD8") {
            return null;
        }

        $at = 2;
        $length = strlen($bytes);
        while ($at + 9 < $length) {
            if ($bytes[$at] !== "\xFF") {
                $at++;

                continue;
            }
            $marker = ord($bytes[$at + 1]);
            // A start-of-frame marker carries the size; C4, C8 and CC are tables, not frames
            if ($marker >= 0xC0 && $marker <= 0xCF && ! in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                $frame = unpack('nheight/nwidth', substr($bytes, $at + 5, 4));

                return ['width' => (int) $frame['width'], 'height' => (int) $frame['height'], 'type' => 'jpeg'];
            }
            if ($marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD9)) {
                $at += 2;

                continue;
            }
            $segment = unpack('n', substr($bytes, $at + 2, 2));
            $at += 2 + (int) $segment[1];
        }

        return null;
    }
}
