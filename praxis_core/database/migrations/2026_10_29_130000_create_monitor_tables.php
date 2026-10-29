<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HQ System Health monitor (app/Support/Monitor):
 *   monitor_error_groups  one row per kind of error (same class, message shape and place)
 *   monitor_error_events  each time it happened, with the request (latest 50 kept per group)
 *   monitor_state         heartbeat, log read positions, alert throttles
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_error_groups', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 40)->unique();
            $table->string('source', 20);                  // exception | log | php
            $table->string('level', 20);                   // warning | error | critical ...
            $table->string('exception_class', 255)->nullable();
            $table->text('message');
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('status', 20)->default('new')->index();   // new | investigating | fixed | ignored
            $table->text('notes')->nullable();
            $table->mediumText('trace')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('monitor_error_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('monitor_error_groups')->cascadeOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->string('url', 1000)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('user_id', 36)->nullable();
            $table->string('component', 255)->nullable();
            $table->text('context')->nullable();
        });

        Schema::create('monitor_state', function (Blueprint $table) {
            $table->string('key', 191)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_error_events');
        Schema::dropIfExists('monitor_error_groups');
        Schema::dropIfExists('monitor_state');
    }
};
