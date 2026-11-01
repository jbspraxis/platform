<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunities PR 6 (milestone handovers, docs/OPPORTUNITIES.md section 12): a client meeting can be a milestone's
 * handover (meetings.phase_id: project_phases are the milestones), and the points raised in it wait as drafts
 * (meeting_points) until the lead confirms each one, which creates a QA ticket on the project (qa_ticket_id).
 * Nothing on qa_tickets or tasks changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->char('phase_id', 36)->nullable()->after('opportunity_id')->comment('The milestone (project_phases) this meeting hands over to the client');
            $table->index('phase_id');
            $table->foreign('phase_id')->references('id')->on('project_phases')->nullOnDelete();
        });

        Schema::create('meeting_points', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('meeting_id', 36);
            $table->char('transcript_id', 36)->nullable()->comment('The transcript the AI read');
            $table->char('ai_run_id', 36)->nullable()->comment('The handover_points run that drafted it');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title', 200);
            $table->text('detail')->nullable()->comment('What was asked or reported');
            $table->string('raised_by', 150)->nullable()->comment('Who raised it, as the transcript names them');
            $table->enum('severity', ['Low', 'Medium', 'Critical'])->default('Medium');
            $table->char('task_id', 36)->nullable()->comment('The milestone task it concerns, when known');
            $table->enum('status', ['draft', 'confirmed', 'dismissed'])->default('draft');
            $table->enum('origin', ['ai', 'lead'])->default('ai');
            $table->char('qa_ticket_id', 36)->nullable()->comment('Created when the lead confirmed it');
            $table->char('decided_by', 36)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->char('deleted_by', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['meeting_id', 'status', 'position']);
            $table->index('qa_ticket_id');
            $table->foreign('meeting_id')->references('id')->on('meetings')->cascadeOnDelete();
            $table->foreign('transcript_id')->references('id')->on('meeting_transcripts')->nullOnDelete();
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('qa_ticket_id')->references('id')->on('qa_tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_points');
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
            $table->dropIndex(['phase_id']);
            $table->dropColumn('phase_id');
        });
    }
};
