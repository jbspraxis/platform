<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new 'mail' and 'mailboxes' modules (config/access.php) on databases that already have their roles,
 * as 2026_10_30_000002 did for Website settings. Every starter staff role gets Mail (mail.view: their own
 * mailbox and the shared mailboxes they are invited to); adding shared mailboxes (mailboxes.create) stays
 * with Owner and Partner / Director, who have every permission already.
 * Safe to run on any database: it only adds missing rows and skips roles that don't exist or were deleted.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'mail.view' => ['module' => 'mail', 'action' => 'view', 'name' => 'Mail: View'],
        'mailboxes.create' => ['module' => 'mailboxes', 'action' => 'create', 'name' => 'Shared mailboxes: Create'],
    ];

    private const GRANTS = [
        'finance' => ['mail.view'],
        'delivery' => ['mail.view'],
        'engineer' => ['mail.view'],
        'sales' => ['mail.view'],
        'hr' => ['mail.view'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasColumn('permissions', 'module')) {
            return;
        }

        foreach (self::PERMISSIONS as $slug => $p) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $slug, 'name' => $p['name'], 'audience' => 'staff', 'module' => $p['module'], 'action' => $p['action'],
                ]);
            }
        }

        $roles = DB::table('roles')->where('audience', 'staff')->whereNull('deleted_at')
            ->whereIn('slug', array_keys(self::GRANTS))->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');

        $rows = [];
        foreach ($roles as $roleSlug => $roleId) {
            foreach (self::GRANTS[$roleSlug] as $perm) {
                if (isset($permissionIds[$perm])) {
                    $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionIds[$perm]];
                }
            }
        }
        if ($rows) {
            DB::table('role_permissions')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $roleIds = DB::table('roles')->where('audience', 'staff')->whereIn('slug', array_keys(self::GRANTS))->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', ['mail.view'])->pluck('id');
        DB::table('role_permissions')->whereIn('role_id', $roleIds)->whereIn('permission_id', $permissionIds)->delete();
    }
};
