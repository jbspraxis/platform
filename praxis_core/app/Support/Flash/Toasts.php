<?php

namespace App\Support\Flash;

/**
 * Confirmations and errors in HQ and the Client Portal.
 *
 * Every message a screen flashes to the session leaves through here and arrives as a toast in the bottom-right
 * corner (resources/views/components/flash.blade.php, docs/BRAND.md section 12). Two things read this class:
 * <x-flash> on a page load, and App\Support\Flash\ToastBridge on a Livewire update.
 */
class Toasts
{
    /** How long a toast stays before it closes itself, in milliseconds. Warnings and errors never do. */
    public const AUTO_CLOSE_MS = 4000;

    /** Never show more than this many at once: the newest push the oldest off. */
    public const MAX_VISIBLE = 4;

    /** tone => [alert class, Lucide icon, ARIA role, closes itself] */
    public const TONES = [
        'success' => ['alert-success', 'circle-check', 'status', true],
        'info' => ['alert-info', 'info', 'status', true],
        'warning' => ['alert-warning', 'triangle-alert', 'alert', false],
        'error' => ['alert-danger', 'circle-alert', 'alert', false],
    ];

    /**
     * Is this session key one of our messages? The four tone names on their own, a name ending in one of them
     * (team_error, global_success), and the plain "message" keys Cash requests uses, including "message-17".
     */
    public static function isMessageKey(string $key): bool
    {
        return (bool) preg_match('/^(success|info|warning|error|message)$/', $key)
            || (bool) preg_match('/_(success|info|warning|error)$/', $key)
            || (bool) preg_match('/^message-/', $key);
    }

    /** The tone a key reads as: the word it ends in, or good news when the name says nothing. */
    public static function toneFor(string $key): string
    {
        return preg_match('/(success|info|warning|error)$/', $key, $m) ? $m[1] : 'success';
    }

    /** The look of a tone: [alert class, Lucide icon, ARIA role, closes itself]. */
    public static function look(string $tone): array
    {
        return self::TONES[$tone] ?? self::TONES['info'];
    }

    /**
     * Take every message waiting in the session and clear it, so one message is shown once.
     *
     * @return array<int, array{key: string, message: string, tone: string}> in the order they were flashed
     */
    public static function pull(): array
    {
        if (! app()->has('session.store')) {
            return [];
        }

        $session = session();

        // Messages flashed by the request that redirected here sit in _flash.old; ones flashed by this request
        // (a Livewire action) sit in _flash.new.
        $keys = array_values(array_unique(array_merge(
            (array) $session->get('_flash.old', []),
            (array) $session->get('_flash.new', []),
        )));

        $toasts = [];

        foreach ($keys as $key) {
            if (! is_string($key) || ! self::isMessageKey($key)) {
                continue;
            }

            $value = $session->get($key);

            // Only text is a message: Cash requests also flashes arrays under keys of its own
            if (! is_string($value) && ! $value instanceof \Stringable) {
                continue;
            }

            if (($message = trim((string) $value)) === '') {
                continue;
            }

            $toasts[] = ['key' => $key, 'message' => $message, 'tone' => self::toneFor($key)];

            $session->forget($key);
        }

        return $toasts;
    }
}
