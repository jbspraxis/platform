<?php

namespace App\Support\Polyfill;

use League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap;

/**
 * What PHP's finfo answers, for servers without the fileinfo extension (the live web PHP lacks it, 30 Oct 2026).
 * The global \finfo, finfo_* and mime_content_type() in fileinfo.php wrap this, so Laravel's file validation
 * (the careers CV's "mimes" rule), Symfony and Flysystem keep working unchanged.
 *
 * The same class as HQ's (praxis_core App\Support\Polyfill\Finfo), so both hosts read a file's type alike.
 * Types come from the first bytes (the formats HQ, the portal and the website accept: PDF, images, Word, Excel,
 * PowerPoint, audio, video, text), then from the file name's extension, then application/octet-stream.
 */
class Finfo
{
    public function __construct(private int $flags = 0, ?string $magicDatabase = null)
    {
    }

    public function file(string $filename, int $flags = 0, $context = null): string|false
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            return false;
        }
        $head = (string) @file_get_contents($filename, false, null, 0, 65536);

        return $this->answer(self::detect($head, $filename, (int) @filesize($filename)), $flags ?: $this->flags);
    }

    public function buffer(string $string, int $flags = 0, $context = null): string|false
    {
        return $this->answer(self::detect(substr($string, 0, 65536), null, strlen($string)), $flags ?: $this->flags);
    }

    public function set_flags(int $flags): bool
    {
        $this->flags = $flags;

        return true;
    }

    /** The MIME type of a file from its first bytes, else its name */
    public static function detect(string $head, ?string $path = null, ?int $size = null): string
    {
        if (($size ?? strlen($head)) === 0) {
            return 'application/x-empty';
        }

        $at = fn (int $offset, string $magic) => substr($head, $offset, strlen($magic)) === $magic;
        $ext = $path !== null ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

        $byMagic = match (true) {
            $at(0, '%PDF-') => 'application/pdf',
            $at(0, "\x89PNG\r\n\x1a\n") => 'image/png',
            $at(0, "\xFF\xD8\xFF") => 'image/jpeg',
            $at(0, 'GIF87a'), $at(0, 'GIF89a') => 'image/gif',
            $at(0, 'RIFF') && $at(8, 'WEBP') => 'image/webp',
            $at(0, 'RIFF') && $at(8, 'WAVE') => 'audio/x-wav',
            $at(0, 'BM') && strlen($head) > 26 => 'image/bmp',
            $at(0, "II*\0"), $at(0, "MM\0*") => 'image/tiff',
            $at(0, "\0\0\1\0") => 'image/vnd.microsoft.icon',
            $at(0, 'ID3'), $at(0, "\xFF\xFB"), $at(0, "\xFF\xF3"), $at(0, "\xFF\xF2") => 'audio/mpeg',
            $at(0, 'OggS') => 'audio/ogg',
            $at(0, 'fLaC') => 'audio/flac',
            $at(0, "\x1A\x45\xDF\xA3") => 'video/webm',
            $at(4, 'ftyp') => self::isoMedia(substr($head, 8, 4)),
            $at(0, "PK\x03\x04") => self::zip($head, $ext),
            $at(0, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") => match ($ext) {
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',
                'msg' => 'application/vnd.ms-outlook',
                default => 'application/msword',
            },
            $at(0, '{\rtf') => 'text/rtf',
            $at(0, "\x1F\x8B") => 'application/gzip',
            $at(0, 'Rar!') => 'application/x-rar',
            $at(0, "7z\xBC\xAF\x27\x1C") => 'application/x-7z-compressed',
            default => null,
        };
        if ($byMagic !== null) {
            return $byMagic;
        }

        // Text: no NUL bytes and valid UTF-8 (or plain 8-bit text)
        if (! str_contains($head, "\0")) {
            $start = strtolower(ltrim(substr($head, 0, 512), "\xEF\xBB\xBF \t\r\n"));
            if (str_starts_with($start, '<svg') || (str_starts_with($start, '<?xml') && str_contains($start, '<svg'))) {
                return 'image/svg+xml';
            }
            if (str_starts_with($start, '<!doctype html') || str_starts_with($start, '<html')) {
                return 'text/html';
            }
            if (str_starts_with($start, '<?xml')) {
                return 'text/xml';
            }
            if (mb_check_encoding($head, 'UTF-8') || ! preg_match('/[\x00-\x08\x0E-\x1F]/', $head)) {
                return match ($ext) {
                    'csv' => 'text/csv',
                    'ics' => 'text/calendar',
                    'vcf' => 'text/vcard',
                    'json' => 'application/json',
                    'md' => 'text/markdown',
                    default => 'text/plain',
                };
            }
        }

        if ($ext !== '' && ($mime = (new GeneratedExtensionToMimeTypeMap())->lookupMimeType($ext))) {
            return $mime;
        }

        return 'application/octet-stream';
    }

    /** MP4 family by its brand */
    private static function isoMedia(string $brand): string
    {
        return match (true) {
            str_starts_with($brand, 'M4A') => 'audio/x-m4a',
            str_starts_with($brand, 'M4V') => 'video/x-m4v',
            $brand === 'qt  ' => 'video/quicktime',
            in_array($brand, ['heic', 'heix', 'mif1', 'msf1'], true) => 'image/heic',
            str_starts_with($brand, '3gp') => 'video/3gpp',
            default => 'video/mp4',
        };
    }

    /** Office Open XML and OpenDocument are zip files; their first entries say which */
    private static function zip(string $head, string $ext): string
    {
        if (str_contains($head, 'mimetypeapplication/vnd.oasis.opendocument.text')) {
            return 'application/vnd.oasis.opendocument.text';
        }
        if (str_contains($head, 'mimetypeapplication/vnd.oasis.opendocument.spreadsheet')) {
            return 'application/vnd.oasis.opendocument.spreadsheet';
        }
        foreach (['word/' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xl/' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt/' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'] as $folder => $mime) {
            if (str_contains($head, $folder)) {
                return $mime;
            }
        }

        return match ($ext) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/zip',
        };
    }

    private function answer(string $mime, int $flags): string
    {
        $type = $flags & FILEINFO_MIME_TYPE;
        $encoding = $flags & FILEINFO_MIME_ENCODING;
        $charset = str_starts_with($mime, 'text/') || $mime === 'image/svg+xml' || $mime === 'application/json' ? 'utf-8' : 'binary';

        return match (true) {
            $type && $encoding => "{$mime}; charset={$charset}",
            (bool) $encoding => $charset,
            default => $mime,
        };
    }
}
