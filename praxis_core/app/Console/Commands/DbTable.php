<?php

namespace App\Console\Commands;

use Illuminate\Database\Console\TableCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Laravel's db:table, also on a PHP without the intl extension (the live server's PHP), as App\Console\Commands\DbShow
 * does for db:show: Laravel writes the table's size with Illuminate\Support\Number, which needs intl. Without intl it
 * prints the same information as JSON, as --json does, with a note on stderr; with intl, nothing changes.
 */
#[AsCommand(name: 'db:table')]
class DbTable extends TableCommand
{
    protected function display(array $data)
    {
        if (! $this->option('json') && ! extension_loaded('intl')) {
            $this->output->getErrorStyle()->writeln('This PHP has no intl extension, which the usual layout needs for sizes: the same information as JSON.');
            $this->displayJson($data);

            return;
        }

        parent::display($data);
    }
}
