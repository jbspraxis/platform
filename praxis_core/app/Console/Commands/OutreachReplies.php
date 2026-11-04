<?php

namespace App\Console\Commands;

use App\Support\Campaigns\Outreach\Inbound;
use App\Support\Campaigns\Outreach\Sorting;
use Illuminate\Console\Command;

/**
 * Outreach replies (docs/CAMPAIGNS.md section 12), every minute from the scheduler, never two at once: the mail HQ
 * stored since the last run is checked for replies, bounce notices and spam complaint reports about outreach emails
 * (App\Support\Campaigns\Outreach\Inbound), and Claude is asked to sort the replies still waiting (Sorting).
 */
class OutreachReplies extends Command
{
    protected $signature = 'outreach:replies';

    protected $description = 'Files replies, bounce notices and spam complaints about outreach emails, and asks Claude to sort the replies.';

    public function handle(): int
    {
        $filed = Inbound::collect();
        $asked = Sorting::sortDue();
        if ($filed || $asked) {
            $this->info("{$filed} filed, {$asked} sent to Claude.");
        }

        return self::SUCCESS;
    }
}
