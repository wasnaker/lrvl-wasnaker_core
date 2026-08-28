<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk payment gateway abstraction.
 *
 * Diadopsi dari pola `gateways/` PerfexCRM (Stripe, Paypal, Mollie, dst).
 * Core hanya expose abstraction; setiap gateway diaktifkan konfiguratif.
 *
 * @group api/v1
     * @subgroup Payment
 */
class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payment
    ) {}

    /**
     * List gateway yang terkonfigurasi.
     *
     * @authenticated
     *
     * @response scenario=success {
     *   "data": [
     *     {"name": "stripe", "configured": true}
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $gateways = collect($this->payment->availableGateways())->map(fn ($g) => [
            'name' => $g->getName(),
            'configured' => $g->isConfigured(),
        ]);

        return response()->json(['data' => $gateways]);
    }

    /**
     * Create payment intent via gateway.
     *
     * @authenticated
     *
     * @bodyParam gateway string required Nama gateway. Example: stripe
     * @bodyParam amount int required Amount dalam satuan terkecil. Example: 50000
     * @bodyParam currency string required Currency code. Example: IDR
     * @bodyParam metadata array<string, mixed> optional Metadata tambahan.
     *
     * @response scenario=success {
     *   "success": true,
     *   "data": {"id": "pi_...", "client_secret": "pi_..._secret_..."}
     * }
     */
    public function createIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => 'required|string',
            'amount' => 'required|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'metadata' => 'sometimes|array',
        ]);

        $result = $this->payment->createPaymentIntent(
            $validated['gateway'],
            [
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'IDR',
                'metadata' => $validated['metadata'] ?? [],
            ]
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Webhook handler untuk payment gateway.
     *
     * @response scenario=success {"success": true}
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $payload = [
            'body' => $request->getContent(),
            'signature' => $request->header('X-Webhook-Signature') ?? '',
        ];

        $result = $this->payment->handleWebhook($gateway, $payload);

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
