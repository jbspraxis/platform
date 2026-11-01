<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proposal v2 (docs/OPPORTUNITIES.md section 4, row 4): the states of a proposal the client reviews in the portal,
 * the AI jobs that write a version and say what changed, and who it was sent to. Legacy proposals keep the five
 * statuses they had; a v2 proposal (signing_mode approve) is Draft, then Sent, then Client Approved, Changes
 * Requested or Superseded (a later version was sent). The dashboards' "Pending Auth" stays legacy only.
 */
return new class extends Migration
{
    private const LEGACY = "'Draft','Pending Auth','Authorized','Expired','Rejected'";

    public function up(): void
    {
        DB::statement('ALTER TABLE proposals MODIFY status ENUM(' . self::LEGACY . ",'Sent','Changes Requested','Client Approved','Superseded') NOT NULL DEFAULT 'Draft'");

        Schema::table('proposals', function (Blueprint $table) {
            $table->char('ai_run_id', 36)->nullable()->after('changes_summary')->comment('The AI job that drafts this version (purpose proposal)');
            $table->char('changes_run_id', 36)->nullable()->after('ai_run_id')->comment('The AI job that says what changed (purpose proposal_changes)');
            $table->json('recipients')->nullable()->after('sent_at')->comment('Who it was sent to: email, name, client_user_id');
            $table->json('recipients_unsent')->nullable()->after('recipients')->comment('Emails the proposal did not reach');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
            $table->foreign('changes_run_id')->references('id')->on('ai_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropForeign(['ai_run_id']);
            $table->dropForeign(['changes_run_id']);
            $table->dropColumn(['ai_run_id', 'changes_run_id', 'recipients', 'recipients_unsent']);
        });
        // Approved by the client is not signed: it waits for a signature, as legacy proposals did
        DB::table('proposals')->whereIn('status', ['Sent', 'Client Approved'])->update(['status' => 'Pending Auth']);
        DB::table('proposals')->where('status', 'Changes Requested')->update(['status' => 'Rejected']);
        DB::table('proposals')->where('status', 'Superseded')->update(['status' => 'Expired']);
        DB::statement('ALTER TABLE proposals MODIFY status ENUM(' . self::LEGACY . ") NOT NULL DEFAULT 'Draft'");
    }
};
