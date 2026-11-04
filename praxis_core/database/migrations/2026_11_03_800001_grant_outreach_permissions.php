<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new 'outreach' module (config/access.php, docs/CAMPAIGNS.md section 10) on databases that already have their
 * roles, as 2026_10_31_800002 did for Opportunities. Sales & Marketing writes and sends outreach from their own
 * mailbox (view, create); Partner / Director does too; Auditor reads it. outreach.approve (the sending limits,
 * resuming a paused sender, every campaign) stays with the Owner, who has every permission already; the owner can
 * tick it for other roles on Roles and permissions.
 * Safe to run on any database: it only adds missing rows and skips roles that don't exist or were deleted.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'outreach.view' => ['module' => 'outreach', 'action' => 'view', 'name' => 'Outreach: View'],
        'outreach.create' => ['module' => 'outreach', 'action' => 'create', 'name' => 'Outreach: Create'],
        'outreach.approve' => ['module' => 'outreach', 'action' => 'approve', 'name' => 'Outreach: Approve'],
    ];

    private const GRANTS = [
        'partner' => ['outreach.view', 'outreach.create'],
        'sales' => ['outreach.view', 'outreach.create'],
        'auditor' => ['outreach.view'],
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

        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
