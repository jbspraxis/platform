<?php

namespace App\Console\Commands;

use App\Support\Monitor\ErrorMonitor;
use App\Support\Monitor\LogScanner;
use App\Support\Monitor\MonitorAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The HQ System Health monitor's background work, every 5 minutes (routes/console.php):
 * heartbeat, read new log lines, alert on new errors and red checks, the 8am digest,
 * and at 3am delete old events.
 */
class MonitorRun extends Command
{
    protected $signature = 'monitor:run';
    protected $description = 'System Health monitor: scan logs, send alerts and the daily digest';

    public function handle(): int
    {
        ErrorMonitor::setState('heartbeat', now()->toDateTimeString());

        $scanned = LogScanner::scanAll();
        $alerted = MonitorAlerts::newErrors();
        $health = MonitorAlerts::health();
        $digest = MonitorAlerts::digest();

        if (now('Africa/Lagos')->hour === 3 && ErrorMonitor::state('cleanup:' . now()->toDateString()) === null) {
            ErrorMonitor::setState('cleanup:' . now()->toDateString(), 1);
            DB::table('monitor_error_events')->where('occurred_at', '<', now()->subDays(30))->delete();
            DB::table('monitor_error_groups')->whereIn('status', ['fixed', 'ignored'])->where('last_seen_at', '<', now()->subDays(90))->delete();
            DB::table('monitor_state')->where('key', 'like', 'alerts_hour:%')->where('updated_at', '<', now()->subDays(2))->delete();
        }

        $this->line("monitor: {$scanned} log entries, {$alerted} alerted, {$health} health alerts" . ($digest ? ', digest sent' : ''));
        return self::SUCCESS;
    }
}
