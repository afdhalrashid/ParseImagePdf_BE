<?php

namespace App\Jobs;

use App\Models\PdfChunk;
use App\Models\PdfUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessChunkedUpload implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 120; // 2 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PdfChunk $pdfChunk,
        public string $tempFilePath
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Processing chunk {$this->pdfChunk->chunk_number} for upload {$this->pdfChunk->pdf_upload_id}");

            // Verify file exists
            if (!file_exists($this->tempFilePath)) {
                throw new \Exception("Temporary chunk file not found: {$this->tempFilePath}");
            }

            // Generate storage path for chunk
            $uploadSession = $this->pdfChunk->pdfUpload->upload_session_id;
            $chunkPath = "chunks/{$uploadSession}/chunk_{$this->pdfChunk->chunk_number}";

            // Store chunk file
            $chunkContent = file_get_contents($this->tempFilePath);
            Storage::put($chunkPath, $chunkContent);

            // Verify chunk hash
            $actualHash = hash('sha256', $chunkContent);
            if ($actualHash !== $this->pdfChunk->chunk_hash) {
                throw new \Exception("Chunk hash mismatch. Expected: {$this->pdfChunk->chunk_hash}, Got: {$actualHash}");
            }

            // Update chunk status
            $this->pdfChunk->update([
                'status' => 'uploaded',
                'stored_path' => $chunkPath,
                'uploaded_at' => now(),
            ]);

            // Update upload progress
            $upload = $this->pdfChunk->pdfUpload;
            $upload->increment('uploaded_chunks');

            // Check if all chunks are uploaded
            if ($upload->uploaded_chunks >= $upload->total_chunks) {
                Log::info("All chunks uploaded for upload {$upload->id}, dispatching merge job");
                MergeChunkedFile::dispatch($upload);
            }

            // Clean up temporary file
            if (file_exists($this->tempFilePath)) {
                unlink($this->tempFilePath);
            }

            Log::info("Successfully processed chunk {$this->pdfChunk->chunk_number} for upload {$this->pdfChunk->pdf_upload_id}");

        } catch (\Exception $e) {
            Log::error("Failed to process chunk {$this->pdfChunk->chunk_number} for upload {$this->pdfChunk->pdf_upload_id}: " . $e->getMessage());

            $this->pdfChunk->update([
                'status' => 'failed',
            ]);

            // Clean up temporary file
            if (file_exists($this->tempFilePath)) {
                unlink($this->tempFilePath);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Chunk processing job failed for chunk {$this->pdfChunk->chunk_number}: " . $exception->getMessage());

        $this->pdfChunk->update([
            'status' => 'failed',
        ]);

        // Clean up temporary file
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
    }
}
