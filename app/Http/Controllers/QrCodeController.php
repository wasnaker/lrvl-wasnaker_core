<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API generator QR Code.
 *
 * Diadopsi dari `Endroid_qrcode.py` PerfexCRM.
 * Dipakai untuk 2FA, signature/scan kode, dll.
 *
 * Endpoint:
 *   POST /api/qr-code/generate   -> generate QR, return base64 PNG / data URI / stored path
 *
 * @group api/v1
     * @subgroup QrCode
 */
class QrCodeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private QrCodeService $qr
    ) {}

    /**
     * Generate QR Code.
     *
     * @authenticated
     *
     * @bodyParam content string required Konten/kode yang di-encode. Example: https://wasnaker.lan
     * @bodyParam size integer Ukuran pixel. Example: 300
     * @bodyParam margin integer Margin pixel. Example: 10
     * @bodyParam format string format output: base64 | data_uri | stored. Example: base64
     * @bodyParam filename string Nama file (jika format=stored). Example: qr-signature
     *
     * @response {
     *   "data": { "format": "base64", "base64": "...", "size": 300 }
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2048'],
            'size' => ['nullable', 'integer', 'between:50,2000'],
            'margin' => ['nullable', 'integer', 'between:0,100'],
            'format' => ['nullable', 'in:base64,data_uri,stored'],
            'filename' => ['nullable', 'string', 'max:120'],
        ]);

        $payload = [
            'content' => $validated['content'],
            'size' => $validated['size'] ?? null,
            'margin' => $validated['margin'] ?? null,
        ];
        $format = $validated['format'] ?? 'base64';

        $result = match ($format) {
            'data_uri' => ['format' => 'data_uri', 'data_uri' => $this->qr->dataUri($payload)],
            'stored' => ['format' => 'stored', 'path' => $this->qr->store([
                'content' => $validated['content'],
                'filename' => $validated['filename'] ?? 'qr_'.time(),
                'size' => $validated['size'] ?? null,
                'margin' => $validated['margin'] ?? null,
            ])],
            default => ['format' => 'base64', 'base64' => $this->qr->base64($payload)],
        };

        return $this->ok($result);
    }
}
