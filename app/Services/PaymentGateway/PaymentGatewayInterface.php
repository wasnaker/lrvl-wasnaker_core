<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

/**
 * PaymentGatewayInterface — abstraksi payment gateway.
 *
 * Setiap gateway (Stripe, PayPal, Mollie, dll) mengimplementasikan interface ini.
 * Core PaymentService memakai interface ini, bukan implementasi konkret.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment intent / session.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createPaymentIntent(array $payload): array;

    /**
     * Handle webhook notification.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): array;

    /**
     * Get gateway name.
     */
    public function getName(): string;

    /**
     * Check if gateway is configured/active.
     */
    public function isConfigured(): bool;
}
