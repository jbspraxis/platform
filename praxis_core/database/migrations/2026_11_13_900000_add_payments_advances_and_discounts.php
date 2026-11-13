<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money HQ couldn't record (owner, 13 Nov 2026: "our system does not allow recording payments and editing invoices"):
 *
 * - A payment received by bank transfer, cash or cheque is recorded in HQ by Finance (transactions.source = 'hq'),
 *   not only uploaded by the client in the portal. Several payments can settle one invoice.
 * - An invoice line has a kind: a charge, a discount (stored negative), an advance on a milestone, or the deduction of
 *   that advance on the milestone's own invoice (less_advance, naming the advance invoice it deducts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('kind', 20)->default('charge')->after('invoice_id')
                ->comment('charge | discount (negative) | advance (on a milestone) | less_advance (deducts an advance invoice)');
            $table->char('advance_invoice_id', 36)->nullable()->after('kind')->comment('On a less_advance line: the advance invoice it deducts');
            $table->index('advance_invoice_id');
        });

        // A client's company registration number (RC in Nigeria), printed on its invoices with its address, email and
        // phone (owner, 13 Nov 2026: "invoice carries no address, email, nothing")
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('registration_number', 60)->nullable()->after('tax_identification_number');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source', 10)->default('portal')->after('invoice_id')->comment('portal: proof uploaded by the client; hq: recorded by Finance');
            $table->date('received_on')->nullable()->after('source')->comment('The day the money arrived (HQ-recorded payments)');
            $table->string('note', 255)->nullable()->after('tx_hash_or_ref');
        });

        // Money arrives by more than the portal's four ways
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY method ENUM('Bank Transfer','USDT','Campay','Paystack','Cash','Cheque','Other') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('transactions')->whereIn('method', ['Cash', 'Cheque', 'Other'])->update(['method' => 'Bank Transfer']);
            DB::statement("ALTER TABLE transactions MODIFY method ENUM('Bank Transfer','USDT','Campay','Paystack') NOT NULL");
        }
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['source', 'received_on', 'note']);
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('registration_number');
        });
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['advance_invoice_id']);
            $table->dropColumn(['kind', 'advance_invoice_id']);
        });
    }
};
