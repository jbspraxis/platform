<?php

namespace App\Services\Site;

use App\Support\Monitor\ErrorMonitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The website's limits (docs/SITE_API.md section 6.2; numbers in config/site_api.php 'limits').
 * The site API goes through here on the limiter keys the old Livewire pages used, so counts carried
 * over when the site moved.
 *
 *   SiteLimits::visitor($ip)                       the IPv4 address, or the IPv6 /64 network
 *   SiteLimits::check('enquiry', $ip, ['email' => $email])   throws LimitReached when any allowance is used
 *   SiteLimits::hit('enquiry', $ip, ['email' => $email])     counts one accepted request
 *   SiteLimits::attempt(...)                       check, then hit
 *   SiteLimits::allowVisitorEmail($address)        false when that address (or all visitors) had enough email today
 *   SiteLimits::aiCall()                           false when the shared daily AI budget is used up
 *
 * A "global" allowance (all visitors together) is a circuit breaker: when it trips, new requests for
 * that action are refused for everyone until the window ends, and HQ → System health gets an alert.
 */
final class SiteLimits
{
    /** The limiter key prefix of each action: the old pages' own keys, kept as they were */
    private const PREFIX = [
        'enquiry' => 'contact-form', 'booking' => 'booking', 'assessment' => 'copq-report', 'kaizen' => 'kaizen-submit',
        'feedback' => 'feedback', 'careers' => 'careers', 'method_pdf' => 'whitepaper-mail', 'chat' => 'ai-agent',
        'read' => 'site-api:read', 'visitor_email' => 'visitor-mail', 'ai' => 'ai-calls',
    ];

    /** The window whose per-visitor key is the old page's key exactly ("contact-form:<ip>") */
    private const OLD_WINDOW = [
        'enquiry' => 'hour', 'booking' => 'day', 'assessment' => 'hour', 'kaizen' => 'day', 'feedback' => 'hour',
        'method_pdf' => 'day', 'read' => 'minute',
    ];

    public const WINDOWS = ['minute' => 60, '10min' => 600, 'hour' => 3600, 'day' => 86400, 'week' => 604800, 'month' => 2592000];

    /** How the System health alert names each action */
    private const LABELS = [
        'enquiry' => 'contact form enquiries', 'booking' => 'free audit requests', 'assessment' => 'cost reports',
        'kaizen' => 'article submissions', 'feedback' => 'website feedback reports', 'careers' => 'job applications',
        'method_pdf' => 'method PDF requests', 'chat' => 'Praxis AI questions', 'read' => 'page data requests from the website',
        'visitor_email' => 'emails to website visitors', 'ai' => 'AI model calls',
    ];

    private const PER = ['minute' => 'a minute', '10min' => 'per 10 minutes', 'hour' => 'an hour', 'day' => 'a day', 'week' => 'a week', 'month' => 'per 30 days'];

    /** The visitor as the limits count them: the IPv4 address, or the IPv6 /64 network ("2001:db8:1:2::/64") */
    public static function visitor(?string $ip): string
    {
        $ip = trim((string) $ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);
            if ($bin !== false && strlen($bin) === 16) {
                // An IPv4 address written as IPv6 (::ffff:203.0.113.5) is that IPv4 address
                if (str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
                    return (string) inet_ntop(substr($bin, 12));
                }

                return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
            }
        }

        return $ip === '' ? 'unknown' : $ip;
    }

    /**
     * Throws LimitReached when the visitor, one of the identities or everyone together has used the
     * allowance for this action. Nothing is counted.
     *
     * @param  array<string, ?string>  $identities  e.g. ['email' => 'a@b.co']; empty values are skipped
     */
    public static function check(string $action, ?string $ip, array $identities = []): void
    {
        $over = [];
        foreach (self::checks($action, $ip, $identities) as [$key, $max, $decay, $scope, $window]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $over[] = [$scope, $window, $max, max(1, RateLimiter::availableIn($key))];
            }
        }
        if (!$over) {
            return;
        }

        foreach ($over as [$scope, $window, $max]) {
            if ($scope === 'global') {
                self::alert($action, $window, $max);
            }
        }

        throw new LimitReached($action, $over[0][0], max(array_column($over, 3)));
    }

    /** Counts one accepted request against every allowance of the action */
    public static function hit(string $action, ?string $ip, array $identities = []): void
    {
        foreach (self::checks($action, $ip, $identities) as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    /** check() then hit() */
    public static function attempt(string $action, ?string $ip, array $identities = []): void
    {
        self::check($action, $ip, $identities);
        self::hit($action, $ip, $identities);
    }

    /**
     * May HQ email this address now? Counts the email when it may. Every email to an address a visitor
     * typed goes through here (3 a day per address, 300 a day in all, by default).
     */
    public static function allowVisitorEmail(?string $address): bool
    {
        $address = strtolower(trim((string) $address));
        if ($address === '') {
            return false;
        }
        try {
            self::attempt('visitor_email', null, ['recipient' => $address]);

            return true;
        } catch (LimitReached $e) {
            Log::info('Website: a visitor email was not sent (limit reached)', ['scope' => $e->scope]);

            return false;
        }
    }

    /** One AI model call from the shared daily budget; false (and an alert) when it is used up */
    public static function aiCall(): bool
    {
        $max = (int) config('site_api.ai_calls_per_day', 500);
        $key = self::PREFIX['ai'] . ':global:day';
        if (RateLimiter::tooManyAttempts($key, $max)) {
            self::alert('ai', 'day', $max);

            return false;
        }
        RateLimiter::hit($key, self::WINDOWS['day']);

        return true;
    }

    /** True while the shared daily AI budget has calls left */
    public static function aiBudgetLeft(): bool
    {
        return !RateLimiter::tooManyAttempts(self::PREFIX['ai'] . ':global:day', (int) config('site_api.ai_calls_per_day', 500));
    }

    /** The first name a visitor typed, at most 40 characters: the only visitor text in visitor emails */
    public static function firstName(?string $fullName): string
    {
        $first = explode(' ', trim((string) $fullName), 2)[0];

        return mb_substr($first, 0, (int) config('site_api.email_first_name_max', 40));
    }

    /**
     * [key, max, decay seconds, scope, window] for every allowance of the action.
     *
     * @return list<array{0: string, 1: int, 2: int, 3: string, 4: string}>
     */
    private static function checks(string $action, ?string $ip, array $identities): array
    {
        $limits = (array) config("site_api.limits.{$action}", []);
        $prefix = self::PREFIX[$action] ?? $action;
        $out = [];

        foreach ($limits as $scope => $windows) {
            foreach ((array) $windows as $window => $max) {
                if (!is_numeric($max) || !isset(self::WINDOWS[$window])) {
                    continue;
                }
                $tag = $window === '10min' ? '10m' : $window;

                if ($scope === 'visitor') {
                    $visitor = self::visitor($ip);
                    $key = (self::OLD_WINDOW[$action] ?? null) === $window ? "{$prefix}:{$visitor}" : "{$prefix}:{$tag}:{$visitor}";
                } elseif ($scope === 'global') {
                    $key = "{$prefix}:global:{$tag}";
                } else {
                    $value = strtolower(trim((string) ($identities[$scope] ?? '')));
                    if ($value === '') {
                        continue;
                    }
                    $key = "{$prefix}:{$scope}:{$tag}:" . sha1($value); // never the address itself in a cache key
                }

                $out[] = [$key, (int) $max, self::WINDOWS[$window], (string) $scope, (string) $window];
            }
        }

        return $out;
    }

    /** A circuit breaker tripped: one System health entry per action and hour (picked up by the monitor's alerts) */
    private static function alert(string $action, string $window, int $max): void
    {
        if (!Cache::add('site-limit:alerted:' . $action, 1, 3600)) {
            return;
        }

        $what = self::LABELS[$action] ?? $action;
        try {
            ErrorMonitor::record([
                'source' => 'site-limit',
                'level' => 'warning',
                'exception_class' => null,
                'message' => "Website limit reached for all visitors: {$max} {$what} " . (self::PER[$window] ?? $window) . '. New ones are refused until the '
                    . ($window === '10min' ? '10 minutes' : $window) . ' is over. This usually means automated traffic. The numbers are in config/site_api.php.',
                'occurred_at' => now(),
                'url' => '/api/site/v1',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Website limit reached for all visitors', ['action' => $action]);
        }
    }
}
