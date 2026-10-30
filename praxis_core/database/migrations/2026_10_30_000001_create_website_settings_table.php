<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website settings (HQ → Marketing → Website settings; app/Support/WebsiteSettings.php):
 * one row per section ('company', 'contact', 'social', 'leadership') with its values as JSON.
 * The marketing site reads the public subset through GET /api/site/v1/settings (docs/SITE_API.md section 4).
 * A missing row means "use the defaults" in WebsiteSettings::defaults().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('key', 64)->primary();
            $table->json('value');
            $table->char('updated_by', 36)->nullable()->index(); // users.id of the last person who saved it
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
