<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intel reports on a lead (docs/LEADS.md section 13): the research the sales team gathers about a company before
 * it is a client (the tools it uses and what they cost, who decides, what hurts), kept with the lead so people and
 * the AI draw on the same facts.
 *
 *   lead_intel_reports  one report: the original (a file on the private disk, or the text pasted in), the layout
 *                       Claude put it into (`structured`, which a person may then edit), and whether the AI may
 *                       read it. Several per lead; each is kept as its own dated report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_intel_reports', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->string('title', 200);
            $table->string('input_kind', 10)->comment('file or text');
            $table->string('original_name', 255)->nullable();
            $table->string('path', 500)->nullable()->comment('On the private disk: lead-intel/<lead>/<id>.<ext>');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->longText('raw_text')->nullable()->comment('What was pasted or typed in');
            $table->longText('structured')->nullable()->comment('JSON in the layout of App\\Support\\Leads\\LeadIntel::normalise: as Claude read it, then as a person edited it');
            $table->timestamp('structured_at')->nullable();
            $table->char('ai_run_id', 36)->nullable()->comment('The latest reading (ai_runs, purpose lead_intel_read)');
            $table->string('read_error', 255)->nullable()->comment('Why the last reading could not start: a lang key or a sentence');
            $table->boolean('use_for_ai')->default(true)->comment('Whether proposals, the consultation line and the pitch brief may read it');
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_intel_reports');
    }
};
