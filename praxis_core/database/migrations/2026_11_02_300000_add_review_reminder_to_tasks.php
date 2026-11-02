<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client review closes after 2 working days, not 5 hours (owner, 2 Nov 2026; docs/QA-PORTAL.md D9), and the client is
 * reminded once the day before: qa_reminded_at says the reminder went, and is cleared when the task re-enters review.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'qa_reminded_at')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->timestamp('qa_reminded_at')->nullable()->after('qa_sla_expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'qa_reminded_at')) {
            Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('qa_reminded_at'));
        }
    }
};
