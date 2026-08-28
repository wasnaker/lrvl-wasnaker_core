<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | QR Code Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi generator QR code (dari Endroid_qrcode.py PerfexCRM).
    | Dipakai untuk 2FA, signature, scan kode, dll.
    |
    */

    'size' => (int) env('QR_SIZE', 300),

    'margin' => (int) env('QR_MARGIN', 10),

    'encoding' => 'UTF-8',

    'error_correction' => 'medium', // low | medium | quartile | high
];
