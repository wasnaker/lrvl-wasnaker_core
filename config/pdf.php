<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi generator PDF (dari App_bulk_pdf_export + pdf/ PerfexCRM).
    | Disk tempat menyimpan hasil generate/bulk-export.
    |
    */

    'disk' => env('PDF_DISK', 'local'),

    'defaults' => [
        'paper' => env('PDF_PAPER', 'a4'),
        'orientation' => env('PDF_ORIENTATION', 'portrait'),
        'encoding' => 'UTF-8',
    ],

    'dir' => 'pdf',
];
