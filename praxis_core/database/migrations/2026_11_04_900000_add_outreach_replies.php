<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns C3, Outreach replies (docs/CAMPAIGNS.md section 12): what came back from an outreach email, sorted.
 *
 *   outreach_replies    one row per message about a prospect's outreach email, found in HQ Mail's copy of the
 *                       mailboxes: a reply (Claude suggests a label, a person confirms or changes it), a bounce notice
 *                       from a mail server, or a spam complaint report. The message itself stays in Mail: this keeps
 *                       who, when and the subject, and points at it by mail_messages.id and the Message-ID's hash.
 *   outreach_prospects  + the prospect's answer (the label a person confirmed last), the lead made from them, and the
 *                       Not now reminder (a date, and when its bell rang).
 */
return new class extends Migration
{
    private const LABELS = ['interested', 'not_now', 'not_interested', 'unsubscribe', 'bounced', 'complained'];

    public function up(): void
    {
        Schema::create('outreach_replies', function (Blueprint $table) {
            $table->id();
            $table->char('campaign_id', 36);
            $table->unsignedBigInteger('prospect_id');
            $table->unsignedBigInteger('email_id')->nullable()->comment('The outreach email it answers or bounced (outreach_emails.id)');
            $table->enum('kind', ['reply', 'bounce', 'complaint'])->comment('reply: the person wrote; bounce and complaint: a mail server’s notice');
            $table->unsignedBigInteger('mail_message_id')->nullable()->comment('A copy in HQ Mail (mail_messages.id); copies can move, so message_key finds it again');
            $table->string('message_key', 64)->comment('mail_messages.message_id_hash, or id:<mail_messages.id> without a Message-ID');
            $table->char('mail_account_id', 36)->nullable()->comment('The mailbox it arrived in');
            $table->string('from_email', 190)->nullable();
            $table->string('from_name', 190)->nullable();
            $table->string('subject', 500)->nullable();
            $table->string('snippet', 300)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->enum('ai_label', array_slice(self::LABELS, 0, 4))->nullable()->comment('Claude’s suggestion (outreach_reply)');
            $table->string('ai_reason', 255)->nullable()->comment('Claude’s one line of why');
            $table->date('ai_remind_on')->nullable()->comment('Not now: the day they asked us to come back, when they named one');
            $table->char('ai_run_id', 36)->nullable();
            $table->string('ai_error', 80)->nullable()->comment('ai.errors.* key when Claude could not sort it');
            $table->enum('label', self::LABELS)->nullable()->comment('What a person confirmed (bounced and complained: the mail server’s report)');
            $table->char('label_by', 36)->nullable()->comment('Who confirmed it (null: a mail server’s notice, filed by HQ)');
            $table->timestamp('label_at')->nullable();
            $table->string('note', 255)->nullable()->comment('A notice’s reason, e.g. 550 5.1.1 user unknown');
            $table->timestamps();
            $table->unique(['prospect_id', 'message_key']);
            $table->index(['campaign_id', 'kind', 'label']);
            $table->index('mail_message_id');
            $table->foreign('campaign_id')->references('id')->on('outreach_campaigns')->cascadeOnDelete();
            $table->foreign('prospect_id')->references('id')->on('outreach_prospects')->cascadeOnDelete();
        });

        Schema::table('outreach_prospects', function (Blueprint $table) {
            $table->enum('reply_label', self::LABELS)->nullable()->after('complained_at')->comment('Their answer: the label a person confirmed last');
            $table->char('organization_id', 36)->nullable()->after('reply_label')->comment('The lead (or client) Make a lead made or added them to');
            $table->timestamp('lead_made_at')->nullable()->after('organization_id');
            $table->char('lead_made_by', 36)->nullable()->after('lead_made_at');
            $table->date('remind_on')->nullable()->after('lead_made_by')->comment('Not now: the day the sender is reminded (Lagos)');
            $table->timestamp('reminded_at')->nullable()->after('remind_on')->comment('When that reminder’s bell rang (once per date set)');
            $table->char('remind_set_by', 36)->nullable()->after('reminded_at');
            $table->index('organization_id');
            $table->index(['remind_on', 'reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['remind_on', 'reminded_at']);
            $table->dropColumn(['reply_label', 'organization_id', 'lead_made_at', 'lead_made_by', 'remind_on', 'reminded_at', 'remind_set_by']);
        });
        Schema::dropIfExists('outreach_replies');
    }
};
