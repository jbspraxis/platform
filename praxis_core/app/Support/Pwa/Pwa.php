<?php

namespace App\Support\Pwa;

/**
 * HQ as an app (docs/PWA.md): the app's own files and their addresses. The files live in
 * resources/pwa and are served on the HQ host only, at /pwa/v/{version}/{path}. The version
 * changes whenever a file does, so every address can be cached for a year.
 */
class Pwa
{
    private const TYPES = [
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'json' => 'application/json',
        'ico' => 'image/x-icon',
    ];

    private static ?string $version = null;

    /** 10 hex characters from the paths, sizes and times of every file in resources/pwa. */
    public static function version(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }

        $root = resource_path('pwa');
        $files = [];
        if (is_dir($root)) {
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($items as $item) {
                if ($item->isFile()) {
                    $files[] = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1)) . ':' . $item->getSize() . ':' . $item->getMTime();
                }
            }
        }
        sort($files);

        return self::$version = substr(md5(implode('|', $files)), 0, 10);
    }

    /** The address of a file in resources/pwa, e.g. asset('hq-pwa.js') is /pwa/v/3f2a9c01de/hq-pwa.js */
    public static function asset(string $path): string
    {
        return '/pwa/v/' . self::version() . '/' . ltrim($path, '/');
    }

    /** The file behind an asset path, or null: only known file types, never outside resources/pwa. */
    public static function file(string $path): ?string
    {
        if (! preg_match('#^[A-Za-z0-9][A-Za-z0-9/_.-]*$#', $path) || str_contains($path, '..') || ! isset(self::TYPES[self::extension($path)])) {
            return null;
        }

        $root = realpath(resource_path('pwa'));
        $file = $root ? realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)) : false;

        return $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file) ? $file : null;
    }

    public static function type(string $path): string
    {
        return self::TYPES[self::extension($path)] ?? 'application/octet-stream';
    }

    /** Forget the computed version (tests and commands that write files in resources/pwa). */
    public static function flush(): void
    {
        self::$version = null;
    }

    private static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }
}
