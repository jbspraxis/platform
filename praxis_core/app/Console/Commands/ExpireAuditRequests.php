<?php

namespace App\Console\Commands;

use App\Services\Site\BookingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hourly: a free audit request still waiting for approval (Requested) once its start time has passed is closed
 * (Cancelled; appointments have no Expired status), with a FREE_AUDIT_EXPIRED line in the audit log. The client
 * isn't emailed. Start times are Lagos wall-clock time (WAT), like every appointment (BookingService).
 */
class ExpireAuditRequests extends Command
{
    protected $signature = 'crm:expire-audit-requests';

    protected $description = 'Close free audit requests that were not approved before their start time';

    public function handle(): int
    {
        $now = Carbon::now(BookingService::TIMEZONE)->format('Y-m-d H:i:s');

        $stale = DB::table('appointments')
            ->leftJoin('organizations', 'organizations.id', '=', 'appointments.organization_id')
            ->where('appointments.status', 'Requested')
            ->where('appointments.start_time', '<', $now)
            ->whereNull('appointments.deleted_at')
            ->select('appointments.id', 'appointments.start_time', 'organizations.name as company_name')
            ->get();

        $closed = 0;
        foreach ($stale as $appt) {
            // Only if it is still waiting: someone may approve or decline it while this runs
            $updated = DB::table('appointments')->where('id', $appt->id)->where('status', 'Requested')
                ->update(['status' => 'Cancelled', 'updated_at' => now()]);
            if ($updated !== 1) {
                continue;
            }

            $company = $appt->company_name ?? 'an organisation no longer on file';
            DB::table('system_audit_logs')->insert([
                'user_id' => null,
                'action' => 'FREE_AUDIT_EXPIRED',
                'description' => "Closed the free audit request from {$company} for {$appt->start_time} WAT: no one approved it before its start time. The client wasn’t emailed.",
                'target_table' => 'appointments',
                'target_id' => $appt->id,
                'old_values' => json_encode(['status' => 'Requested']),
                'new_values' => json_encode(['status' => 'Cancelled', 'reason' => 'expired', 'client_emailed' => false]),
                'ip_address' => null,
                'created_at' => now(),
            ]);
            $closed++;
        }

        $this->line("expire-audit-requests: {$closed} request(s) closed");

        return self::SUCCESS;
    }
}
