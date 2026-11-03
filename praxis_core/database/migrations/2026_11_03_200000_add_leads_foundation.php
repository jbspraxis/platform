<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leads PR 1 (docs/LEADS.md section 4): what a lead needs beyond the organisation row it already is.
 *
 *   organizations   lead_status (new, contacted, qualified, client, lost: independent of opportunities), why it was
 *                   lost, since when and how it became a client, where it came from, its next step, its own phone and
 *                   email, and when anything last happened (lead_timeline)
 *   client_users    a contact's deal role and the lead's main contact; email may be empty (a contact known by phone)
 *   meetings        opportunity_id may be empty: a meeting can belong to a lead alone (PR 2)
 *   lead_timeline   one row per thing that happened to a lead (LeadTimeline::record)
 *
 * The default owner of website leads is a setting (integration_settings key leads.default_owner_id), not a column.
 * The backfill of existing rows is the next migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'lead_status')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->enum('lead_status', ['new', 'contacted', 'qualified', 'client', 'lost'])->nullable()->default('new')->after('pipeline_stage')
                    ->comment('Leads (docs/LEADS.md): independent of opportunities; client goes with status Active Client');
                $table->string('lost_reason', 30)->nullable()->after('lead_status')->comment('LeadStatus::LOST_REASONS key');
                $table->text('lost_note')->nullable()->after('lost_reason');
                $table->timestamp('lost_at')->nullable()->after('lost_note');
                $table->timestamp('client_since')->nullable()->after('lost_at');
                $table->enum('client_via', ['signed', 'manual', 'existing'])->nullable()->after('client_since')
                    ->comment('signed: first signed proposal or SOW; manual: marked by hand; existing: a client before 3 Nov 2026');
                $table->string('client_reference', 60)->nullable()->after('client_via')->comment('The proposal or SOW reference that made it a client');
                $table->string('source', 30)->nullable()->after('client_reference')->comment('Leads\Sources key: website_enquiry, website_booking, cost_report, manual, import, referral…');
                $table->string('source_detail', 255)->nullable()->after('source');
                $table->string('next_step', 255)->nullable()->after('source_detail');
                $table->date('next_step_due')->nullable()->after('next_step');
                $table->string('phone', 30)->nullable()->after('city');
                $table->string('email', 150)->nullable()->after('phone');
                $table->timestamp('last_activity_at')->nullable()->after('next_step_due');
                $table->index(['lead_status', 'assigned_to'], 'organizations_lead_status_owner');
                $table->index('next_step_due', 'organizations_next_step_due');
            });
        }

        if (! Schema::hasColumn('client_users', 'deal_role')) {
            Schema::table('client_users', function (Blueprint $table) {
                $table->enum('deal_role', ['decision_maker', 'finance', 'technical', 'user'])->nullable()->after('job_title');
                $table->boolean('is_main_contact')->default(false)->after('deal_role')->comment('The lead’s main contact (not the portal’s Client Admin: is_primary_client)');
            });
        }
        // A contact may be known by phone only; portal accounts always have an email (unique stays: MySQL allows many nulls)
        Schema::table('client_users', fn (Blueprint $table) => $table->string('email', 100)->nullable()->change());

        // A meeting may belong to a lead alone (PR 2); opportunity meetings keep their opportunity
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['opportunity_id']);
        });
        Schema::table('meetings', function (Blueprint $table) {
            $table->char('opportunity_id', 36)->nullable()->change();
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
        });
        if (! Schema::hasIndex('meetings', 'meetings_organization_starts')) {
            Schema::table('meetings', fn (Blueprint $table) => $table->index(['organization_id', 'starts_at'], 'meetings_organization_starts'));
        }

        if (! Schema::hasTable('lead_timeline')) {
            Schema::create('lead_timeline', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('organization_id', 36);
                $table->string('type', 40)->comment('LeadTimeline::TYPES key');
                $table->enum('actor_type', ['staff', 'client', 'system'])->default('staff');
                $table->char('actor_id', 36)->nullable()->comment('users.id (staff) or client_users.id (client)');
                $table->string('summary', 255);
                $table->text('body')->nullable()->comment('What was said or typed: a note, a call, a website message');
                $table->json('payload')->nullable();
                $table->char('client_user_id', 36)->nullable()->comment('The contact it concerns');
                $table->char('opportunity_id', 36)->nullable();
                $table->char('meeting_id', 36)->nullable();
                $table->string('related_type', 40)->nullable()->comment('Anything else it points at: crm_note, proposal, statement_of_work, mail_message…');
                $table->char('related_id', 36)->nullable();
                $table->timestamp('occurred_at', 6)->useCurrent()->comment('UTC, to the microsecond so rows keep their order');
                $table->string('dedupe_key', 100)->nullable()->unique()->comment('Set by backfills and imports, so running them again adds nothing');
                $table->char('deleted_by', 36)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['organization_id', 'occurred_at']);
                $table->index(['type', 'occurred_at']);
                $table->index('actor_id');
                $table->index('client_user_id');
                $table->index('opportunity_id');
                $table->index('meeting_id');
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('client_user_id')->references('id')->on('client_users')->nullOnDelete();
                $table->foreign('opportunity_id')->references('id')->on('opportunities')->nullOnDelete();
                $table->foreign('meeting_id')->references('id')->on('meetings')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_timeline');

        // Lead meetings (no opportunity) can't survive a NOT NULL column: they go with it
        DB::table('meetings')->whereNull('opportunity_id')->delete();
        // meetings_organization_starts stays: MySQL now uses it for the organization_id foreign key, so it can't be dropped
        Schema::table('meetings', fn (Blueprint $table) => $table->dropForeign(['opportunity_id']));
        Schema::table('meetings', function (Blueprint $table) {
            $table->char('opportunity_id', 36)->nullable(false)->change();
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
        });

        if (Schema::hasColumn('client_users', 'deal_role')) {
            Schema::table('client_users', fn (Blueprint $table) => $table->dropColumn(['deal_role', 'is_main_contact']));
        }

        if (Schema::hasColumn('organizations', 'lead_status')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropIndex('organizations_lead_status_owner');
                $table->dropIndex('organizations_next_step_due');
                $table->dropColumn(['lead_status', 'lost_reason', 'lost_note', 'lost_at', 'client_since', 'client_via', 'client_reference',
                    'source', 'source_detail', 'next_step', 'next_step_due', 'phone', 'email', 'last_activity_at']);
            });
        }
    }
};
