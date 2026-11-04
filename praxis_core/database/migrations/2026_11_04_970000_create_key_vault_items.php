<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings and access › Key vault (docs/KEY_VAULT.md): the owner's own secrets, such as the server-backup private
 * key, locked in the owner's browser with a passphrase that never leaves it (PBKDF2-SHA-256 then AES-256-GCM).
 *
 * The row holds only what a browser needs to unlock it again with that passphrase: the ciphertext, the salt, the
 * IV and the KDF's parameters, plus the item's name and its dates. Nothing on the server can read a secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_vault_items', function (Blueprint $table) {
            $table->char('id', 36)->primary()->comment('Made by the browser: the ciphertext is bound to it (AES-GCM additional data)');
            $table->char('user_id', 36)->comment('The owner who locked it: only they see it, reveal it or delete it');
            $table->string('name', 120)->comment('In plain text, so the owner knows which secret it is: never the secret itself');
            $table->unsignedTinyInteger('format_version')->default(1);
            $table->string('kdf', 20)->comment('PBKDF2-SHA-256');
            $table->unsignedInteger('kdf_iterations')->comment('At least 600,000');
            $table->string('kdf_salt', 64)->comment('Base64, 16 random bytes from the browser');
            $table->string('cipher', 20)->comment('AES-256-GCM');
            $table->string('iv', 32)->comment('Base64, 12 random bytes from the browser');
            $table->text('ciphertext')->comment('Base64: the secret encrypted in the browser, with its 16-byte tag at the end');
            $table->timestamp('last_revealed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_vault_items');
    }
};
