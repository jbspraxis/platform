<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast links carry a token from now on (docs/SITE_API.md section 6.4: t = first 32 hex of
 * hmac_sha256(APP_KEY, campaign_id|user_id)). signed_links marks the emails sent with it: their open
 * pixel and unsubscribe link need the token; emails sent before (0) keep working without it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('broadcast_logs', 'signed_links')) {
            Schema::table('broadcast_logs', function (Blueprint $table) {
                $table->boolean('signed_links')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('broadcast_logs', 'signed_links')) {
            Schema::table('broadcast_logs', fn (Blueprint $table) => $table->dropColumn('signed_links'));
        }
    }
};
