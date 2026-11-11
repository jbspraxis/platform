<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HQ Mail's hot reads, from an index instead of every message row (performance pass, 11 Nov 2026). No column changes.
 *
 *  - mail_messages_folder_list_index (folder_id, thread_id, received_at, is_seen, is_flagged, has_attachments): the
 *    conversation list groups a folder's messages by thread (unread, flagged, latest, count, attachments) on every
 *    Mail page render, and a folder's counts (total, unread) are recounted after each change. Both now read only this
 *    index, in thread order, where they read every (wide) message row of the folder.
 *  - mail_messages_reply_lookup_index (account_id, in_reply_to): sync files each new message into its conversation by
 *    looking for replies to it, which read the whole mailbox.
 */
return new class extends Migration
{
    private const INDEXES = [
        'mail_messages_folder_list_index' => ['folder_id', 'thread_id', 'received_at', 'is_seen', 'is_flagged', 'has_attachments'],
        'mail_messages_reply_lookup_index' => ['account_id', 'in_reply_to'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('mail_messages')) {
            return;
        }
        foreach (self::INDEXES as $name => $columns) {
            if (! Schema::hasIndex('mail_messages', $name)) {
                Schema::table('mail_messages', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mail_messages')) {
            return;
        }
        foreach (self::INDEXES as $name => $columns) {
            if (Schema::hasIndex('mail_messages', $name)) {
                Schema::table('mail_messages', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
