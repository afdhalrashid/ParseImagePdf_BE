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
        Schema::create('pdf_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_upload_id')->constrained()->onDelete('cascade');
            $table->integer('chunk_number');
            $table->integer('chunk_size'); // in bytes
            $table->string('chunk_hash');
            $table->string('stored_path');
            $table->enum('status', ['pending', 'uploaded', 'processed', 'failed'])->default('pending');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['pdf_upload_id', 'chunk_number']);
            $table->index(['pdf_upload_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_chunks');
    }
};
