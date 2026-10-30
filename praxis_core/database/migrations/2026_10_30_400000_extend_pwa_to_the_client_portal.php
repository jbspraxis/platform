<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Client Portal as an app (docs/PWA.md, "Client Portal"): a device now belongs to a staff member
 * (user_id) or to a client portal account (client_user_id), and keeps the language it was registered in.
 * client_notifications is the portal's inbox; each row is pushed once (pushed_at is the claim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->change();
            $table->char('client_user_id', 36)->nullable()->after('user_id');
            $table->string('locale', 5)->nullable()->after('device');
            $table->index('client_user_id');
            $table->foreign('client_user_id')->references('id')->on('client_users')->cascadeOnDelete();
        });

        Schema::create('client_notifications', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('client_user_id', 36);
            $table->char('organization_id', 36)->nullable();
            // What happened (App\Support\Pwa\ClientNotify::KINDS); the words come from lang/*/portal.php in the reader's language
            $table->string('kind', 40);
            $table->json('params')->nullable();
            $table->string('link_url', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('pushed_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['client_user_id', 'is_read']);
            $table->index('created_at');
            $table->foreign('client_user_id')->references('id')->on('client_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notifications');

        DB::table('push_subscriptions')->whereNull('user_id')->delete();
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['client_user_id']);
            $table->dropIndex(['client_user_id']);
            $table->dropColumn(['client_user_id', 'locale']);
        });
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable(false)->change();
        });
    }
};
