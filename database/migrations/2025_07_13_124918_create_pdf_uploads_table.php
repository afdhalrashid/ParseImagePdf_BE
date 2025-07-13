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
        Schema::create('pdf_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_filename')->unique();
            $table->string('file_hash');
            $table->bigInteger('file_size'); // in bytes
            $table->string('mime_type');
            $table->enum('status', ['uploading', 'processing', 'completed', 'failed'])->default('uploading');
            $table->boolean('is_chunked')->default(false);
            $table->integer('total_chunks')->nullable();
            $table->integer('uploaded_chunks')->default(0);
            $table->string('upload_session_id')->nullable();
            $table->text('extracted_text')->nullable();
            $table->json('metadata')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('upload_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_uploads');
    }
};
