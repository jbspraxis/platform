<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 5 (docs/OPPORTUNITIES.md section 4, row 5): what the SOW and the project draft need beyond PR 1's tables.
 *
 * statements_of_work: its title, the milestones the client signs (title, deliverables and acceptance, weeks, weight:
 * the share of the contract), the AI job drafting it, validity, "what changed", who it was sent to (and the addresses
 * still to send, as proposals), JBS Praxis's legal name as it stood when HQ signed, the client signer's title and
 * email, the client's request for changes, and the project created on signing (it holds the deposit invoice, since
 * invoices.project_id is required).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statements_of_work', function (Blueprint $table) {
            $table->string('title', 200)->nullable()->after('reference');
            $table->json('milestones')->nullable()->after('sections')->comment('[{key, title, description, weeks, weight}]');
            $table->char('ai_run_id', 36)->nullable()->after('status');
            $table->unsignedSmallInteger('validity_days')->default(30)->after('ai_run_id');
            $table->text('changes_summary')->nullable()->after('validity_days');
            $table->json('recipients')->nullable()->after('sent_at');
            $table->json('recipients_unsent')->nullable()->after('recipients');
            $table->string('provider_legal_name', 150)->nullable()->after('hq_signed_at')->comment('JBS Praxis’s legal name when HQ signed');
            $table->string('signed_by_title', 150)->nullable()->after('signed_by_name');
            $table->string('signed_by_email', 150)->nullable()->after('signed_by_title');
            $table->text('changes_request')->nullable()->after('signed_at');
            $table->string('changes_requested_by', 255)->nullable()->after('changes_request');
            $table->timestamp('changes_requested_at')->nullable()->after('changes_requested_by');
            $table->char('project_id', 36)->nullable()->after('deposit_invoice_id')->comment('Created on signing; holds the deposit invoice');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('statements_of_work', function (Blueprint $table) {
            $table->dropForeign(['ai_run_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['title', 'milestones', 'ai_run_id', 'validity_days', 'changes_summary', 'recipients', 'recipients_unsent',
                'provider_legal_name', 'signed_by_title', 'signed_by_email', 'changes_request', 'changes_requested_by', 'changes_requested_at', 'project_id']);
        });
    }
};
