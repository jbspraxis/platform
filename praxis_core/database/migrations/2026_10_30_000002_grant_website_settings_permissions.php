<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new 'website' module (config/access.php) on databases that already have their roles.
 *
 * `access:sync` adds new permissions to the catalogue but leaves existing roles alone, so on the live
 * database nobody but the Owner would get Website settings. This gives the starter roles what a fresh
 * install gives them: Partner / Director and Sales & Marketing view and edit, Auditor view (its '*.view').
 * Safe to run on any database: it only adds missing rows, skips roles that don't exist or were deleted,
 * and does nothing on a fresh install (the seeders create the roles with these grants).
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'website.view' => ['action' => 'view', 'name' => 'Website settings: View'],
        'website.edit' => ['action' => 'edit', 'name' => 'Website settings: Edit'],
    ];

    private const GRANTS = [
        'partner' => ['website.view', 'website.edit'],
        'sales' => ['website.view', 'website.edit'],
        'auditor' => ['website.view'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasColumn('permissions', 'module')) {
            return;
        }

        $roles = DB::table('roles')->where('audience', 'staff')->whereNull('deleted_at')
            ->whereIn('slug', array_keys(self::GRANTS))->pluck('id', 'slug');
        if ($roles->isEmpty()) {
            return;
        }

        // The permission rows themselves (access:sync writes the same values; this keeps the order of deploy steps free)
        foreach (self::PERMISSIONS as $slug => $p) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $slug, 'name' => $p['name'], 'audience' => 'staff', 'module' => 'website', 'action' => $p['action'],
                ]);
            }
        }
        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');

        $rows = [];
        foreach ($roles as $roleSlug => $roleId) {
            foreach (self::GRANTS[$roleSlug] as $perm) {
                if (isset($permissionIds[$perm])) {
                    $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionIds[$perm]];
                }
            }
        }
        DB::table('role_permissions')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $roleIds = DB::table('roles')->where('audience', 'staff')->whereIn('slug', array_keys(self::GRANTS))->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permissions')->whereIn('role_id', $roleIds)->whereIn('permission_id', $permissionIds)->delete();
    }
};
