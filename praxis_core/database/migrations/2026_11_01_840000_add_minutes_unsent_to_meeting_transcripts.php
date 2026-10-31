<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The client addresses the minutes didn't reach, so "Send again" retries only those (Transcripts::resendMinutes)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->json('minutes_unsent')->nullable()->after('minutes_sent_at')->comment('Emails the minutes did not reach');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_transcripts', function (Blueprint $table) {
            $table->dropColumn('minutes_unsent');
        });
    }
};
