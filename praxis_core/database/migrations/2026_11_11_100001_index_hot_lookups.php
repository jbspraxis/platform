<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for lookups that run on every HQ page or every few minutes and read whole tables today (performance pass,
 * 11 Nov 2026). Indexes only: no column changes, nothing to back fill, each added once (re-running skips it).
 *
 *  notifications   the bell on every HQ page: the person's latest six, and their unread count. Both read every one
 *                  of the person's notifications (the table only grows); now an index range of six rows, and a count
 *                  from the index alone.
 *  sessions        the 2-in-100 garbage collection (DELETE ... WHERE last_activity <= ?) scanned and locked the whole
 *                  table, holding up every other request's session write; Laravel's own schema has both indexes.
 *  cache           cache:prune-expired every five minutes (DELETE ... WHERE expiration <= ?), the same way.
 *  jobs            the AI queue worker's poll (WHERE queue = ? ... FOR UPDATE SKIP LOCKED).
 *  kaizen_articles the rail's "articles waiting" count on every HQ page for approvers (WHERE status = ?).
 *  task_time_logs  the running-timer check on every HQ page (WHERE user_id = ? AND end_time IS NULL).
 */
return new class extends Migration
{
    private const INDEXES = [
        'notifications' => [
            'notifications_user_read_index' => ['user_id', 'is_read'],
            'notifications_user_created_index' => ['user_id', 'created_at'],
        ],
        'sessions' => [
            'sessions_last_activity_index' => ['last_activity'],
            'sessions_user_id_index' => ['user_id'],
        ],
        'cache' => [
            'cache_expiration_index' => ['expiration'],
        ],
        'jobs' => [
            'jobs_queue_index' => ['queue'],
        ],
        'kaizen_articles' => [
            'kaizen_articles_status_index' => ['status'],
        ],
        'task_time_logs' => [
            'task_time_logs_user_open_index' => ['user_id', 'end_time'],
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
