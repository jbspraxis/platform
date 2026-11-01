<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The VAT switch per JBS Praxis company (owner, 1 Nov 2026: "We are not billing VAT until we fully register JBS
 * Praxis"; App\Support\Finance\Vat).
 *
 * mdm_legal_entities: charges_vat (every company starts off), its VAT number, the day VAT starts. opportunities: the
 * company that bills the work (billing_entity_id; null means Nigeria's). Each document then keeps the VAT it was
 * issued with and the company that issued it: proposals already have vat_rate and get vat_applied; proposals,
 * statements_of_work and invoices get billing_entity_id; statements_of_work and invoices get vat_rate and vat_applied
 * too (a SOW's VAT is fixed when HQ signs it, an invoice's when it is raised). proposals.vat_rate defaults to 0.
 *
 * Existing rows (backfill()): what was issued keeps what it had (sent, approved or signed proposals and SOWs record
 * their rate as it is; issued invoices aren't touched, their vat_amount says what they had; none gets an issuing
 * company: null means Nigeria's). Proposals not sent yet follow the switch, which is off, so their VAT goes to 0 and
 * their total to their subtotal. SOW drafts HQ hasn't signed stay open (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mdm_legal_entities', function (Blueprint $table) {
            $table->boolean('charges_vat')->default(false)->after('tax_identification_number')->comment('Off until the company is registered for VAT');
            $table->string('vat_number', 100)->nullable()->after('charges_vat');
            $table->date('vat_starts_on')->nullable()->after('vat_number')->comment('Documents dated from this day get VAT');
        });
        Schema::table('opportunities', function (Blueprint $table) {
            $table->char('billing_entity_id', 36)->nullable()->after('lead_user_id')->comment('The JBS Praxis company that bills the work; null = Nigeria’s');
            $table->foreign('billing_entity_id')->references('id')->on('mdm_legal_entities')->nullOnDelete();
        });
        Schema::table('proposals', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->change();
            $table->boolean('vat_applied')->nullable()->after('vat_rate')->comment('Set while a draft; kept from when it is sent');
            $table->char('billing_entity_id', 36)->nullable()->after('vat_applied')->comment('The company that issued it; null = Nigeria’s');
        });
        Schema::table('statements_of_work', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->nullable()->after('milestones')->comment('Fixed when HQ signs; null while a draft');
            $table->boolean('vat_applied')->nullable()->after('vat_rate');
            $table->char('billing_entity_id', 36)->nullable()->after('vat_applied')->comment('The company that issued it; null = Nigeria’s');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->nullable()->after('vat_amount')->comment('Null on invoices issued before the VAT switch');
            $table->boolean('vat_applied')->nullable()->after('vat_rate');
            $table->char('billing_entity_id', 36)->nullable()->after('vat_applied')->comment('The company that issued it; null = Nigeria’s');
        });

        $this->backfill();
    }

    /** What existing rows record (public, so a test can run it on rows in their before shape) */
    public function backfill(): void
    {
        // updated_at is set to itself so MySQL's ON UPDATE doesn't move it: nothing about these documents changed
        $keep = ['updated_at' => DB::raw('updated_at')];
        DB::table('proposals')->whereNull('vat_applied')->where(fn ($q) => $q->where('status', '!=', 'Draft')->orWhereNotNull('sent_at'))
            ->update(['vat_applied' => DB::raw('CASE WHEN vat_rate > 0 THEN 1 ELSE 0 END')] + $keep);
        DB::table('proposals')->whereNull('vat_applied')->where('status', 'Draft')->whereNull('sent_at')
            ->update(['vat_rate' => 0, 'vat_applied' => 0, 'total_value' => DB::raw('subtotal')] + $keep);

        DB::table('statements_of_work')->whereNull('vat_applied')->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhereNotNull('approved_at'))
            ->orderBy('id')->get(['id', 'proposal_id'])
            ->each(function ($sow) use ($keep) {
                $rate = $sow->proposal_id ? (float) DB::table('proposals')->where('id', $sow->proposal_id)->value('vat_rate') : 0.0;
                DB::table('statements_of_work')->where('id', $sow->id)->update(['vat_rate' => $rate, 'vat_applied' => $rate > 0] + $keep);
            });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['vat_rate', 'vat_applied', 'billing_entity_id']));
        Schema::table('statements_of_work', fn (Blueprint $table) => $table->dropColumn(['vat_rate', 'vat_applied', 'billing_entity_id']));
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['vat_applied', 'billing_entity_id']);
            $table->decimal('vat_rate', 5, 2)->default(7.50)->change();
        });
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['billing_entity_id']);
            $table->dropColumn('billing_entity_id');
        });
        Schema::table('mdm_legal_entities', fn (Blueprint $table) => $table->dropColumn(['charges_vat', 'vat_number', 'vat_starts_on']));
    }
};
