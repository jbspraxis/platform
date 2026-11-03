<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns C2, Outreach (docs/CAMPAIGNS.md section 10): one-to-one cold email to researched prospects, written by
 * Claude, reviewed by a person and sent from the salesperson's own mailbox.
 *
 *   outreach_campaigns  a list of prospects with its sender (whose mailbox sends), owner and status
 *   outreach_prospects  the people, kept apart from client_users and marketing_subscribers so no broadcast can
 *                       ever reach them; one row per address across every campaign
 *   outreach_emails     one row per email to a prospect: the first email and its one follow-up, from Claude's
 *                       draft to sent, each with its own unsubscribe token
 *   outreach_senders    each salesperson's sending state: when they started (the first weeks' lower limit) and an
 *                       automatic pause (bounces, a spam complaint, a mailbox that refused its password)
 *   outreach_settings   the limits the owner edits (App\Support\Campaigns\Outreach\Limits)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_campaigns', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 150);
            $table->text('brief')->nullable()->comment('What to offer: the salesperson’s note to Claude for every email of the campaign');
            $table->char('owner_id', 36)->comment('Who made it (users.id)');
            $table->char('sender_id', 36)->comment('Whose own mailbox sends it (users.id)');
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');
            $table->char('paused_by', 36)->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();
            $table->index('sender_id');
        });

        Schema::create('outreach_prospects', function (Blueprint $table) {
            $table->id();
            $table->char('campaign_id', 36);
            $table->string('company', 190);
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('email', 190)->unique()->comment('Lower case (Marketing::normalize); one prospect per address');
            $table->string('country', 100);
            $table->string('industry', 150);
            $table->char('language', 2)->default('en')->comment('en or fr: the emails are written in it');
            $table->text('findings')->nullable()->comment('"What we found": the research the insight is built on');
            $table->string('website', 500)->nullable();
            $table->mediumText('website_text')->nullable()->comment('The words of the website’s page, read once (Website::read)');
            $table->string('website_note', 255)->nullable()->comment('Why the website could not be read');
            $table->timestamp('website_read_at')->nullable();
            $table->enum('status', ['new', 'emailed', 'replied', 'bounced', 'unsubscribed', 'skipped'])->default('new');
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('complained_at')->nullable()->comment('Marked as spam (found on the do-not-email list)');
            $table->string('dedupe_key', 80)->nullable()->unique()->comment('import:<file>:<row>, so a file’s row comes in once');
            $table->char('imported_by', 36)->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status']);
            $table->foreign('campaign_id')->references('id')->on('outreach_campaigns')->cascadeOnDelete();
        });

        Schema::create('outreach_emails', function (Blueprint $table) {
            $table->id();
            $table->char('campaign_id', 36);
            $table->unsignedBigInteger('prospect_id');
            $table->enum('kind', ['first', 'follow_up']);
            $table->enum('status', ['writing', 'not_written', 'draft', 'approved', 'scheduled', 'sending', 'sent', 'skipped', 'bounced', 'failed', 'cancelled'])
                ->default('writing');
            $table->string('subject', 255)->nullable();
            $table->text('body_html')->nullable()->comment('Rich text, cleaned (RichText::clean): the words only, no signature');
            $table->char('ai_run_id', 36)->nullable();
            $table->string('ai_error', 80)->nullable()->comment('ai.errors.* key when Claude could not write it');
            $table->string('insight', 500)->nullable()->comment('Claude’s line for the reviewer: the insight used and where it came from');
            $table->string('rewrite_note', 500)->nullable();
            $table->boolean('edited')->default(false);
            $table->char('approved_by', 36)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->enum('approval', ['hand', 'bulk'])->nullable()->comment('Approved one by one, or with Approve the rest');
            $table->timestamp('send_after')->nullable()->comment('A follow-up: not before this (working days after the first)');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->char('sender_id', 36)->nullable()->comment('Whose mailbox it went from');
            $table->char('mail_account_id', 36)->nullable();
            $table->timestamp('tried_at')->nullable()->comment('When the sender took it: counts toward that day’s limit');
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id', 255)->nullable()->comment('Without brackets; replies point at it');
            $table->char('token', 40)->unique()->comment('The unsubscribe link’s secret');
            $table->string('note', 255)->nullable()->comment('Why it was skipped, cancelled, bounced or failed');
            $table->timestamps();
            $table->unique(['prospect_id', 'kind']);
            $table->index(['status', 'send_after']);
            $table->index(['campaign_id', 'kind', 'status']);
            $table->index(['sender_id', 'tried_at']);
            $table->index('message_id');
            $table->foreign('campaign_id')->references('id')->on('outreach_campaigns')->cascadeOnDelete();
            $table->foreign('prospect_id')->references('id')->on('outreach_prospects')->cascadeOnDelete();
        });

        Schema::create('outreach_senders', function (Blueprint $table) {
            $table->char('user_id', 36)->primary();
            $table->timestamp('first_sent_at')->nullable()->comment('Their first outreach email: the lower limit lasts from here');
            $table->timestamp('paused_at')->nullable();
            $table->enum('paused_reason', ['bounces', 'complaint', 'mailbox'])->nullable();
            $table->string('paused_note', 255)->nullable();
            $table->char('resumed_by', 36)->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outreach_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->string('value', 255);
            $table->char('updated_by', 36)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_settings');
        Schema::dropIfExists('outreach_senders');
        Schema::dropIfExists('outreach_emails');
        Schema::dropIfExists('outreach_prospects');
        Schema::dropIfExists('outreach_campaigns');
    }
};
