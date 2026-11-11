<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Client Portal's bell (docs/PWA.md, "Client Portal") on every portal page: the account's latest six notifications.
 * The (client_user_id, is_read) index finds the account's rows but not their order, so the database read and sorted
 * the account's whole history (never pruned) each time; with (client_user_id, created_at) it reads six rows
 * (performance pass, 11 Nov 2026). Index only: nothing to back fill, added once (re-running skips it).
 */
return new class extends Migration
{
    private const TABLE = 'client_notifications';

    private const NAME = 'client_notifications_client_created_index';

    private const COLUMNS = ['client_user_id', 'created_at'];

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE) && ! Schema::hasIndex(self::TABLE, self::NAME) && ! Schema::hasIndex(self::TABLE, self::COLUMNS)) {
            Schema::table(self::TABLE, fn (Blueprint $t) => $t->index(self::COLUMNS, self::NAME));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE) && Schema::hasIndex(self::TABLE, self::NAME)) {
            Schema::table(self::TABLE, fn (Blueprint $t) => $t->dropIndex(self::NAME));
        }
    }
};
