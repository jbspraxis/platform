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
 *  - mail_messages_contacts_index (account_id, is_outbound, from_email, from_name, received_at): the composer's address
 *    suggestions (a few characters typed in To, Cc or Bcc) list the senders matching them, newest first, from every
 *    mailbox the person reads; the match is "contains", which no index can seek, but this one holds every column it
 *    reads, so it scans the index and not the message rows (which carry the bodies).
 */
return new class extends Migration
{
    private const INDEXES = [
        'mail_messages_folder_list_index' => ['folder_id', 'thread_id', 'received_at', 'is_seen', 'is_flagged', 'has_attachments'],
        'mail_messages_reply_lookup_index' => ['account_id', 'in_reply_to'],
        'mail_messages_contacts_index' => ['account_id', 'is_outbound', 'from_email', 'from_name', 'received_at'],
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
