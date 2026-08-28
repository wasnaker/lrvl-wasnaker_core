<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pengiriman SMS.
 *
 * Diadopsi dari `sms/` PerfexCRM (Twilio, Clickatell, Msg91) sebagai abstraksi
 * provider pluggable + channel notifikasi.
 *
 * Endpoint:
 *   POST /api/sms/send       -> kirim SMS via driver aktif/terpilih
 *   GET  /api/sms/drivers    -> daftar driver yang terkonfigurasi
 *
 * @group Sms
 */
class SmsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private SmsService $sms
    ) {}

    /**
     * Kirim SMS.
     *
     * @authenticated
     *
     * @bodyParam to string required Nomor tujuan (format internasional). Example: +6281234567890
     * @bodyParam body string required Isi pesan. Example: Kode OTP Anda: 123456
     * @bodyParam driver string optional Nama driver (twilio, log). Example: log
     *
     * @response {
     *   "data": { "success": true, "message": "SMS terkirim" }
     * }
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'body' => ['required', 'string', 'max:1600'],
            'driver' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->sms->send($validated);

        return $this->ok($result);
    }

    /**
     * Daftar driver SMS yang terkonfigurasi.
     *
     * @authenticated
     *
     * @response {
     *   "data": ["log"]
     * }
     */
    public function drivers(): JsonResponse
    {
        return $this->ok($this->sms->availableDrivers());
    }
}
