<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HQ as an app (docs/PWA.md): the devices that receive push notifications, each person's muted groups,
 * one delivery row per bell notification (so each is pushed at most once), and the VAPID key pair.
 * Every notification that already exists gets a 'baseline' delivery row, so none of them is ever pushed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 16)->default('aes128gcm');
            $table->string('device', 120)->nullable();
            $table->boolean('standalone')->default(false);
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamps();
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('push_preferences', function (Blueprint $table) {
            $table->char('user_id', 36)->primary();
            $table->json('muted')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // status: baseline (existed before push), sending, sent, partial, failed, muted, no-device, skipped
        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->char('notification_id', 36)->primary();
            $table->char('user_id', 36)->nullable();
            $table->string('status', 16);
            $table->unsignedSmallInteger('devices')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('created_at');
        });

        Schema::create('pwa_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value');
            $table->timestamps();
        });

        // The dispatcher looks for recent notifications without a delivery row
        if (! Schema::hasIndex('notifications', 'notifications_created_at_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index('created_at', 'notifications_created_at_index');
            });
        }

        // "Or ignore": a push run that starts while this migration runs may already have a row for a recent
        // notification; that row stays, and the migration can't fail half way on a duplicate key
        DB::table('push_deliveries')->insertOrIgnoreUsing(
            ['notification_id', 'user_id', 'status', 'devices', 'created_at'],
            DB::table('notifications')->select('id', 'user_id', DB::raw("'baseline'"), DB::raw('0'), DB::raw('CURRENT_TIMESTAMP')),
        );
    }

    public function down(): void
    {
        if (Schema::hasIndex('notifications', 'notifications_created_at_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_created_at_index');
            });
        }
        Schema::dropIfExists('pwa_settings');
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_preferences');
        Schema::dropIfExists('push_subscriptions');
    }
};
