<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * Kontrak driver SMS (dari sms/ PerfexCRM).
 */
interface SmsDriver
{
    /**
     * Kirim SMS.
     *
     * @param  string  $to  nomor tujuan (format internasional, mis. +6281234...)
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, raw?: mixed}
     */
    public function send(string $to, string $body, array $options = []): array;

    /**
     * Apakah driver terkonfigurasi (kredensial tersedia)?
     */
    public function isConfigured(): bool;
}
