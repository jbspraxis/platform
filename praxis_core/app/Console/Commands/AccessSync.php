<?php

namespace App\Console\Commands;

use App\Support\Access;
use Illuminate\Console\Command;

/** Run after deploying a change to config/access.php (new modules or actions). */
class AccessSync extends Command
{
    protected $signature = 'access:sync';

    protected $description = 'Write the permissions catalogue to the database and add missing starter roles';

    public function handle(): int
    {
        $result = Access::sync();
        $created = Access::seedStarterRoles();

        $this->info("{$result['permissions']} permissions in the catalogue, {$result['removed']} retired.");
        $this->info($created ? 'Starter roles created: ' . implode(', ', $created) : 'All starter roles already exist.');

        return self::SUCCESS;
    }
}
