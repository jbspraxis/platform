<?php

namespace App\Livewire\Hq;

use Illuminate\Database\Query\Builder;

/**
 * Money on HQ screens, in British English (docs/BRAND.md section 4).
 * ₦1,250,000.00 · $322.50 · 2,500,000 FCFA · −₦4.00 (a true minus sign). FCFA has no minor unit and shows
 * none, as on the Client Portal; halves round up everywhere (ICU would round them to even).
 *
 * Amounts in different currencies are never added together: totals are kept per currency.
 */
final class HqMoney
{
    /** One amount with its currency. A blank currency shows the bare number, without claiming one. */
    public static function format($currency, $amount, int $decimals = 2): string
    {
        $amount = (float) $amount;
        $code = strtoupper(trim((string) $currency));
        $sign = $amount < 0 ? "\u{2212}" : '';
        $number = self::number(abs($amount), $decimals);

        return match ($code) {
            'NGN' => "{$sign}₦{$number}",
            'USD' => "{$sign}\${$number}",
            'XAF', 'XOF' => $sign . self::number(abs($amount), 0) . "\u{00A0}FCFA",
            '' => $sign . $number,
            default => self::otherCurrency($code, abs($amount), $decimals, $sign, $number),
        };
    }

    /**
     * Sum a column per currency: ['NGN' => 3332.5, 'USD' => 322.5], largest first.
     * $query is a fresh query builder; it is cloned, so the caller’s builder is not changed.
     */
    public static function sumByCurrency(Builder $query, string $column, string $currencyColumn = 'currency'): array
    {
        return (clone $query)
            ->selectRaw("UPPER(COALESCE({$currencyColumn}, '')) as cur, SUM({$column}) as total")
            ->groupBy('cur')
            ->get()
            ->filter(fn ($row) => (float) $row->total != 0.0)
            ->sortByDesc(fn ($row) => (float) $row->total)
            ->mapWithKeys(fn ($row) => [(string) $row->cur => (float) $row->total])
            ->all();
    }

    /** Totals per currency as display lines; ['0'] when there is nothing to show. */
    public static function lines(array $totals, int $decimals = 2): array
    {
        if (empty($totals)) {
            return [self::number(0, 0)];
        }

        $lines = [];
        foreach ($totals as $currency => $amount) {
            $lines[] = self::format($currency, $amount, $decimals);
        }

        return $lines;
    }

    private static function number(float $value, int $decimals): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter('en_GB', \NumberFormatter::DECIMAL);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
            $formatted = $f->format($value);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format($value, $decimals);
    }

    private static function otherCurrency(string $code, float $value, int $decimals, string $sign, string $number): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter('en_GB', \NumberFormatter::CURRENCY);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $f->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
            $formatted = $f->formatCurrency($value, $code);
            if ($formatted !== false) {
                return $sign . $formatted;
            }
        }

        return "{$sign}{$code}\u{00A0}{$number}";
    }
}
