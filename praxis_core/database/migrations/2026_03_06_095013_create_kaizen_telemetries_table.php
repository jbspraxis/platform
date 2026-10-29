<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaizen_telemetries', function (Blueprint $table) {
            $table->id();
            $table->string('identity')->nullable(); // Optional node origin
            $table->string('email')->nullable();    // Optional comm channel
            $table->string('category');             // Friction category
            $table->tinyInteger('severity');        // 1, 2, or 3
            $table->text('description');            // Anomaly description
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaizen_telemetries');
    }
};