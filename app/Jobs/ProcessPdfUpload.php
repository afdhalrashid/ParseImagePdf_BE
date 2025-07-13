<?php

namespace App\Jobs;

use App\Models\PdfUpload;
use App\Models\UserQuota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class ProcessPdfUpload implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes
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
            Log::info("Processing PDF upload: {$this->pdfUpload->id}");

            $this->pdfUpload->update(['status' => 'processing']);

            // Get file path
            $filePath = Storage::path($this->pdfUpload->stored_filename);

            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            // Parse PDF and extract text
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $extractedText = $pdf->getText();

            // Get metadata
            $metadata = [
                'pages' => count($pdf->getPages()),
                'title' => $pdf->getDetails()['Title'] ?? null,
                'author' => $pdf->getDetails()['Author'] ?? null,
                'creator' => $pdf->getDetails()['Creator'] ?? null,
                'producer' => $pdf->getDetails()['Producer'] ?? null,
                'creation_date' => $pdf->getDetails()['CreationDate'] ?? null,
                'modification_date' => $pdf->getDetails()['ModDate'] ?? null,
            ];

            // Update upload record
            $this->pdfUpload->update([
                'status' => 'completed',
                'extracted_text' => $extractedText,
                'metadata' => $metadata,
                'completed_at' => now(),
            ]);

            // Update user quota
            $quota = $this->pdfUpload->user->quota;
            if ($quota) {
                $quota->increment('used_storage', $this->pdfUpload->file_size);
            }

            Log::info("Successfully processed PDF upload: {$this->pdfUpload->id}");

        } catch (\Exception $e) {
            Log::error("Failed to process PDF upload {$this->pdfUpload->id}: " . $e->getMessage());

            $this->pdfUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("PDF processing job failed for upload {$this->pdfUpload->id}: " . $exception->getMessage());

        $this->pdfUpload->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
