<?php

/*
| PHP's fileinfo extension, for servers that lack it (the live web PHP: uploads failed with 'Class "finfo" not
| found', 30 Oct 2026). Loaded by AppServiceProvider only when the real extension is missing; the work is done by
| App\Support\Polyfill\Finfo. Same names, flags and return values as the extension.
*/

defined('FILEINFO_NONE') || define('FILEINFO_NONE', 0);
defined('FILEINFO_SYMLINK') || define('FILEINFO_SYMLINK', 2);
defined('FILEINFO_MIME_TYPE') || define('FILEINFO_MIME_TYPE', 16);
defined('FILEINFO_MIME_ENCODING') || define('FILEINFO_MIME_ENCODING', 1024);
defined('FILEINFO_MIME') || define('FILEINFO_MIME', 1040);
defined('FILEINFO_DEVICES') || define('FILEINFO_DEVICES', 8);
defined('FILEINFO_CONTINUE') || define('FILEINFO_CONTINUE', 32);
defined('FILEINFO_PRESERVE_ATIME') || define('FILEINFO_PRESERVE_ATIME', 128);
defined('FILEINFO_RAW') || define('FILEINFO_RAW', 256);
defined('FILEINFO_EXTENSION') || define('FILEINFO_EXTENSION', 16777216);

if (! class_exists('finfo', false)) {
    class finfo extends App\Support\Polyfill\Finfo
    {
    }
}

if (! function_exists('finfo_open')) {
    function finfo_open(int $flags = FILEINFO_NONE, ?string $magic_database = null): finfo|false
    {
        return new finfo($flags, $magic_database);
    }

    function finfo_file(finfo $finfo, string $filename, int $flags = FILEINFO_NONE, $context = null): string|false
    {
        return $finfo->file($filename, $flags, $context);
    }

    function finfo_buffer(finfo $finfo, string $string, int $flags = FILEINFO_NONE, $context = null): string|false
    {
        return $finfo->buffer($string, $flags, $context);
    }

    function finfo_set_flags(finfo $finfo, int $flags): bool
    {
        return $finfo->set_flags($flags);
    }

    function finfo_close(finfo $finfo): bool
    {
        return true;
    }
}

if (! function_exists('mime_content_type')) {
    function mime_content_type($filename): string|false
    {
        return (new finfo(FILEINFO_MIME_TYPE))->file(is_resource($filename) ? (stream_get_meta_data($filename)['uri'] ?? '') : (string) $filename);
    }
}
