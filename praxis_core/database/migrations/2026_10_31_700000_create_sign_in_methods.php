<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner, 31 Oct 2026: staff (HQ) and clients (Client Portal) can sign in with a passkey (fingerprint, face or
 * screen lock) or a 4-digit PIN as well as their password (docs/SIGN_IN.md).
 *
 * Both tables serve both sign-ins: `guard` says whose account `owner_id` is (web = users, client = client_users),
 * so neither table has a foreign key; an account's rows go when the account is deleted (App\Support\SignIn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table) {
                $table->id();
                $table->string('guard', 10);
                $table->char('owner_id', 36);
                // base64url, as the browser names it; unique across both sign-ins
                $table->string('credential_id', 255)->unique();
                // The public key as PEM (SubjectPublicKeyInfo), ready for openssl_verify
                $table->text('public_key');
                $table->smallInteger('algorithm');
                $table->unsignedBigInteger('sign_count')->default(0);
                $table->json('transports')->nullable();
                $table->boolean('backed_up')->default(false);
                $table->string('name', 60);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->index(['guard', 'owner_id']);
            });
        }

        if (! Schema::hasTable('sign_in_pins')) {
            Schema::create('sign_in_pins', function (Blueprint $table) {
                $table->id();
                $table->string('guard', 10);
                $table->char('owner_id', 36);
                // Null once wrong tries switched it off: the person sets a new one
                $table->string('pin_hash', 255)->nullable();
                $table->unsignedSmallInteger('failed_count')->default(0);
                $table->timestamp('last_failed_at')->nullable();
                // Times of wrong tries in the last 30 days (at most 20 kept)
                $table->json('recent_failures')->nullable();
                $table->timestamp('switched_off_at')->nullable();
                $table->timestamps();
                $table->unique(['guard', 'owner_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_in_pins');
        Schema::dropIfExists('passkeys');
    }
};
