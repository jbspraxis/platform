<?php

namespace App\Support\Ai;

/**
 * Checks decoded JSON against the subset of JSON Schema config/ai.php uses: type (or a list of types), properties,
 * required, additionalProperties false, items, enum, minItems, maxItems. Structured output already makes Claude
 * follow the schema; this catches the cases it can't (a cut-off answer, Gemini, a fake in tests).
 */
final class JsonShape
{
    /** @return list<string> what is wrong, by path ('$.sections[2].title: missing'); empty when it fits */
    public static function errors(mixed $value, array $schema, string $path = '$'): array
    {
        $types = (array) ($schema['type'] ?? []);
        if ($types && ! collect($types)->contains(fn ($t) => self::is($value, $t))) {
            return ["{$path}: expected " . implode(' or ', $types)];
        }
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return ["{$path}: not one of the allowed values"];
        }

        $errors = [];
        if (is_array($value) && array_is_list($value) && in_array('array', $types, true)) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
                $errors[] = "{$path}: fewer than {$schema['minItems']} items";
            }
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                $errors[] = "{$path}: more than {$schema['maxItems']} items";
            }
            foreach ($value as $i => $item) {
                array_push($errors, ...(isset($schema['items']) ? self::errors($item, $schema['items'], "{$path}[{$i}]") : []));
            }
        }
        if (is_array($value) && in_array('object', $types, true)) {
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $value)) {
                    $errors[] = "{$path}.{$key}: missing";
                }
            }
            foreach ($value as $key => $item) {
                if (isset($schema['properties'][$key])) {
                    array_push($errors, ...self::errors($item, $schema['properties'][$key], "{$path}.{$key}"));
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = "{$path}.{$key}: not expected";
                }
            }
        }

        return $errors;
    }

    private static function is(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }
}
