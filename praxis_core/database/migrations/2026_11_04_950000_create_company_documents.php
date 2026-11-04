<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company documents (owner's decisions, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md): each JBS Praxis company's
 * administrative papers in Master data, with renewal dates, version history and a compliance calendar.
 *
 *   company_documents          one paper of one company (legal_entity_id null = head office, group-wide); archived,
 *                              never deleted
 *   company_document_versions  every file it ever had: replacing a file adds a version, the older ones stay; each
 *                              version keeps Claude's reading of it (ai_run_id) and what a person applied from it
 *   company_deadlines          the compliance calendar: a filing or renewal due on a day, once or every year
 *
 * Three registration details join Legal entities, so what a certificate says has somewhere to go: the date of
 * registration, the company type and the share capital.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mdm_legal_entities', function (Blueprint $table) {
            $table->date('incorporated_on')->nullable()->after('tax_identification_number')->comment('Date of registration, from the certificate of incorporation');
            $table->string('company_type', 100)->nullable()->after('incorporated_on')->comment('e.g. Private company limited by shares');
            $table->string('share_capital', 100)->nullable()->after('company_type')->comment('As registered, with its currency, e.g. NGN 1,000,000');
        });

        Schema::create('company_documents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('legal_entity_id', 36)->nullable()->comment('mdm_legal_entities.id; null = head office, group-wide');
            $table->string('kind', 40)->comment('App\Support\CompanyDocuments\Documents::KINDS');
            $table->string('title', 190);
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->comment('Expiry or renewal date');
            $table->unsignedSmallInteger('remind_days')->nullable()->default(30)->comment('Bell this many days before expires_on; null = no bell');
            $table->date('reminded_for')->nullable()->comment('The expires_on the bell last rang for: once per date');
            $table->text('notes')->nullable();
            $table->char('current_version_id', 36)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->char('archived_by', 36)->nullable();
            $table->timestamps();
            $table->index(['legal_entity_id', 'archived_at']);
            $table->index('expires_on');
        });

        Schema::create('company_document_versions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('document_id', 36);
            $table->unsignedSmallInteger('number')->comment('1, 2, 3… within the document');
            $table->string('path', 255)->comment('On the private disk (storage/app/private), served by CompanyDocumentController');
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size')->comment('Bytes');
            $table->char('sha256', 64);
            $table->char('uploaded_by', 36)->nullable();
            $table->char('ai_run_id', 36)->nullable()->comment('Claude reading the file (purpose company_document_read)');
            $table->string('read_error', 190)->nullable()->comment('Why it was not read: a lang key (ai.errors.… or company_documents.read.…)');
            $table->json('applied')->nullable()->comment('What a person applied from it: field => [old, new]');
            $table->timestamp('reviewed_at')->nullable()->comment('Suggestions applied or set aside');
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['document_id', 'number']);
        });

        Schema::create('company_deadlines', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('legal_entity_id', 36)->nullable()->comment('mdm_legal_entities.id; null = head office, group-wide');
            $table->string('title', 190);
            $table->date('due_on');
            $table->enum('repeats', ['once', 'yearly'])->default('once')->comment('yearly: marking it done adds next year’s');
            $table->unsignedSmallInteger('remind_days')->nullable()->default(30)->comment('Bell this many days before due_on; null = no bell');
            $table->date('reminded_for')->nullable()->comment('The due_on the bell last rang for: once per date');
            $table->text('notes')->nullable();
            $table->char('document_id', 36)->nullable()->comment('The document it was read from, if any');
            $table->timestamp('done_at')->nullable();
            $table->char('done_by', 36)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->char('archived_by', 36)->nullable();
            $table->timestamps();
            $table->index(['legal_entity_id', 'archived_at']);
            $table->index('due_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_deadlines');
        Schema::dropIfExists('company_document_versions');
        Schema::dropIfExists('company_documents');
        Schema::table('mdm_legal_entities', function (Blueprint $table) {
            $table->dropColumn(['incorporated_on', 'company_type', 'share_capital']);
        });
    }
};
