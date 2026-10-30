<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| HQ Mail PR 3 (docs/MAIL.md): the numbers a person shows in their email signature. Everything else in the
| signature comes from HR records (users, mdm_employees, staff roles) and the company settings (mail_settings
| signature.company), so this is the only part a person edits (owner decision 5).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_signatures', function (Blueprint $table) {
            $table->char('user_id', 36)->primary();
            $table->string('phone', 30)->nullable();   // office line; empty = the HR record's phone
            $table->string('mobile', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_signatures');
    }
};
