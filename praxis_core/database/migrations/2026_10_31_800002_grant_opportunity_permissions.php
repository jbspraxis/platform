<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new 'opportunities' and 'integrations' modules (config/access.php, docs/OPPORTUNITIES.md 8.1) on databases
 * that already have their roles, as 2026_10_31_100001 did for Mail. Grants follow what each starter role already
 * does with leads and proposals (crm): Sales runs opportunities; Delivery reads them and their transcripts (its
 * leads take over the project); Finance and Auditor read them. Approving proposals and SOWs and managing the
 * integrations stay with the Owner (decision 7), who has every permission already; the owner can tick them for
 * other roles on Roles and permissions. Partner / Director gets everything except those two.
 * Safe to run on any database: it only adds missing rows and skips roles that don't exist or were deleted.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'opportunities.view' => ['module' => 'opportunities', 'action' => 'view', 'name' => 'Opportunities: View'],
        'opportunities.create' => ['module' => 'opportunities', 'action' => 'create', 'name' => 'Opportunities: Create'],
        'opportunities.manage' => ['module' => 'opportunities', 'action' => 'manage', 'name' => 'Opportunities: Manage'],
        'opportunities.approve' => ['module' => 'opportunities', 'action' => 'approve', 'name' => 'Opportunities: Approve'],
        'opportunities.transcripts' => ['module' => 'opportunities', 'action' => 'transcripts', 'name' => 'Opportunities: Transcripts'],
        'integrations.manage' => ['module' => 'integrations', 'action' => 'manage', 'name' => 'Integrations: Manage'],
    ];

    private const GRANTS = [
        'partner' => ['opportunities.view', 'opportunities.create', 'opportunities.manage', 'opportunities.transcripts'],
        'sales' => ['opportunities.view', 'opportunities.create', 'opportunities.manage', 'opportunities.transcripts'],
        'delivery' => ['opportunities.view', 'opportunities.transcripts'],
        'finance' => ['opportunities.view'],
        'auditor' => ['opportunities.view'],
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
