<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A receiving account's currency and the JBS Praxis company that holds it (owner, 2 Nov 2026; docs/QA-PORTAL.md D8):
 * a client sees only the accounts in the invoice's currency held by the company that bills it (ReceivingAccounts).
 * Existing accounts keep both empty and are shown to no client until HQ sets them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mdm_remittance_accounts', 'currency')) {
            Schema::table('mdm_remittance_accounts', function (Blueprint $table) {
                $table->string('currency', 3)->nullable()->after('routing_code')->comment('The currency it receives; empty = shown to no client');
                $table->char('legal_entity_id', 36)->nullable()->after('currency')->comment('The JBS Praxis company that holds it');
                $table->foreign('legal_entity_id')->references('id')->on('mdm_legal_entities')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mdm_remittance_accounts', 'currency')) {
            Schema::table('mdm_remittance_accounts', function (Blueprint $table) {
                $table->dropForeign(['legal_entity_id']);
                $table->dropColumn(['currency', 'legal_entity_id']);
            });
        }
    }
};
