<?php

namespace App\Console\Commands;

use Illuminate\Database\Console\ShowCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Laravel's db:show, also on a PHP without the intl extension (the live server's PHP: "artisan db:show --counts"
 * failed on 29 Oct 2026, because Laravel writes sizes and row counts with Illuminate\Support\Number, which needs
 * intl). Without intl it prints the same information as JSON, as --json does, with a note on stderr; with intl,
 * nothing changes. Replaces the framework's command of the same name.
 */
#[AsCommand(name: 'db:show')]
class DbShow extends ShowCommand
{
    protected function display(array $data)
    {
        if (! $this->option('json') && ! extension_loaded('intl')) {
            $this->output->getErrorStyle()->writeln('This PHP has no intl extension, which the usual layout needs for sizes and counts: the same information as JSON.');
            $this->displayJson($data);

            return;
        }

        parent::display($data);
    }
}
