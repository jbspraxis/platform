<?php

namespace Tests\Unit;

use App\Support\Monitor\HealthChecks;
use PHPUnit\Framework\TestCase;

/** System health → Server speed settings (HealthChecks::speedFrom): what it says for each way the server can run HQ. */
class SpeedHealthCheckTest extends TestCase
{
    private const GOOD = ['sapi' => 'fpm-fcgi', 'early_finish' => true, 'opcache' => true, 'opcache_full' => false,
        'hit_rate' => 99.2, 'free_mb' => 80, 'cached_files' => 3200, 'laravel_caches' => true];

    public function test_a_well_set_server_is_ok_and_says_why(): void
    {
        $check = HealthChecks::speedFrom(self::GOOD);
        $this->assertSame('ok', $check['state']);
        $this->assertNull($check['fix']);
        $this->assertStringContainsString('99.2% of code from memory', $check['detail']);
        $this->assertStringContainsString('3,200 files, 80 MB free', $check['detail']);
    }

    public function test_each_problem_is_a_warning_with_its_fix_and_never_bad(): void
    {
        foreach ([
            [['early_finish' => false, 'sapi' => 'cgi-fcgi'], 'opening a mail waits for the mail server', 'PHP-FPM'],
            [['opcache' => false], 'OPcache is off', 'opcache.enable=1'],
            [['opcache_full' => true], 'OPcache is full or misses often', 'more OPcache memory'],
            [['hit_rate' => 80.0], '(80% from memory)', 'more OPcache memory'],
            [['laravel_caches' => false], "Laravel's caches are not built", 'php artisan optimize'],
        ] as [$change, $detail, $fix]) {
            $check = HealthChecks::speedFrom($change + self::GOOD);
            $this->assertSame('warn', $check['state'], $detail);
            $this->assertStringContainsString($detail, $check['detail']);
            $this->assertStringContainsString($fix, (string) $check['fix']);
        }
    }
}
