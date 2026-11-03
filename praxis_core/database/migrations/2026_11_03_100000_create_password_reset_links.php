<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner, 3 Nov 2026: "Forgot your password?" on HQ and the Client Portal emails a reset link (docs/SIGN_IN.md
 * section 8). Before this, nothing was sent, and HQ's request overwrote a pending invitation's token.
 *
 * One table for both sign-ins, like passkeys and PINs: `guard` says whose account `owner_id` is (web = users,
 * client = client_users), so there is no foreign key. Only a hash of the token is kept: the link itself is in
 * the email alone. Invitations keep their own `invitation_token`, so a reset never touches one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_links')) {
            Schema::create('password_reset_links', function (Blueprint $table) {
                $table->id();
                $table->string('guard', 10);
                $table->char('owner_id', 36);
                // sha256 of the token in the link
                $table->char('token_hash', 64)->unique();
                // The staff member who sent it from Accounts; null when the person asked on the sign-in page
                $table->char('requested_by', 36)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['guard', 'owner_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_links');
    }
};
