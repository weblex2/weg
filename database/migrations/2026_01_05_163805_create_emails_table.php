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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 500);
            $table->string('from_email')->index();
            $table->string('from_name')->nullable();
            $table->string('to_email')->index();
            $table->string('to_name')->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('received_at')->index();
            $table->enum('imported_via', ['drag_drop', 'imap'])->default('drag_drop');
            $table->json('topics')->nullable();
            $table->timestamps();

            // Index für schnellere Suche
            $table->index('created_at');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('storage_path');
            $table->timestamps();

            // Index für schnellere Queries
            $table->index('email_id');
            $table->index('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('emails');
    }
};
