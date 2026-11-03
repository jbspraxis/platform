<?php

namespace App\Support\Import;

use App\Models\User;

/**
 * One kind of import (docs/LEADS.md section 6, The shared import engine). A schema says what its file holds and how
 * its rows become records; the engine (Importer) does the rest the same way for every schema: the template, reading
 * a file into a preview (Ready / Needs fixing / Already exists), importing the Ready rows once each, and the file of
 * rows to fix. The dialog (<livewire:hq.import.import-dialog schema="…" />) shows it.
 *
 * A new kind of import: a class extending this one, listed in Imports::SCHEMAS. Leads' is App\Support\Leads\LeadImportSchema.
 */
abstract class ImportSchema
{
    /** The key in Imports::SCHEMAS and the dialog's schema="…" */
    abstract public function key(): string;

    /** The dialog's title and button: "Import leads" */
    abstract public function title(): string;

    /** [one, many] of what a group becomes: ['lead', 'leads'] */
    abstract public function noun(): array;

    /** The data sheet's name in the template: "Leads" */
    abstract public function sheetName(): string;

    /** The template's file name, without .xlsx */
    public function templateName(): string
    {
        return 'JBS Praxis ' . strtolower($this->title()) . ' template';
    }

    abstract public function canImport(?User $user): bool;

    /** @return array<int, Column> in the template's order */
    abstract public function columns(): array;

    /** Lines printed at the top of the template's guide sheet */
    abstract public function guide(): array;

    /** Example rows (values by column key) shown in the guide sheet, never in the data sheet */
    public function examples(): array
    {
        return [];
    }

    /** What the guide's Required column says for a column of a oneOf group */
    public function oneOfText(string $group): string
    {
        return 'At least one of the columns marked +';
    }

    /** @return array<int, Option> what the dialog asks before importing */
    public function options(User $by, string $fileName): array
    {
        return [];
    }

    /** Problems with the dialog's choices that the types don't catch: option key => message */
    public function checkOptions(array $options, User $by): array
    {
        return [];
    }

    /** Values the schema fills in itself, on every row before grouping (e.g. a language from the country) */
    public function fill(array $row): array
    {
        return $row;
    }

    /** Rows with the same key are one record. By default every row is a record of its own. */
    public function groupKey(array $row, int $line): string
    {
        return 'row:' . $line;
    }

    /** The record this group already is, or null: ['id', 'label', 'url' => ?, 'note' => ?] ("Already exists") */
    abstract public function existing(Group $group, Run $run): ?array;

    /**
     * Find what is wrong with the group: $group->fail() for each problem. For an existing record ($group->existing),
     * put the rows that would still bring something new in $group->adds (the rest show "Already exists").
     */
    abstract public function check(Group $group, Run $run): void;

    /**
     * Create the record from a group with nothing wrong at its level (use only $group->isGood() rows for row-level
     * parts). Return its id; on a rule broken at the last moment, $group->fail() and return null.
     */
    abstract public function create(Group $group, Run $run): ?string;

    /** Add $group->adds to the existing record; return how many rows were added */
    public function add(Group $group, Run $run): int
    {
        return 0;
    }

    /** After an import: bells, a summary line… $created holds the new records' ids */
    public function finish(Run $run, array $created, int $added): void
    {
    }

    /** [one, many] of what an added row is ("contact"); the done screen says "3 contacts added to 2 leads already in HQ" */
    public function addedNoun(): array
    {
        return ['row', 'rows'];
    }
}
