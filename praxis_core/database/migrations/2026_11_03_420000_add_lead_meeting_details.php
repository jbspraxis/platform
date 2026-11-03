<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads PR 2 (docs/LEADS.md section 9): what a lead meeting keeps beyond an opportunity meeting.
 *
 *   meetings.location     an address, for a meeting in person (the Google invite then has no Meet link)
 *   meetings.typed_notes  what the team typed about the meeting, next to the transcript and the AI's notes
 *
 * Both stay with the meeting when an opportunity is opened from it, so the opportunity's Meetings tab shows them too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'location')) {
                $table->string('location', 255)->nullable()->after('meet_code')->comment('An address, for a meeting in person');
            }
            if (! Schema::hasColumn('meetings', 'typed_notes')) {
                $table->text('typed_notes')->nullable()->after('agenda')->comment('What the team typed about the meeting');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['location', 'typed_notes']);
        });
    }
};
