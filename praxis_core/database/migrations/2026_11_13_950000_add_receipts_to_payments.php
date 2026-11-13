<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A receipt for every verified payment (owner, 13 Nov 2026; docs/RECEIPTS.md): its number (RCT-202611-0001, in
 * order within the month the payment was verified, Lagos time) and when it was last emailed. Payments verified
 * before today get their numbers now, in the order they were verified; nobody is emailed about them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('receipt_number', 30)->nullable()->after('status')->comment('RCT-YYYYMM-NNNN, given when the payment is verified');
            $table->timestamp('receipt_emailed_at')->nullable()->after('receipt_number');
            $table->unique('receipt_number');
        });

        $next = [];
        DB::table('transactions')->where('status', 'Verified')->whereNull('deleted_at')->whereNull('receipt_number')
            ->orderByRaw('COALESCE(verified_at, created_at)')->orderBy('id')
            ->get(['id', 'verified_at', 'created_at'])
            ->each(function ($tx) use (&$next) {
                $month = Carbon::parse($tx->verified_at ?? $tx->created_at ?? now())->setTimezone('Africa/Lagos')->format('Ym');
                $next[$month] = ($next[$month] ?? 0) + 1;
                DB::table('transactions')->where('id', $tx->id)->update(['receipt_number' => 'RCT-' . $month . '-' . str_pad((string) $next[$month], 4, '0', STR_PAD_LEFT)]);
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn(['receipt_number', 'receipt_emailed_at']);
        });
    }
};
