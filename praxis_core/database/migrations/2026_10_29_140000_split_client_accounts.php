<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff and clients get separate accounts.
 *
 *   users          staff only (hq.jbspraxis.com, guard 'web')
 *   client_users   everyone outside JBS Praxis: website leads (no password) and client
 *                  portal accounts (invited, password set) (portal.jbspraxis.com, guard 'client')
 *
 * Columns that either side can write keep their id and get a type next to it:
 * qa_tickets.submitted_by_type, qa_comments.author_type, qa_attachments.uploader_type,
 * task_comments.author_type, crm_notes.author_type ('staff' | 'client').
 * Payment proofs record the client in transactions.client_user_id; campaign recipients
 * (broadcast_logs.user_id) and Kaizen contributors are client users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36)->primary();
            $table->char('organization_id', 36);
            $table->char('restricted_project_id', 36)->nullable()->comment('Portal access limited to this project');
            $table->boolean('is_primary_client')->default(false)->comment('The client admin: invites colleagues');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email', 100)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('password')->nullable()->comment('Null for leads and until an invitation is accepted');
            $table->string('invitation_token', 100)->nullable()->index();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_subscribed')->default(true);
            $table->enum('status', ['Active', 'Suspended'])->default('Active');
            $table->string('lifecycle_stage', 50)->default('Lead')->comment('Lead, Onboarding, Active, Public Contributor');
            $table->string('source', 50)->nullable()->comment('How they reached us: contact_form, booking, cost_report, kaizen, sos, proposal, invite, hq');
            $table->timestamp('last_login_at')->nullable();
            $table->char('created_by', 36)->nullable()->comment('Staff member who added them, if any');
            $table->timestamps();
            $table->softDeletes();
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('restricted_project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // Written by staff or clients: keep the id, add who it is
        $mixed = [
            ['qa_tickets', 'submitted_by', 'qa_tickets_ibfk_3', 'submitted_by_type', 'client'],
            ['qa_comments', 'user_id', 'qa_comments_ibfk_2', 'author_type', 'staff'],
            ['qa_attachments', 'uploaded_by', 'qa_attachments_ibfk_2', 'uploader_type', 'staff'],
            ['task_comments', 'user_id', 'task_comments_ibfk_2', 'author_type', 'staff'],
            ['crm_notes', 'author_id', 'crm_notes_ibfk_2', 'author_type', 'staff'],
        ];
        foreach ($mixed as [$tableName, $column, $fk, $typeColumn, $default]) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $fk, $typeColumn, $default) {
                $table->dropForeign($fk);
                $table->enum($typeColumn, ['staff', 'client'])->default($default)->after($column);
                $table->index([$column, $typeColumn]);
            });
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->char('client_user_id', 36)->nullable()->after('created_by')->comment('Client who uploaded the payment proof');
            $table->foreign('client_user_id')->references('id')->on('client_users')->nullOnDelete();
        });

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropForeign('broadcast_logs_ibfk_2');
            $table->foreign('user_id', 'broadcast_logs_client_user_fk')->references('id')->on('client_users')->cascadeOnDelete();
        });

        // The client dashboard's urgent-help button always wrote user_id, which never existed
        Schema::table('redline_sos_requests', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->after('organization_id')->comment('Client who raised it (client_users)');
            $table->foreign('user_id', 'redline_sos_client_user_fk')->references('id')->on('client_users')->nullOnDelete();
        });

        Schema::table('kaizen_articles', function (Blueprint $table) {
            $table->char('contributor_id', 36)->nullable()->after('created_by')->comment('Public contributor (client_users); created_by stays the staff/editorial author');
            $table->foreign('contributor_id')->references('id')->on('client_users')->nullOnDelete();
        });

        // Staff accounts no longer carry client-only fields
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_user_restr_proj');
            $table->dropColumn(['restricted_project_id', 'is_primary_client', 'is_subscribed']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('restricted_project_id', 36)->nullable()->after('organization_id');
            $table->boolean('is_primary_client')->default(false)->after('role_id');
            $table->boolean('is_subscribed')->default(true)->after('email');
            $table->foreign('restricted_project_id', 'fk_user_restr_proj')->references('id')->on('projects')->nullOnDelete();
        });
        Schema::table('kaizen_articles', fn (Blueprint $t) => [$t->dropForeign(['contributor_id']), $t->dropColumn('contributor_id')]);
        Schema::table('redline_sos_requests', fn (Blueprint $t) => [$t->dropForeign('redline_sos_client_user_fk'), $t->dropColumn('user_id')]);
        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropForeign('broadcast_logs_client_user_fk');
            $table->foreign('user_id', 'broadcast_logs_ibfk_2')->references('id')->on('users');
        });
        Schema::table('transactions', fn (Blueprint $t) => [$t->dropForeign(['client_user_id']), $t->dropColumn('client_user_id')]);
        foreach ([['qa_tickets', 'submitted_by', 'qa_tickets_ibfk_3', 'submitted_by_type'], ['qa_comments', 'user_id', 'qa_comments_ibfk_2', 'author_type'],
                  ['qa_attachments', 'uploaded_by', 'qa_attachments_ibfk_2', 'uploader_type'], ['task_comments', 'user_id', 'task_comments_ibfk_2', 'author_type'],
                  ['crm_notes', 'author_id', 'crm_notes_ibfk_2', 'author_type']] as [$tableName, $column, $fk, $typeColumn]) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $fk, $typeColumn) {
                $table->dropIndex([$column, $typeColumn]);
                $table->dropColumn($typeColumn);
                $table->foreign($column, $fk)->references('id')->on('users');
            });
        }
        Schema::dropIfExists('client_users');
    }
};
