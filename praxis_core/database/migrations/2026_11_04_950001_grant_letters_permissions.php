<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new 'letters' module (config/access.php, docs/DOCUMENTS.md section 2) on databases that already have their
 * roles. Owner decision, 4 November 2026: only the Owner role gets Documents for now, and the Owner role already
 * has every permission, so this migration grants nothing to any other role. The owner ticks what each role may do
 * on Roles and permissions when they are ready.
 * Safe to run on any database: it only adds the four permissions if they are missing.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'letters.view' => ['action' => 'view', 'name' => 'Documents: View'],
        'letters.create' => ['action' => 'create', 'name' => 'Documents: Create'],
        'letters.sign' => ['action' => 'sign', 'name' => 'Documents: Sign'],
        'letters.manage' => ['action' => 'manage', 'name' => 'Documents: Manage'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasColumn('permissions', 'module')) {
            return;
        }

        foreach (self::PERMISSIONS as $slug => $p) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $slug, 'name' => $p['name'], 'audience' => 'staff', 'module' => 'letters', 'action' => $p['action'],
                ]);
            }
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
