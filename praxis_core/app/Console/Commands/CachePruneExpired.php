<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes expired rows from the database cache. Laravel removes an expired row only when the same key is read again,
 * and the website's limits (App\Services\Site\SiteLimits) keep a counter per visitor IP address, with the address in
 * the key: without this, an address would stay in the table long after the 24 hours the privacy policy on
 * jbspraxis.com promises. Runs every five minutes (routes/console.php); does nothing for another cache driver.
 */
class CachePruneExpired extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Delete expired rows from the database cache (visitor limits hold IP addresses: privacy policy)';

    public function handle(): int
    {
        $store = (array) config('cache.stores.' . config('cache.default'), []);
        if (($store['driver'] ?? null) !== 'database') {
            $this->line('cache:prune-expired: the cache is not the database, nothing to do.');

            return self::SUCCESS;
        }

        $deleted = DB::connection($store['connection'] ?? null)
            ->table($store['table'] ?? 'cache')
            ->where('expiration', '<=', now()->getTimestamp())
            ->delete();

        $this->line("cache:prune-expired: {$deleted} expired row(s) deleted.");

        return self::SUCCESS;
    }
}
