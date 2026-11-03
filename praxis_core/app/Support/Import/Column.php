<?php

namespace App\Support\Import;

/**
 * One column of an import (a schema's columns(), in the template's order). The template's guide prints the label,
 * whether it is required, the help line and the good and bad examples; the data sheet's header is header().
 */
final class Column
{
    /**
     * @param  string  $key  the value's key in a row (schemas read $row[$key])
     * @param  bool  $required  every row (or group) needs it: marked * in the template
     * @param  string|null  $oneOf  a rule across columns ("at least one of these"): every column with the same name is marked +; the schema checks it
     * @param  array<string, string>  $choices  value => label: a drop-down in the template; a cell holding a label or a value reads as the value
     * @param  bool  $strict  only the choices are allowed (the drop-down refuses anything else); else they are suggestions
     * @param  bool  $text  formatted as text in the template, so 0803… keeps its 0 and +234… its +
     * @param  array<int, string>  $aliases  other headers read as this column (exports from other tools)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $required = false,
        public readonly ?string $oneOf = null,
        public readonly string $help = '',
        public readonly string $example = '',
        public readonly string $bad = '',
        public readonly array $choices = [],
        public readonly bool $strict = false,
        public readonly bool $text = true,
        public readonly int $width = 20,
        public readonly array $aliases = [],
    ) {
    }

    /** The data sheet's header: "Organisation name *" (required) or "Organisation phone +" (one of a group) */
    public function header(): string
    {
        return $this->label . ($this->required ? ' *' : ($this->oneOf ? ' +' : ''));
    }

    /** What the guide's "Required" column says */
    public function requirement(string $oneOfText = 'At least one of the columns marked +'): string
    {
        return $this->required ? 'Required' : ($this->oneOf ? $oneOfText : 'Optional');
    }

    /** Headers (normalised) that read as this column */
    public function matches(): array
    {
        return array_values(array_unique(array_map([self::class, 'normalise'], [$this->key, $this->label, $this->header(), ...$this->aliases])));
    }

    /** "Organisation name *" → "organisationname": headers compare without case, spaces or marks */
    public static function normalise(string $header): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($header)) ?? '';
    }

    /** A cell read into this column: a choice's label (any case) gives its value; the rest is kept as typed */
    public function read(string $cell): string
    {
        $cell = trim(preg_replace('/\s+/u', ' ', $cell) ?? '');
        if ($cell === '' || ! $this->choices) {
            return $cell;
        }
        $key = self::normalise($cell);
        foreach ($this->choices as $value => $label) {
            if (self::normalise((string) $value) === $key || self::normalise($label) === $key) {
                return (string) $value;
            }
        }

        return $cell;
    }
}
