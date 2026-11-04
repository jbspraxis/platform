<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents (docs/DOCUMENTS.md): formal documents on the company letterhead, signed in HQ and issued with a
 * reference anyone can check on jbspraxis.com.
 *
 *   letter_documents            one row per document: its type, the company it is issued by, its fields, body,
 *                               reference and, once issued, the PDF itself with the hash the verify page checks
 *   letter_document_signatures  who signed a document, with the signature as it was applied
 *   letter_signatures           one stored signature per person, encrypted (never a public path)
 *   letter_verification_hits    scans of the QR code, counted per token and per day for the rate limit
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_documents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('reference', 40)->unique()->comment('JBS/RES/2026/001: the code of the type, the year and the number');
            $table->string('type_code', 8)->comment('RES, LET, INT, EMP, AUT: the part of the reference the number runs with');
            $table->string('type', 40)->comment('board_resolution, formal_letter, introduction, employment, authority, free_letter');
            $table->string('subtype', 40)->nullable()->comment('A ready-made template of the type, e.g. open_bank_account');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->char('legal_entity_id', 36)->comment('mdm_legal_entities.id: whose letterhead it carries');
            $table->string('title', 200);
            $table->string('addressed_to', 200)->nullable()->comment('The organisation it is addressed to; shown on the verify page');
            $table->text('addressed_to_address')->nullable();
            $table->date('document_date')->comment('The date on the document (Africa/Lagos today by default, editable)');
            $table->json('fields')->nullable()->comment('The template’s fields as the author filled them');
            $table->longText('body')->nullable()->comment('A free letter or a Claude draft: the rich-text body (sanitised HTML)');
            $table->enum('status', ['draft', 'issued', 'revoked', 'replaced'])->default('draft');
            $table->boolean('show_qr')->default(false)->comment('Off by default: a small QR code that opens the verify page');
            $table->boolean('copy_public')->default(false)->comment('Off for confidential documents: the verify page may show the PDF');
            $table->char('verify_token', 64)->nullable()->unique()->comment('What the QR carries: random, never the reference');
            $table->timestamp('issued_at')->nullable();
            $table->char('issued_by', 36)->nullable();
            $table->string('file_path', 255)->nullable()->comment('The issued PDF on the private disk; it never changes again');
            $table->char('file_sha256', 64)->nullable()->comment('What the visitor’s own copy is checked against');
            $table->unsignedInteger('file_bytes')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->char('revoked_by', 36)->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->char('replaced_by_id', 36)->nullable()->comment('The newer document that took its place');
            $table->char('replaces_id', 36)->nullable()->comment('The document this one replaces');
            $table->char('created_by', 36);
            $table->char('ai_run_id', 36)->nullable()->comment('The Claude draft the body started from');
            $table->timestamps();
            $table->index(['type_code', 'year', 'sequence']);
            $table->index('status');
            $table->index('legal_entity_id');
        });

        Schema::create('letter_signatures', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->unique()->comment('One stored signature per person; only they may apply it');
            $table->string('signatory_name', 150)->comment('The name as it is printed under the signature');
            $table->string('signatory_title', 150)->comment('The role as it is printed under the signature');
            $table->enum('kind', ['drawn', 'uploaded'])->default('drawn');
            $table->longText('image')->comment('Encrypted: a JPEG as base64. dompdf renders JPEG without the gd extension, which the live PHP has not got.');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('letter_document_signatures', function (Blueprint $table) {
            $table->id();
            $table->char('document_id', 36);
            $table->char('user_id', 36)->comment('Whose signature it is: only they could have applied it');
            $table->string('signatory_name', 150);
            $table->string('signatory_title', 150);
            $table->longText('image')->comment('Encrypted: the signature as it was at the moment it was applied');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'user_id']);
        });

        Schema::create('letter_verification_hits', function (Blueprint $table) {
            $table->id();
            $table->char('verify_token', 64);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['verify_token', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_verification_hits');
        Schema::dropIfExists('letter_document_signatures');
        Schema::dropIfExists('letter_signatures');
        Schema::dropIfExists('letter_documents');
    }
};
