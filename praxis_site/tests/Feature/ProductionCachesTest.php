<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The live server may run php artisan config:cache, route:cache and view:cache. Here the three run in a second PHP,
 * into a folder of their own under storage/framework/testing (never bootstrap/cache), and the site is then booted
 * from them: tests/Fixtures/with-production-caches.php.
 */
class ProductionCachesTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        // The cached configuration holds the .env values: it does not outlive the test
        if (isset($this->dir)) {
            File::deleteDirectory($this->dir);
        }
        parent::tearDown();
    }

    public function test_the_site_works_from_the_production_caches(): void
    {
        $relative = 'storage/framework/testing/production-caches-' . getmypid();
        $this->dir = base_path($relative);
        $env = [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            // Relative to the site's folder: Laravel reads a cache path without a leading slash that way
            'APP_CONFIG_CACHE' => $relative . '/config.php', 'APP_ROUTES_CACHE' => $relative . '/routes.php',
            'VIEW_COMPILED_PATH' => $this->dir . DIRECTORY_SEPARATOR . 'views',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null', 'MAIL_MAILER' => 'array',
            'SITE_API_BASE' => 'https://hq.test/api/site/v1',
        ];
        File::ensureDirectoryExists($this->dir . '/views');

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            $run = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', $command]);
            $this->assertTrue($run->successful(), "{$command}: " . $run->errorOutput() . $run->output());
        }
        $this->assertFileExists($this->dir . '/config.php');
        $this->assertFileExists($this->dir . '/routes.php');
        $this->assertNotEmpty(glob($this->dir . '/views/*.php'), 'view:cache compiled nothing');

        $paths = [
            '/', '/fr', '/services', '/fr/services', '/how-we-work', '/company', '/security', '/privacy', '/contact',
            '/careers', '/fr/careers', '/feedback', '/robots.txt', '/sitemap.xml', '/llms.txt', '/{indexnow}.txt',
            '/0123456789abcdef.txt', '/about', '/no-such-page', '/schedule', '/kaizen',
        ];
        $run = Process::path(base_path())->env($env)->run([PHP_BINARY, base_path('tests/Fixtures/with-production-caches.php'), ...$paths]);
        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());

        $this->assertTrue($out['config'], 'the site did not boot from the cached configuration');
        $this->assertTrue($out['routes'], 'the site did not boot from the cached routes');
        $ok = array_fill_keys(array_slice($paths, 0, 16), 200);
        $this->assertSame($ok + [
            '/0123456789abcdef.txt' => 404,   // not the IndexNow key
            '/about' => 301,
            '/no-such-page' => 404,
            // Pages that need HQ answer with the calm "HQ is unavailable" page, as without the caches
            '/schedule' => '503 App\Exceptions\Hq\HqUnavailable',
            '/kaizen' => '503 App\Exceptions\Hq\HqUnavailable',
        ], array_map(fn ($status) => is_string($status) ? strstr($status . ':', ':', true) : $status, $out['paths']));
    }

    public function test_env_is_read_only_in_the_config_files(): void
    {
        // After config:cache the .env file is no longer loaded: env() anywhere else would return null on the live site
        $files = Finder::create()->files()->name('*.php')->in([app_path(), base_path('routes'), base_path('bootstrap'), resource_path('views')])
            ->notPath('cache');
        $found = [];
        foreach ($files as $file) {
            if (preg_match('/(?<![\w>:$])env\s*\(/', $file->getContents())) {   // not ->env(), ::env() or $env(
                $found[] = $file->getRelativePathname();
            }
        }
        $this->assertSame([], $found);
    }
}
