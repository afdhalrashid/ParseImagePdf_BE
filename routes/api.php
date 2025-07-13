<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MaskingController;
use App\Http\Controllers\API\PdfUploadController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\UserQuotaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// PayPal webhook route (public)
Route::post('/paypal/webhook', [PaymentController::class, 'webhook']);

// Payment success/cancel routes (public for PayPal redirects)
Route::get('/payment/{payment}/success', [PaymentController::class, 'handleSuccess'])->name('api.payment.success');
Route::get('/payment/{payment}/cancel', [PaymentController::class, 'handleCancel'])->name('api.payment.cancel');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // PDF Uploads
    Route::apiResource('uploads', PdfUploadController::class);
    Route::post('/uploads/{upload}/chunks', [PdfUploadController::class, 'uploadChunk']);

    // User Quota
    Route::get('/quota', [UserQuotaController::class, 'show']);
    Route::get('/quota/stats', [UserQuotaController::class, 'getUploadStats']);
    Route::get('/quota/pricing', [UserQuotaController::class, 'getPricingInfo']);

    // Payments
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show']);
    Route::post('/payments/create', [PaymentController::class, 'createPayment']);

    // PDF Masking
    Route::prefix('masking')->name('api.masking.')->group(function () {
        Route::post('/process', [MaskingController::class, 'processMasking'])->name('process');
        Route::get('/results/{jobId}', [MaskingController::class, 'getMaskingResults'])->name('results');
        Route::get('/download/{resultId}', [MaskingController::class, 'downloadMaskedPdf'])->name('download');
        Route::delete('/results/{resultId}', [MaskingController::class, 'deleteMaskingResult'])->name('delete');
        Route::get('/history', [MaskingController::class, 'getMaskingHistory'])->name('history');
        Route::get('/algorithms', [MaskingController::class, 'getAvailableAlgorithms'])->name('algorithms');
    });
});
