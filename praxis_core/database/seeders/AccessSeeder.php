<?php

namespace Database\Seeders;

use App\Support\Access;
use Illuminate\Database\Seeder;

/**
 * Permissions catalogue plus the starter roles (8 staff, 3 client) from config/access.php.
 * Safe to run again: existing roles keep whatever permissions the owner gave them.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        Access::sync();
        Access::seedStarterRoles();
    }
}
