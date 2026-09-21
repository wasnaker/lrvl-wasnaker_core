<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Varnish HTTP Cache Purge
    |--------------------------------------------------------------------------
    |
    | Konfigurasi Varnish buat invalidasi cache otomatis saat module
    | enable/disable (via event ModuleActivated / ModuleDeactivated).
    |
    | Diisi dari .env:
    |   VARNISH_HOST, VARNISH_PORT, VARNISH_SECRET, VARNISH_PURGE_URL
    |
    | Jika VARNISH_PURGE_URL diisi, gunakan itu (prioritas). Jika tidak,
    | bangun URL dari HOST:PORT. Jika HOST kosong, nonaktifkan.
    */
    'enabled' => (bool) env('VARNISH_HOST', false),

    'host' => env('VARNISH_HOST'),

    'port' => (int) env('VARNISH_PORT', 6082),

    'secret' => env('VARNISH_SECRET'),

    'purge_url' => env('VARNISH_PURGE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Cache tags / URL patterns yang di-purge
    |--------------------------------------------------------------------------
    |
    | Setiap event module toggle mengirim PURGE ke semua pattern ini.
    | Gunakan regex (Varnish 4+) atau path exact (Varnish 6+ ban).
    */
    'purge_patterns' => [
        '/api/v1/modules/extensions',
        '/api/v1/modules',
        '/api/v1/dashboard',
    ],
];