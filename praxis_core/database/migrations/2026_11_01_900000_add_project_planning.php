<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projects, one page each (docs/PROJECTS.md section 4): dates and acceptance on milestones (project_phases), a goal
 * and notes on sprints, due dates on tasks, the project lead, the people on a task, its steps, "waits on" links
 * between tasks and between milestones, and change reports (SOW clause 7.1).
 *
 * Live data is real (MyCity App ERP), so the backfills only fill empty columns: a milestone's start and due dates
 * from the sprints holding its tasks, a project's lead from its opportunity's lead, else whoever created it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_phases', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('status');
            $table->date('due_date')->nullable()->after('start_date');
            $table->date('accepted_at')->nullable()->after('due_date')->comment('The client\'s written acceptance');
            $table->string('accepted_by_name', 150)->nullable()->after('accepted_at')->comment('Who accepted, e.g. "Oyebode, CTO"');
            $table->text('notes')->nullable()->after('accepted_by_name');
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->text('goal')->nullable()->after('status');
            $table->text('notes')->nullable()->after('goal');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('estimated_hours');
            $table->date('proposed_due_date')->nullable()->after('due_date')->comment('A team member\'s proposal, for the project lead to accept');
            $table->char('proposed_due_by', 36)->nullable()->after('proposed_due_date');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->char('lead_user_id', 36)->nullable()->after('organization_id')->comment('Edits everything on the project page');
            $table->index('lead_user_id');
        });

        // The people beside a task's lead (tasks.assigned_to): sharing it, or watching it. One role per person.
        Schema::create('task_people', function (Blueprint $table) {
            $table->id();
            $table->char('task_id', 36);
            $table->char('user_id', 36);
            $table->enum('role', ['sharing', 'watcher']);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('task_steps', function (Blueprint $table) {
            $table->id();
            $table->char('task_id', 36);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('text', 500);
            $table->timestamp('done_at')->nullable();
            $table->char('done_by', 36)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->timestamps();
            $table->index(['task_id', 'position']);
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
        });

        // "Waits on": a warning while the other isn't done, never a block (decision 5). Self-links and loops are
        // refused by App\Support\Projects\Dependencies.
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->char('task_id', 36)->comment('The task that waits');
            $table->char('depends_on_task_id', 36);
            $table->char('created_by', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['task_id', 'depends_on_task_id']);
            $table->index('depends_on_task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('depends_on_task_id')->references('id')->on('tasks')->cascadeOnDelete();
        });

        Schema::create('phase_dependencies', function (Blueprint $table) {
            $table->id();
            $table->char('phase_id', 36)->comment('The milestone that waits');
            $table->char('depends_on_phase_id', 36);
            $table->char('created_by', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['phase_id', 'depends_on_phase_id']);
            $table->index('depends_on_phase_id');
            $table->foreign('phase_id')->references('id')->on('project_phases')->cascadeOnDelete();
            $table->foreign('depends_on_phase_id')->references('id')->on('project_phases')->cascadeOnDelete();
        });

        // Work that changes the price (decision 7). The milestone and tasks it adds wait in `plan` and join the project
        // only when the client approves it; then phase_id points at the milestone it created.
        Schema::create('change_reports', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('project_id', 36);
            $table->unsignedSmallInteger('number')->comment('Shown as CR-01, CR-02 … per project');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('currency', 3);
            $table->integer('timeline_days')->default(0)->comment('Days the target end date moves when approved');
            $table->enum('status', ['draft', 'sent', 'approved', 'declined'])->default('draft');
            $table->json('plan')->nullable()->comment('{milestone: {title, description, due_date}, tasks: [{title}]}');
            $table->date('decided_on')->nullable();
            $table->string('decided_by_name', 150)->nullable();
            $table->string('decided_by_title', 150)->nullable();
            $table->char('phase_id', 36)->nullable()->comment('The milestone it added, once approved');
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->char('deleted_by', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'number']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('phase_id')->references('id')->on('project_phases')->nullOnDelete();
        });

        $this->backfillMilestoneDates();
        $this->backfillProjectLeads();
    }

    /** One sprint per milestone (MyCity): its start and end are the milestone's. Only empty dates are filled. */
    private function backfillMilestoneDates(): void
    {
        $spans = DB::table('tasks')
            ->join('sprints', 'sprints.id', '=', 'tasks.sprint_id')
            ->whereNull('tasks.deleted_at')->whereNull('sprints.deleted_at')
            ->groupBy('tasks.phase_id')
            ->selectRaw('tasks.phase_id, MIN(sprints.start_date) AS first_day, MAX(sprints.end_date) AS last_day')
            ->get();

        foreach ($spans as $span) {
            DB::table('project_phases')->where('id', $span->phase_id)->whereNull('start_date')->update(['start_date' => $span->first_day]);
            DB::table('project_phases')->where('id', $span->phase_id)->whereNull('due_date')->update(['due_date' => $span->last_day]);
        }
    }

    private function backfillProjectLeads(): void
    {
        $staff = fn (?string $id) => $id && DB::table('users')->where('id', $id)->whereNull('deleted_at')->exists() ? $id : null;

        foreach (DB::table('projects')->whereNull('lead_user_id')->get(['id', 'opportunity_id', 'created_by']) as $project) {
            $fromOpportunity = DB::table('opportunities')->whereNull('deleted_at')->whereNotNull('lead_user_id')
                ->where(fn ($q) => $q->where('project_id', $project->id)->when($project->opportunity_id, fn ($q) => $q->orWhere('id', $project->opportunity_id)))
                ->orderByDesc('updated_at')->value('lead_user_id');
            $lead = $staff($fromOpportunity) ?? $staff($project->created_by);
            if ($lead) {
                DB::table('projects')->where('id', $project->id)->whereNull('lead_user_id')->update(['lead_user_id' => $lead]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('change_reports');
        Schema::dropIfExists('phase_dependencies');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_steps');
        Schema::dropIfExists('task_people');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['lead_user_id']);
            $table->dropColumn('lead_user_id');
        });
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['due_date', 'proposed_due_date', 'proposed_due_by']));
        Schema::table('sprints', fn (Blueprint $table) => $table->dropColumn(['goal', 'notes']));
        Schema::table('project_phases', fn (Blueprint $table) => $table->dropColumn(['start_date', 'due_date', 'accepted_at', 'accepted_by_name', 'notes']));
    }
};
