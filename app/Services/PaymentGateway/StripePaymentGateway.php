<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * StripePaymentGateway — implementasi PaymentGatewayInterface untuk Stripe.
 *
 * Diadopsi dari `Stripe_core.php` PerfexCRM.
 * REF: docs/analisis-library-perfex.md (gateways/ — ❌ BELUM)
 */
class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private ?string $secretKey = null,
        private ?string $webhookSecret = null
    ) {}

    public function getName(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    /**
     * Create a Stripe Payment Intent.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createPaymentIntent(array $payload): array
    {
        $amount = $payload['amount'] ?? 0;
        $currency = strtoupper($payload['currency'] ?? 'IDR');
        $metadata = $payload['metadata'] ?? [];

        $response = Http::withToken($this->secretKey)
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => (int) $amount,
                'currency' => strtolower($currency),
                'metadata' => $metadata,
            ]);

        if ($response->failed()) {
            Log::error('Stripe payment intent failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->json('error.message') ?? 'Failed to create payment intent',
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    /**
     * Verify Stripe webhook signature and return event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): array
    {
        $payloadBody = $payload['body'] ?? '';
        $signature = $payload['signature'] ?? '';

        if (empty($this->webhookSecret)) {
            return [
                'success' => false,
                'error' => 'Webhook secret not configured',
            ];
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payloadBody,
                $signature,
                $this->webhookSecret
            );
        } catch (\Exception $e) {
            Log::error('Stripe webhook verification failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'data' => $event,
        ];
    }
}
