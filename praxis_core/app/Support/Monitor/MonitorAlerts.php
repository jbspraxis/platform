<?php

namespace App\Support\Monitor;

use App\Mail\SystemAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails from the monitor, to MONITOR_ALERT_EMAIL (default: the leads inbox).
 *   newErrors()   one email per run listing new or returning error types
 *   health()      a check turned red (each check at most every 6 hours)
 *   digest()      the 8am summary (Lagos time), once a day
 * At most 6 alert emails an hour, so an error storm cannot flood the inbox.
 */
class MonitorAlerts
{
    private const MAX_PER_HOUR = 6;

    public static function newErrors(): int
    {
        $groups = DB::table('monitor_error_groups')->whereNull('alerted_at')->where('status', 'new')
            ->orderByDesc('last_seen_at')->limit(10)->get();
        if ($groups->isEmpty() || !self::allowed()) {
            return 0;
        }

        $errors = [];
        foreach ($groups as $g) {
            $errors[] = ['summary' => self::line($g), 'hint' => ErrorMonitor::explain($g)];
        }

        if (self::send(SystemAlert::newErrors($errors))) {
            DB::table('monitor_error_groups')->whereIn('id', $groups->pluck('id'))->update(['alerted_at' => now()]);
            return $groups->count();
        }
        return 0;
    }

    // Checks worth an immediate email when red. The rest (enquiries, logs, old jobs,
    // exchange rates) only appear in the 8am digest, so they cannot nag every 6 hours.
    private const URGENT_CHECKS = ['scheduler', 'email', 'ai', 'config'];

    public static function health(): int
    {
        $sent = 0;
        foreach (HealthChecks::all() as $check) {
            if ($check['state'] !== 'bad' || !in_array($check['key'], self::URGENT_CHECKS, true)) {
                continue;
            }
            $key = 'health_alert:' . $check['key'];
            $last = ErrorMonitor::state($key);
            if ($last && Carbon::parse($last)->gt(now()->subHours(6))) {
                continue;
            }
            if (self::allowed() && self::send(SystemAlert::checkFailing($check['label'], $check['detail'], $check['fix']))) {
                ErrorMonitor::setState($key, now()->toDateTimeString());
                $sent++;
            }
        }
        return $sent;
    }

    public static function digest(): bool
    {
        $lagos = now('Africa/Lagos');
        $key = 'digest:' . $lagos->toDateString();
        if ($lagos->hour !== 8 || ErrorMonitor::state($key)) {
            return false;
        }
        ErrorMonitor::setState($key, now()->toDateTimeString());

        $checks = HealthChecks::all();
        $top = DB::table('monitor_error_groups')->where('last_seen_at', '>=', now()->subDay())->whereIn('status', ['new', 'investigating'])
            ->orderByDesc('occurrences')->limit(10)->get();

        return self::send(SystemAlert::dailyReport(
            HealthChecks::worst($checks),
            $lagos->toDateString(),
            array_map(fn ($c) => ['label' => $c['label'], 'state' => $c['state'], 'detail' => $c['detail']], $checks),
            $top->map(fn ($g) => self::line($g))->all(),
        ));
    }

    private static function line(object $g): string
    {
        $where = $g->file ? " ({$g->file}" . ($g->line ? ":{$g->line}" : '') . ')' : '';
        return '[' . strtolower($g->level) . '] ' . ($g->exception_class ? class_basename($g->exception_class) . ': ' : '')
            . mb_substr(trim($g->message), 0, 180) . $where . " ×{$g->occurrences}";
    }

    private static function allowed(): bool
    {
        $hour = 'alerts_hour:' . now()->format('YmdH');
        $count = (int) ErrorMonitor::state($hour, 0);
        if ($count >= self::MAX_PER_HOUR) {
            return false;
        }
        ErrorMonitor::setState($hour, $count + 1);
        return true;
    }

    /** Team alerts are English only. A failure is logged, never thrown: monitoring must not break what it watches. */
    private static function send(SystemAlert $mail): bool
    {
        $to = config('services.monitor.alert_email');
        if (!$to) {
            return false;
        }
        try {
            Mail::to($to)->locale('en')->send($mail);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Monitor: alert email failed', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
