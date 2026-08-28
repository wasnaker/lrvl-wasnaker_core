<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\PaymentGateway\StripePaymentGateway;
use Illuminate\Support\Facades\Config;

/**
 * PaymentService — registry + factory untuk payment gateway.
 *
 * Memilih gateway aktif berdasarkan config, lalu delegasikan ke implementasi
 * PaymentGatewayInterface.
 *
 * Diadopsi dari pola abstraksi gateway di `gateways/` PerfexCRM.
 * REF: docs/analisis-library-perfex.md (gateways/ — ❌ BELUM)
 */
class PaymentService
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    private array $gateways = [];

    public function __construct()
    {
        $this->registerGateways();
    }

    /**
     * Register semua gateway yang tersedia.
     */
    private function registerGateways(): void
    {
        $this->gateways['stripe'] = new StripePaymentGateway(
            Config::get('payment.stripe.secret_key'),
            Config::get('payment.stripe.webhook_secret')
        );
    }

    /**
     * Dapatkan gateway berdasarkan nama.
     */
    public function gateway(string $name): ?PaymentGatewayInterface
    {
        return $this->gateways[$name] ?? null;
    }

    /**
     * Dapatkan daftar gateway yang terkonfigurasi.
     */
    public function availableGateways(): array
    {
        return array_values(array_filter($this->gateways, fn (PaymentGatewayInterface $g): bool => $g->isConfigured()));
    }

    /**
     * Create payment intent via gateway aktif.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createPaymentIntent(string $gateway, array $payload): array
    {
        $service = $this->gateway($gateway);

        if (!$service) {
            return ['success' => false, 'error' => 'Gateway not found'];
        }

        return $service->createPaymentIntent($payload);
    }

    /**
     * Handle webhook via gateway aktif.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(string $gateway, array $payload): array
    {
        $service = $this->gateway($gateway);

        if (!$service) {
            return ['success' => false, 'error' => 'Gateway not found'];
        }

        return $service->handleWebhook($payload);
    }
}
