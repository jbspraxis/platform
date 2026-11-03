<?php

namespace App\Support\Import;

/**
 * A choice the import dialog asks for before importing (a schema's options()), shown in this order:
 *
 *   staff   a select of active staff (value: users.id), "No one" when $nullable
 *   choice  a select of $choices (value => label)
 *   text    one line, up to $max characters
 *   date    a date (Y-m-d)
 *
 * The engine checks the type (an active person, one of the choices, the length, a date); the schema's
 * checkOptions() checks the rest (e.g. a next step needs a date).
 */
final class Option
{
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
        public readonly mixed $default = null,
        public readonly string $help = '',
        public readonly array $choices = [],
        public readonly bool $optional = false,
        public readonly bool $nullable = false,
        public readonly string $none = 'No one',
        public readonly int $max = 255,
    ) {
    }

    public static function staff(string $key, string $label, ?string $default, string $help = '', bool $nullable = true, string $none = 'No one'): self
    {
        return new self($key, 'staff', $label, $default, $help, nullable: $nullable, none: $none);
    }

    public static function choice(string $key, string $label, array $choices, ?string $default, string $help = ''): self
    {
        return new self($key, 'choice', $label, $default, $help, $choices);
    }

    public static function text(string $key, string $label, ?string $default = null, string $help = '', bool $optional = true, int $max = 255): self
    {
        return new self($key, 'text', $label, $default, $help, optional: $optional, max: $max);
    }

    public static function date(string $key, string $label, ?string $default = null, string $help = '', bool $optional = true): self
    {
        return new self($key, 'date', $label, $default, $help, optional: $optional);
    }
}
