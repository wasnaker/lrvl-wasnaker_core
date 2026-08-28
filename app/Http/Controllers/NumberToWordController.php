<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NumberToWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk konversi angka ke terbilang (Indonesian/Indian).
 *
 * Digunakan oleh frontend saat generate invoice PDF, dokumen keuangan, dll.
 *
 * @group api/v1
     * @subgroup Utilities
 */
class NumberToWordController extends Controller
{
    public function __construct(
        private NumberToWord $numberToWord
    ) {}

    /**
     * Konversi angka ke terbilang Indonesian (format Rupiah).
     *
     * @authenticated
     *
     * @bodyParam number numeric required Angka yang dikonversi. Example: 1234567
     * @bodyParam currency string optional Suffix mata uang. Default: rupiah. Example: rupiah
     *
     * @response scenario=success {
     *   "number": 1234567,
     *   "terbilang": "satu juta dua ratus tiga puluh empat ribu lima ratus enam puluh tujuh rupiah"
     * }
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number'   => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:20',
        ]);

        $terbilang = $this->numberToWord->convert($validated['number'], $validated['currency'] ?? 'rupiah');

        return response()->json([
            'number'   => (float) $validated['number'],
            'terbilang' => $terbilang,
        ]);
    }

    /**
     * Konversi angka ke terbilang Indian format (lakh/crore).
     *
     * @authenticated
     *
     * @bodyParam number numeric required Angka yang dikonversi. Example: 1234567
     * @bodyParam currency string optional Suffix mata uang. Default: (kosong). Example: INR
     *
     * @response scenario=success {
     *   "number": 1234567,
     *   "terbilang": "twelve lakh thirty four thousand five hundred sixty seven"
     * }
     */
    public function convertIndian(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number'   => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
        ]);

        $terbilang = $this->numberToWord->convertIndian($validated['number'], $validated['currency'] ?? '');

        return response()->json([
            'number'   => (float) $validated['number'],
            'terbilang' => $terbilang,
        ]);
    }
}