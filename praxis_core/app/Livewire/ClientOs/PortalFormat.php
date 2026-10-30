<?php

namespace App\Livewire\ClientOs;

use Carbon\Carbon;

/**
 * Money and dates on the Client Portal, in the page's language (docs/BRAND.md section 4).
 * EN: ₦1,250,000.00 · 2,500,000 FCFA · $4,200.50 · 5 November 2026 · 5 Nov 2026, 10:30 (WAT)
 * FR: 1 250 000,00 ₦ · 2 500 000 FCFA · 4 200,50 $US · 5 novembre 2026 · 5 nov. 2026 à 10 h 30 (WAT)
 * (narrow no-break spaces in French). Amounts always show 2 decimals, so figures in a list line up;
 * FCFA has no minor unit and shows none. Times are shown in West Africa Time (Lagos and Douala).
 */
final class PortalFormat
{
    private const TIMEZONE = 'Africa/Lagos'; // WAT

    public static function money($amount, ?string $currency, int $decimals = 2): string
    {
        $fr = app()->getLocale() === 'fr';
        $amount = (float) $amount;
        $code = strtoupper(trim((string) $currency)) ?: 'USD'; // the projects and proposals default, as on the token pages

        $number = self::number(abs($amount), $decimals, $fr);
        $sign = $amount < 0 ? "\u{2212}" : ''; // a true minus sign

        if ($code === 'NGN') {
            return $fr ? "{$sign}{$number}\u{202F}₦" : "{$sign}₦{$number}";
        }
        if ($code === 'XAF' || $code === 'XOF') {
            return $sign . self::number(abs($amount), 0, $fr) . ($fr ? "\u{202F}" : "\u{00A0}") . 'FCFA';
        }
        if ($code === 'USD' && !$fr) {
            return "{$sign}\${$number}"; // "$4,200.50", as HQ shows it (en_GB alone would write "US$")
        }
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter($fr ? 'fr_FR' : 'en_GB', \NumberFormatter::CURRENCY);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
            $formatted = $f->formatCurrency(abs($amount), $code);
            if ($formatted !== false) {
                return $sign . ($fr ? str_replace("\u{00A0}", "\u{202F}", $formatted) : $formatted);
            }
        }

        return $fr ? "{$sign}{$number}\u{202F}{$code}" : "{$sign}{$code}\u{00A0}{$number}";
    }

    private static function number(float $value, int $decimals, bool $fr): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter($fr ? 'fr_FR' : 'en_GB', \NumberFormatter::DECIMAL);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP); // as HQ: halves round up
            $number = $f->format($value);
        } else {
            $number = number_format($value, $decimals, $fr ? ',' : '.', $fr ? "\u{202F}" : ',');
        }

        return str_replace("\u{00A0}", "\u{202F}", (string) $number);
    }

    /**
     * A date, or a date and time; $format is 'date' or 'datetime' (lang/<locale>/portal.php, formats).
     * Shown in West Africa Time; a date and time names the zone once: "5 Nov 2026, 10:30 (WAT)".
     */
    public static function date($value, string $format = 'date'): string
    {
        if (blank($value)) {
            return '';
        }

        $out = Carbon::parse($value)->setTimezone(self::TIMEZONE)->locale(app()->getLocale())->isoFormat(__('portal.formats.' . $format));

        return $format === 'datetime' ? $out . ' (' . __('portal.timezone') . ')' : $out;
    }
}
