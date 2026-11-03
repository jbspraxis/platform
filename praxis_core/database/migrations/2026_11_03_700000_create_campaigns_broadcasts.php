<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns C1, Broadcasts (docs/CAMPAIGNS.md section 4).
 *
 *   marketing_suppressions  the global do-not-email list: one row per address that gets no marketing at all, from
 *                           Broadcasts or Outreach (App\Support\Campaigns\Marketing::canEmail)
 *   marketing_subscribers   people who asked to hear from us without being a client's or a lead's contact
 *   broadcast_segments      saved audiences: who (clients, leads, subscribers) and filters, as rules
 *   broadcast_links         the links of one sent broadcast, per language, so clicks are counted
 *   broadcast_clicks        one row per click
 *   broadcast_campaigns     + layout, the English and French versions (content), the audience, scheduling
 *   broadcast_logs          + one row per recipient with their address, language and secret link token; user_id
 *                           may be empty (a subscriber has no contact record)
 *
 * The backfill of the do-not-email list from today's unsubscribed contacts is the next migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_suppressions')) {
            Schema::create('marketing_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('email', 190)->unique()->comment('Lower case, trimmed (Marketing::normalize)');
                $table->enum('reason', ['unsubscribed', 'bounced', 'complained', 'manual']);
                $table->string('source', 40)->nullable()->comment('broadcast, outreach, legacy_link, backfill, hq');
                $table->char('broadcast_id', 36)->nullable()->comment('The broadcast whose link or send led here');
                $table->string('note', 255)->nullable();
                $table->char('created_by', 36)->nullable()->comment('Staff member, when added by hand');
                $table->timestamps();
                $table->index('reason');
            });
        }

        if (! Schema::hasTable('marketing_subscribers')) {
            Schema::create('marketing_subscribers', function (Blueprint $table) {
                $table->id();
                $table->string('email', 190)->unique();
                $table->string('first_name', 60)->nullable();
                $table->string('last_name', 60)->nullable();
                $table->string('organisation', 150)->nullable();
                $table->string('country', 100)->nullable();
                $table->char('language', 2)->nullable()->comment('en or fr; empty: from the country');
                $table->string('source', 30)->default('hq')->comment('hq (added in Campaigns), website (a future sign-up form)');
                $table->string('consent', 255)->nullable()->comment('How they agreed to hear from us');
                $table->char('added_by', 36)->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('broadcast_segments')) {
            Schema::create('broadcast_segments', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('name', 120);
                $table->json('rules')->comment('Audience rules (App\Support\Campaigns\Audience)');
                $table->char('created_by', 36)->nullable();
                $table->char('updated_by', 36)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('broadcast_links')) {
            Schema::create('broadcast_links', function (Blueprint $table) {
                $table->id();
                $table->char('campaign_id', 36);
                $table->char('locale', 2);
                $table->text('url');
                $table->unsignedInteger('clicks')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->index(['campaign_id', 'locale']);
            });
        }

        if (! Schema::hasTable('broadcast_clicks')) {
            Schema::create('broadcast_clicks', function (Blueprint $table) {
                $table->id();
                $table->char('campaign_id', 36);
                $table->unsignedBigInteger('log_id');
                $table->unsignedBigInteger('link_id');
                $table->timestamp('created_at')->nullable();
                $table->index(['campaign_id', 'link_id']);
                $table->index('log_id');
            });
        }

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->string('subject', 255)->default('')->change();
            $table->longText('body_html')->nullable()->change();
            $table->enum('status', ['Draft', 'Scheduled', 'Sending', 'Completed', 'Cancelled'])->default('Draft')->change();
            // en or fr: everyone gets that version (campaigns before 3 Nov 2026); both: each person gets their own
            $table->string('language', 4)->default('both')->change();
        });
        if (! Schema::hasColumn('broadcast_campaigns', 'layout')) {
            Schema::table('broadcast_campaigns', function (Blueprint $table) {
                $table->string('layout', 30)->nullable()->after('subject')->comment('Campaigns\Layouts key; empty: a broadcast from before 3 Nov 2026');
                $table->json('content')->nullable()->after('body_html')->comment('{"en": {subject, preheader, headline, blocks}, "fr": {…}}');
                $table->boolean('greeting')->default(true)->after('content')->comment('Opens with Hello :first_name, / Bonjour,');
                $table->char('segment_id', 36)->nullable()->after('greeting');
                $table->json('audience')->nullable()->after('segment_id')->comment('The rules used, kept as they were when sending started');
                $table->timestamp('scheduled_at')->nullable()->after('estimated_completion_time');
                $table->timestamp('started_at')->nullable()->after('scheduled_at');
                $table->timestamp('completed_at')->nullable()->after('started_at');
                $table->char('updated_by', 36)->nullable()->after('created_by');
                $table->char('sent_by', 36)->nullable()->after('updated_by')->comment('Who chose Send now or Schedule');
                $table->index(['status', 'scheduled_at']);
            });
        }

        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->dropForeign('broadcast_logs_client_user_fk');
        });
        Schema::table('broadcast_logs', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->change();
            $table->foreign('user_id', 'broadcast_logs_client_user_fk')->references('id')->on('client_users')->nullOnDelete();
            $table->enum('status', ['Queued', 'Sending', 'Sent', 'Failed', 'Skipped', 'Bounced'])->default('Queued')->change();
        });
        if (! Schema::hasColumn('broadcast_logs', 'token')) {
            Schema::table('broadcast_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('subscriber_id')->nullable()->after('user_id');
                $table->string('kind', 12)->nullable()->after('subscriber_id')->comment('client, lead, subscriber, kaizen: the footer says why they got it');
                $table->string('email', 190)->nullable()->after('kind')->comment('The address it went to (kept if the contact changes)');
                $table->string('first_name', 60)->nullable()->after('email');
                $table->string('last_name', 60)->nullable()->after('first_name');
                $table->string('company', 150)->nullable()->after('last_name');
                $table->char('locale', 2)->nullable()->after('company');
                $table->char('token', 40)->nullable()->after('locale')->comment('Secret of this email’s links (open, click, unsubscribe)');
                $table->timestamp('sent_at')->nullable()->after('error_message');
                $table->timestamp('first_opened_at')->nullable()->after('open_count');
                $table->unsignedInteger('click_count')->default(0)->after('last_open_geo');
                $table->timestamp('first_clicked_at')->nullable()->after('click_count');
                $table->timestamp('unsubscribed_at')->nullable()->after('first_clicked_at');
                $table->unique('token');
                $table->unique(['campaign_id', 'email']);
                $table->index(['status', 'id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('broadcast_logs', 'token')) {
            Schema::table('broadcast_logs', function (Blueprint $table) {
                $table->dropUnique(['token']);
                $table->dropUnique(['campaign_id', 'email']);
                $table->dropIndex(['status', 'id']);
                $table->dropColumn(['subscriber_id', 'kind', 'email', 'first_name', 'last_name', 'company', 'locale', 'token', 'sent_at',
                    'first_opened_at', 'click_count', 'first_clicked_at', 'unsubscribed_at']);
            });
        }
        if (Schema::hasColumn('broadcast_campaigns', 'layout')) {
            Schema::table('broadcast_campaigns', function (Blueprint $table) {
                $table->dropIndex(['status', 'scheduled_at']);
                $table->dropColumn(['layout', 'content', 'greeting', 'segment_id', 'audience', 'scheduled_at', 'started_at', 'completed_at', 'updated_by', 'sent_by']);
            });
        }
        Schema::dropIfExists('broadcast_clicks');
        Schema::dropIfExists('broadcast_links');
        Schema::dropIfExists('broadcast_segments');
        Schema::dropIfExists('marketing_subscribers');
        Schema::dropIfExists('marketing_suppressions');
        // The widened enums, the nullable user_id and the 'both' language stay: rows written since may use them
    }
};
