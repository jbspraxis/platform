<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions replace clearance levels (see config/access.php).
 *
 *   roles             audience 'staff' (HQ) or 'client' (portal); the Owner role is protected
 *   permissions       one row per module x action, e.g. finance.view, portal.billing.create
 *   role_permissions  what each role may do (existing table)
 *   staff_roles       staff can hold several roles; their permissions add up
 *   client_users      one role each; an organisation's primary account is always Client Admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('audience', ['staff', 'client'])->default('staff')->after('slug');
            $table->string('description', 255)->nullable()->after('audience');
            $table->boolean('is_protected')->default(false)->after('description')->comment('Owner: always every permission, cannot be edited or deleted');
            $table->dropUnique('slug');
            $table->unique(['audience', 'slug'], 'roles_audience_slug_unique');
            $table->dropColumn('clearance_level');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->enum('audience', ['staff', 'client'])->default('staff')->after('slug');
            $table->string('module', 50)->after('audience');
            $table->string('action', 20)->after('module');
        });

        Schema::create('staff_roles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('user_id', 36);
            $table->bigInteger('role_id')->unsigned();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->primary(['user_id', 'role_id']);
            $table->index('role_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::table('client_users', function (Blueprint $table) {
            $table->bigInteger('role_id')->unsigned()->nullable()->after('organization_id');
            $table->foreign('role_id', 'client_users_role_fk')->references('id')->on('roles')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_ibfk_2');
            $table->dropColumn('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('role_id')->unsigned()->nullable()->after('organization_id');
            $table->foreign('role_id', 'users_ibfk_2')->references('id')->on('roles')->nullOnDelete();
        });

        Schema::table('client_users', function (Blueprint $table) {
            $table->dropForeign('client_users_role_fk');
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('staff_roles');

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['audience', 'module', 'action']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->integer('clearance_level')->default(1)->after('slug')->comment('1 = Client, 5 = Super Admin');
            $table->dropUnique('roles_audience_slug_unique');
            $table->unique('slug', 'slug');
            $table->dropColumn(['audience', 'description', 'is_protected']);
        });
    }
};
