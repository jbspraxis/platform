<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemManager as BaseManager;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;

/**
 * Laravel's storage manager, for servers whose PHP lacks the fileinfo extension (the live cPanel web PHP does:
 * every upload failed with 'Class "finfo" not found', 30 Oct 2026). Local disks then tell file types from the file
 * name's extension instead of the contents. With fileinfo present, nothing changes. Bound in AppServiceProvider.
 */
class FilesystemManager extends BaseManager
{
    /** Tests: behave as on a server without fileinfo */
    public static bool $withoutFileinfo = false;

    public function createLocalDriver(array $config, string $name = 'local')
    {
        if (class_exists(\finfo::class) && ! static::$withoutFileinfo) {
            return parent::createLocalDriver($config, $name);
        }

        $visibility = PortableVisibilityConverter::fromArray(
            $config['permissions'] ?? [],
            $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE
        );

        $links = ($config['links'] ?? null) === 'skip'
            ? LocalAdapter::SKIP_LINKS
            : LocalAdapter::DISALLOW_LINKS;

        $adapter = new LocalAdapter(
            $config['root'], $visibility, $config['lock'] ?? LOCK_EX, $links, new ExtensionMimeTypeDetector()
        );

        return (new LocalFilesystemAdapter(
            $this->createFlysystem($adapter, $config), $adapter, $config
        ))->diskName(
            $name
        )->shouldServeSignedUrls(
            $config['serve'] ?? false,
            fn () => $this->app['url'],
        );
    }
}
