<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the filters the Finance screens read on every open (performance pass, 11 Nov 2026). Indexes only: no
 * column changes, nothing to back fill, each added once (re-running skips it).
 *
 *  invoices         Finance › Invoices: one status at a time, soonest due first (WHERE status = ? ORDER BY due_date),
 *                   and the overdue run (WHERE status = 'Pending' AND due date passed).
 *  cash_requests    Cash request approvals: one status at a time, newest first.
 *  transactions     Payment verification: one status at a time, newest first.
 *  journal_entries  General ledger and the BI overview read a period (WHERE entry_date BETWEEN ...); a verified
 *                   payment checks the invoice's accrual was posted (WHERE source_module = ? AND source_id = ?).
 */
return new class extends Migration
{
    private const INDEXES = [
        'invoices' => [
            'invoices_status_due_index' => ['status', 'due_date'],
        ],
        'cash_requests' => [
            'cash_requests_status_created_index' => ['status', 'created_at'],
        ],
        'transactions' => [
            'transactions_status_created_index' => ['status', 'created_at'],
        ],
        'journal_entries' => [
            'journal_entries_date_created_index' => ['entry_date', 'created_at'],
            'journal_entries_source_index' => ['source_module', 'source_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasIndex($table, $name) && ! Schema::hasIndex($table, $columns)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
