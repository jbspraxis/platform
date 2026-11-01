<?php

namespace App\Support\Projects;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Waits on" links (docs/PROJECTS.md, decision 5): a task waits on tasks of its project, a milestone on milestones.
 * They only warn; nothing is ever stopped. A link to itself, one that already exists, or one that closes a loop
 * (the other already waits, directly or through others, on this one) is refused.
 */
final class Dependencies
{
    // kind => [table, waiting column, prerequisite column]
    private const TABLES = [
        'task' => ['task_dependencies', 'task_id', 'depends_on_task_id'],
        'phase' => ['phase_dependencies', 'phase_id', 'depends_on_phase_id'],
    ];

    /** How deep the loop check follows links (MyCity's limit) */
    private const DEPTH = 50;

    /**
     * Link $waiting to $prerequisite. Returns null when linked, else why not: 'self', 'exists' or 'loop'
     * (lang projects.waits.errors.*). Both ids must already be checked to belong to the same project.
     */
    public static function link(string $kind, string $waiting, string $prerequisite): ?string
    {
        [$table, $from, $to] = self::TABLES[$kind];
        if ($waiting === $prerequisite) {
            return 'self';
        }
        if (DB::table($table)->where($from, $waiting)->where($to, $prerequisite)->exists()) {
            return 'exists';
        }
        if (self::closesLoop($kind, $waiting, $prerequisite)) {
            return 'loop';
        }
        DB::table($table)->insert([$from => $waiting, $to => $prerequisite, 'created_by' => Auth::id(), 'created_at' => now()]);

        return null;
    }

    public static function unlink(string $kind, string $waiting, string $prerequisite): bool
    {
        [$table, $from, $to] = self::TABLES[$kind];

        return DB::table($table)->where($from, $waiting)->where($to, $prerequisite)->delete() > 0;
    }

    /** Would "$waiting waits on $prerequisite" close a loop? Follows what the prerequisite waits on. */
    public static function closesLoop(string $kind, string $waiting, string $prerequisite): bool
    {
        [$table, $from, $to] = self::TABLES[$kind];
        if ($waiting === $prerequisite) {
            return true;
        }
        $seen = [$prerequisite => true];
        $frontier = [$prerequisite];
        for ($depth = 0; $frontier && $depth < self::DEPTH; $depth++) {
            $next = [];
            foreach (DB::table($table)->whereIn($from, $frontier)->pluck($to) as $id) {
                if ($id === $waiting) {
                    return true;
                }
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $next[] = $id;
                }
            }
            $frontier = $next;
        }

        return false;
    }
}
