<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\ExcelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * API import/export data Excel/CSV.
 *
 * Diadopsi dari `import/` PerfexCRM (Import_customers, Import_items, Import_leads).
 * Proses berat (import besar / export banyak baris) disarankan via Job + queue.
 *
 * Endpoint:
 *   POST /api/excel/export   -> export data array ke file Excel/CSV
 *   POST /api/excel/import   -> import file (multipart) menjadi baris
 *
 * @group Excel
 */
class ExcelController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ExcelService $excel
    ) {}

    /**
     * Export data array menjadi file Excel/CSV.
     *
     * @authenticated
     *
     * @bodyParam rows array required Daftar baris data.
     * @bodyParam filename string required Nama file (tanpa ekstensi). Example: customers
     * @bodyParam extension string Format: xlsx|csv. Example: xlsx
     * @bodyParam headings array optional Daftar header kolom.
     *
     * @response {
     *   "data": { "path": "excel/customers.xlsx", "count": 3 }
     * }
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'filename' => ['required', 'string', 'max:120'],
            'extension' => ['nullable', 'in:xlsx,csv'],
            'headings' => ['nullable', 'array'],
        ]);

        $result = $this->excel->export(
            $validated['rows'],
            $validated['filename'],
            $validated['extension'] ?? 'xlsx',
            $validated['headings'] ?? []
        );

        return $this->created($result);
    }

    /**
     * Import file Excel/CSV menjadi baris data.
     *
     * @authenticated
     *
     * @bodyParam file file required File Excel/CSV (baris pertama = heading).
     *
     * @response {
     *   "data": { "count": 2, "rows": [ { "name": "A", "email": "a@x.com" } ] }
     * }
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];

        $result = $this->excel->importUpload($file);

        return $this->ok($result);
    }
}
