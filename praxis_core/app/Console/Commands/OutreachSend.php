<?php

namespace App\Console\Commands;

use App\Support\Campaigns\Outreach\Sender;
use Illuminate\Console\Command;

/**
 * The outreach sender (docs/CAMPAIGNS.md section 10), every minute from the scheduler, never two at once: within the
 * window, each sender who may send now sends one approved email from their own mailbox (App\Support\Campaigns\Outreach\Sender).
 */
class OutreachSend extends Command
{
    protected $signature = 'outreach:send';

    protected $description = 'Sends the next approved outreach emails within the limits, and stops follow-ups after a reply.';

    public function handle(): int
    {
        $result = array_filter(Sender::run());
        if ($result) {
            $this->info(collect($result)->map(fn ($n, $what) => "{$n} {$what}")->implode(', ') . '.');
        }

        return self::SUCCESS;
    }
}
