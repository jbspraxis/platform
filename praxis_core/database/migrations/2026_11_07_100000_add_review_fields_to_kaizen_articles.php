<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insights articles review (HQ → Marketing → Insights articles): what a reviewer needs to answer a contributor.
 *   submitted_locale  the language of the website page the article was sent from ('en' or 'fr'), so the email that
 *                     says it was published, or why it wasn't, is written in the contributor's language
 *   rejected_reason   the reviewer's note to the contributor, kept with the article
 *   rejected_at       when it was turned down
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaizen_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('kaizen_articles', 'submitted_locale')) {
                $table->char('submitted_locale', 2)->nullable()->after('contributor_id');
            }
            if (! Schema::hasColumn('kaizen_articles', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('kaizen_articles', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kaizen_articles', function (Blueprint $table) {
            foreach (['submitted_locale', 'rejected_reason', 'rejected_at'] as $column) {
                if (Schema::hasColumn('kaizen_articles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
