<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client logo library (company-profile/CONTRACT.md, docs/COMPANY_PROFILE.md section 2). One row per
 * organisation whose logo the firm may show, with the three renditions each use needs:
 *
 *   print  SVG, or a JPEG on white. What the company profile PDF and the letterhead can actually draw: the
 *          live PHP has no gd and no imagick, so dompdf cannot read a PNG at all (docs/DOCUMENTS.md section 3).
 *   png    the same artwork as a transparent PNG, for the deck and for anything that can read one.
 *   web    a WebP, which is what jbspraxis.com serves.
 *
 * All three are made in the browser when the logo is uploaded, the way a signature is (docs/DOCUMENTS.md
 * section 5), because no server here can convert an image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_logos', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('key', 80)->unique()->comment('The slug the website asks for: /img/clients/<key>.webp');
            $table->string('name', 150)->comment('The client as it may be named (CONTRACT.md, client naming rules)');
            $table->string('sector', 80)->nullable();
            $table->unsignedInteger('position')->default(0)->comment('The order the wall and the strip show them in');

            $table->boolean('on_profile')->default(true)->comment('Shown on the company profile');
            $table->boolean('on_deck')->default(true)->comment('Shown in the deck');
            $table->boolean('on_website')->default(true)->comment('Shown in the logo strip on jbspraxis.com');

            $table->string('print_path', 255)->comment('Private disk: the SVG or JPEG the PDF draws');
            $table->enum('print_kind', ['svg', 'jpeg'])->default('jpeg');
            $table->string('png_path', 255)->nullable()->comment('Private disk: the transparent PNG');
            $table->string('web_path', 255)->nullable()->comment('Private disk: the WebP the site is given');
            $table->char('web_sha256', 64)->nullable()->comment('The site caches the file under this, so a new upload is fetched once');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            $table->timestamp('retired_at')->nullable()->comment('Retired, not deleted: the history of what was shown stays');
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();

            $table->index(['retired_at', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile_logos');
    }
};
