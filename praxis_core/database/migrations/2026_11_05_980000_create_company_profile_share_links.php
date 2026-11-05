<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracked share links for the company profile (docs/COMPANY_PROFILE.md section 14).
 *
 * A link is a long random token that opens the profile PDF on jbspraxis.com with no sign-in. The token is the
 * only secret: it is never the version number, so nobody can walk the versions by guessing, and a link can be
 * revoked or left to expire without touching the profile itself.
 *
 *   company_profile_share_links  one link: who it was made for, which version and language it opens, when it
 *                                stops working, and how often it has been opened
 *   company_profile_share_opens  one row per open that was counted, with the visitor **hashed**, never stored
 *                                in clear, and the user agent reduced to a browser and a platform
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_share_links', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('token', 48)->unique()->comment('What the address carries: random, never the version number');
            $table->char('version_id', 36)->nullable()
                ->comment('A version pinned at the time, or null to follow whatever is published when it is opened');
            $table->string('locale', 2)->default('en');
            $table->string('recipient_name', 150)->nullable()->comment('Who it was made for, so the bell can say');
            $table->string('recipient_company', 200)->nullable();
            $table->timestamp('expires_at')->nullable()->comment('After this the link shows the ended page, never the PDF');
            $table->timestamp('revoked_at')->nullable();
            $table->char('revoked_by', 36)->nullable();
            $table->unsignedInteger('opens')->default(0)->comment('Opens that were counted, after the repeat window');
            $table->timestamp('last_opened_at')->nullable();
            $table->char('created_by', 36);
            $table->timestamps();

            $table->index(['created_by', 'created_at']);
        });

        Schema::create('company_profile_share_opens', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('share_link_id', 36);
            $table->char('visitor', 64)->comment('sha256 of the address and the app key: never the address itself');
            $table->string('agent', 120)->nullable()->comment('Coarse: "Chrome on Windows", never the whole string');
            $table->timestamp('created_at');

            $table->index(['share_link_id', 'created_at']);
            $table->index(['share_link_id', 'visitor', 'created_at'], 'share_opens_debounce');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile_share_opens');
        Schema::dropIfExists('company_profile_share_links');
    }
};
