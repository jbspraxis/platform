<?php

namespace App\Support\Import;

/**
 * What the engine found in a file (the preview), and after an import what it did. Kept as a plain array between the
 * dialog's requests (toArray / fromArray). Each row has a status:
 *
 *   ready     comes in as (part of) a new record
 *   add       its record already exists, and this row still brings something new (added on import)
 *   exists    its record already exists and this row adds nothing
 *   fix       needs fixing: reasons, and the red cells (column keys)
 *   imported  / added   after an import: what happened to it
 */
final class Analysis
{
    /**
     * @param  array<int, array{values: array<string, string>, status: string, reasons: array<int, string>, red: array<int, string>, note: ?string, url: ?string}>  $rows  by row number
     * @param  array<int, string>  $ignored  headers in the file that aren't the schema's columns
     */
    public function __construct(
        public readonly string $fileName,
        public readonly string $hash,
        public readonly string $sheet,
        public array $rows,
        public readonly array $ignored = [],
        public array $result = [],
    ) {
    }

    /** @param  array<string, Group>  $groups */
    public static function fromGroups(string $fileName, string $hash, string $sheet, array $groups, array $ignored): self
    {
        $rows = [];
        foreach ($groups as $group) {
            foreach ($group->rows as $line => $values) {
                $problems = $group->problems($line);
                $status = match (true) {
                    (bool) $problems => 'fix',
                    $group->existing !== null => in_array($line, $group->adds, true) ? 'add' : 'exists',
                    default => 'ready',
                };
                $rows[$line] = [
                    'values' => $values,
                    'status' => $status,
                    'reasons' => array_values(array_unique(array_column($problems, 'message'))),
                    'red' => array_values(array_unique(array_filter(array_column($problems, 'column')))),
                    'note' => $group->existing['label'] ?? null,
                    'url' => $group->existing['url'] ?? null,
                    'group' => $group->key,
                ];
            }
        }
        ksort($rows);

        return new self($fileName, $hash, $sheet, $rows, $ignored);
    }

    /** Rows by status, and how many new records the ready rows make */
    public function counts(): array
    {
        $by = array_count_values(array_column($this->rows, 'status'));
        $records = count(array_unique(array_column(array_filter($this->rows, fn ($r) => $r['status'] === 'ready'), 'group')));

        return [
            'total' => count($this->rows),
            'ready' => $by['ready'] ?? 0,
            'add' => $by['add'] ?? 0,
            'exists' => ($by['exists'] ?? 0) + ($by['add'] ?? 0) + ($by['added'] ?? 0),
            'imported' => $by['imported'] ?? 0,
            'fix' => $by['fix'] ?? 0,
            'records' => $records,
        ];
    }

    /** Whether importing now would bring anything in */
    public function hasWork(): bool
    {
        $c = $this->counts();

        return $c['ready'] + $c['add'] > 0;
    }

    /** @return array<int, array> the rows that need fixing, by row number */
    public function toFix(): array
    {
        return array_filter($this->rows, fn ($r) => $r['status'] === 'fix');
    }

    public function toArray(): array
    {
        return ['file' => $this->fileName, 'hash' => $this->hash, 'sheet' => $this->sheet, 'rows' => $this->rows, 'ignored' => $this->ignored, 'result' => $this->result];
    }

    public static function fromArray(array $a): self
    {
        $rows = [];
        foreach ($a['rows'] ?? [] as $line => $row) {
            $rows[(int) $line] = $row;
        }

        return new self((string) $a['file'], (string) $a['hash'], (string) ($a['sheet'] ?? ''), $rows, $a['ignored'] ?? [], $a['result'] ?? []);
    }
}
