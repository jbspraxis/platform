<?php

namespace App\Livewire\Portal\Concerns;

use Carbon\Carbon;

/**
 * Money, percentages and dates on the client's token pages, in the page language (docs/BRAND.md section 4):
 * EN "₦1,250,000.00", "2,500,000 FCFA", "$1,250.00", "7.5%", "5 November 2026, 10:30 (WAT)";
 * FR "1 250 000,00 ₦", "2 500 000 FCFA", "7,5 %", "5 novembre 2026, 10 h 30 (WAT)" (narrow no-break spaces).
 */
final class GatewayFormat
{
    private const TIMEZONE = 'Africa/Lagos'; // WAT: Lagos and Douala share it

    private static function isFrench(): bool
    {
        return app()->getLocale() === 'fr';
    }

    private static function number(float $value, int $decimals): string
    {
        $fr = self::isFrench();
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter($fr ? 'fr_FR' : 'en_GB', \NumberFormatter::DECIMAL);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP); // as HQ: halves round up
            $out = $f->format($value);
        } else {
            $out = number_format($value, $decimals, $fr ? ',' : '.', $fr ? "\u{202F}" : ',');
        }

        return str_replace("\u{00A0}", "\u{202F}", (string) $out);
    }

    /** An amount in its currency (ISO code as stored: NGN, XAF, USD...). */
    public static function money($amount, ?string $currency, int $decimals = 2): string
    {
        $fr = self::isFrench();
        $code = strtoupper(trim((string) $currency)) ?: 'USD';
        $amount = (float) $amount;
        $minus = $amount < 0 ? "\u{2212}" : '';
        $amount = abs($amount);

        if ($code === 'NGN') {
            $n = self::number($amount, $decimals);
            return $minus . ($fr ? "{$n}\u{202F}₦" : "₦{$n}");
        }
        if ($code === 'XAF' || $code === 'XOF') {
            return $minus . self::number($amount, 0) . ($fr ? "\u{202F}" : "\u{00A0}") . 'FCFA';
        }
        if ($code === 'USD' && !$fr) {
            return $minus . '$' . self::number($amount, $decimals); // as HQ and the Client Portal show it (en_GB alone writes "US$")
        }
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter($fr ? 'fr_FR' : 'en_GB', \NumberFormatter::CURRENCY);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
            $out = $f->formatCurrency($amount, $code);
            if ($out !== false) {
                return $minus . ($fr ? str_replace("\u{00A0}", "\u{202F}", $out) : $out);
            }
        }
        $n = self::number($amount, $decimals);

        return $minus . ($fr ? "{$n}\u{202F}{$code}" : "{$code}\u{00A0}{$n}");
    }

    /** 50 → "50%" / "50 %"; 7.5 → "7.5%" / "7,5 %". */
    public static function percent($value): string
    {
        $value = (float) $value;
        $decimals = abs($value - round($value)) < 0.005 ? 0 : (abs($value * 10 - round($value * 10)) < 0.05 ? 1 : 2);

        return self::number($value, $decimals) . (self::isFrench() ? "\u{202F}%" : '%');
    }

    /** A date in the page language; $format is a key of gateway.formats (date, date_medium, date_short). */
    public static function date($value, string $format = 'date'): string
    {
        if (empty($value)) {
            return '';
        }

        return Carbon::parse($value)->setTimezone(self::TIMEZONE)->locale(app()->getLocale())->isoFormat(__('gateway.formats.' . $format));
    }

    /** A date and time in West Africa Time, with the zone named once: "5 November 2026, 10:30 (WAT)". */
    public static function dateTime($value): string
    {
        if (empty($value)) {
            return '';
        }

        return self::date($value, 'datetime') . ' (' . __('gateway.timezone') . ')';
    }

    /** A time of day in West Africa Time: "10:30" / "10 h 30". */
    public static function time($value): string
    {
        return self::date($value, 'time');
    }
}
