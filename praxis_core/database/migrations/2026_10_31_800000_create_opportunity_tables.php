<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunities (docs/OPPORTUNITIES.md section 7): every table PRs 1–5 use, created at once so the later PRs
 * only add behaviour. One opportunity per piece of work with a client holds its meetings, transcripts,
 * questionnaire, proposal versions, SOW and project draft; AI jobs are logged in ai_runs (cost, progress) and
 * the Google and Claude secrets live in integration_settings (encrypted).
 *
 * Times: meetings.starts_at and ends_at are UTC (like every timestamp Laravel writes here); time_zone says how to
 * show them. appointments.start_time keeps the Lagos wall-clock time the website shows.
 */
return new class extends Migration
{
    private function audit(Blueprint $table, bool $softDeletes = true): void
    {
        $table->char('created_by', 36)->nullable();
        $table->char('updated_by', 36)->nullable();
        if ($softDeletes) {
            $table->char('deleted_by', 36)->nullable();
        }
        $table->timestamps();
        if ($softDeletes) {
            $table->softDeletes();
        }
    }

    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('purpose', 40)->comment('config/ai.php purposes: meeting_notes, proposal, ...');
            $table->string('provider', 20)->comment('claude | gemini');
            $table->string('model', 64);
            $table->string('subject_type', 60)->nullable()->comment('Morph alias or table of what the run is about');
            $table->char('subject_id', 36)->nullable();
            $table->enum('status', ['queued', 'running', 'done', 'failed'])->default('queued');
            $table->unsignedTinyInteger('progress')->default(0)->comment('0–100, shown while the job runs');
            $table->json('input')->nullable()->comment('What Ai::queue() was given: context, instructions, language');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->string('stop_reason', 30)->nullable()->comment('end_turn, max_tokens, refusal, budget, ...');
            $table->json('output')->nullable()->comment("Validated against the purpose's schema");
            $table->text('error')->nullable()->comment('A message fit to show the person who asked');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->char('requested_by', 36)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'created_at']);
            $table->index(['created_at', 'purpose']);
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('integration_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary()->comment('google.client_id, anthropic.api_key, ai.monthly_budget_usd, ...');
            $table->text('value')->nullable()->comment('Secrets encrypted with Crypt (App\Support\Integrations)');
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->string('title', 200);
            $table->enum('stage', ['discovery', 'questionnaire', 'proposal', 'proposal_sent', 'proposal_approved', 'sow', 'sow_sent', 'won', 'lost', 'on_hold'])->default('discovery');
            $table->char('lead_user_id', 36)->nullable()->comment('Staff member who runs it; becomes the project lead');
            $table->enum('language', ['en', 'fr'])->default('en')->comment('Language of the AI documents and client emails');
            $table->enum('source', ['website_booking', 'manual', 'backfill'])->default('manual');
            $table->char('project_id', 36)->nullable()->comment('Set when the project is created (won)');
            $table->text('lost_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $this->audit($table);
            $table->index('organization_id');
            $table->index(['stage', 'updated_at']);
            $table->index('lead_user_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('opportunity_id', 36);
            $table->char('organization_id', 36);
            $table->enum('kind', ['client', 'internal'])->default('client');
            $table->string('title', 200);
            $table->text('agenda')->nullable();
            $table->dateTime('starts_at')->comment('UTC');
            $table->dateTime('ends_at')->comment('UTC');
            $table->string('time_zone', 64)->default('Africa/Lagos');
            $table->enum('status', ['requested', 'scheduled', 'held', 'cancelled'])->default('scheduled');
            $table->char('appointment_id', 36)->nullable()->comment('The website booking it came from');
            $table->string('google_event_id', 255)->nullable();
            $table->string('meet_link', 255)->nullable();
            $table->string('meet_code', 32)->nullable();
            $table->enum('transcript_status', ['waiting', 'collecting', 'ready', 'not_found', 'manual'])->default('waiting');
            $table->dateTime('transcript_next_try_at')->nullable()->comment('UTC');
            $table->unsignedSmallInteger('transcript_tries')->default(0);
            $this->audit($table);
            $table->index(['opportunity_id', 'starts_at']);
            $table->index(['transcript_status', 'transcript_next_try_at']);
            $table->index('appointment_id');
            $table->index('google_event_id');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('meeting_id', 36);
            $table->char('user_id', 36)->nullable()->comment('Staff');
            $table->char('client_user_id', 36)->nullable()->comment('Client contact');
            $table->string('email', 150);
            $table->string('name', 150)->nullable();
            $table->enum('role', ['staff', 'client', 'guest']);
            $table->string('response', 20)->nullable()->comment("Google's responseStatus: needsAction, accepted, declined, tentative");
            $table->timestamps();
            $table->unique(['meeting_id', 'email']);
            $table->foreign('meeting_id')->references('id')->on('meetings')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('client_user_id')->references('id')->on('client_users')->nullOnDelete();
        });

        Schema::create('meeting_transcripts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('meeting_id', 36);
            $table->enum('source', ['meet_api', 'calendar_doc', 'upload', 'paste']);
            $table->string('google_doc_id', 128)->nullable();
            $table->string('language', 10)->nullable();
            $table->longText('text');
            $table->json('entries')->nullable()->comment('Lines: speaker, time, text');
            $table->json('notes')->nullable()->comment('AI internal notes (meeting_notes)');
            $table->text('minutes')->nullable()->comment('Client minutes, as edited by the lead');
            $table->timestamp('minutes_sent_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $this->audit($table);
            $table->index('meeting_id');
            $table->foreign('meeting_id')->references('id')->on('meetings')->cascadeOnDelete();
        });

        Schema::create('questionnaires', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('opportunity_id', 36);
            $table->unsignedTinyInteger('question_count');
            $table->text('focus')->nullable();
            $table->enum('status', ['drafting', 'open', 'complete'])->default('drafting');
            $table->char('ai_run_id', 36)->nullable();
            $table->char('completed_by', 36)->nullable();
            $table->timestamp('completed_at')->nullable();
            $this->audit($table);
            $table->index('opportunity_id');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('questionnaire_questions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('questionnaire_id', 36);
            $table->unsignedSmallInteger('position');
            $table->text('question');
            $table->text('why_it_matters')->nullable();
            $table->json('options')->comment('At least 3 suggested answers');
            $table->unsignedTinyInteger('recommended_option')->nullable()->comment('Index into options');
            $table->text('recommendation_reason')->nullable();
            $table->unsignedTinyInteger('answer_option')->nullable()->comment('Index into options, or null for a free answer');
            $table->text('answer_text')->nullable();
            $table->char('answered_by', 36)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->enum('origin', ['ai', 'lead'])->default('ai');
            $this->audit($table, softDeletes: false);
            $table->index(['questionnaire_id', 'position']);
            $table->foreign('questionnaire_id')->references('id')->on('questionnaires')->cascadeOnDelete();
            $table->foreign('answered_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->char('opportunity_id', 36)->nullable()->after('organization_id');
            $table->unsignedSmallInteger('version')->default(1)->after('proposal_ref');
            $table->char('previous_version_id', 36)->nullable()->after('version');
            $table->json('sections')->nullable()->after('exclusions')->comment('Proposal v2 (PR 4): key, title, body');
            $table->enum('signing_mode', ['legacy', 'approve'])->default('legacy')->after('status')
                ->comment('legacy: signed with the one-time code as before PR 4; approve: the client approves, the SOW is signed');
            $table->char('approved_by', 36)->nullable()->after('signing_mode')->comment('Owner or lead who approved sending it');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('sent_at')->nullable()->after('approved_at');
            $table->string('client_approved_name', 150)->nullable()->after('sent_at');
            $table->string('client_approved_title', 150)->nullable()->after('client_approved_name');
            $table->string('client_approved_email', 150)->nullable()->after('client_approved_title');
            $table->timestamp('client_approved_at')->nullable()->after('client_approved_email');
            $table->text('changes_summary')->nullable()->after('client_approved_at')->comment('What changed from the previous version');
            $table->index('opportunity_id');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->nullOnDelete();
            $table->foreign('previous_version_id')->references('id')->on('proposals')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('proposal_comments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('proposal_id', 36);
            $table->string('section_key', 64);
            $table->enum('author_type', ['client', 'staff']);
            $table->string('author_name', 150);
            $table->string('author_email', 150)->nullable();
            $table->text('body');
            $table->char('resolved_by', 36)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $this->audit($table);
            $table->index(['proposal_id', 'section_key']);
            $table->foreign('proposal_id')->references('id')->on('proposals')->cascadeOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('statements_of_work', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('opportunity_id', 36);
            $table->char('proposal_id', 36)->nullable()->comment('The approved proposal it was drafted from');
            $table->string('reference', 50)->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->char('previous_version_id', 36)->nullable();
            $table->json('sections')->nullable();
            $table->enum('status', ['draft', 'awaiting_approval', 'sent', 'changes_requested', 'signed', 'superseded'])->default('draft');
            $table->char('approved_by', 36)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            // Signing, as on proposals: a token link, the one-time code, then both signatures
            $table->string('token_hash', 100)->nullable();
            $table->string('auth_otp', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('hq_signer_name', 150)->nullable();
            $table->longText('hq_signature_b64')->nullable();
            $table->string('hq_signature_hash', 100)->nullable();
            $table->string('hq_ip_address', 45)->nullable();
            $table->timestamp('hq_signed_at')->nullable();
            $table->string('signed_by_name', 150)->nullable();
            $table->longText('client_signature_b64')->nullable();
            $table->string('client_signature_hash', 100)->nullable();
            $table->string('client_ip_address', 45)->nullable();
            $table->string('ip_security_flag', 255)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->char('deposit_invoice_id', 36)->nullable();
            $this->audit($table);
            $table->index(['opportunity_id', 'version']);
            $table->index('token_hash');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
            $table->foreign('proposal_id')->references('id')->on('proposals')->nullOnDelete();
            $table->foreign('previous_version_id')->references('id')->on('statements_of_work')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deposit_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('project_drafts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('opportunity_id', 36);
            $table->char('sow_id', 36)->nullable();
            $table->json('payload')->nullable()->comment('project, phases, milestones, sprints, tasks, roles');
            $table->enum('status', ['drafting', 'ready', 'created'])->default('drafting');
            $table->char('ai_run_id', 36)->nullable();
            $table->char('created_project_id', 36)->nullable();
            $this->audit($table);
            $table->index('opportunity_id');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
            $table->foreign('sow_id')->references('id')->on('statements_of_work')->nullOnDelete();
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->nullOnDelete();
            $table->foreign('created_project_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->char('opportunity_id', 36)->nullable()->after('organization_id');
            $table->index('opportunity_id');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['opportunity_id']);
            $table->dropColumn('opportunity_id');
        });
        Schema::dropIfExists('project_drafts');
        Schema::dropIfExists('statements_of_work');
        Schema::dropIfExists('proposal_comments');
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropForeign(['opportunity_id']);
            $table->dropForeign(['previous_version_id']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['opportunity_id']);
            $table->dropColumn(['opportunity_id', 'version', 'previous_version_id', 'sections', 'signing_mode', 'approved_by', 'approved_at',
                'sent_at', 'client_approved_name', 'client_approved_title', 'client_approved_email', 'client_approved_at', 'changes_summary']);
        });
        Schema::dropIfExists('questionnaire_questions');
        Schema::dropIfExists('questionnaires');
        Schema::dropIfExists('meeting_transcripts');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('integration_settings');
        Schema::dropIfExists('ai_runs');
    }
};
