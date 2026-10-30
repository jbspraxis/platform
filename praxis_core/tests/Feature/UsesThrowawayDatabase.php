<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Access;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * For feature tests that need HQ's real schema. The baseline migration is MySQL only (phpunit.xml's SQLite in
 * memory can't build it), so these tests run on a throwaway MySQL database and are skipped otherwise:
 *
 *   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_PASSWORD=… DB_DATABASE=praxis_test \
 *     php vendor/bin/phpunit tests/Feature/SiteApiTest.php tests/Feature/WebsiteSettingsScreenTest.php
 *
 * The database is rebuilt (migrate:fresh --seed), so its name must start with praxis_test or praxis_pr
 * (for example praxis_test or praxis_pr2b_fix_hq); any other name is refused, so praxis_local and the live
 * database can never be wiped by a test run. Create the empty database first.
 */
trait UsesThrowawayDatabase
{
    use RefreshDatabase {
        refreshDatabase as private rebuildThrowawayDatabase;
    }

    /** migrate:fresh --seed: reference data, roles and JBS Praxis itself (no people) */
    protected bool $seed = true;

    public function refreshDatabase(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $name = (string) config("database.connections.{$connection}.database");

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Needs MySQL: run with DB_CONNECTION=mysql and a throwaway DB_DATABASE (see tests/Feature/UsesThrowawayDatabase.php).');
        }
        if (!preg_match('/^praxis_(test|pr\d)[a-z0-9_]*$/', $name)) {
            $this->markTestSkipped("Refusing to rebuild the database '{$name}': use a throwaway database named praxis_test… or praxis_pr….");
        }

        $this->rebuildThrowawayDatabase();
    }

    /** Fills the NOT NULL columns a test doesn't care about, so the fixtures follow the real schema */
    protected function insertRow(string $table, array $values): array
    {
        $columns = DB::select('SELECT COLUMN_NAME c, DATA_TYPE t, COLUMN_TYPE ct, IS_NULLABLE n, COLUMN_DEFAULT d, EXTRA e
            FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
        foreach ($columns as $col) {
            if (array_key_exists($col->c, $values) || $col->n === 'YES' || $col->d !== null || str_contains((string) $col->e, 'auto_increment')) {
                continue;
            }
            $values[$col->c] = match (true) {
                $col->t === 'enum' => trim(explode(',', substr($col->ct, 5, -1))[0], "'"),
                $col->t === 'char' && str_contains($col->ct, '36') => (string) Str::uuid(),
                in_array($col->t, ['int', 'bigint', 'tinyint', 'smallint', 'decimal', 'double', 'float'], true) => 0,
                in_array($col->t, ['date', 'datetime', 'timestamp'], true) => now(),
                $col->t === 'json' => '{}',
                default => 'x',
            };
        }
        DB::table($table)->insert($values);

        return $values;
    }

    /** A staff member with these roles (slugs from AccessSeeder, e.g. sales, auditor, engineer) */
    protected function staff(string $slug, array $roles): User
    {
        $id = (string) Str::uuid();
        $this->insertRow('users', [
            'id' => $id, 'organization_id' => CoreSeeder::INTERNAL_ORG_ID, 'first_name' => ucfirst($slug), 'last_name' => 'Test',
            'email' => $slug . '.' . Str::random(6) . '@local.test', 'password' => Hash::make(Str::random(24)), 'status' => 'Active',
        ]);
        foreach ($roles as $role) {
            DB::table('staff_roles')->insert([
                'user_id' => $id,
                'role_id' => DB::table('roles')->where('audience', 'staff')->where('slug', $role)->value('id'),
            ]);
        }
        Access::flush();

        return User::findOrFail($id);
    }
}
