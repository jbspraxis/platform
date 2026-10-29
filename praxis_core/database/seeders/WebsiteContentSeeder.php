<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * The website content still served by this app until PR2-4 replace it: the six Kaizen
 * articles, the portfolio case studies and quotes. Their author is the Editorial account.
 */
class WebsiteContentSeeder extends Seeder
{
    public const TABLES = ['kaizen_articles', 'portfolio_case_studies', 'portfolio_quotes'];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            ReferenceDataSeeder::load($table, __DIR__ . "/data/{$table}.php", ['__EDITORIAL__' => CoreSeeder::EDITORIAL_USER_ID]);
        }
        Schema::enableForeignKeyConstraints();
    }
}
