<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * TwilioSmsDriver — kirim SMS via Twilio REST API.
 *
 * Tidak bergantung SDK eksternal; memakai Laravel HTTP client.
 */
class TwilioSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from
    ) {}

    public function send(string $to, string $body, array $options = []): array
    {
        $base = 'https://api.twilio.com/2010-04-01/Accounts/'.$this->accountSid.'/Messages.json';

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post($base, [
                'To' => $to,
                'From' => $this->from,
                'Body' => $body,
            ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'Twilio API error: '.$response->status(),
                'raw' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'message' => 'SMS terkirim',
            'raw' => $response->json(),
        ];
    }

    public function isConfigured(): bool
    {
        return $this->accountSid !== '' && $this->authToken !== '' && $this->from !== '';
    }
}
