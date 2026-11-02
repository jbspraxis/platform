<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Client Approver role (owner, 2 Nov 2026; docs/QA-PORTAL.md D5): approving a proposal and signing a SOW need the
 * portal's projects.approve, which only Client Admin had. This role has it without the team or branding: projects
 * (view, approve), QA & messages, the document vault and redline escalation, as config/access.php's starter role.
 * Safe to run on any database: it adds the role once and leaves it alone if the owner has changed or deleted it.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'portal.projects.view', 'portal.projects.approve', 'portal.qa.view', 'portal.qa.create',
        'portal.documents.view', 'portal.documents.export', 'portal.support.create',
    ];

    public function up(): void
    {
        // A new database has no permissions yet: the AccessSeeder makes the role from config/access.php instead
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')
            || ! DB::table('permissions')->where('audience', 'client')->exists()
            || DB::table('roles')->where('audience', 'client')->where('slug', 'client-approver')->exists()) {
            return;
        }

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Client Approver', 'slug' => 'client-approver', 'audience' => 'client', 'is_protected' => false,
            'description' => 'Approves proposals, signs statements of work and approves delivered work; follows projects, QA and documents. No team or branding.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $rows = DB::table('permissions')->where('audience', 'client')->whereIn('slug', self::PERMISSIONS)->pluck('id')
            ->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])->all();
        DB::table('role_permissions')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('audience', 'client')->where('slug', 'client-approver')->value('id');
        if ($roleId && ! DB::table('client_users')->where('role_id', $roleId)->exists()) {
            DB::table('role_permissions')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
