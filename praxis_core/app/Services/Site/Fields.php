<?php

namespace App\Services\Site;

/**
 * One set of rules, two callers. The services name fields as the site API does (snake_case JSON);
 * the old Livewire pages use their own property names. These helpers rename between the two, so
 * both validate with exactly the same rules.
 */
final class Fields
{
    /** Rules whose first parameter names another field */
    private const FIRST_IS_FIELD = ['required_if', 'required_unless', 'exclude_if', 'exclude_unless', 'prohibited_if', 'prohibited_unless', 'same', 'different'];

    /** Rules whose every parameter names another field */
    private const ALL_ARE_FIELDS = ['required_with', 'required_with_all', 'required_without', 'required_without_all'];

    /**
     * API-named rules renamed for a Livewire component.
     *
     * @param  array<string, mixed>  $rules  keyed by API name
     * @param  array<string, string>  $map  Livewire property => API name
     */
    public static function rulesFor(array $rules, array $map): array
    {
        $toProp = array_flip($map);
        $out = [];
        foreach ($rules as $field => $rule) {
            if (is_string($rule)) {
                $rule = implode('|', array_map(fn ($r) => self::rename($r, $toProp), explode('|', $rule)));
            } elseif (is_array($rule)) {
                $rule = array_map(fn ($r) => is_string($r) ? self::rename($r, $toProp) : $r, $rule);
            }
            $out[$toProp[$field] ?? $field] = $rule;
        }

        return $out;
    }

    /** Custom messages ("field.rule" => text) renamed for a Livewire component. */
    public static function messagesFor(array $messages, array $map): array
    {
        $toProp = array_flip($map);
        $out = [];
        foreach ($messages as $key => $text) {
            [$field, $rule] = array_pad(explode('.', $key, 2), 2, null);
            $out[($toProp[$field] ?? $field) . ($rule !== null ? '.' . $rule : '')] = $text;
        }

        return $out;
    }

    /** API-named input from a Livewire component's properties. */
    public static function fromComponent(object $component, array $map): array
    {
        $in = [];
        foreach ($map as $prop => $name) {
            $in[$name] = $component->{$prop};
        }

        return $in;
    }

    /** API-named input from an array keyed by Livewire names (only the mapped keys that are present). */
    public static function fromArray(array $data, array $map): array
    {
        $in = [];
        foreach ($map as $prop => $name) {
            if (array_key_exists($prop, $data)) {
                $in[$name] = $data[$prop];
            }
        }

        return $in;
    }

    /**
     * A typed full name as client_users' first_name and last_name (50 characters each): the first word, then
     * the rest ("Unknown" when there is none). A long name is shortened instead of failing the form (a 500).
     *
     * @return array{0: string, 1: string}
     */
    public static function nameParts(?string $fullName): array
    {
        $parts = explode(' ', trim((string) $fullName), 2);
        $last = trim($parts[1] ?? '');

        return [mb_substr($parts[0], 0, 50), $last === '' ? 'Unknown' : mb_substr($last, 0, 50)];
    }

    private static function rename(string $rule, array $toProp): string
    {
        [$name, $params] = array_pad(explode(':', $rule, 2), 2, null);
        $first = in_array($name, self::FIRST_IS_FIELD, true);
        $all = in_array($name, self::ALL_ARE_FIELDS, true);
        if ($params === null || (!$first && !$all)) {
            return $rule;
        }
        $parts = explode(',', $params);
        foreach ($parts as $i => $p) {
            if ($all || $i === 0) {
                $parts[$i] = $toProp[$p] ?? $p;
            }
        }

        return $name . ':' . implode(',', $parts);
    }
}
