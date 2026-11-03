<?php

namespace Tests\Feature\Import;

use App\Models\User;
use App\Support\Import\Column;
use App\Support\Import\Group;
use App\Support\Import\ImportSchema;
use App\Support\Import\Option;
use App\Support\Import\Run;

/**
 * A made-up schema for the engine's own tests (and a short example of what a new import type writes): one row per
 * person, rows of the same company are one record, a company the test lists in $known "already exists", and its
 * people the test lists in $people are already there. Nothing touches the database.
 */
final class TeamsSchema extends ImportSchema
{
    /** Companies that already exist (lower case) => id */
    public static array $known = [];

    /** People already in a known company: "company|name" (lower case) */
    public static array $people = [];

    /** What create() and add() did: [company, [names]] */
    public static array $created = [];

    public static array $added = [];

    public function key(): string
    {
        return 'teams';
    }

    public function title(): string
    {
        return 'Import teams';
    }

    public function noun(): array
    {
        return ['team', 'teams'];
    }

    public function sheetName(): string
    {
        return 'Teams';
    }

    public function addedNoun(): array
    {
        return ['person', 'people'];
    }

    public function canImport(?User $user): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            new Column('company', 'Company', required: true, help: 'Rows with the same company are one team.', example: 'Kilo Ltd', bad: 'Kilo?', aliases: ['organisation']),
            new Column('name', 'Name', help: 'One person per row.', example: 'Ada Obi', aliases: ['full name']),
            new Column('email', 'Email', oneOf: 'reach', example: 'ada@kilo.test', aliases: ['e-mail']),
            new Column('phone', 'Phone', oneOf: 'reach', example: '+234 803 555 0101'),
            new Column('size', 'Size', choices: ['small' => 'Small team', 'large' => 'Large team'], strict: true, example: 'Small team'),
            new Column('started', 'Started', text: false, example: '2026-11-03'),
        ];
    }

    public function guide(): array
    {
        return ['Fill in the second sheet: one row per person.'];
    }

    public function examples(): array
    {
        return [['company' => 'Example team (guide only)', 'name' => 'Ada Example', 'email' => 'ada@example.test', 'size' => 'small']];
    }

    public function options(User $by, string $fileName): array
    {
        return [Option::choice('colour', 'Colour', ['teal' => 'Teal', 'ink' => 'Ink'], 'teal'), Option::text('note', 'Note', $fileName, max: 20)];
    }

    public function groupKey(array $row, int $line): string
    {
        return $row['company'] === '' ? 'row:' . $line : mb_strtolower($row['company']);
    }

    public function existing(Group $group, Run $run): ?array
    {
        $id = self::$known[mb_strtolower($group->value('company'))] ?? null;

        return $id ? ['id' => $id, 'label' => $group->value('company') . ' (known)', 'url' => null] : null;
    }

    public function check(Group $group, Run $run): void
    {
        if ($group->value('company') === '') {
            $group->failAll('Enter the company.', 'company');
        }
        foreach ($group->rows as $line => $row) {
            if ($row['email'] !== '' && ! str_contains($row['email'], '@')) {
                $group->fail($line, 'email', 'Enter an email like ada@kilo.test.');
            }
            if ($row['size'] !== '' && ! in_array($row['size'], ['small', 'large'], true)) {
                $group->fail($line, 'size', 'Choose Small team or Large team.');
            }
        }
        if (! $group->existing && $group->value('email') === '' && $group->value('phone') === '') {
            $group->failAll('Add a phone or an email.', 'email');
        }
        if ($group->existing) {
            foreach ($group->rows as $line => $row) {
                if ($row['name'] !== '' && $group->isGood($line) && ! in_array(mb_strtolower($group->value('company') . '|' . $row['name']), self::$people, true)) {
                    $group->adds[] = $line;
                }
            }
        }
    }

    public function create(Group $group, Run $run): ?string
    {
        self::$created[] = [$group->value('company'), array_map(fn ($l) => $group->rows[$l]['name'], $group->goodLines()), $run->dedupeKey($group->first()), $run->options];
        self::$known[mb_strtolower($group->value('company'))] = 'id-' . count(self::$created);
        foreach ($group->goodLines() as $line) {
            self::$people[] = mb_strtolower($group->value('company') . '|' . $group->rows[$line]['name']);
        }

        return 'id-' . count(self::$created);
    }

    public function add(Group $group, Run $run): int
    {
        foreach ($group->adds as $line) {
            self::$added[] = [$group->existing['id'], $group->rows[$line]['name']];
            self::$people[] = mb_strtolower($group->value('company') . '|' . $group->rows[$line]['name']);
        }

        return count($group->adds);
    }
}
