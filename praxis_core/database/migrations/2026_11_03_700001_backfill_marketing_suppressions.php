<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every contact who unsubscribed before 3 Nov 2026 (client_users.is_subscribed = 0) goes on the do-not-email list,
 * so the new rule "one unsubscribe stops all marketing to that address" covers them too (docs/CAMPAIGNS.md 4.1).
 * Safe to run again: an address already on the list is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('client_users')->where('is_subscribed', 0)->whereNotNull('email')->where('email', '<>', '')
            ->orderBy('id')->select('email')->chunk(500, function ($rows) {
                $now = now();
                $values = collect($rows)->map(fn ($r) => mb_strtolower(trim((string) $r->email)))->filter()->unique()
                    ->map(fn ($email) => ['email' => $email, 'reason' => 'unsubscribed', 'source' => 'backfill', 'note' => 'Unsubscribed before 3 Nov 2026',
                        'created_at' => $now, 'updated_at' => $now])->values()->all();
                if ($values) {
                    DB::table('marketing_suppressions')->insertOrIgnore($values);
                }
            });
    }

    public function down(): void
    {
        DB::table('marketing_suppressions')->where('source', 'backfill')->delete();
    }
};
