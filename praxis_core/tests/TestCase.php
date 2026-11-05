<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A test that rebuilds the database (RefreshDatabase) does it only in SQLite or on a throwaway MySQL database
     * named praxis_test… or praxis_pr…, as UsesThrowawayDatabase does: a run with DB_CONNECTION=mysql pointed at
     * praxis_local, or at the live database, is skipped instead of wiping it.
     */
    protected function setUpTraits()
    {
        if (isset(array_flip(class_uses_recursive(static::class))[RefreshDatabase::class])) {
            $connection = config('database.default');
            $driver = (string) config("database.connections.{$connection}.driver");
            $name = (string) config("database.connections.{$connection}.database");
            if (! self::mayRebuild($driver, $name)) {
                $this->markTestSkipped("Refusing to rebuild the database '{$name}': use a throwaway database named praxis_test… or praxis_pr….");
            }
        }

        return parent::setUpTraits();
    }

    /** MySQL or MariaDB only under a throwaway name; any other driver (SQLite in memory in phpunit.xml) as before */
    public static function mayRebuild(string $driver, string $name): bool
    {
        return ! in_array($driver, ['mysql', 'mariadb'], true) || (bool) preg_match('/^praxis_(test|pr\d)[a-z0-9_]*$/', $name);
    }

    /**
     * "The screen told the person this." Confirmations and errors are toasts (docs/BRAND.md section 12): a screen
     * flashes its message and App\Support\Flash\ToastBridge dispatches it to the browser, so the words are in the
     * praxis-toast event rather than in the screen's own HTML. Use in place of assertSee for a flashed message:
     *
     *     $page->call('approve')->assertDispatched('praxis-toast', $this->toast('Booked.'));
     */
    protected function toast(string $text): \Closure
    {
        return fn ($event, $params) => str_contains((string) ($params['message'] ?? ''), $text);
    }
}
