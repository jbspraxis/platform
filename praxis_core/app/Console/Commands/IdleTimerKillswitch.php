<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class IdleTimerKillswitch extends Command
{
    protected $signature = 'ops:timer-killswitch';
    protected $description = 'Ruthlessly terminates idle timers exceeding 120 minutes to prevent margin bleed.';

    public function handle()
    {
        // Find logs running longer than 120 minutes without a stop time
        $abandonedLogs = DB::table('task_time_logs')
            ->whereNull('end_time')
            ->where('start_time', '<=', now()->subMinutes(120))
            ->get();

        if ($abandonedLogs->isEmpty()) {
            $this->info('All active timers are within safe operational limits.');
            return;
        }

        foreach ($abandonedLogs as $log) {
            DB::transaction(function () use ($log) {
                // Hard-cap the duration at exactly 120 minutes from start
                $hardCutoffTime = Carbon::parse($log->start_time)->addMinutes(120);
                
                // Calculate the financial deduction for the 2 hours
                $rate = DB::table('compensation_history')
                    ->where('user_id', $log->user_id)
                    ->orderBy('effective_from', 'desc')
                    ->value('hourly_rate') ?? 50.00;
                    
                $costCalculated = (120 / 60) * $rate; // Exactly 2 hours cost

                // Execute the Killswitch
                DB::table('task_time_logs')->where('id', $log->id)->update([
                    'end_time' => $hardCutoffTime,
                    'duration_minutes' => 120,
                    'cost_calculated' => $costCalculated
                ]);

                // Update total actuals on the parent task
                DB::table('tasks')->where('id', $log->task_id)->update([
                    'actual_hours_logged' => DB::raw("actual_hours_logged + 2.00")
                ]);

                // Send a severe warning notification to the engineer
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $log->user_id,
                    'title' => 'TIMER GUILLOTINE EXECUTED',
                    'message' => 'Your active timer exceeded 120 idle minutes and was forcefully terminated. Unverified time must be requested via Level 4 Time Correction.',
                    'type' => 'System_Alert',
                    'created_at' => now()
                ]);
            });

            $this->info("Timer ID {$log->id} terminated at 120 minutes.");
        }
    }
}