<?php

namespace App\Console\Commands;

use App\Support\Access;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QAAutoApprove extends Command
{
    protected $signature = 'ops:qa-auto-approve';
    protected $description = 'Reminds the client the day before, then auto-approves tasks left in client review for 2 working days.';

    public function handle()
    {
        // The day before: one reminder to the client's portal
        $due = DB::table('tasks')->where('status', 'QA_Review')->whereNull('deleted_at')->whereNull('qa_reminded_at')
            ->whereNotNull('qa_sla_expires_at')->whereBetween('qa_sla_expires_at', [now(), now()->addDay()])->get();
        foreach ($due as $task) {
            if (DB::table('tasks')->where('id', $task->id)->whereNull('qa_reminded_at')->update(['qa_reminded_at' => now()])) {
                \App\Support\Pwa\ClientNotify::reviewReminder($task);
            }
        }

        $expiredTasks = DB::table('tasks')
            ->where('status', 'QA_Review')
            ->whereNotNull('qa_sla_expires_at')
            ->where('qa_sla_expires_at', '<', now())
            ->where('qa_auto_approved', 0)
            ->get();

        if ($expiredTasks->isEmpty()) {
            $this->info('No expired QA tasks found.');
            return;
        }

        foreach ($expiredTasks as $task) {
            DB::transaction(function () use ($task) {
                // Force task to Done and flag as auto-approved
                DB::table('tasks')->where('id', $task->id)->update([
                    'status' => 'Done',
                    'qa_auto_approved' => 1,
                    'updated_at' => now()
                ]);

                // Notify the QA approvers of the auto-approval
                $notifications = [];
                foreach (Access::staffWith('qa.approve') as $adminId) {
                    $notifications[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $adminId,
                        'title' => 'Task approved automatically',
                        'message' => "The client didn’t review ‘{$task->title}’ within 2 working days, so it was approved automatically and moved to Done.",
                        'type' => 'Project',
                        'created_at' => now()
                    ];
                }

                if (!empty($notifications)) {
                    DB::table('notifications')->insert($notifications);
                }
            });

            $this->info("Task ID {$task->id} auto-approved.");
        }
    }
}