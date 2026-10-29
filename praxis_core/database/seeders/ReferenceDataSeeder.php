<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data the app needs to work: chart of accounts, financial dictionaries,
 * currencies and rates, tax tables, the tech catalogue and the cost calculator's settings
 * (database/seeders/data). Bank accounts and the legal entity come from
 * database/seeders/private, which is not in git: copy it from the server or enter them in HQ.
 */
class ReferenceDataSeeder extends Seeder
{
    public const TABLES = [
        'chart_of_accounts', 'financial_dictionary', 'mdm_financial_dictionary',
        'mdm_copq_architectures', 'mdm_copq_bottlenecks', 'mdm_copq_industries',
        'mdm_exchange_rates', 'mdm_tax_compliance', 'tax_configurations', 'mdm_tech_catalog',
    ];

    public const PRIVATE_TABLES = ['mdm_legal_entities', 'mdm_remittance_accounts'];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            self::load($table, __DIR__ . "/data/{$table}.php");
        }
        foreach (self::PRIVATE_TABLES as $table) {
            $file = __DIR__ . "/private/{$table}.php";
            if (is_file($file)) {
                self::load($table, $file);
            } else {
                $this->command?->warn("No private data for {$table}: enter it in HQ → Master Data.");
            }
        }
        Schema::enableForeignKeyConstraints();
    }

    public static function load(string $table, string $file, array $replace = []): int
    {
        $rows = require $file;
        foreach (array_chunk($rows, 100) as $chunk) {
            if ($replace) {
                $chunk = array_map(fn ($row) => array_map(fn ($v) => is_string($v) && isset($replace[$v]) ? $replace[$v] : $v, $row), $chunk);
            }
            DB::table($table)->insertOrIgnore($chunk);
        }
        return count($rows);
    }
}
