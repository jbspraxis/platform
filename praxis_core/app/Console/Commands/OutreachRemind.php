<?php

namespace App\Console\Commands;

use App\Support\Campaigns\Outreach\Reminders;
use Illuminate\Console\Command;

/**
 * Not now reminders (docs/CAMPAIGNS.md section 12), on working days at 07:30 Lagos time: each prospect who said not now
 * and whose day has come rings the campaign's sender once (App\Support\Campaigns\Outreach\Reminders).
 */
class OutreachRemind extends Command
{
    protected $signature = 'outreach:remind {--dry-run : List who would be rung, and ring nobody}';

    protected $description = 'Rings each salesperson about prospects who said not now and whose reminder day has come';

    public function handle(): int
    {
        foreach (Reminders::ring((bool) $this->option('dry-run')) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
