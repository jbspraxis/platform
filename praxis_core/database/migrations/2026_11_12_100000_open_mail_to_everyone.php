<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opens HQ Mail to everyone whose role has the Mail permission (owner, 12 Nov 2026), as the owner's "Open Mail to
 * everyone" on Mailboxes does, with the same audit line. Mail is now one of the four places on HQ's phone bottom
 * bar (docs/HQ_PHONE.md). An Owner account can still limit it again on Mailboxes. docs/MAIL.md, decision 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_settings')) {
            return;
        }

        DB::table('mail_settings')->upsert(
            [['key' => 'open_to', 'value' => json_encode('everyone'), 'updated_by' => null, 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_by', 'updated_at'],
        );

        // The audit line where there are people to account to (a new, empty database has nobody yet)
        if (Schema::hasTable('system_audit_logs') && Schema::hasTable('users') && DB::table('users')->exists()) {
            DB::table('system_audit_logs')->insert([
                'user_id' => null,
                'action' => 'MAIL_OPENED_TO_EVERYONE',
                'description' => 'Opened HQ Mail to everyone with the Mail permission (owner decision, 12 Nov 2026).',
                'target_table' => 'mail_settings',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_settings')) {
            DB::table('mail_settings')->where('key', 'open_to')->update(['value' => json_encode('owner'), 'updated_at' => now()]);
        }
    }
};
