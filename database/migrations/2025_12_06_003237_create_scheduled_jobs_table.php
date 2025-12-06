<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('entity_id'); // z.B. light.wohnzimmer
            $table->string('action'); // turn_on, turn_off, toggle
            $table->json('parameters')->nullable(); // Brightness, color, etc.
            $table->time('scheduled_time');
            $table->json('weekdays')->nullable(); // [1,2,3,4,5] für Mo-Fr
            $table->boolean('is_active')->default(true);
            $table->boolean('is_repeating')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_jobs');
    }
};
