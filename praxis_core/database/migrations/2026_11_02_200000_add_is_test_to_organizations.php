<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A test organisation: QA data that `php artisan qa:wipe` may delete with everything tied to it (docs/QA-PORTAL.md).
 * Only the owner sets it, on the opportunity page's Edit details; every real organisation stays 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'is_test')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->boolean('is_test')->default(false)->after('status')->comment('QA data: qa:wipe may delete it');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'is_test')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('is_test'));
        }
    }
};
