<?php

namespace App\Support\Backups;

use App\Support\Monitor\ErrorMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The owner's bell about the backups (owner's decision 7): a site failed or is older than 26 hours, an upload's
 * checksum didn't match, a database no site claims. Owners only (the protected staff role); it opens System health
 * and is pushed to their phone like any other bell. once() rings at most once per key in a period.
 */
final class Bells
{
    public static function owners(string $title, string $message): int
    {
        $owners = DB::table('users')->join('staff_roles', 'staff_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'staff_roles.role_id')
            ->where('roles.audience', 'staff')->where('roles.is_protected', 1)->whereNull('roles.deleted_at')
            ->where('users.status', 'Active')->distinct()->pluck('users.id')->all();
        $rows = array_map(fn ($id) => [
            'id' => (string) Str::uuid(), 'user_id' => $id, 'type' => 'System',
            'title' => Str::limit($title, 150, '…'), 'message' => Str::limit($message, 500, '…'),
            'link_url' => route('hq.system.health', [], false), 'is_read' => 0, 'created_at' => now(),
        ], $owners);
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }

        return count($rows);
    }

    /** Rings unless the same $key rang within $hours */
    public static function once(string $key, int $hours, string $title, string $message): bool
    {
        $state = 'backups.bell:' . $key;
        $last = ErrorMonitor::state($state);
        if ($last && Carbon::parse($last)->gt(now()->subHours($hours)->addMinutes(5))) {
            return false;
        }
        ErrorMonitor::setState($state, now()->toDateTimeString());

        return self::owners($title, $message) > 0;
    }
}
