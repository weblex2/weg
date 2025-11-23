<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('weg_doc_attachments', function (Blueprint $table) {
            $table->id(); // Primärschlüssel
            $table->string('message_id')->index(); // Fremdschlüssel, der auf die message_id der weg_docs verweist
            $table->string('filename'); // Name der Anlage
            $table->string('content_type'); // MIME-Typ der Anlage
            $table->longText('content')->nullable(); // Inhalt der Anlage, falls gewünscht
            $table->integer('size')->nullable(); // Größe der Anlage in Bytes
            $table->timestamps(); // created_at und updated_at
        });

        // Füge einen Fremdschlüssel hinzu (optional, falls message_id eindeutig sein soll)
        Schema::table('weg_doc_attachments', function (Blueprint $table) {
            $table->foreign('message_id')->references('message_id')->on('weg_docs')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('weg_doc_attachments');
    }
};
