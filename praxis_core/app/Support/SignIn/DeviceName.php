<?php

namespace App\Support\SignIn;

/**
 * A short name for the device a passkey was added on ("iPhone · Safari", "Windows · Edge"), from its browser's
 * user agent, so a person can tell their passkeys apart. Product names only, so it reads the same in English
 * and French.
 */
final class DeviceName
{
    public static function from(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        $device = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA/') || str_contains($ua, 'EdgiOS/') => 'Edge',
            str_contains($ua, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        $name = implode(' · ', array_filter([$device, $browser]));

        return $name !== '' ? mb_substr($name, 0, 60) : 'Passkey';
    }
}
