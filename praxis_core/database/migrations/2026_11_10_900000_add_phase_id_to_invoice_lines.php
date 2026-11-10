<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One invoice, several milestones (owner, 10 Nov 2026): each invoice line can bill its own milestone. invoices.phase_id
 * keeps naming the milestone when an invoice bills exactly one, as before; App\Support\Finance\MilestoneInvoices reads
 * both. Existing invoices' lines take their invoice's milestone, so nothing already invoiced is offered again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_lines', 'phase_id')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->char('phase_id', 36)->nullable()->after('invoice_id');
                $table->index('phase_id', 'invoice_lines_phase_id_index');
            });
        }

        DB::table('invoices')->whereNotNull('phase_id')->orderBy('id')->select('id', 'phase_id')
            ->chunk(500, function ($invoices) {
                foreach ($invoices as $invoice) {
                    DB::table('invoice_lines')->where('invoice_id', $invoice->id)->whereNull('phase_id')->update(['phase_id' => $invoice->phase_id]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_lines', 'phase_id')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->dropIndex('invoice_lines_phase_id_index');
                $table->dropColumn('phase_id');
            });
        }
    }
};
