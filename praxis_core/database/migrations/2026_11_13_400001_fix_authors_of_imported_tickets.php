<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets brought in by `opportunities:import` (MyCity's handover points, among others) were saved without
 * submitted_by_type, so they took the column's default, 'client', although a staff member (the project lead)
 * raised them. In the conversation (docs/CHAT.md) their cards showed on the client's side with no name, and
 * Quality and issues had no "Reported by". The author type follows the account that really exists: staff when
 * the id is a staff member's and no client account has it. The same for the conversation's messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qa_tickets') || ! Schema::hasColumn('qa_tickets', 'submitted_by_type')) {
            return;
        }

        DB::table('qa_tickets')->where('submitted_by_type', 'client')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('client_users')->whereColumn('client_users.id', 'qa_tickets.submitted_by'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'qa_tickets.submitted_by'))
            ->update(['submitted_by_type' => 'staff']);

        if (Schema::hasTable('chat_messages')) {
            DB::table('chat_messages')->where('author_type', 'client')->whereNotNull('author_id')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('client_users')->whereColumn('client_users.id', 'chat_messages.author_id'))
                ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'chat_messages.author_id'))
                ->update(['author_type' => 'staff']);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the corrected rows were wrong before
    }
};
