<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A broadcast is written in one language (en or fr), chosen in HQ → Broadcasts. The email frame (greeting
 * fallbacks, sign-off, footer) follows it (broadcast:process). Campaigns sent before were English.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('broadcast_campaigns', 'language')) {
            Schema::table('broadcast_campaigns', function (Blueprint $table) {
                $table->char('language', 2)->default('en')->after('body_html');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('broadcast_campaigns', 'language')) {
            Schema::table('broadcast_campaigns', fn (Blueprint $table) => $table->dropColumn('language'));
        }
    }
};
