<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunities PR 3 (questionnaire): the AI job redrafting one question, or suggesting answers to the lead's own
 * question. Set while the job runs; cleared when its answer replaces the question. A failed job stays linked, so
 * the question shows what went wrong until the lead dismisses it or asks again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->char('ai_run_id', 36)->nullable()->after('origin')->comment('Redrafting this question (ai_runs), until applied');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table) {
            $table->dropForeign(['ai_run_id']);
            $table->dropColumn('ai_run_id');
        });
    }
};
