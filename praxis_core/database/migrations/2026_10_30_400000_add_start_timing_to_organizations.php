<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contact form's "When would you like to start?" (docs/SITE_API.md section 6.4): this_quarter,
 * next_quarter, later or exploring, stored with the lead and shown in HQ → Leads. It replaces the
 * budget question on the new site (budget_estimation stays for the old page and older leads).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('organizations', 'start_timing')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('start_timing', 20)->nullable()->after('budget_estimation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'start_timing')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('start_timing'));
        }
    }
};
