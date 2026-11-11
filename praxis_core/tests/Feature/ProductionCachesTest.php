<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The live server runs HQ from Laravel's caches: the deployer builds them after every release (`artisan optimize`,
 * deploy/server/jbspraxis-deploy.sh), so no request pays to read every config file, register every route, scan the
 * listeners or compile a view. Here config:cache, route:cache, event:cache and view:cache run in a second PHP, into a
 * folder of their own under storage/framework/testing (never bootstrap/cache), and HQ is then booted from them
 * (tests/Fixtures/with-production-caches.php): a route closure that can't be cached, a duplicate route name or an
 * env() outside config/ fails here, not on the live site.
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

    public function test_hq_the_portal_and_the_redirects_work_from_the_production_caches(): void
    {
        $relative = 'storage/framework/testing/production-caches-' . getmypid();
        $this->dir = base_path($relative);
        $env = [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            // Relative to the app's folder: Laravel reads a cache path without a leading slash that way
            'APP_CONFIG_CACHE' => $relative . '/config.php', 'APP_ROUTES_CACHE' => $relative . '/routes.php',
            'APP_EVENTS_CACHE' => $relative . '/events.php',
            'VIEW_COMPILED_PATH' => $this->dir . DIRECTORY_SEPARATOR . 'views',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null', 'MAIL_MAILER' => 'array',
            // The pages below are for signed-out visitors: they read nothing from the database
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'HQ_DOMAIN' => 'hq.jbspraxis.com', 'PORTAL_DOMAIN' => 'portal.jbspraxis.com', 'SITE_DOMAIN' => 'jbspraxis.com',
        ];
        File::ensureDirectoryExists($this->dir . '/views');

        foreach (['config:cache', 'route:cache', 'event:cache', 'view:cache'] as $command) {
            $run = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', $command]);
            $this->assertTrue($run->successful(), "{$command}: " . $run->errorOutput() . $run->output());
        }
        $this->assertFileExists($this->dir . '/config.php');
        $this->assertFileExists($this->dir . '/routes.php');
        $this->assertFileExists($this->dir . '/events.php');
        $this->assertNotEmpty(glob($this->dir . '/views/*.php'), 'view:cache compiled nothing');

        $expected = [
            'https://hq.jbspraxis.com/login' => 200,
            'https://portal.jbspraxis.com/login' => 200,
            'https://hq.jbspraxis.com/up' => 200,
            'https://hq.jbspraxis.com/pwa/ping' => 204,
            // Signed out: to the sign-in page of that host
            'https://hq.jbspraxis.com/mail' => 302,
            'https://portal.jbspraxis.com/' => 302,
            // Old single-host addresses and www
            'https://hq.jbspraxis.com/hq/finance/invoice-ledger' => 301,
            'https://jbspraxis.com/hq' => 301,
            'https://www.jbspraxis.com/services' => 301,
            'https://hq.jbspraxis.com/no-such-page' => 404,
        ];
        $run = Process::path(base_path())->env($env)->run([PHP_BINARY, base_path('tests/Fixtures/with-production-caches.php'), ...array_keys($expected)]);
        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());

        $this->assertTrue($out['config'], 'HQ did not boot from the cached configuration');
        $this->assertTrue($out['routes'], 'HQ did not boot from the cached routes');
        $this->assertTrue($out['events'], 'HQ did not boot from the cached events');
        $this->assertSame($expected, $out['paths']);
    }

    public function test_env_is_read_only_in_the_config_files(): void
    {
        // After config:cache the .env file is no longer loaded: env() anywhere else would return null on the live site
        $files = Finder::create()->files()->name('*.php')->in([app_path(), base_path('routes'), base_path('bootstrap'), resource_path('views')])
            ->notPath('#^cache/#');   // bootstrap/cache only
        $found = [];
        foreach ($files as $file) {
            // Not ->env(), ::env(), $env(, or CSS's env(safe-area-inset-…) in a view
            $code = preg_replace('/env\(\s*safe-area-inset[^)]*\)/', '', $file->getContents());
            // Nor in a comment
            $code = preg_replace('~/\*.*?\*/|^\s*//.*$~ms', '', $code);
            if (preg_match('/(?<![\w>:$-])env\s*\(/', $code)) {
                $found[] = $file->getRelativePathname();
            }
        }
        $this->assertSame([], $found);
    }
}
