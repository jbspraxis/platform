<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Baseline foreign keys (63 tables), generated from the live schema on 29 Oct 2026. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('organization_id', 'appointments_ibfk_1')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('host_id', 'appointments_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'appointments_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'appointments_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'appointments_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('asset_credential_history', function (Blueprint $table) {
            $table->foreign('asset_id', 'asset_credential_history_ibfk_1')->references('id')->on('social_media_accounts')->onDelete('cascade');
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->foreign('created_by', 'broadcast_campaigns_ibfk_1')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->foreign('campaign_id', 'broadcast_logs_ibfk_1')->references('id')->on('broadcast_campaigns')->onDelete('cascade');
            $table->foreign('user_id', 'broadcast_logs_ibfk_2')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('calendar_availability', function (Blueprint $table) {
            $table->foreign('user_id', 'calendar_availability_ibfk_1')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('cash_requests', function (Blueprint $table) {
            $table->foreign('requester_id', 'cash_requests_ibfk_1')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('project_id', 'cash_requests_ibfk_2')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('financial_dictionary_id', 'cash_requests_ibfk_3')->references('id')->on('mdm_financial_dictionary');
            $table->foreign('authorizer_id', 'cash_requests_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cleared_by', 'cash_requests_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('client_portal_settings', function (Blueprint $table) {
            $table->foreign('organization_id', 'fk_cps_org')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::table('compensation_history', function (Blueprint $table) {
            $table->foreign('user_id', 'compensation_history_ibfk_1')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('copq_assessments', function (Blueprint $table) {
            $table->foreign('organization_id', 'copq_assessments_ibfk_1')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by', 'copq_assessments_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'copq_assessments_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'copq_assessments_ibfk_4')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('crm_notes', function (Blueprint $table) {
            $table->foreign('organization_id', 'crm_notes_ibfk_1')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('author_id', 'crm_notes_ibfk_2')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by', 'crm_notes_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'crm_notes_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'crm_notes_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->foreign('mdm_employee_id', 'fk_el_employee')->references('id')->on('mdm_employees')->onDelete('cascade');
        });

        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->foreign('mdm_employee_id', 'employment_contracts_ibfk_1')->references('id')->on('talent_pool')->onDelete('cascade');
            $table->foreign('created_by', 'employment_contracts_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'employment_contracts_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'employment_contracts_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('mdm_employee_id', 'fk_ec_employee')->references('id')->on('mdm_employees')->onDelete('cascade');
        });

        Schema::table('exchange_rates_ledger', function (Blueprint $table) {
            $table->foreign('set_by', 'exchange_rates_ledger_ibfk_1')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('global_settings', function (Blueprint $table) {
            $table->foreign('created_by', 'global_settings_ibfk_1')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'global_settings_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'global_settings_ibfk_3')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreign('invoice_id', 'invoice_lines_ibfk_1')->references('id')->on('invoices')->onDelete('cascade');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('remittance_account_id', 'fk_inv_remittance')->references('id')->on('mdm_remittance_accounts')->onDelete('set null');
            $table->foreign('project_id', 'invoices_ibfk_1')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('phase_id', 'invoices_ibfk_2')->references('id')->on('project_phases')->onDelete('set null');
            $table->foreign('created_by', 'invoices_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'invoices_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'invoices_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreign('account_id', 'fk_jl_account')->references('id')->on('chart_of_accounts');
            $table->foreign('journal_entry_id', 'fk_jl_journal')->references('id')->on('journal_entries')->onDelete('cascade');
        });

        Schema::table('kaizen_articles', function (Blueprint $table) {
            $table->foreign('created_by', 'kaizen_articles_ibfk_1')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'kaizen_articles_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'kaizen_articles_ibfk_3')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('kaizen_case_studies', function (Blueprint $table) {
            $table->foreign('project_id', 'kaizen_case_studies_ibfk_1')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::table('kaizen_embeddings', function (Blueprint $table) {
            $table->foreign('article_id', 'kaizen_embeddings_ibfk_1')->references('id')->on('kaizen_articles')->onDelete('cascade');
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->foreign('mdm_employee_id', 'fk_lb_employee')->references('id')->on('mdm_employees')->onDelete('cascade');
        });

        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->foreign('creative_media_id', 'fk_marketing_media')->references('id')->on('media_assets')->onDelete('set null');
        });

        Schema::table('mdm_employee_compensations', function (Blueprint $table) {
            $table->foreign('mdm_employee_id', 'fk_comp_employee')->references('id')->on('mdm_employees')->onDelete('cascade');
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->foreign('talent_id', 'fk_media_talent')->references('id')->on('talent_pool')->onDelete('cascade');
            $table->foreign('created_by', 'media_assets_ibfk_1')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'media_assets_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'media_assets_ibfk_3')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('monthly_payroll_variables', function (Blueprint $table) {
            $table->foreign('mdm_employee_id', 'fk_mpv_employee')->references('id')->on('mdm_employees')->onDelete('cascade');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('user_id', 'notifications_ibfk_1')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('created_by', 'organizations_ibfk_1')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'organizations_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'organizations_ibfk_3')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->foreign('payroll_run_id', 'fk_payroll_run')->references('id')->on('payroll_runs')->onDelete('cascade');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreign('approver_id', 'fk_approver_user')->references('id')->on('users');
            $table->foreign('checker_id', 'fk_checker_user')->references('id')->on('users');
            $table->foreign('maker_id', 'fk_maker_user')->references('id')->on('users');
        });

        Schema::table('performance_appraisals', function (Blueprint $table) {
            $table->foreign('mdm_employee_id', 'fk_pa_employee')->references('id')->on('mdm_employees')->onDelete('cascade');
            $table->foreign('manager_id', 'fk_pa_manager')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('portfolio_case_studies', function (Blueprint $table) {
            $table->foreign('project_id', 'portfolio_case_studies_ibfk_1')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('created_by', 'portfolio_case_studies_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'portfolio_case_studies_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'portfolio_case_studies_ibfk_4')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('project_budgets', function (Blueprint $table) {
            $table->foreign('project_id', 'fk_pb_proj')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::table('project_magic_links', function (Blueprint $table) {
            $table->foreign('project_id', 'project_magic_links_ibfk_1')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::table('project_phases', function (Blueprint $table) {
            $table->foreign('project_id', 'project_phases_ibfk_1')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by', 'project_phases_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'project_phases_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'project_phases_ibfk_4')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('project_signoffs', function (Blueprint $table) {
            $table->foreign('jbs_signer_id', 'fk_jbs_signer')->references('id')->on('users')->onDelete('set null');
            $table->foreign('project_id', 'fk_ps_project')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('organization_id', 'projects_ibfk_1')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by', 'projects_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'projects_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'projects_ibfk_4')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('proposal_ai_narratives', function (Blueprint $table) {
            $table->foreign('proposal_id', 'fk_pan_proposal')->references('id')->on('proposals')->onDelete('cascade');
        });

        Schema::table('proposal_line_items', function (Blueprint $table) {
            $table->foreign('proposal_id', 'proposal_line_items_ibfk_1')->references('id')->on('proposals')->onDelete('cascade');
        });

        Schema::table('proposal_tech_stack', function (Blueprint $table) {
            $table->foreign('tech_id', 'fk_tech_cat')->references('id')->on('mdm_tech_catalog')->onDelete('cascade');
            $table->foreign('proposal_id', 'fk_tech_prop')->references('id')->on('proposals')->onDelete('cascade');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreign('organization_id', 'proposals_ibfk_1')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('project_id', 'proposals_ibfk_2')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('created_by', 'proposals_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'proposals_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'proposals_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('qa_attachments', function (Blueprint $table) {
            $table->foreign('ticket_id', 'qa_attachments_ibfk_1')->references('id')->on('qa_tickets')->onDelete('cascade');
            $table->foreign('uploaded_by', 'qa_attachments_ibfk_2')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('qa_comments', function (Blueprint $table) {
            $table->foreign('ticket_id', 'qa_comments_ibfk_1')->references('id')->on('qa_tickets')->onDelete('cascade');
            $table->foreign('user_id', 'qa_comments_ibfk_2')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('qa_tickets', function (Blueprint $table) {
            $table->foreign('resolved_by', 'fk_qa_resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('project_id', 'qa_tickets_ibfk_1')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('task_id', 'qa_tickets_ibfk_2')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('submitted_by', 'qa_tickets_ibfk_3')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by', 'qa_tickets_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'qa_tickets_ibfk_5')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'qa_tickets_ibfk_6')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('redline_sos_requests', function (Blueprint $table) {
            $table->foreign('organization_id', 'fk_sos_org')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->foreign('role_id', 'role_permissions_ibfk_1')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id', 'role_permissions_ibfk_2')->references('id')->on('permissions')->onDelete('cascade');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('created_by', 'roles_ibfk_1')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'roles_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'roles_ibfk_3')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->foreign('project_id', 'sprints_ibfk_1')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by', 'sprints_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'sprints_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'sprints_ibfk_4')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('system_audit_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'system_audit_logs_ibfk_1')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('talent_pool', function (Blueprint $table) {
            $table->foreign('created_by', 'talent_pool_ibfk_1')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'talent_pool_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'talent_pool_ibfk_3')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->foreign('task_id', 'task_comments_ibfk_1')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id', 'task_comments_ibfk_2')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by', 'task_comments_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'task_comments_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'task_comments_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('task_time_logs', function (Blueprint $table) {
            $table->foreign('task_id', 'task_time_logs_ibfk_1')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id', 'task_time_logs_ibfk_2')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('phase_id', 'tasks_ibfk_1')->references('id')->on('project_phases')->onDelete('cascade');
            $table->foreign('sprint_id', 'tasks_ibfk_2')->references('id')->on('sprints')->onDelete('set null');
            $table->foreign('assigned_to', 'tasks_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'tasks_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'tasks_ibfk_5')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'tasks_ibfk_6')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('tax_bands', function (Blueprint $table) {
            $table->foreign('tax_configuration_id', 'fk_tb_config')->references('id')->on('tax_configurations')->onDelete('cascade');
        });

        Schema::table('time_correction_requests', function (Blueprint $table) {
            $table->foreign('user_id', 'time_correction_requests_ibfk_1')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('task_id', 'time_correction_requests_ibfk_2')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('time_log_id', 'time_correction_requests_ibfk_3')->references('id')->on('task_time_logs')->onDelete('set null');
            $table->foreign('reviewed_by', 'time_correction_requests_ibfk_4')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('tracker_messages', function (Blueprint $table) {
            $table->foreign('parent_message_id', 'tracker_messages_parent_message_id_foreign')->references('id')->on('tracker_messages')->onDelete('cascade');
            $table->foreign('sender_user_id', 'tracker_messages_sender_user_id_foreign')->references('id')->on('tracker_users');
            $table->foreign('task_id', 'tracker_messages_task_id_foreign')->references('id')->on('tracker_tasks')->onDelete('cascade');
            $table->foreign('tracker_id', 'tracker_messages_tracker_id_foreign')->references('id')->on('trackers')->onDelete('cascade');
        });

        Schema::table('tracker_milestones', function (Blueprint $table) {
            $table->foreign('tracker_id', 'tracker_milestones_tracker_id_foreign')->references('id')->on('trackers')->onDelete('cascade');
        });

        Schema::table('tracker_tasks', function (Blueprint $table) {
            $table->foreign('assigned_to', 'fk_task_assignee')->references('id')->on('tracker_users')->onDelete('set null');
            $table->foreign('assigned_to', 'tracker_tasks_assigned_to_foreign')->references('id')->on('tracker_users');
            $table->foreign('milestone_id', 'tracker_tasks_milestone_id_foreign')->references('id')->on('tracker_milestones')->onDelete('cascade');
        });

        Schema::table('tracker_user_access', function (Blueprint $table) {
            $table->foreign('tracker_id', 'tracker_user_access_tracker_id_foreign')->references('id')->on('trackers')->onDelete('cascade');
            $table->foreign('user_id', 'tracker_user_access_user_id_foreign')->references('id')->on('tracker_users')->onDelete('cascade');
        });

        Schema::table('trackers', function (Blueprint $table) {
            $table->foreign('created_by', 'trackers_created_by_foreign')->references('id')->on('tracker_users');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('invoice_id', 'transactions_ibfk_1')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('verified_by', 'transactions_ibfk_2')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'transactions_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'transactions_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'transactions_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('user_clock_logs', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_clock_user')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('restricted_project_id', 'fk_user_restr_proj')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('organization_id', 'users_ibfk_1')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('role_id', 'users_ibfk_2')->references('id')->on('roles')->onDelete('set null');
            $table->foreign('created_by', 'users_ibfk_3')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'users_ibfk_4')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by', 'users_ibfk_5')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('whitepaper_downloads', function (Blueprint $table) {
            $table->foreign('organization_id', 'whitepaper_downloads_ibfk_1')->references('id')->on('organizations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign('appointments_ibfk_1');
            $table->dropForeign('appointments_ibfk_2');
            $table->dropForeign('appointments_ibfk_3');
            $table->dropForeign('appointments_ibfk_4');
            $table->dropForeign('appointments_ibfk_5');
        });

        Schema::table('asset_credential_history', function (Blueprint $table) {
            $table->dropForeign('asset_credential_history_ibfk_1');
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropForeign('broadcast_campaigns_ibfk_1');
        });

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropForeign('broadcast_logs_ibfk_1');
            $table->dropForeign('broadcast_logs_ibfk_2');
        });

        Schema::table('calendar_availability', function (Blueprint $table) {
            $table->dropForeign('calendar_availability_ibfk_1');
        });

        Schema::table('cash_requests', function (Blueprint $table) {
            $table->dropForeign('cash_requests_ibfk_1');
            $table->dropForeign('cash_requests_ibfk_2');
            $table->dropForeign('cash_requests_ibfk_3');
            $table->dropForeign('cash_requests_ibfk_4');
            $table->dropForeign('cash_requests_ibfk_5');
        });

        Schema::table('client_portal_settings', function (Blueprint $table) {
            $table->dropForeign('fk_cps_org');
        });

        Schema::table('compensation_history', function (Blueprint $table) {
            $table->dropForeign('compensation_history_ibfk_1');
        });

        Schema::table('copq_assessments', function (Blueprint $table) {
            $table->dropForeign('copq_assessments_ibfk_1');
            $table->dropForeign('copq_assessments_ibfk_2');
            $table->dropForeign('copq_assessments_ibfk_3');
            $table->dropForeign('copq_assessments_ibfk_4');
        });

        Schema::table('crm_notes', function (Blueprint $table) {
            $table->dropForeign('crm_notes_ibfk_1');
            $table->dropForeign('crm_notes_ibfk_2');
            $table->dropForeign('crm_notes_ibfk_3');
            $table->dropForeign('crm_notes_ibfk_4');
            $table->dropForeign('crm_notes_ibfk_5');
        });

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropForeign('fk_el_employee');
        });

        Schema::table('employment_contracts', function (Blueprint $table) {
            $table->dropForeign('employment_contracts_ibfk_1');
            $table->dropForeign('employment_contracts_ibfk_2');
            $table->dropForeign('employment_contracts_ibfk_3');
            $table->dropForeign('employment_contracts_ibfk_4');
            $table->dropForeign('fk_ec_employee');
        });

        Schema::table('exchange_rates_ledger', function (Blueprint $table) {
            $table->dropForeign('exchange_rates_ledger_ibfk_1');
        });

        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropForeign('global_settings_ibfk_1');
            $table->dropForeign('global_settings_ibfk_2');
            $table->dropForeign('global_settings_ibfk_3');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropForeign('invoice_lines_ibfk_1');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('fk_inv_remittance');
            $table->dropForeign('invoices_ibfk_1');
            $table->dropForeign('invoices_ibfk_2');
            $table->dropForeign('invoices_ibfk_3');
            $table->dropForeign('invoices_ibfk_4');
            $table->dropForeign('invoices_ibfk_5');
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign('fk_jl_account');
            $table->dropForeign('fk_jl_journal');
        });

        Schema::table('kaizen_articles', function (Blueprint $table) {
            $table->dropForeign('kaizen_articles_ibfk_1');
            $table->dropForeign('kaizen_articles_ibfk_2');
            $table->dropForeign('kaizen_articles_ibfk_3');
        });

        Schema::table('kaizen_case_studies', function (Blueprint $table) {
            $table->dropForeign('kaizen_case_studies_ibfk_1');
        });

        Schema::table('kaizen_embeddings', function (Blueprint $table) {
            $table->dropForeign('kaizen_embeddings_ibfk_1');
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropForeign('fk_lb_employee');
        });

        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropForeign('fk_marketing_media');
        });

        Schema::table('mdm_employee_compensations', function (Blueprint $table) {
            $table->dropForeign('fk_comp_employee');
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropForeign('fk_media_talent');
            $table->dropForeign('media_assets_ibfk_1');
            $table->dropForeign('media_assets_ibfk_2');
            $table->dropForeign('media_assets_ibfk_3');
        });

        Schema::table('monthly_payroll_variables', function (Blueprint $table) {
            $table->dropForeign('fk_mpv_employee');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign('notifications_ibfk_1');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign('organizations_ibfk_1');
            $table->dropForeign('organizations_ibfk_2');
            $table->dropForeign('organizations_ibfk_3');
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropForeign('fk_payroll_run');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign('fk_approver_user');
            $table->dropForeign('fk_checker_user');
            $table->dropForeign('fk_maker_user');
        });

        Schema::table('performance_appraisals', function (Blueprint $table) {
            $table->dropForeign('fk_pa_employee');
            $table->dropForeign('fk_pa_manager');
        });

        Schema::table('portfolio_case_studies', function (Blueprint $table) {
            $table->dropForeign('portfolio_case_studies_ibfk_1');
            $table->dropForeign('portfolio_case_studies_ibfk_2');
            $table->dropForeign('portfolio_case_studies_ibfk_3');
            $table->dropForeign('portfolio_case_studies_ibfk_4');
        });

        Schema::table('project_budgets', function (Blueprint $table) {
            $table->dropForeign('fk_pb_proj');
        });

        Schema::table('project_magic_links', function (Blueprint $table) {
            $table->dropForeign('project_magic_links_ibfk_1');
        });

        Schema::table('project_phases', function (Blueprint $table) {
            $table->dropForeign('project_phases_ibfk_1');
            $table->dropForeign('project_phases_ibfk_2');
            $table->dropForeign('project_phases_ibfk_3');
            $table->dropForeign('project_phases_ibfk_4');
        });

        Schema::table('project_signoffs', function (Blueprint $table) {
            $table->dropForeign('fk_jbs_signer');
            $table->dropForeign('fk_ps_project');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign('projects_ibfk_1');
            $table->dropForeign('projects_ibfk_2');
            $table->dropForeign('projects_ibfk_3');
            $table->dropForeign('projects_ibfk_4');
        });

        Schema::table('proposal_ai_narratives', function (Blueprint $table) {
            $table->dropForeign('fk_pan_proposal');
        });

        Schema::table('proposal_line_items', function (Blueprint $table) {
            $table->dropForeign('proposal_line_items_ibfk_1');
        });

        Schema::table('proposal_tech_stack', function (Blueprint $table) {
            $table->dropForeign('fk_tech_cat');
            $table->dropForeign('fk_tech_prop');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropForeign('proposals_ibfk_1');
            $table->dropForeign('proposals_ibfk_2');
            $table->dropForeign('proposals_ibfk_3');
            $table->dropForeign('proposals_ibfk_4');
            $table->dropForeign('proposals_ibfk_5');
        });

        Schema::table('qa_attachments', function (Blueprint $table) {
            $table->dropForeign('qa_attachments_ibfk_1');
            $table->dropForeign('qa_attachments_ibfk_2');
        });

        Schema::table('qa_comments', function (Blueprint $table) {
            $table->dropForeign('qa_comments_ibfk_1');
            $table->dropForeign('qa_comments_ibfk_2');
        });

        Schema::table('qa_tickets', function (Blueprint $table) {
            $table->dropForeign('fk_qa_resolved_by');
            $table->dropForeign('qa_tickets_ibfk_1');
            $table->dropForeign('qa_tickets_ibfk_2');
            $table->dropForeign('qa_tickets_ibfk_3');
            $table->dropForeign('qa_tickets_ibfk_4');
            $table->dropForeign('qa_tickets_ibfk_5');
            $table->dropForeign('qa_tickets_ibfk_6');
        });

        Schema::table('redline_sos_requests', function (Blueprint $table) {
            $table->dropForeign('fk_sos_org');
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropForeign('role_permissions_ibfk_1');
            $table->dropForeign('role_permissions_ibfk_2');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign('roles_ibfk_1');
            $table->dropForeign('roles_ibfk_2');
            $table->dropForeign('roles_ibfk_3');
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->dropForeign('sprints_ibfk_1');
            $table->dropForeign('sprints_ibfk_2');
            $table->dropForeign('sprints_ibfk_3');
            $table->dropForeign('sprints_ibfk_4');
        });

        Schema::table('system_audit_logs', function (Blueprint $table) {
            $table->dropForeign('system_audit_logs_ibfk_1');
        });

        Schema::table('talent_pool', function (Blueprint $table) {
            $table->dropForeign('talent_pool_ibfk_1');
            $table->dropForeign('talent_pool_ibfk_2');
            $table->dropForeign('talent_pool_ibfk_3');
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropForeign('task_comments_ibfk_1');
            $table->dropForeign('task_comments_ibfk_2');
            $table->dropForeign('task_comments_ibfk_3');
            $table->dropForeign('task_comments_ibfk_4');
            $table->dropForeign('task_comments_ibfk_5');
        });

        Schema::table('task_time_logs', function (Blueprint $table) {
            $table->dropForeign('task_time_logs_ibfk_1');
            $table->dropForeign('task_time_logs_ibfk_2');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign('tasks_ibfk_1');
            $table->dropForeign('tasks_ibfk_2');
            $table->dropForeign('tasks_ibfk_3');
            $table->dropForeign('tasks_ibfk_4');
            $table->dropForeign('tasks_ibfk_5');
            $table->dropForeign('tasks_ibfk_6');
        });

        Schema::table('tax_bands', function (Blueprint $table) {
            $table->dropForeign('fk_tb_config');
        });

        Schema::table('time_correction_requests', function (Blueprint $table) {
            $table->dropForeign('time_correction_requests_ibfk_1');
            $table->dropForeign('time_correction_requests_ibfk_2');
            $table->dropForeign('time_correction_requests_ibfk_3');
            $table->dropForeign('time_correction_requests_ibfk_4');
        });

        Schema::table('tracker_messages', function (Blueprint $table) {
            $table->dropForeign('tracker_messages_parent_message_id_foreign');
            $table->dropForeign('tracker_messages_sender_user_id_foreign');
            $table->dropForeign('tracker_messages_task_id_foreign');
            $table->dropForeign('tracker_messages_tracker_id_foreign');
        });

        Schema::table('tracker_milestones', function (Blueprint $table) {
            $table->dropForeign('tracker_milestones_tracker_id_foreign');
        });

        Schema::table('tracker_tasks', function (Blueprint $table) {
            $table->dropForeign('fk_task_assignee');
            $table->dropForeign('tracker_tasks_assigned_to_foreign');
            $table->dropForeign('tracker_tasks_milestone_id_foreign');
        });

        Schema::table('tracker_user_access', function (Blueprint $table) {
            $table->dropForeign('tracker_user_access_tracker_id_foreign');
            $table->dropForeign('tracker_user_access_user_id_foreign');
        });

        Schema::table('trackers', function (Blueprint $table) {
            $table->dropForeign('trackers_created_by_foreign');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_ibfk_1');
            $table->dropForeign('transactions_ibfk_2');
            $table->dropForeign('transactions_ibfk_3');
            $table->dropForeign('transactions_ibfk_4');
            $table->dropForeign('transactions_ibfk_5');
        });

        Schema::table('user_clock_logs', function (Blueprint $table) {
            $table->dropForeign('fk_clock_user');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_user_restr_proj');
            $table->dropForeign('users_ibfk_1');
            $table->dropForeign('users_ibfk_2');
            $table->dropForeign('users_ibfk_3');
            $table->dropForeign('users_ibfk_4');
            $table->dropForeign('users_ibfk_5');
        });

        Schema::table('whitepaper_downloads', function (Blueprint $table) {
            $table->dropForeign('whitepaper_downloads_ibfk_1');
        });
    }
};
