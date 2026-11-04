<?php

namespace App\Console\Commands;

use App\Support\Campaigns\Outreach\Drafts;
use Illuminate\Console\Command;

/**
 * Campaigns › Outreach (docs/CAMPAIGNS.md section 10), every minute: the next imported prospects' websites are read
 * (safely) and their emails go to Claude on the 'ai' queue, which the scheduler's worker runs. 10 a run.
 */
class OutreachWrite extends Command
{
    protected $signature = 'outreach:write';

    protected $description = 'Starts Claude on the next outreach drafts (10 a run) and files finished ones.';

    public function handle(): int
    {
        $started = Drafts::writeDue();
        if ($started) {
            $this->info("Started {$started} " . ($started === 1 ? 'draft' : 'drafts') . '.');
        }

        return self::SUCCESS;
    }
}
