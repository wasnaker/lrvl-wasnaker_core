<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\TwilioSmsDriver;
use Illuminate\Support\Facades\Config;

/**
 * SmsService — registry + factory untuk SMS provider.
 *
 * Diadopsi dari pola abstraksi `sms/` PerfexCRM (Twilio, Clickatell, Msg91).
 * Driver aktif dipilih dari config (SMS_DRIVER).
 *
 * REF: docs/analisis-library-perfex.md (sms/ — ❌ BELUM)
 */
class SmsService
{
    /**
     * @var array<string, SmsDriver>
     */
    private array $drivers = [];

    public function __construct()
    {
        $this->registerDrivers();
    }

    private function registerDrivers(): void
    {
        $drivers = Config::get('sms.drivers', []);

        foreach ($drivers as $name => $cfg) {
            $class = $cfg['driver'] ?? null;
            if (! $class || ! class_exists($class)) {
                continue;
            }

            $this->drivers[$name] = $this->resolve($class, $cfg);
        }
    }

    private function resolve(string $class, array $cfg): SmsDriver
    {
        // Driver dengan kredensial di konstruktor
        if ($class === TwilioSmsDriver::class) {
            return new $class(
                (string) ($cfg['account_sid'] ?? ''),
                (string) ($cfg['auth_token'] ?? ''),
                (string) ($cfg['from'] ?? '')
            );
        }

        return new $class;
    }

    public function driver(?string $name = null): SmsDriver
    {
        $name = $name ?: (string) Config::get('sms.default', 'log');

        return $this->drivers[$name] ?? $this->drivers['log'] ?? new LogSmsDriver;
    }

    /**
     * Daftar driver yang terkonfigurasi.
     *
     * @return list<string>
     */
    public function availableDrivers(): array
    {
        return array_values(array_filter($this->drivers, fn (SmsDriver $d): bool => $d->isConfigured()));
    }

    /**
     * Kirim SMS.
     *
     * @param  array{to: string, body: string, driver?: string|null}  $payload
     */
    public function send(array $payload): array
    {
        $to = $payload['to'] ?? '';
        $body = $payload['body'] ?? '';
        $driver = $payload['driver'] ?? null;

        if ($to === '' || $body === '') {
            return ['success' => false, 'message' => 'to dan body wajib diisi'];
        }

        return $this->driver($driver)->send($to, $body);
    }
}
