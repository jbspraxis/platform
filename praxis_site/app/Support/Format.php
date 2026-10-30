<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use NumberFormatter;
use Throwable;

/**
 * Numbers, money and dates as docs/BRAND.md section 4 writes them (English for now; French in PR2c):
 * ₦1,250,000 (symbol first, no space), 2,500,000 FCFA (after, with a space), a true minus sign (−),
 * and "Thursday 5 November 2026, 10:30 (WAT)". Digits come from intl's NumberFormatter when the
 * extension is loaded.
 */
final class Format
{
    private const PREFIX = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh', 'ZAR' => 'R'];

    private const SUFFIX = ['XAF' => 'FCFA', 'XOF' => 'FCFA'];

    public static function number(float|int|string|null $value, int $decimals = 0): string
    {
        $value = (float) $value;
        $negative = $value < 0;
        $abs = abs($value);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(self::intlLocale(), NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $digits = (string) $formatter->format($abs);
        } else {
            // The live web PHP has no intl: write the digits as fr_FR / en_GB do (French: 1 250 000,5 with U+202F)
            $fr = self::intlLocale() === 'fr_FR';
            $digits = number_format($abs, $decimals, $fr ? ',' : '.', $fr ? "\u{202F}" : ',');
        }

        return ($negative ? '−' : '') . $digits;
    }

    public static function money(float|int|string|null $amount, ?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency)) ?: 'USD';
        $amount = (float) $amount;
        $digits = self::number(abs($amount));
        $sign = $amount < 0 ? '−' : '';

        if (isset(self::PREFIX[$currency])) {
            return $sign . self::PREFIX[$currency] . $digits;
        }
        if (isset(self::SUFFIX[$currency])) {
            return $sign . $digits . ' ' . self::SUFFIX[$currency];
        }

        return $sign . $currency . ' ' . $digits;
    }

    /** 3 → "3%", 3.25 → "3.3%" */
    public static function percent(float|int|string|null $value): string
    {
        $value = round((float) $value, 1);

        return self::number($value, floor($value) == $value ? 0 : 1) . '%';
    }

    /** "Thursday 5 November 2026" in the given time zone, or null when the value is not a date. */
    public static function date(?string $value, ?string $timezone = null): ?string
    {
        $date = self::parse($value, $timezone);

        return $date?->locale(app()->getLocale())->isoFormat('dddd D MMMM YYYY');
    }

    /** "Thursday 5 November 2026, 10:30 (WAT)" for a booking start. */
    public static function bookingTime(?string $value): ?string
    {
        $date = self::parse($value, (string) config('site.booking_timezone', 'Africa/Lagos'));

        return $date ? __('site.schedule.when', [
            'date' => $date->locale(app()->getLocale())->isoFormat('dddd D MMMM YYYY'),
            'time' => $date->format('H:i'),
        ]) : null;
    }

    private static function parse(?string $value, ?string $timezone): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            // ISO 8601 with an offset (the contract) is converted; a bare wall-clock time is read in $timezone
            $hasOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', trim($value));
            $date = CarbonImmutable::parse($value, $hasOffset ? null : $timezone);

            return $timezone ? $date->setTimezone($timezone) : $date;
        } catch (Throwable) {
            return null;
        }
    }

    private static function intlLocale(): string
    {
        return app()->getLocale() === 'fr' ? 'fr_FR' : 'en_GB';
    }
}
