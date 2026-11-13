<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a milestone needs from the client (owner, 13 Nov 2026: "build a section for dependencies of a milestone"),
 * e.g. Fidson M1 needs from Tales the org chart, the Azure AD app registration, the environments. Each item has a
 * group ("1.A Organisation and people data"), a needed-by date, and a status the client moves to Sent in the portal
 * and HQ to Received. An item that arrives late extends the milestone day for day (SOW clause 8.2).
 * Not phase_dependencies, which says one milestone waits on another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestone_dependencies', function (Blueprint $table) {
            $table->id();
            $table->char('project_id', 36);
            $table->char('phase_id', 36);
            $table->string('group_code', 12)->nullable()->comment('e.g. 1.A');
            $table->string('group_title', 150)->nullable()->comment('e.g. Organisation and people data');
            $table->text('title')->comment('What the client provides');
            $table->unsignedSmallInteger('position')->default(0);
            $table->date('due_date')->nullable()->comment('Needed by: the milestone start, or 7 business days before it for an integration (clause 8.3)');
            $table->enum('status', ['needed', 'requested', 'sent', 'received', 'waived'])->default('needed');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('sent_at')->nullable()->comment('The client marked it sent in the portal');
            $table->char('sent_by_client_user_id', 36)->nullable();
            $table->text('client_note')->nullable();
            $table->string('file_path', 255)->nullable()->comment('dependency_files/<project id>/…, sent by the client');
            $table->string('file_name', 200)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->char('received_by', 36)->nullable();
            $table->text('note')->nullable()->comment('HQ note');
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['phase_id', 'position']);
            $table->index(['project_id', 'status']);
            $table->foreign('phase_id')->references('id')->on('project_phases')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_dependencies');
    }
};
