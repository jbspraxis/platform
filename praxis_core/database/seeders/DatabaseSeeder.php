<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Builds a working database from scratch (php artisan migrate:fresh --seed):
 * reference data, JBS Praxis itself, then the public website content.
 * No test records and no people: the owner's account is created with
 * php artisan app:invite-owner (they choose their own password).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            CoreSeeder::class,
            WebsiteContentSeeder::class,
        ]);
    }
}
