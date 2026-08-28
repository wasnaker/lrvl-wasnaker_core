<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * LogSmsDriver — dummy driver yang menulis ke log.
 *
 * Berguna untuk development/testing tanpa biaya SMS sungguhan.
 */
class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $body, array $options = []): array
    {
        Log::info('SMS [log-driver]', [
            'to' => $to,
            'body' => $body,
            'options' => $options,
        ]);

        return [
            'success' => true,
            'message' => 'SMS dikirim via log driver (no-op)',
        ];
    }

    public function isConfigured(): bool
    {
        return true;
    }
}
