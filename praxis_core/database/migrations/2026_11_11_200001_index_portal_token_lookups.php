<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the client's token pages and files (performance pass, 11 Nov 2026). Indexes only: no column changes,
 * nothing to back fill, each added once (re-running skips it).
 *
 *  proposals         the proposal page (/proposal/{token}) finds its proposal by token_hash when it opens, on every
 *                    action and again to draw the page: each one read the whole table.
 *  project_signoffs  the document check behind a signed sign-off's QR code (/verify/{hash}) looks up
 *                    client_signature_hash.
 *  qa_attachments    every QA evidence file opened from the portal is checked by its file_path. A varchar(255) in
 *                    utf8mb4 is 1,020 bytes, within InnoDB's 3,072 (MySQL 8, MariaDB 10.11), so the whole column is
 *                    indexed, as tracker_users.email already is.
 */
return new class extends Migration
{
    private const INDEXES = [
        'proposals' => [
            'proposals_token_hash_index' => ['token_hash'],
        ],
        'project_signoffs' => [
            'project_signoffs_client_signature_hash_index' => ['client_signature_hash'],
        ],
        'qa_attachments' => [
            'qa_attachments_file_path_index' => ['file_path'],
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
