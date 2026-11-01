<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MyCity's Change Report 01 (docs/PROJECTS.md section 4): the Mailbox, ₦250,000, no effect on the timeline, declined
 * by MyCity on 1 November 2026. Added as Declined only where the MyCity App ERP project exists (live) and has no
 * change report 01 yet; any other database is left alone.
 */
return new class extends Migration
{
    private const PROJECT = 'MyCity App ERP';

    public function up(): void
    {
        $project = DB::table('projects')->where('title', self::PROJECT)->whereNull('deleted_at')->orderBy('created_at')->first(['id', 'currency', 'lead_user_id']);
        if (! $project || DB::table('change_reports')->where('project_id', $project->id)->where('number', 1)->exists()) {
            return;
        }

        DB::table('change_reports')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'number' => 1,
            'title' => 'Mailbox and communications hub',
            'description' => 'A full staff mailbox inside the platform (CR/JBSP/MCA-ERP/2026-01, 22 October 2026): each person\'s own mail, '
                . 'company signatures, AI drafting and summaries, shared inboxes on request. Built alongside Milestone 1 and shown on 21 October.',
            'price' => 250000,
            'currency' => $project->currency ?: 'NGN',
            'timeline_days' => 0,
            'status' => 'declined',
            'decided_on' => '2026-11-01',
            'created_by' => $project->lead_user_id,
            'updated_by' => $project->lead_user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $projectId = DB::table('projects')->where('title', self::PROJECT)->whereNull('deleted_at')->orderBy('created_at')->value('id');
        if ($projectId) {
            DB::table('change_reports')->where('project_id', $projectId)->where('number', 1)->where('status', 'declined')->delete();
        }
    }
};
