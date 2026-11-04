<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outreach (docs/CAMPAIGNS.md section 10.8) looks for prospects' replies in HQ Mail's copy every minute: mail that
 * arrived after the first email went (received_at), from the prospect's address (from_email) or in its thread. Two
 * plain indexes on mail_messages so that lookup reads a few rows instead of the whole table. No column changes.
 */
return new class extends Migration
{
    private const INDEXES = [
        'mail_messages_received_at_index' => 'received_at',
        'mail_messages_from_email_index' => 'from_email',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('mail_messages')) {
            return;
        }
        foreach (self::INDEXES as $name => $column) {
            if (! Schema::hasIndex('mail_messages', $name)) {
                Schema::table('mail_messages', fn (Blueprint $table) => $table->index($column, $name));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mail_messages')) {
            return;
        }
        foreach (self::INDEXES as $name => $column) {
            if (Schema::hasIndex('mail_messages', $name)) {
                Schema::table('mail_messages', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
