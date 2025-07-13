<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaskingJob;
use App\Models\MaskingResult;
use App\Services\PDFMaskingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MaskingController extends Controller
{
    protected $maskingService;

    public function __construct(PDFMaskingService $maskingService)
    {
        $this->maskingService = $maskingService;
    }

    /**
     * Process PDF masking with multiple algorithms
     */
    public function processMasking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB max
            'words_to_mask' => 'required|json',
            'algorithms' => 'required|json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $wordsToMask = json_decode($request->input('words_to_mask'), true);
            $algorithms = json_decode($request->input('algorithms'), true);

            // Validate words count (max 5)
            if (count($wordsToMask) > 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 5 words allowed for masking'
                ], 422);
            }

            // Store the original file
            $originalPath = $file->store('masking/originals', 'public');

            // Create masking job
            $jobId = Str::uuid();
            $maskingJob = MaskingJob::create([
                'id' => $jobId,
                'user_id' => auth()->id(),
                'original_file_path' => $originalPath,
                'original_filename' => $file->getClientOriginalName(),
                'words_to_mask' => $wordsToMask,
                'algorithms' => $algorithms,
                'status' => 'processing'
            ]);

            // Process masking with different algorithms
            $results = [];
            foreach ($algorithms as $algorithm) {
                try {
                    $result = $this->maskingService->processWithAlgorithm(
                        $originalPath,
                        $wordsToMask,
                        $algorithm,
                        $jobId
                    );

                    // Create result record
                    $maskingResult = MaskingResult::create([
                        'id' => Str::uuid(),
                        'masking_job_id' => $jobId,
                        'algorithm_name' => $result['algorithm_name'],
                        'library_used' => $result['library_used'],
                        'status' => $result['status'],
                        'processing_time' => $result['processing_time'],
                        'file_size' => $result['file_size'] ?? 0,
                        'words_masked_count' => $result['words_masked_count'],
                        'masked_file_path' => $result['masked_file_path'] ?? null,
                        'error_message' => $result['error_message'] ?? null
                    ]);

                    $results[] = $this->formatMaskingResult($maskingResult);
                } catch (\Exception $e) {
                    // Create failed result record
                    $maskingResult = MaskingResult::create([
                        'id' => Str::uuid(),
                        'masking_job_id' => $jobId,
                        'algorithm_name' => $this->getAlgorithmName($algorithm),
                        'library_used' => $this->getLibraryName($algorithm),
                        'status' => 'failed',
                        'processing_time' => 0,
                        'file_size' => 0,
                        'words_masked_count' => 0,
                        'error_message' => $e->getMessage()
                    ]);

                    $results[] = $this->formatMaskingResult($maskingResult);
                }
            }

            // Update job status
            $maskingJob->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'PDF masking completed',
                'data' => [
                    'job_id' => $jobId,
                    'results' => $results
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process PDF masking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get masking results for a specific job
     */
    public function getMaskingResults($jobId)
    {
        try {
            $job = MaskingJob::where('id', $jobId)
                ->where('user_id', auth()->id())
                ->with('results')
                ->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masking job not found'
                ], 404);
            }

            $results = $job->results->map(function ($result) {
                return $this->formatMaskingResult($result);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'results' => $results
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get masking results: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download masked PDF
     */
    public function downloadMaskedPdf($resultId)
    {
        try {
            $result = MaskingResult::where('id', $resultId)
                ->whereHas('maskingJob', function ($query) {
                    $query->where('user_id', auth()->id());
                })
                ->first();

            if (!$result || !$result->masked_file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masked PDF not found'
                ], 404);
            }

            if (!Storage::disk('public')->exists($result->masked_file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masked PDF file not found on disk'
                ], 404);
            }

            $filename = "masked_{$result->algorithm_name}_{$result->id}.pdf";

            return Storage::disk('public')->download($result->masked_file_path, $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download masked PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete masking result
     */
    public function deleteMaskingResult($resultId)
    {
        try {
            $result = MaskingResult::where('id', $resultId)
                ->whereHas('maskingJob', function ($query) {
                    $query->where('user_id', auth()->id());
                })
                ->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masking result not found'
                ], 404);
            }

            // Delete file from storage
            if ($result->masked_file_path && Storage::disk('public')->exists($result->masked_file_path)) {
                Storage::disk('public')->delete($result->masked_file_path);
            }

            // Delete record
            $result->delete();

            return response()->json([
                'success' => true,
                'message' => 'Masking result deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete masking result: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get masking history for the authenticated user
     */
    public function getMaskingHistory(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            $results = MaskingResult::whereHas('maskingJob', function ($query) {
                    $query->where('user_id', auth()->id());
                })
                ->with('maskingJob')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $formattedResults = $results->getCollection()->map(function ($result) {
                return $this->formatMaskingResult($result);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $formattedResults,
                    'total' => $results->total(),
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get masking history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available masking algorithms
     */
    public function getAvailableAlgorithms()
    {
        $algorithms = [
            [
                'id' => 'regex_replace',
                'name' => 'Regex Replace',
                'description' => 'Simple text replacement using regular expressions',
                'library' => 'Python re module'
            ],
            [
                'id' => 'pypdf_redaction',
                'name' => 'PyPDF Redaction',
                'description' => 'PDF-level redaction using PyPDF library',
                'library' => 'PyPDF2/PyPDF4'
            ],
            [
                'id' => 'reportlab_overlay',
                'name' => 'ReportLab Overlay',
                'description' => 'Black box overlay using ReportLab',
                'library' => 'ReportLab'
            ],
            [
                'id' => 'fitz_redaction',
                'name' => 'PyMuPDF Redaction',
                'description' => 'Advanced redaction using PyMuPDF (Fitz)',
                'library' => 'PyMuPDF (Fitz)'
            ],
            [
                'id' => 'pdfplumber_mask',
                'name' => 'PDFPlumber Mask',
                'description' => 'Text extraction and replacement with PDFPlumber',
                'library' => 'PDFPlumber'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => ['algorithms' => $algorithms]
        ]);
    }

    /**
     * Format masking result for API response
     */
    private function formatMaskingResult(MaskingResult $result)
    {
        return [
            'id' => $result->id,
            'job_id' => $result->masking_job_id,
            'algorithm_name' => $result->algorithm_name,
            'library_used' => $result->library_used,
            'status' => $result->status,
            'processing_time' => $result->processing_time,
            'file_size' => $result->file_size,
            'words_masked_count' => $result->words_masked_count,
            'download_url' => $result->status === 'completed' ? route('api.masking.download', $result->id) : null,
            'preview_url' => $result->status === 'completed' && $result->masked_file_path ?
                Storage::disk('public')->url($result->masked_file_path) : null,
            'error_message' => $result->error_message,
            'created_at' => $result->created_at->toISOString()
        ];
    }

    /**
     * Get algorithm name by ID
     */
    private function getAlgorithmName($algorithmId)
    {
        $names = [
            'regex_replace' => 'Regex Replace',
            'pypdf_redaction' => 'PyPDF Redaction',
            'reportlab_overlay' => 'ReportLab Overlay',
            'fitz_redaction' => 'PyMuPDF Redaction',
            'pdfplumber_mask' => 'PDFPlumber Mask'
        ];

        return $names[$algorithmId] ?? 'Unknown Algorithm';
    }

    /**
     * Get library name by algorithm ID
     */
    private function getLibraryName($algorithmId)
    {
        $libraries = [
            'regex_replace' => 'Python re module',
            'pypdf_redaction' => 'PyPDF2/PyPDF4',
            'reportlab_overlay' => 'ReportLab',
            'fitz_redaction' => 'PyMuPDF (Fitz)',
            'pdfplumber_mask' => 'PDFPlumber'
        ];

        return $libraries[$algorithmId] ?? 'Unknown Library';
    }
}
