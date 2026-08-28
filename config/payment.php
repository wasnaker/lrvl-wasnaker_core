<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi payment gateway. Aktifkan sesuai kebutuhan.
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'stripe'),

    'gateways' => [
        'stripe' => [
            'driver' => \App\Services\PaymentGateway\StripePaymentGateway::class,
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

];
