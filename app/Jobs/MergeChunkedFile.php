<?php

namespace App\Jobs;

use App\Models\PdfUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MergeChunkedFile implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PdfUpload $pdfUpload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Merging chunks for upload {$this->pdfUpload->id}");

            // Get all chunks ordered by chunk number
            $chunks = $this->pdfUpload->chunks()
                ->where('status', 'uploaded')
                ->orderBy('chunk_number')
                ->get();

            if ($chunks->count() !== $this->pdfUpload->total_chunks) {
                throw new \Exception("Missing chunks. Expected: {$this->pdfUpload->total_chunks}, Found: {$chunks->count()}");
            }

            // Create final file path
            $finalPath = "pdfs/{$this->pdfUpload->stored_filename}";
            $tempMergedFile = storage_path("app/temp/merged_{$this->pdfUpload->upload_session_id}.pdf");

            // Ensure temp directory exists
            $tempDir = dirname($tempMergedFile);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Merge chunks
            $mergedHandle = fopen($tempMergedFile, 'wb');
            if (!$mergedHandle) {
                throw new \Exception("Cannot create temporary merged file");
            }

            $totalSize = 0;
            foreach ($chunks as $chunk) {
                $chunkContent = Storage::get($chunk->stored_path);
                if ($chunkContent === false) {
                    throw new \Exception("Cannot read chunk {$chunk->chunk_number}");
                }

                fwrite($mergedHandle, $chunkContent);
                $totalSize += strlen($chunkContent);
            }

            fclose($mergedHandle);

            // Verify merged file size
            if ($totalSize !== $this->pdfUpload->file_size) {
                throw new \Exception("Merged file size mismatch. Expected: {$this->pdfUpload->file_size}, Got: {$totalSize}");
            }

            // Verify file hash
            $actualHash = hash_file('sha256', $tempMergedFile);
            if ($actualHash !== $this->pdfUpload->file_hash) {
                throw new \Exception("Merged file hash mismatch. Expected: {$this->pdfUpload->file_hash}, Got: {$actualHash}");
            }

            // Move to final location
            Storage::put($finalPath, file_get_contents($tempMergedFile));

            // Update upload status
            $this->pdfUpload->update([
                'status' => 'processing',
                'stored_filename' => $finalPath,
            ]);

            // Mark all chunks as processed
            $this->pdfUpload->chunks()->update(['status' => 'processed']);

            // Clean up chunk files and temp merged file
            foreach ($chunks as $chunk) {
                Storage::delete($chunk->stored_path);
            }
            unlink($tempMergedFile);

            // Clean up chunk directory
            $chunkDir = "chunks/{$this->pdfUpload->upload_session_id}";
            Storage::deleteDirectory($chunkDir);

            // Dispatch PDF processing job
            ProcessPdfUpload::dispatch($this->pdfUpload);

            Log::info("Successfully merged chunks for upload {$this->pdfUpload->id}");

        } catch (\Exception $e) {
            Log::error("Failed to merge chunks for upload {$this->pdfUpload->id}: " . $e->getMessage());

            $this->pdfUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Clean up temp file if it exists
            $tempMergedFile = storage_path("app/temp/merged_{$this->pdfUpload->upload_session_id}.pdf");
            if (file_exists($tempMergedFile)) {
                unlink($tempMergedFile);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Chunk merging job failed for upload {$this->pdfUpload->id}: " . $exception->getMessage());

        $this->pdfUpload->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
