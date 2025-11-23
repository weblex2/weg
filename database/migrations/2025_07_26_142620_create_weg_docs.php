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
        Schema::create('weg_docs', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable()->unique();
            $table->string('name');         // Original Dateiname
            $table->string('mime_type');    // z. B. 'application/pdf'
            $table->unsignedBigInteger('size'); // in Bytes
            $table->binary('content');
            $table->datetime('received')->nullable(); // Größe der Anlage in Bytes
            $table->timestamps();
        });

        // Nachträglich in LONGBLOB umwandeln
        DB::statement("ALTER TABLE documents MODIFY content LONGBLOB");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weg_docs');
    }
};
