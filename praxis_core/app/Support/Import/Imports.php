<?php

namespace App\Support\Import;

/**
 * The kinds of import the dialog takes (<livewire:hq.import.import-dialog schema="leads" />). Campaigns Outreach
 * adds 'prospects' here with its own schema class; the engine and the dialog don't change.
 */
final class Imports
{
    public const SCHEMAS = [
        'leads' => \App\Support\Leads\LeadImportSchema::class,
    ];

    public static function schema(string $key): ImportSchema
    {
        $class = self::SCHEMAS[$key] ?? abort(404);

        return app($class);
    }
}
