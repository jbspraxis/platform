<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agreements (docs/DOCUMENTS.md section 12): a document JBS Praxis signs and the other party countersigns online.
 *
 *   letter_documents        two more statuses, and the countersigned PDF beside the issued one. The issued file
 *                           never changes: the countersigned one is a second version that points back to it by
 *                           printing its fingerprint on the signature record page.
 *   letter_counterparties   the other signatories: one row each, so an agreement can have more than one later.
 *                           Each row carries its own private link, its own one-time code and its own signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_documents', function (Blueprint $table) {
            $table->string('signed_file_path', 255)->nullable()->after('file_bytes')
                ->comment('The countersigned PDF: a new version beside the issued one, which never changes');
            $table->char('signed_file_sha256', 64)->nullable()->after('signed_file_path');
            $table->unsignedInteger('signed_file_bytes')->nullable()->after('signed_file_sha256');
            $table->timestamp('fully_signed_at')->nullable()->after('signed_file_bytes')
                ->comment('When the last counterparty signed');
        });

        $this->widenStatus(['draft', 'issued', 'sent_for_signature', 'fully_signed', 'revoked', 'replaced']);

        Schema::create('letter_counterparties', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('document_id', 36);
            $table->unsignedTinyInteger('position')->default(1)->comment('The order they are printed in; one today, more later');
            $table->string('name', 150);
            $table->string('role_title', 150)->comment('The role printed under the signature, e.g. Contractor');
            $table->string('email', 150)->comment('Where the private link and the one-time code are sent');
            $table->text('address')->nullable();
            $table->string('company', 200)->nullable()->comment('When they sign for a company rather than themselves');
            $table->string('registration', 100)->nullable()->comment('That company’s registration number');

            $table->enum('status', ['pending', 'sent', 'signed'])->default('pending');
            $table->char('token', 64)->nullable()->unique()->comment('What the private link carries: random, never the reference');
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedSmallInteger('sent_count')->default(0);
            $table->char('sent_by', 36)->nullable();

            $table->string('otp', 64)->nullable()->comment('An HMAC of the one-time code, never the code itself');
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('code_verified_at')->nullable()->comment('When the code they were emailed was accepted');

            $table->string('signed_name', 150)->nullable()->comment('The full name they typed as they signed');
            $table->longText('image')->nullable()->comment('Encrypted: their signature as a JPEG, base64, as it was applied');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'position']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_counterparties');
        $this->widenStatus(['draft', 'issued', 'revoked', 'replaced']);
        Schema::table('letter_documents', function (Blueprint $table) {
            $table->dropColumn(['signed_file_path', 'signed_file_sha256', 'signed_file_bytes', 'fully_signed_at']);
        });
    }

    /** The status column is an enum on MySQL; anything else (a test on sqlite) already holds a plain string */
    private function widenStatus(array $values): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $list = implode(', ', array_map(fn ($value) => "'" . $value . "'", $values));
        DB::statement("ALTER TABLE letter_documents MODIFY status ENUM({$list}) NOT NULL DEFAULT 'draft'");
    }
};
