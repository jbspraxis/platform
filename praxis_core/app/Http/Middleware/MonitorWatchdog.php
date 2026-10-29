<?php

namespace App\Http\Middleware;

use App\Mail\SystemAlert;
use App\Support\Monitor\ErrorMonitor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the cron dies, the monitor stops too and cannot report it. So ordinary page
 * requests check the monitor's heartbeat now and then (at most every 15 minutes,
 * after the response is sent) and email an alert when it is over 20 minutes old.
 */
class MonitorWatchdog
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $files = Cache::store('file');
            if (!$files->add('monitor_watchdog_checked', 1, now()->addMinutes(15))) {
                return;
            }
            $beat = ErrorMonitor::state('heartbeat');
            if (!$beat || Carbon::parse($beat)->gt(now()->subMinutes(20))) {
                return; // not installed yet, or alive
            }
            if (!$files->add('monitor_watchdog_alerted', 1, now()->addHours(6))) {
                return;
            }
            $to = config('services.monitor.alert_email');
            if ($to) {
                // A team alert: English, whatever language the visitor's page was in
                Mail::to($to)->locale('en')->send(SystemAlert::schedulerStopped(
                    (string) $beat,
                    '* * * * * /usr/local/bin/ea-php83 /home/smartqaq/praxis_core/artisan schedule:run'
                ));
            }
        } catch (\Throwable $e) {
            // never break a visitor's request
        }
    }
}
