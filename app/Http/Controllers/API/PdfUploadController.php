<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessChunkedUpload;
use App\Jobs\ProcessPdfUpload;
use App\Models\PdfChunk;
use App\Models\PdfUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PdfUploadController extends Controller
{
    public function index(Request $request)
    {
        $uploads = $request->user()
            ->pdfUploads()
            ->with('chunks')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $uploads
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf|max:' . (config('app.max_file_size_mb', 100) * 1024),
            'filename' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $file = $request->file('file');
        $fileSize = $file->getSize();

        // Check user quota
        $quota = $user->quota ?? $user->createQuotaIfNotExists();
        if (!$quota->canUpload($fileSize)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient storage quota',
                'data' => [
                    'required' => $fileSize,
                    'available' => $quota->remaining_storage,
                    'used' => $quota->used_storage,
                    'max' => $quota->max_storage
                ]
            ], 403);
        }

        // Generate file hash
        $fileHash = hash_file('sha256', $file->getPathname());

        // Check for duplicate
        $existingUpload = PdfUpload::where('user_id', $user->id)
            ->where('file_hash', $fileHash)
            ->first();

        if ($existingUpload) {
            return response()->json([
                'success' => false,
                'message' => 'File already uploaded',
                'data' => $existingUpload
            ], 409);
        }

        // Determine if file should be chunked
        $chunkSizeMB = config('app.chunk_size_mb', 20);
        $chunkSizeBytes = $chunkSizeMB * 1048576;
        $isChunked = $fileSize > $chunkSizeBytes;

        // Generate stored filename
        $storedFilename = Str::uuid() . '.pdf';

        // Create upload record
        $upload = PdfUpload::create([
            'user_id' => $user->id,
            'original_filename' => $request->filename,
            'stored_filename' => $storedFilename,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
            'mime_type' => $file->getMimeType(),
            'is_chunked' => $isChunked,
            'total_chunks' => $isChunked ? ceil($fileSize / $chunkSizeBytes) : 1,
            'upload_session_id' => $isChunked ? Str::uuid() : null,
        ]);

        if ($isChunked) {
            return response()->json([
                'success' => true,
                'message' => 'Upload initialized. Use chunked upload.',
                'data' => [
                    'upload' => $upload,
                    'chunk_size' => $chunkSizeBytes,
                    'total_chunks' => $upload->total_chunks
                ]
            ]);
        } else {
            // Store file directly
            $path = $file->storeAs('pdfs', $storedFilename);
            $upload->update(['stored_filename' => $path]);

            // Dispatch processing job
            ProcessPdfUpload::dispatch($upload);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $upload
            ]);
        }
    }

    public function uploadChunk(Request $request, PdfUpload $upload)
    {
        $validator = Validator::make($request->all(), [
            'chunk_number' => 'required|integer|min:1',
            'chunk' => 'required|file',
            'chunk_hash' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($upload->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!$upload->is_chunked) {
            return response()->json([
                'success' => false,
                'message' => 'Upload is not configured for chunking'
            ], 400);
        }

        $chunkNumber = $request->chunk_number;
        $chunkFile = $request->file('chunk');
        $chunkHash = $request->chunk_hash;

        // Verify chunk hash
        $actualHash = hash_file('sha256', $chunkFile->getPathname());
        if ($actualHash !== $chunkHash) {
            return response()->json([
                'success' => false,
                'message' => 'Chunk hash mismatch'
            ], 400);
        }

        // Check if chunk already exists
        $existingChunk = PdfChunk::where('pdf_upload_id', $upload->id)
            ->where('chunk_number', $chunkNumber)
            ->first();

        if ($existingChunk) {
            return response()->json([
                'success' => false,
                'message' => 'Chunk already uploaded'
            ], 409);
        }

        // Create chunk record
        $chunk = PdfChunk::create([
            'pdf_upload_id' => $upload->id,
            'chunk_number' => $chunkNumber,
            'chunk_size' => $chunkFile->getSize(),
            'chunk_hash' => $chunkHash,
            'stored_path' => '', // Will be set by job
        ]);

        // Save chunk to temporary location
        $tempPath = storage_path("app/temp/chunk_{$upload->upload_session_id}_{$chunkNumber}");
        $chunkFile->move(dirname($tempPath), basename($tempPath));

        // Dispatch chunk processing job
        ProcessChunkedUpload::dispatch($chunk, $tempPath);

        return response()->json([
            'success' => true,
            'message' => 'Chunk uploaded successfully',
            'data' => [
                'chunk' => $chunk,
                'progress' => $upload->fresh()->upload_progress
            ]
        ]);
    }

    public function show(Request $request, PdfUpload $upload)
    {
        if ($upload->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $upload->load('chunks');

        return response()->json([
            'success' => true,
            'data' => $upload
        ]);
    }

    public function destroy(Request $request, PdfUpload $upload)
    {
        if ($upload->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Delete file from storage
        if ($upload->stored_filename && Storage::exists($upload->stored_filename)) {
            Storage::delete($upload->stored_filename);
        }

        // Delete chunks if any
        if ($upload->is_chunked) {
            foreach ($upload->chunks as $chunk) {
                if ($chunk->stored_path && Storage::exists($chunk->stored_path)) {
                    Storage::delete($chunk->stored_path);
                }
            }
        }

        // Update user quota
        $quota = $upload->user->quota;
        if ($quota && $upload->isCompleted()) {
            $quota->decrement('used_storage', $upload->file_size);
        }

        $upload->delete();

        return response()->json([
            'success' => true,
            'message' => 'Upload deleted successfully'
        ]);
    }
}
