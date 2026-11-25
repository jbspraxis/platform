<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice drafts (owner, 25 Nov 2026: "Do not send any yet. I am going to review and send."): what Create invoice was
 * filled with, kept apart from invoices so a draft never reaches the client's portal, an email, a reminder, the
 * general ledger or a total. Issue and send turns it into an invoice through the usual path and marks the draft issued
 * (App\Support\Finance\InvoiceDrafts; docs/PROJECTS.md §9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_drafts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('project_id', 36)->index();
            $table->string('kind', 16)->default('invoice')->comment('invoice (milestones and lines) or advance');
            $table->string('invoice_type', 50);
            $table->string('invoice_number', 50)->comment('Kept for it, used when it is issued if still free');
            $table->string('currency', 3);
            $table->string('payment_terms', 100);
            $table->bigInteger('remittance_account_id')->unsigned()->nullable();
            $table->json('lines')->comment('The lines as typed: kind, phase_id, description, amount');
            $table->char('advance_phase_id', 36)->nullable();
            $table->decimal('advance_amount', 15, 2)->nullable();
            $table->string('advance_description', 255)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->char('issued_invoice_id', 36)->nullable()->comment('The invoice it became');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable()->comment('Issued or discarded');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_drafts');
    }
};
