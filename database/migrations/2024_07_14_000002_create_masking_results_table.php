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
        Schema::create('masking_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('masking_job_id');
            $table->string('algorithm_name');
            $table->string('library_used');
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->integer('processing_time')->default(0); // milliseconds
            $table->bigInteger('file_size')->default(0); // bytes
            $table->integer('words_masked_count')->default(0);
            $table->string('masked_file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('masking_job_id')->references('id')->on('masking_jobs')->onDelete('cascade');
            $table->index(['masking_job_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('masking_results');
    }
};
