<?php

namespace App\Support\Monitor;

use App\Support\Pwa\PushDispatcher;
use App\Support\Pwa\VapidKeys;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The checks behind HQ → System Health. Each returns
 * ['key', 'label', 'state' => ok|warn|bad|unknown, 'detail', 'fix'].
 * A check that fails to run reports 'unknown' instead of breaking the page.
 */
class HealthChecks
{
    /** @param  ?array  $backupSites  Backups\Status::sites() when the caller has read it already (System health's table) */
    public static function all(?array $backupSites = null): array
    {
        $checks = [];
        $keys = ['scheduler', 'errors', 'email', 'ai', 'integrations', 'backups', 'queue', 'logs', 'leads', 'rates', 'config', 'push'];
        // How the web server runs PHP can only be seen from a page: the cron's command-line PHP has its own settings
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $keys[] = 'speed';
        }
        foreach ($keys as $key) {
            try {
                $checks[] = ['key' => $key] + ($key === 'backups' ? self::backups($backupSites) : self::$key());
            } catch (\Throwable $e) {
                $checks[] = ['key' => $key, 'label' => ucfirst($key), 'state' => 'unknown', 'detail' => 'This check could not run: ' . mb_substr($e->getMessage(), 0, 160), 'fix' => null];
            }
        }
        return $checks;
    }

    public static function worst(array $checks): string
    {
        foreach (['bad', 'warn', 'unknown'] as $state) {
            if (collect($checks)->contains('state', $state)) {
                return $state;
            }
        }
        return 'ok';
    }

    private static function scheduler(): array
    {
        $beat = ErrorMonitor::state('heartbeat');
        if (!$beat) {
            return ['label' => 'Scheduler (cron)', 'state' => 'bad', 'detail' => 'The monitor has never run. The cron job is missing or failing.', 'fix' => 'cPanel → Cron Jobs: "* * * * * /usr/local/bin/ea-php83 /home/smartqaq/praxis_core/artisan schedule:run"'];
        }
        $minutes = (int) Carbon::parse($beat)->diffInMinutes(now(), true);
        $state = $minutes <= 10 ? 'ok' : ($minutes <= 30 ? 'warn' : 'bad');
        return ['label' => 'Scheduler (cron)', 'state' => $state,
            'detail' => 'Last run ' . self::ago($beat) . '. It runs every minute; the monitor every 5 minutes.',
            'fix' => $state === 'ok' ? null : 'Check the cron job in cPanel → Cron Jobs, then run "php artisan schedule:run" by hand to see the error.'];
    }

    private static function errors(): array
    {
        $since = now()->subDay();
        $open = DB::table('monitor_error_groups')->whereIn('status', ['new', 'investigating'])->where('last_seen_at', '>=', $since);
        $groups = (clone $open)->count();
        // New = first seen today and still open: errors already marked fixed or ignored no longer count
        $new = (clone $open)->where('first_seen_at', '>=', $since)->count();
        $critical = (clone $open)->whereIn('level', ['critical', 'alert', 'emergency'])->count();
        $state = $critical > 0 || $new > 5 ? 'bad' : ($groups > 0 ? 'warn' : 'ok');
        return ['label' => 'Errors (last 24 h)', 'state' => $state,
            'detail' => $groups === 0 ? 'No open errors in the last 24 hours.' : "{$groups} open error type(s) seen, {$new} new" . ($critical ? ", {$critical} critical" : '') . '.',
            'fix' => $groups ? 'Open the Errors tab, then use "Copy for AI" on each.' : null];
    }

    private static function email(): array
    {
        // Open failures only, as in the errors check: one marked fixed reopens by itself if it comes back (ErrorMonitor)
        $n = DB::table('monitor_error_groups')->where('last_seen_at', '>=', now()->subDay())->whereIn('status', ['new', 'investigating'])
            ->where(fn ($q) => $q->where('message', 'like', '%email failed%')->orWhere('message', 'like', '%Connection could not be established%')->orWhere('message', 'like', '%Expected response code%'))
            ->sum('occurrences');
        return ['label' => 'Email sending', 'state' => $n ? 'bad' : 'ok',
            'detail' => $n ? "Emails failed {$n} time(s) recently (enquiry confirmations, alerts or reports)." : 'No email failures in the last 24 hours.',
            'fix' => $n ? 'Check MAIL_* in .env and the mailbox in cPanel → Email Accounts.' : null];
    }

    private static function ai(): array
    {
        $rows = DB::table('monitor_error_groups')->where('last_seen_at', '>=', now()->subDay())->where('message', 'like', 'AiAgent:%')->get(['message', 'occurrences']);
        $gemini = $rows->filter(fn ($r) => str_contains($r->message, 'Gemini'))->sum('occurrences');
        $deepseek = $rows->filter(fn ($r) => str_contains($r->message, 'DeepSeek'))->sum('occurrences');
        $keys = array_filter(['Gemini' => config('services.gemini.api_key'), 'DeepSeek' => config('services.deepseek.api_key')]);
        $state = count($keys) === 0 || $deepseek > 0 ? 'bad' : ($gemini > 0 || count($keys) < 2 ? 'warn' : 'ok');
        $detail = 'Website chat: ' . config('services.gemini.chat_model') . ' first, then ' . config('services.deepseek.model') . '. ';
        $detail .= $gemini || $deepseek ? "Failures in 24 h: Gemini {$gemini}, DeepSeek {$deepseek}." : 'No failures in the last 24 hours.';
        if (count($keys) < 2) {
            $detail .= ' Missing key: ' . implode(', ', array_diff(['Gemini', 'DeepSeek'], array_keys($keys))) . '.';
        }
        return ['label' => 'AI providers', 'state' => $state, 'detail' => $detail,
            'fix' => $deepseek ? 'Both providers failed for some visitors: check the DeepSeek balance and the Gemini quota.' : null];
    }

    /** Opportunities (docs/OPPORTUNITIES.md 6): the Google connection, the Claude key and the month's AI budget */
    private static function integrations(): array
    {
        $google = app(\App\Support\Google\GoogleWorkspace::class);
        $cap = \App\Support\Ai\Budget::cap();
        $spent = \App\Support\Ai\Budget::spent();
        $claude = \App\Support\Integrations::get('anthropic.api_key') !== null;
        $refused = DB::table('ai_runs')->where('created_at', '>=', now()->subDay())->whereIn('error', ['ai.errors.key', 'ai.errors.credit'])->count();

        $detail = ($google->isConnected() ? 'Google: connected as ' . ($google->accountEmail() ?? 'the company account') . '.' : ($google->needsReconnect() ? 'Google: access withdrawn, reconnect needed.' : 'Google: not connected yet.'))
            . ' Claude key: ' . ($claude ? 'saved' : 'missing') . '.'
            . sprintf(' AI this month: $%s of $%s.', number_format($spent, 2), number_format($cap, 2));

        [$state, $fix] = match (true) {
            $google->needsReconnect() => ['bad', 'Google withdrew HQ’s access, so meetings and transcripts are stopped. HQ › System › Integrations → Connect Google.'],
            $cap > 0 && $spent >= $cap => ['bad', 'The month’s AI budget is spent, so AI jobs are stopped. Raise it in HQ › System › Integrations, or wait for the 1st.'],
            $refused > 0 => ['bad', 'Claude refused the key or the credit has run out. Check platform.claude.com, then the key in HQ › System › Integrations.'],
            $cap > 0 && $spent >= 0.8 * $cap => ['warn', 'Over 80% of the month’s AI budget is spent.'],
            ! $google->isConnected() || ! $claude => ['warn', 'Not set up yet: enter the Google client and the Claude key in HQ › System › Integrations, then click Connect Google.'],
            default => ['ok', null],
        };

        return ['label' => 'Integrations (Google, Claude)', 'state' => $state, 'detail' => $detail, 'fix' => $fix];
    }

    /** Server backups (docs/BACKUPS.md): every site's last database and files backup, and the weekly restore test */
    private static function backups(?array $sites = null): array
    {
        return \App\Support\Backups\Status::check($sites);
    }

    private static function queue(): array
    {
        // AI jobs (queues 'ai' and 'ai-bulk') are run by the worker the scheduler keeps up; nothing else should wait here
        $aiQueues = array_values(array_filter([config('ai.queue.name', 'ai'), config('ai.queue.bulk')]));
        $other = DB::table('jobs')->whereNotIn('queue', $aiQueues);
        $waiting = (clone $other)->count();
        $oldest = $waiting ? (clone $other)->min('created_at') : null;
        $ai = DB::table('jobs')->whereIn('queue', $aiQueues);
        $aiWaiting = (clone $ai)->count();
        $aiOldest = $aiWaiting ? (clone $ai)->min('created_at') : null;
        $failed = DB::table('failed_jobs')->count();
        $stale = $oldest && (time() - (int) $oldest) > 3600;
        $aiStale = $aiOldest && (time() - (int) $aiOldest) > 600;
        return ['label' => 'Background jobs', 'state' => $stale || $aiStale || $failed ? 'warn' : 'ok',
            'detail' => "{$aiWaiting} AI job(s) waiting" . ($aiOldest ? ' (oldest ' . self::ago(Carbon::createFromTimestamp((int) $aiOldest)) . ')' : '')
                . ", {$waiting} other waiting" . ($oldest ? ' (oldest ' . self::ago(Carbon::createFromTimestamp((int) $oldest)) . ')' : '') . ", {$failed} failed. Emails are sent straight away; AI jobs start within seconds.",
            'fix' => $aiStale ? 'AI jobs are not being picked up: check that the cron job runs "schedule:run" every minute.'
                : ($stale ? 'Old waiting jobs outside the AI queue will never run (no worker for them). Review and clear them.' : null)];
    }

    private static function logs(): array
    {
        $bytes = 0;
        foreach (glob(storage_path('logs/*')) ?: [] as $f) {
            $bytes += is_file($f) ? filesize($f) : 0;
        }
        foreach ([base_path('error_log'), dirname(base_path()) . '/public_html/PRAXIS/error_log'] as $f) {
            $bytes += is_file($f) ? filesize($f) : 0;
        }
        $mb = round($bytes / 1048576, 1);
        return ['label' => 'Log files', 'state' => $mb > 150 ? 'warn' : 'ok',
            'detail' => "{$mb} MB of logs. Daily logs are kept 14 days.",
            'fix' => $mb > 150 ? 'Archive or delete old large logs (storage/logs/laravel.log, praxis_core/error_log) after checking them.' : null];
    }

    private static function leads(): array
    {
        $last = collect(['appointments', 'crm_notes', 'copq_assessments'])
            ->map(fn ($t) => DB::table($t)->max('created_at'))->filter()->max();
        if (!$last) {
            return ['label' => 'Website enquiries', 'state' => 'warn', 'detail' => 'No enquiry has ever been recorded.', 'fix' => null];
        }
        $days = (int) Carbon::parse($last)->diffInDays(now(), true);
        return ['label' => 'Website enquiries', 'state' => $days > 30 ? 'bad' : ($days > 14 ? 'warn' : 'ok'),
            'detail' => 'Last enquiry (booking, contact form or cost report) ' . self::ago($last) . '.',
            'fix' => $days > 14 ? 'Test the contact and booking forms yourself, and check the site is getting visitors.' : null];
    }

    private static function rates(): array
    {
        $last = DB::table('mdm_exchange_rates')->where('is_manual_override', 0)->max('last_synced_at');
        if (!$last) {
            return ['label' => 'Exchange rates', 'state' => 'warn', 'detail' => 'Rates have not synced automatically yet (they sync daily at midnight).', 'fix' => null];
        }
        $hours = (int) Carbon::parse($last)->diffInHours(now(), true);
        return ['label' => 'Exchange rates', 'state' => $hours > 50 ? 'warn' : 'ok', 'detail' => 'Last automatic sync ' . self::ago($last) . '.',
            'fix' => $hours > 50 ? 'The daily sync is not running: check the scheduler and the EXCHANGERATE_API_KEY.' : null];
    }

    private static function config(): array
    {
        $problems = [];
        if (config('app.debug')) $problems[] = 'APP_DEBUG is on (shows code to visitors on errors)';
        if (app()->environment() !== 'production') $problems[] = 'APP_ENV is not production';
        if (in_array(strtolower((string) config('logging.channels.daily.level')), ['debug', 'info'])) $problems[] = 'LOG_LEVEL is very chatty';
        return ['label' => 'Live settings', 'state' => $problems ? 'bad' : 'ok',
            'detail' => $problems ? implode('; ', $problems) . '.' : 'Production mode, debug off, warning-level logs.',
            'fix' => $problems ? 'Fix these lines in praxis_core/.env.' : null];
    }

    /** How this web server runs HQ (performance pass, 11 Nov 2026). Shown on System health only; never 'bad'. */
    private static function speed(): array
    {
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $stats = is_array($opcache) ? ($opcache['opcache_statistics'] ?? []) : [];

        return self::speedFrom([
            'sapi' => PHP_SAPI,
            'early_finish' => function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'),
            'opcache' => is_array($opcache) && ! empty($opcache['opcache_enabled']),
            'opcache_full' => is_array($opcache) && ! empty($opcache['cache_full']),
            'hit_rate' => isset($stats['opcache_hit_rate']) ? (float) $stats['opcache_hit_rate'] : null,
            'free_mb' => isset($opcache['memory_usage']['free_memory']) ? (int) round($opcache['memory_usage']['free_memory'] / 1048576) : null,
            'cached_files' => isset($stats['num_cached_scripts']) ? (int) $stats['num_cached_scripts'] : null,
            'laravel_caches' => app()->configurationIsCached() && app()->routesAreCached() && app()->eventsAreCached(),
        ]);
    }

    /**
     * The speed check from what was measured, so it can be tested: whether PHP can answer before slow work is done
     * (opening a mail marks it read on the mail server after the page has answered; pushes go after the response),
     * whether OPcache keeps compiled code in memory, and whether the deploy built Laravel's caches.
     */
    public static function speedFrom(array $facts): array
    {
        $problems = $fixes = [];
        if (empty($facts['early_finish'])) {
            $problems[] = "PHP runs as {$facts['sapi']}, which can't answer a page before slow work is done, so opening a mail waits for the mail server";
            $fixes[] = 'cPanel → MultiPHP Manager: switch hq.jbspraxis.com and portal.jbspraxis.com to PHP-FPM.';
        }
        if (empty($facts['opcache'])) {
            $problems[] = 'OPcache is off, so every page compiles HQ\'s code again';
            $fixes[] = 'Ask the host to switch OPcache on (opcache.enable=1, memory_consumption=256, max_accelerated_files=32531).';
        } elseif (! empty($facts['opcache_full']) || (isset($facts['hit_rate']) && $facts['hit_rate'] < 95)) {
            $problems[] = 'OPcache is full or misses often' . (isset($facts['hit_rate']) ? ' (' . round($facts['hit_rate'], 1) . '% from memory)' : '');
            $fixes[] = 'Ask the host for more OPcache memory (memory_consumption=256, max_accelerated_files=32531).';
        }
        if (empty($facts['laravel_caches'])) {
            $problems[] = 'Laravel\'s caches are not built';
            $fixes[] = 'The deployer builds them after every release; to build them now, run "php artisan optimize" in praxis_core.';
        }

        $ok = 'PHP (' . $facts['sapi'] . ') answers before slow work is done; OPcache serves '
            . (isset($facts['hit_rate']) ? round($facts['hit_rate'], 1) . '% of code from memory' : 'code from memory')
            . (isset($facts['cached_files']) ? ' (' . number_format($facts['cached_files']) . ' files' . (isset($facts['free_mb']) ? ', ' . $facts['free_mb'] . ' MB free' : '') . ')' : '')
            . "; Laravel's caches are built.";

        return ['label' => 'Server speed settings', 'state' => $problems ? 'warn' : 'ok',
            'detail' => $problems ? implode('; ', $problems) . '.' : $ok,
            'fix' => $fixes ? implode(' ', $fixes) : null];
    }

    // HQ app notifications (docs/PWA.md). Never 'bad': a phone missing a notification is not an emergency.
    private static function push(): array
    {
        $label = 'Push notifications';
        if (!PushDispatcher::ready()) {
            return ['label' => $label, 'state' => 'warn', 'detail' => 'The HQ app tables aren’t in the database yet, so nothing is pushed.',
                'fix' => 'Run the database migration on the server (docs/PWA.md, Deploy).'];
        }
        try {
            VapidKeys::generate();
        } catch (\Throwable $e) {
            return ['label' => $label, 'state' => 'warn', 'detail' => 'PHP’s OpenSSL can’t make the P-256 keys that push needs, so no notification can be sent.',
                'fix' => 'Ask the host to enable OpenSSL with elliptic curves for PHP 8.3.'];
        }

        $devices = DB::table('push_subscriptions')->count();
        $people = DB::table('push_subscriptions')->distinct()->count('user_id');
        $last = collect([DB::table('push_deliveries')->max('sent_at'), DB::table('push_subscriptions')->max('last_sent_at')])->filter()->max();
        $since = now()->subDay();
        $failed = DB::table('push_deliveries')->where('created_at', '>=', $since)->where('status', 'failed')->count();
        $reached = DB::table('push_deliveries')->where('created_at', '>=', $since)->whereIn('status', ['sent', 'partial'])->count();

        $detail = $devices === 0
            ? 'No device has notifications turned on yet. Each person turns them on from the bell menu in HQ.'
            : $devices . ' ' . Str::plural('device', $devices) . ' for ' . $people . ' ' . Str::plural('person', $people) . '; ' . ($last ? 'last sent ' . self::ago($last) : 'nothing sent yet') . '.';
        if ($failed >= 5 && $failed > $reached) {
            return ['label' => $label, 'state' => 'warn', 'detail' => $detail . " In the last 24 hours {$failed} notifications reached no device and {$reached} did.",
                'fix' => 'Send yourself a test notification from the bell menu. If it doesn’t arrive, check the Errors tab.'];
        }
        return ['label' => $label, 'state' => 'ok', 'detail' => $detail, 'fix' => null];
    }

    public static function ago($time): string
    {
        return Carbon::parse($time)->diffForHumans(now(), ['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]);
    }
}
