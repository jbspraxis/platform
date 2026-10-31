<?php

namespace App\Support\Monitor;

use App\Mail\SystemAlert;
use App\Support\Access;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Emails from the monitor, to MONITOR_ALERT_EMAIL (default: the leads inbox).
 *   newErrors()   one email per run listing new or returning error types
 *   health()      a check turned red (each check at most every 6 hours)
 *   digest()      the 8am summary (Lagos time), once a day
 * At most 6 alert emails an hour, so an error storm cannot flood the inbox.
 * newErrors() and health() also put a notification in the HQ bell of every staff member who can
 * open System health (and so on their phones, docs/PWA.md), with or without an alert email address.
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

        $n = $groups->count();
        $mailed = self::send(SystemAlert::newErrors($errors));
        $notified = self::notifyStaff('New errors in HQ', "HQ recorded {$n} new or returning " . Str::plural('error type', $n) . '. Review ' . ($n === 1 ? 'it' : 'them') . ' in System health.');
        if ($mailed || $notified) {
            DB::table('monitor_error_groups')->whereIn('id', $groups->pluck('id'))->update(['alerted_at' => now()]);
            return $n;
        }
        return 0;
    }

    // Checks worth an immediate email when red. The rest (enquiries, logs, old jobs,
    // exchange rates) only appear in the 8am digest, so they cannot nag every 6 hours.
    private const URGENT_CHECKS = ['scheduler', 'email', 'ai', 'integrations', 'config'];

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
            if (!self::allowed()) {
                continue;
            }
            $mailed = self::send(SystemAlert::checkFailing($check['label'], $check['detail'], $check['fix']));
            // "Live settings need attention", "Scheduler (cron) needs attention"
            $plural = preg_match('/[^s]s$/i', trim(preg_replace('/\s*\(.*\)$/', '', $check['label'])));
            $notified = self::notifyStaff($check['label'] . ($plural ? ' need' : ' needs') . ' attention', rtrim($check['detail']) . ' See how to fix it in System health.');
            if ($mailed || $notified) {
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

    /** A bell notification (pushed like any other) for each staff member who can open System health. Never throws. */
    private static function notifyStaff(string $title, string $message): bool
    {
        try {
            $rows = array_map(fn ($userId) => [
                'id' => (string) Str::uuid(), 'user_id' => $userId, 'title' => $title, 'message' => $message,
                'link_url' => route('hq.system.health', [], false), 'type' => 'System', 'is_read' => 0, 'created_at' => now(),
            ], Access::staffWith('system.view'));
            if (!$rows) {
                return false;
            }
            DB::table('notifications')->insert($rows);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Monitor: bell alert failed', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
