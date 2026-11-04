<?php

namespace App\Console\Commands;

use App\Support\CompanyDocuments\Reminders;
use Illuminate\Console\Command;

/**
 * The compliance bell of Master data › Company documents (owner's decision 3, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md):
 * on working days at 07:30 Lagos time, documents nearing their expiry and calendar deadlines nearing their due date
 * ring the owner and everyone with mdm.edit, once per item and date (App\Support\CompanyDocuments\Reminders).
 */
class ComplianceBell extends Command
{
    protected $signature = 'mdm:compliance-bell {--dry-run : List what would ring, and ring nobody}';

    protected $description = 'Ring the owner and Master data editors about expiring company documents and compliance deadlines';

    public function handle(): int
    {
        $lines = Reminders::ring((bool) $this->option('dry-run'));
        foreach ($lines as $line) {
            $this->line($line);
        }
        $this->info($this->option('dry-run')
            ? 'Dry run: nobody was rung.'
            : count($lines) . ' ' . (count($lines) === 1 ? 'item' : 'items') . ' due a bell.');

        return self::SUCCESS;
    }
}
