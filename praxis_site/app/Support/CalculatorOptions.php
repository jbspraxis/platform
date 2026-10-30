<?php

namespace App\Support;

/**
 * The calculator's lists from GET /calculator/reference, as value => label for the selects.
 * Accepts plain strings or objects ({"value","label"}, or the reference tables' own column names).
 */
final class CalculatorOptions
{
    /** @return array{industries: array<string,string>, systems: array<string,string>, bottlenecks: array<string,string>, currencies: array<string,string>} */
    public static function fromApi(array $data): array
    {
        return [
            'industries' => self::options($data['industries'] ?? [], ['value', 'key', 'industry_name', 'name'], ['label', 'name', 'industry_name', 'value']),
            'systems' => self::options(
                $data['systems'] ?? $data['current_systems'] ?? $data['tools'] ?? $data['architectures'] ?? [],
                ['value', 'key', 'tech_key', 'code'],
                ['label', 'display_name', 'name', 'value']
            ),
            'bottlenecks' => self::options($data['bottlenecks'] ?? [], ['value', 'key', 'bottleneck_name', 'name'], ['label', 'name', 'bottleneck_name', 'value']),
            'currencies' => self::currencies($data['currencies'] ?? []),
        ];
    }

    /** @return array<string, string> */
    private static function options(mixed $rows, array $valueKeys, array $labelKeys): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $key => $row) {
            if (is_string($row) || is_numeric($row)) {
                $value = is_string($key) ? $key : (string) $row;
                $label = (string) $row;
            } elseif (is_array($row)) {
                $value = self::first($row, $valueKeys);
                $label = self::first($row, $labelKeys) ?? $value;
            } else {
                continue;
            }
            if ($value !== null && $value !== '' && mb_strlen($value) <= 150) {
                $out[$value] = trim(strip_tags((string) $label));
            }
        }

        return $out;
    }

    /** @return array<string, string> code => "NGN: Nigerian naira" */
    private static function currencies(mixed $rows): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $key => $row) {
            $code = is_array($row) ? self::first($row, ['code', 'currency_code', 'value']) : (is_string($key) ? $key : (string) $row);
            $name = is_array($row) ? self::first($row, ['name', 'label', 'currency_name']) : (is_string($key) ? (string) $row : null);
            if ($code === null || ! preg_match('/^[A-Z]{3}$/', strtoupper($code))) {
                continue;
            }
            $code = strtoupper($code);
            $out[$code] = $name && strtoupper($name) !== $code ? $code . ': ' . trim(strip_tags($name)) : $code;
        }
        ksort($out);

        return $out;
    }

    private static function first(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && (is_string($row[$key]) || is_numeric($row[$key])) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }
}
