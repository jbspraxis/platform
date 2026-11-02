<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an invoice last reached the client's billing contacts by email, with its PDF (owner, 2 Nov 2026;
 * docs/QA-PORTAL.md D11): Finance › Invoices shows it, and sends it again when it never went.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'emailed_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->timestamp('emailed_at')->nullable()->after('client_viewed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'emailed_at')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('emailed_at'));
        }
    }
};
