<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the history panels, the overviews and the per-minute operations jobs, which read whole tables today
 * (performance pass, 11 Nov 2026). Indexes only: no column changes, nothing to back fill, each added once (re-running
 * skips it). 2026_11_11_100001_index_hot_lookups already covers notifications and the running-timer check
 * (task_time_logs user_id, end_time).
 *
 *  system_audit_logs  every drawer's history, the project Overview's recent activity and the opportunity page (one
 *                     record's lines, newest first: an index range of 10 to 20 rows instead of a scan and sort of the
 *                     whole log, which only grows), and the Audit log (this year's count, the latest eight).
 *  appointments       My workspace's "Coming up" (from now on, soonest first) and a website booking's day lock.
 *  tasks              ops:qa-auto-approve every minute (tasks in client review) and the All projects velocity
 *                     figure (tasks done in the last 7 days).
 *  task_time_logs     timers running now (end_time IS NULL: Company overview, Time tracking, ops:timer-killswitch)
 *                     and a person's time this week (My workspace).
 *  tracker_messages   the task force tracker's latest messages.
 */
return new class extends Migration
{
    private const INDEXES = [
        'system_audit_logs' => [
            'system_audit_logs_target_index' => ['target_table', 'target_id', 'created_at'],
            'system_audit_logs_created_at_index' => ['created_at'],
        ],
        'appointments' => [
            'appointments_start_time_index' => ['start_time'],
        ],
        'tasks' => [
            'tasks_status_updated_index' => ['status', 'updated_at'],
        ],
        'task_time_logs' => [
            'task_time_logs_end_start_index' => ['end_time', 'start_time'],
            'task_time_logs_user_start_index' => ['user_id', 'start_time'],
        ],
        'tracker_messages' => [
            'tracker_messages_tracker_created_index' => ['tracker_id', 'created_at'],
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
