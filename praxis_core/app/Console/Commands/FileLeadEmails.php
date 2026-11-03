<?php

namespace App\Console\Commands;

use App\Support\Leads\LeadEmails;
use Illuminate\Console\Command;

/**
 * Every five minutes: the mail HQ stored since the last run that is to or from a lead's contact becomes a line on that
 * lead's timeline (docs/LEADS.md section 9, LeadEmails). The first run after the release works through the mail
 * already in HQ, a batch per run.
 */
class FileLeadEmails extends Command
{
    protected $signature = 'leads:file-emails';

    protected $description = 'Put mail to and from leads’ contacts on their timelines';

    public function handle(): int
    {
        $written = LeadEmails::fileNew();
        $this->line("file-emails: {$written} line(s) written");

        return self::SUCCESS;
    }
}
