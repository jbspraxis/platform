<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * cache:prune-expired: expired database cache rows (the website's visitor limits, keyed by IP address) go, live ones
 * stay, and it runs every five minutes. MySQL only (UsesThrowawayDatabase).
 */
class CachePruneExpiredTest extends TestCase
{
    use UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A visitor's day counter that has run out, its timer, and a value still in use
        DB::table('cache')->insert([
            ['key' => 'laravel_cache_contact-form:day:203.0.113.5', 'value' => 'i:1;', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'laravel_cache_contact-form:day:203.0.113.5:timer', 'value' => 'i:1;', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'laravel_cache_site-settings', 'value' => 's:2:"{}";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);
    }

    public function test_expired_rows_go_and_live_rows_stay(): void
    {
        config(['cache.default' => 'database']);

        $this->artisan('cache:prune-expired')->expectsOutput('cache:prune-expired: 2 expired row(s) deleted.')->assertSuccessful();

        $this->assertSame(['laravel_cache_site-settings'], DB::table('cache')->pluck('key')->all());
    }

    public function test_another_cache_driver_is_left_alone(): void
    {
        config(['cache.default' => 'array']);

        $this->artisan('cache:prune-expired')->expectsOutputToContain('nothing to do')->assertSuccessful();

        $this->assertSame(3, DB::table('cache')->count());
    }

    public function test_it_runs_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'cache:prune-expired'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
