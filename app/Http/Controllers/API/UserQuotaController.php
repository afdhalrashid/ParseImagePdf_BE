<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserQuotaController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $quota = $user->quota ?? $user->createQuotaIfNotExists();

        return response()->json([
            'success' => true,
            'data' => [
                'quota' => $quota,
                'usage_stats' => [
                    'used_storage_mb' => $quota->used_storage_mb,
                    'max_storage_mb' => $quota->max_storage_mb,
                    'remaining_storage_mb' => round($quota->remaining_storage / 1048576, 2),
                    'usage_percentage' => $quota->max_storage > 0 ? round(($quota->used_storage / $quota->max_storage) * 100, 2) : 0,
                ]
            ]
        ]);
    }

    public function getUploadStats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_uploads' => $user->pdfUploads()->count(),
            'completed_uploads' => $user->pdfUploads()->where('status', 'completed')->count(),
            'failed_uploads' => $user->pdfUploads()->where('status', 'failed')->count(),
            'processing_uploads' => $user->pdfUploads()->whereIn('status', ['uploading', 'processing'])->count(),
            'total_size_bytes' => $user->pdfUploads()->where('status', 'completed')->sum('file_size'),
        ];

        $stats['total_size_mb'] = round($stats['total_size_bytes'] / 1048576, 2);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function getPricingInfo()
    {
        $pricing = [
            'price_per_mb' => 0.10,
            'currency' => 'USD',
            'packages' => [
                [
                    'storage_mb' => 500,
                    'price' => 50.00,
                    'savings' => '0%',
                ],
                [
                    'storage_mb' => 1024,
                    'price' => 92.16,
                    'savings' => '10%',
                ],
                [
                    'storage_mb' => 5120,
                    'price' => 409.60,
                    'savings' => '20%',
                ],
                [
                    'storage_mb' => 10240,
                    'price' => 716.80,
                    'savings' => '30%',
                ],
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $pricing
        ]);
    }
}
