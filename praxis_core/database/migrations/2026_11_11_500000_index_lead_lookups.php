<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads (performance pass, 11 Nov 2026): the Leads list, its summary strip, My follow-ups and the morning bell start
 * from one person's leads (the list's Mine filter is on by default), and organizations had no index leading with the
 * owner, so each read every organisation. An index only: no column changes, added once (re-running skips it).
 */
return new class extends Migration
{
    private const NAME = 'organizations_owner_status_index';

    private const COLUMNS = ['assigned_to', 'status', 'deleted_at'];

    public function up(): void
    {
        if (Schema::hasTable('organizations') && ! Schema::hasIndex('organizations', self::NAME) && ! Schema::hasIndex('organizations', self::COLUMNS)) {
            Schema::table('organizations', fn (Blueprint $t) => $t->index(self::COLUMNS, self::NAME));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('organizations') && Schema::hasIndex('organizations', self::NAME)) {
            Schema::table('organizations', fn (Blueprint $t) => $t->dropIndex(self::NAME));
        }
    }
};
