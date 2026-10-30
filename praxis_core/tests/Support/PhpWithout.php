<?php

namespace Tests\Support;

/**
 * A second PHP like the live web PHP, which has no intl, fileinfo, exif, sodium or gmp extension, for tests that
 * check HQ still works there.
 */
trait PhpWithout
{
    /**
     * A PHP without $extension: this one without php.ini (-n) plus the extensions it loads except that one (so a
     * suite already run with -n and fewer extensions keeps them that way); null when the extension is compiled in.
     */
    private function phpWithout(string $extension): ?array
    {
        $dir = (string) ini_get('extension_dir');
        if (! is_dir($dir)) {
            $dir = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $dir;
        }
        $shared = fn (string $name) => is_file("{$dir}/php_{$name}.dll") || is_file("{$dir}/{$name}.so");
        if (extension_loaded($extension) && ! $shared($extension)) {
            return null;
        }

        $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . $dir, '-d', 'memory_limit=512M'];
        $zend = array_map('strtolower', get_loaded_extensions(true));
        foreach (get_loaded_extensions() as $loaded) {
            $name = strtolower($loaded);
            // Compiled-in extensions stay without asking; Zend ones (OPcache, Xdebug) are not needed here
            if ($name !== $extension && ! in_array($name, $zend, true) && $shared($name)) {
                array_push($command, '-d', 'extension=' . $name);
            }
        }

        return $command;
    }
}
