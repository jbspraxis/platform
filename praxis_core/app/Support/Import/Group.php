<?php

namespace App\Support\Import;

/**
 * The rows of a file that make one record (a schema's groupKey(): for Leads, the rows of one organisation), with
 * what is wrong with each. A schema's check() calls fail() for each problem: the row becomes "Needs fixing" with the
 * message, and the cell of $column turns red in the preview and in the file of rows to fix.
 */
final class Group
{
    /** @var array<int, array<int, array{column: ?string, message: string}>> row => problems */
    private array $problems = [];

    /** The record this group already is (ImportSchema::existing()): ['id', 'label', 'url' => ?, 'note' => ?] */
    public ?array $existing = null;

    /** For an existing record: the rows that still bring something new (added on import) */
    public array $adds = [];

    /** @param  array<int, array<string, string>>  $rows  row number in the file => values by column key */
    public function __construct(public readonly string $key, public array $rows)
    {
    }

    /** @return array<int, int> the row numbers, in file order */
    public function lines(): array
    {
        return array_keys($this->rows);
    }

    public function first(): int
    {
        return array_key_first($this->rows);
    }

    /** The first value given for $column in the group's rows ('' when none) */
    public function value(string $column): string
    {
        foreach ($this->rows as $row) {
            if (($row[$column] ?? '') !== '') {
                return $row[$column];
            }
        }

        return '';
    }

    /** The row holding value($column), else the first row */
    public function lineOf(string $column): int
    {
        foreach ($this->rows as $line => $row) {
            if (($row[$column] ?? '') !== '') {
                return $line;
            }
        }

        return $this->first();
    }

    /** Row $line needs fixing: $message, with $column's cell in red (null: no cell in particular) */
    public function fail(int $line, ?string $column, string $message): void
    {
        foreach ($this->problems[$line] ?? [] as $p) {
            if ($p['column'] === $column && $p['message'] === $message) {
                return;
            }
        }
        $this->problems[$line][] = ['column' => $column, 'message' => $message];
    }

    /** Every row needs fixing ($message on each); the red cell is $column on the row that holds it */
    public function failAll(string $message, ?string $column = null, ?int $line = null): void
    {
        $red = $line ?? ($column ? $this->lineOf($column) : $this->first());
        foreach ($this->lines() as $l) {
            $this->fail($l, $l === $red ? $column : null, $message);
        }
    }

    /** @return array<int, array{column: ?string, message: string}> */
    public function problems(int $line): array
    {
        return $this->problems[$line] ?? [];
    }

    public function isGood(int $line): bool
    {
        return empty($this->problems[$line]);
    }

    /** @return array<int, int> rows with nothing to fix */
    public function goodLines(): array
    {
        return array_values(array_filter($this->lines(), fn ($l) => $this->isGood($l)));
    }
}
