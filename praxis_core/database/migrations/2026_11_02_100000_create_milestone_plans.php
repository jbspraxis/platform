<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 5b, the planner (docs/PROJECTS.md section 9): Plan milestones runs on the AI service. A milestone_plans row is one
 * request (one milestone of a running project, or the whole plan of a project with no milestones and no project draft),
 * with the scope it read, its sources, its job and pass (a second pass reads one meeting's transcript when the AI asks),
 * and a revision so a page that is behind can't save it. plan_suggestions are what the AI proposed for a milestone: a
 * new task, an edit to an existing task (only the fields the AI changes, and the task as it read it, so an edit to a
 * task changed since is refused), or a note (a task that looks duplicated or outside the SOW); the lead ticks the ones
 * to keep, and nothing is ever removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestone_plans', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('project_id', 36);
            $table->char('phase_id', 36)->nullable()->comment('The milestone planned; null: the whole plan of a project with none');
            $table->longText('scope')->nullable()->comment('The scope box as sent');
            $table->json('sources')->nullable();
            $table->enum('status', ['drafting', 'ready', 'saved', 'failed'])->default('drafting');
            $table->char('ai_run_id', 36)->nullable();
            $table->unsignedTinyInteger('pass')->default(1);
            $table->char('transcript_meeting_id', 36)->nullable()->comment('The one meeting whose transcript the second pass read');
            $table->string('transcript_reason', 500)->nullable();
            $table->json('output')->nullable()->comment('Whole plan: [{title, goal, tasks: [{title, detail, estimate_hours}]}]');
            $table->unsignedInteger('revision')->default(0);
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->char('deleted_by', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('phase_id')->references('id')->on('project_phases')->nullOnDelete();
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
            $table->foreign('transcript_meeting_id')->references('id')->on('meetings')->nullOnDelete();
        });

        Schema::create('plan_suggestions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('plan_id', 36);
            $table->char('phase_id', 36)->nullable();
            $table->enum('kind', ['new', 'edit', 'note']);
            $table->char('task_id', 36)->nullable();
            $table->string('title', 255)->nullable()->comment('An edit: null when the AI leaves the title as it is');
            $table->text('detail')->nullable()->comment('An edit: null when the AI leaves the detail as it is');
            $table->decimal('estimate_hours', 6, 1)->nullable()->comment('An edit: null when the AI leaves the estimate as it is');
            $table->json('base')->nullable()->comment('An edit: the task as the AI read it {title, description, estimated_hours, phase_id}; saving refuses the edit if it changed');
            $table->text('why')->nullable()->comment('The AI’s reason for an edit, or a note’s text');
            $table->unsignedSmallInteger('position')->default(0);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'refused'])->default('pending')->comment('refused: ticked, but its task changed after the AI read it');
            $table->char('decided_by', 36)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['plan_id', 'phase_id']);
            $table->foreign('plan_id')->references('id')->on('milestone_plans')->cascadeOnDelete();
            $table->foreign('phase_id')->references('id')->on('project_phases')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_suggestions');
        Schema::dropIfExists('milestone_plans');
    }
};
