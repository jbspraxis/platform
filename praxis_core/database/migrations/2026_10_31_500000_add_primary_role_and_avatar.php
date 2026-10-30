<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner, 30 Oct 2026: each person has one primary role, the one HQ shows (top bar, menu, profile); their other
 * roles only add access. And each person can upload a photo for their avatar (My profile; HQ only).
 *
 * Existing people get as primary their first role that isn't the protected Owner role (so an owner who is also
 * Partner / Director shows as Partner / Director); someone with only the Owner role keeps Owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('staff_roles', 'is_primary')) {
            Schema::table('staff_roles', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false)->after('role_id');
            });
        }
        if (! Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('avatar_path', 255)->nullable();
            });
        }

        $rows = DB::table('staff_roles')->join('roles', 'roles.id', '=', 'staff_roles.role_id')
            ->orderBy('roles.is_protected')->orderBy('roles.id')
            ->get(['staff_roles.user_id', 'staff_roles.role_id', 'staff_roles.is_primary']);
        foreach ($rows->groupBy('user_id') as $userId => $roles) {
            if ($roles->contains(fn ($r) => (bool) $r->is_primary)) {
                continue;
            }
            DB::table('staff_roles')->where('user_id', $userId)->where('role_id', $roles->first()->role_id)->update(['is_primary' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_roles', 'is_primary')) {
            Schema::table('staff_roles', fn (Blueprint $table) => $table->dropColumn('is_primary'));
        }
        if (Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
        }
    }
};
