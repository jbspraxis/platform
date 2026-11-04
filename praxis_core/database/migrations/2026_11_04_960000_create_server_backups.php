<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server backups (owner's decisions, 4 Nov 2026; docs/BACKUPS.md): one row per database dump or files archive,
 * from the moment it starts, so HQ › System health shows each site's last backup and its restore test, and a backup
 * that never finished is visible. No secret and no backed-up data is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_backups', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10)->comment('db | files');
            $table->string('site', 60)->comment('praxis, lpc, nfelia, hodlc, smartls, or other/<database>');
            $table->string('source', 190)->comment('The database name, or "files"');
            $table->date('backup_date')->comment('The Lagos date in the file name');
            $table->string('file_name', 190);
            $table->string('status', 10)->default('running')->comment('running | ok | failed');
            $table->string('stage', 20)->nullable()->comment('Where it stopped: dump, restore_test, encrypt, upload, verify, prune');
            $table->text('error')->nullable();
            $table->unsignedBigInteger('plain_bytes')->nullable()->comment('The .sql.gz or .tar.gz before encryption');
            $table->char('plain_sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable()->comment('The encrypted file, as uploaded');
            $table->char('md5', 32)->nullable()->comment('Of the encrypted file; Drive must report the same');
            $table->string('drive_file_id', 100)->nullable();
            $table->string('drive_folder', 20)->nullable()->comment('daily | files');
            $table->string('monthly_file_id', 100)->nullable()->comment('The copy in monthly/, for the first backup of a month');
            $table->json('restore_test')->nullable()->comment('{ok, tables, rows, views, mismatches, seconds} from the weekly test');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'site', 'status', 'finished_at']);
            $table->index(['backup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_backups');
    }
};
