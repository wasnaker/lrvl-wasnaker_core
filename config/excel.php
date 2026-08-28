<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Excel Import/Export Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi import/export (dari import/ PerfexCRM: Import_customers,
    | Import_items, Import_leads). File hasil tersimpan di storage disk ini.
    |
    */

    'disk' => env('EXCEL_DISK', 'local'),

    'dir' => 'excel',
];
