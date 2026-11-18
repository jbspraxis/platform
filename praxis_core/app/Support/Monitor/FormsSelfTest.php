<?php

namespace App\Support\Monitor;

use App\Services\Site\SiteSignature;
use App\Support\Hosts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * The website forms' daily self-test (owner, 18 Nov 2026: a quiet week with no enquiry is information; a form
 * that no longer works is the problem). Once a day the monitor sends each enquiry form's endpoint an empty but
 * correctly signed request, exactly as the website signs its own (docs/SITE_API.md section 1). The answer must
 * be 422 "invalid": that proves the web server, the site key and signature check, the route and the form's
 * validation all answer. An empty form never reaches its service, so nothing is stored or emailed, and none of
 * the visitor limits is used.
 */
final class FormsSelfTest
{
    /** The forms that bring enquiries in: API path => what the owner calls it */
    public const FORMS = [
        'enquiries' => 'contact form',
        'bookings' => 'booking form',
        'calculator/assessments' => 'cost calculator',
    ];

    private const STATE = 'forms_self_test';

    /** Run the test when it is due: once a day, or every hour while the last run failed. Null when not due. */
    public static function runIfDue(): ?array
    {
        $last = self::last();
        if ($last !== null) {
            $minutes = Carbon::parse($last['at'])->diffInMinutes(now(), true);
            if ($minutes < ($last['ok'] ? 24 * 60 : 60)) {
                return null;
            }
        }

        $result = self::run();
        ErrorMonitor::setState(self::STATE, json_encode($result));

        return $result;
    }

    /** The last run: ['at' => ISO time, 'ok' => bool, 'failed' => [form => reason]], or null before the first */
    public static function last(): ?array
    {
        $raw = ErrorMonitor::state(self::STATE);
        $last = $raw ? json_decode((string) $raw, true) : null;

        return is_array($last) && isset($last['at'], $last['ok']) ? $last + ['failed' => []] : null;
    }

    public static function run(): array
    {
        $keys = (array) config('site_api.keys', []);
        $keyId = (string) array_key_first($keys);
        $failed = [];
        foreach (self::FORMS as $path => $form) {
            if ($keyId === '') {
                $failed[$form] = 'no site key in .env';
                continue;
            }
            try {
                $status = self::send($path, $keyId, (string) $keys[$keyId]);
                if ($status !== 422) {
                    $failed[$form] = 'answered HTTP ' . $status . ' instead of 422';
                }
            } catch (\Throwable $e) {
                $failed[$form] = 'could not be reached: ' . mb_substr($e->getMessage(), 0, 120);
            }
        }

        return ['at' => now()->toIso8601String(), 'ok' => $failed === [], 'failed' => $failed];
    }

    /** One empty form, signed like the website's requests; returns the HTTP status */
    private static function send(string $path, string $keyId, string $secret): int
    {
        $uri = '/api/site/v1/' . $path;
        $body = '{}';
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $visitorIp = '127.0.0.1';
        $canonical = SiteSignature::canonical('POST', $uri, $timestamp, $nonce, $visitorIp, 'en', $keyId, 'application/json', hash('sha256', $body));

        return Http::timeout(20)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Site-Key' => $keyId,
                'X-Site-Timestamp' => $timestamp,
                'X-Site-Nonce' => $nonce,
                'X-Site-Visitor-Ip' => $visitorIp,
                'X-Site-Locale' => 'en',
                'X-Site-Signature' => SiteSignature::sign($secret, $canonical),
            ])
            ->withBody($body, 'application/json')
            ->post(self::base() . $uri)
            ->status();
    }

    /** HQ's public address (the cron has no request to take a scheme from); MONITOR_FORMS_BASE overrides it locally */
    private static function base(): string
    {
        return rtrim((string) (config('services.monitor.forms_base') ?: 'https://' . Hosts::hq()), '/');
    }
}
