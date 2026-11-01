<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The team of an opportunity: staff chosen when it is opened (or later, on its page) who can open it and work
 * on it even when their role has no Opportunities permission (docs/OPPORTUNITIES.md 8.1). The lead is not a
 * row here: being the lead already grants everything a member gets, and more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_members', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('opportunity_id', 36);
            $table->char('user_id', 36);
            $table->char('added_by', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['opportunity_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_members');
    }
};
