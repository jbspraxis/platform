<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new 'company_profile' module (config/access.php, docs/COMPANY_PROFILE.md) on databases that already have
 * their roles. The profile carries the teams' credentials, so the module is marked 'wildcard' => false: no
 * starter role's "*" sweeps it up and the owner ticks it on Roles and permissions.
 *
 * One grant is made here, because sales cannot do their job without it: the Sales & Marketing role gets
 * company_profile.view, which is read and download only. Editing, approving and managing stay with the Owner
 * until the owner says otherwise.
 * Safe to run on any database: it only adds what is missing.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'company_profile.view' => ['action' => 'view', 'name' => 'Company profile: View'],
        'company_profile.edit' => ['action' => 'edit', 'name' => 'Company profile: Edit'],
        'company_profile.approve' => ['action' => 'approve', 'name' => 'Company profile: Approve'],
        'company_profile.manage' => ['action' => 'manage', 'name' => 'Company profile: Manage'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasColumn('permissions', 'module')) {
            return;
        }

        foreach (self::PERMISSIONS as $slug => $p) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $slug, 'name' => $p['name'], 'audience' => 'staff',
                    'module' => 'company_profile', 'action' => $p['action'],
                ]);
            }
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $view = DB::table('permissions')->where('slug', 'company_profile.view')->value('id');
        $sales = DB::table('roles')->where('slug', 'sales')->where('audience', 'staff')->value('id');
        if ($view && $sales && ! DB::table('role_permissions')->where('role_id', $sales)->where('permission_id', $view)->exists()) {
            DB::table('role_permissions')->insert(['role_id' => $sales, 'permission_id' => $view]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        }
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
