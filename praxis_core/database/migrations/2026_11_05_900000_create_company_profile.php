<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HQ › Company profile (docs/COMPANY_PROFILE.md): the firm's own profile, written once in English and French
 * and kept under version control so nothing goes out that an approver has not read.
 *
 *   company_profile_versions   one row per version: draft -> in review -> published, with its reference
 *   company_profile_sections   one row per (version, section): the structure once, the words per locale
 *
 * Why the split in company_profile_sections: a credential that is held, a fact that is enabled and the order
 * of a list are facts about the firm, not about a language. Holding them once in `structure` makes it
 * impossible for English and French to disagree about which credentials are real. `text_en` and `text_fr`
 * hold only the words, keyed by the ids in `structure`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_versions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->unsignedInteger('number')->unique()->comment('1, 2, 3 ...; shown as v1, v2, v3');
            $table->string('reference', 40)->unique()->comment('JBS-CP-2026-11-v3: the year, the month and the version');
            $table->enum('status', ['draft', 'in_review', 'published', 'superseded'])->default('draft')
                ->comment('Exactly one row is `published`; the one it replaced becomes `superseded`');
            $table->char('created_by', 36)->nullable();
            $table->char('submitted_by', 36)->nullable()->comment('Who sent it for review');
            $table->timestamp('submitted_at')->nullable();
            $table->char('reviewed_by', 36)->nullable()->comment('Who approved and published it');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->string('note', 500)->nullable()->comment('What changed in this version, in the author’s words');
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('company_profile_sections', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('version_id', 36);
            $table->string('key', 32)->comment('cover, pov, system, problems, practices, method, teams, work, clients, security, facts, contact');
            $table->json('structure')->comment('Locale-invariant: list item ids in order, their held/enabled switches and their non-text values');
            $table->json('text_en');
            $table->json('text_fr');
            $table->timestamps();
            $table->unique(['version_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile_sections');
        Schema::dropIfExists('company_profile_versions');
    }
};
