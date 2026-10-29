<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QAAutoApprove extends Command
{
    protected $signature = 'ops:qa-auto-approve';
    protected $description = 'Enforces the 5-Hour QA SLA by auto-approving stalled client tickets.';

    public function handle()
    {
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

                // Notify Level 4/5 Command of the auto-approval
                $admins = DB::table('users')
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->where('roles.clearance_level', '>=', 4)
                    ->select('users.id')->get();

                $notifications = [];
                foreach ($admins as $admin) {
                    $notifications[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $admin->id,
                        'title' => 'SLA AUTO-APPROVAL',
                        'message' => "Client failed to respond within 5 hours. Task '{$task->title}' force-moved to Done.",
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