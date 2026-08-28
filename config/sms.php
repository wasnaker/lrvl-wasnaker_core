<?php

declare(strict_types=1);
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\TwilioSmsDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi SMS provider (dari sms/ PerfexCRM: Twilio, Clickatell, Msg91).
    | Driver aktif ditentukan dari SMS_DRIVER.
    |
    */

    'default' => env('SMS_DRIVER', 'log'),

    'from' => env('SMS_FROM', ''),

    'drivers' => [
        'log' => [
            'driver' => LogSmsDriver::class,
        ],
        'twilio' => [
            'driver' => TwilioSmsDriver::class,
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],
];
