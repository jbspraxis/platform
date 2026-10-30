<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HQ Mail PR 2 (docs/MAIL.md): senders whose pictures a person chose to always load ("Always for this
 * sender" in the reader). Per person: one person's choice never loads pictures for someone else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_image_trust', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->string('sender', 255); // lower-case address
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->unique(['user_id', 'sender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_image_trust');
    }
};
