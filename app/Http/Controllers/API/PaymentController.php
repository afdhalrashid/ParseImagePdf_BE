<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Omnipay\Omnipay;

class PaymentController extends Controller
{
    private $gateway;

    public function __construct()
    {
        $this->gateway = Omnipay::create('PayPal_Rest');
        $this->gateway->setClientId(config('services.paypal.client_id'));
        $this->gateway->setSecret(config('services.paypal.client_secret'));
        $this->gateway->setTestMode(config('services.paypal.mode') === 'sandbox');
    }

    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'storage_mb' => 'required|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $storageMB = $request->storage_mb;

        // Calculate price (e.g., $0.10 per MB)
        $pricePerMB = 0.10;
        $amount = $storageMB * $pricePerMB;

        try {
            // Create payment record
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_id' => '', // Will be set after PayPal response
                'amount' => $amount,
                'currency' => 'USD',
                'additional_storage_mb' => $storageMB,
                'status' => 'pending',
            ]);

            // Create PayPal payment
            $response = $this->gateway->purchase([
                'amount' => $amount,
                'currency' => 'USD',
                'returnUrl' => route('api.payment.success', $payment->id),
                'cancelUrl' => route('api.payment.cancel', $payment->id),
                'description' => "Additional storage: {$storageMB}MB",
                'metadata' => [
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                    'storage_mb' => $storageMB,
                ]
            ])->send();

            if ($response->isRedirect()) {
                // Update payment with PayPal payment ID
                $payment->update([
                    'payment_id' => $response->getTransactionReference(),
                    'payment_details' => $response->getData(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment created successfully',
                    'data' => [
                        'payment' => $payment,
                        'approval_url' => $response->getRedirectUrl(),
                        'payment_id' => $response->getTransactionReference(),
                    ]
                ]);
            } else {
                $payment->update([
                    'status' => 'failed',
                    'payment_details' => $response->getData(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment creation failed',
                    'error' => $response->getMessage()
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('PayPal payment creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Payment creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleSuccess(Request $request, Payment $payment)
    {
        $paymentId = $request->get('paymentId');
        $payerId = $request->get('PayerID');

        if (!$paymentId || !$payerId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing payment parameters'
            ], 400);
        }

        try {
            // Complete the payment
            $response = $this->gateway->completePurchase([
                'paymentId' => $paymentId,
                'payerId' => $payerId,
            ])->send();

            if ($response->isSuccessful()) {
                $data = $response->getData();

                $payment->update([
                    'payer_id' => $payerId,
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_details' => $data,
                ]);

                // Update user quota
                $quota = $payment->user->quota ?? $payment->user->createQuotaIfNotExists();
                $additionalBytes = $payment->additional_storage_bytes;
                $quota->increment('max_storage', $additionalBytes);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'payment' => $payment,
                        'transaction_id' => $data['id'] ?? null,
                    ]
                ]);
            } else {
                $payment->update([
                    'status' => 'failed',
                    'payment_details' => $response->getData(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment completion failed',
                    'error' => $response->getMessage()
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('PayPal payment completion failed: ' . $e->getMessage());

            $payment->update([
                'status' => 'failed',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment completion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleCancel(Request $request, Payment $payment)
    {
        $payment->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled',
            'data' => $payment
        ]);
    }

    public function webhook(Request $request)
    {
        // PayPal webhook handler
        Log::info('PayPal webhook received', $request->all());

        $eventType = $request->get('event_type');

        switch ($eventType) {
            case 'PAYMENT.SALE.COMPLETED':
                $this->handlePaymentCompleted($request->all());
                break;
            case 'PAYMENT.SALE.DENIED':
                $this->handlePaymentDenied($request->all());
                break;
            default:
                Log::info("Unhandled PayPal webhook event: {$eventType}");
        }

        return response()->json(['status' => 'success']);
    }

    private function handlePaymentCompleted($data)
    {
        $paymentId = $data['resource']['parent_payment'] ?? null;

        if ($paymentId) {
            $payment = Payment::where('payment_id', $paymentId)->first();

            if ($payment && $payment->status === 'pending') {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_details' => $data,
                ]);

                // Update user quota
                $quota = $payment->user->quota ?? $payment->user->createQuotaIfNotExists();
                $additionalBytes = $payment->additional_storage_bytes;
                $quota->increment('max_storage', $additionalBytes);

                Log::info("Payment {$payment->id} completed via webhook");
            }
        }
    }

    private function handlePaymentDenied($data)
    {
        $paymentId = $data['resource']['parent_payment'] ?? null;

        if ($paymentId) {
            $payment = Payment::where('payment_id', $paymentId)->first();

            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'payment_details' => $data,
                ]);

                Log::info("Payment {$payment->id} denied via webhook");
            }
        }
    }

    public function index(Request $request)
    {
        $payments = $request->user()
            ->payments()
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    public function show(Request $request, Payment $payment)
    {
        if ($payment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }
}
