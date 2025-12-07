<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
        $table->id();
        $table->string('level'); // Log Level (z.B. error, info)
        $table->string('type')->nullable();
        $table->json('context')->nullable();// Zusätzliche Kontextdaten
        $table->text('message'); // Log Message
        $table->timestamps(); // Timestamp
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
