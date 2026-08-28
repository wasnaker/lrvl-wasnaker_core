<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Jobs\PdfExportJob;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API generator dokumen PDF.
 *
 * Diadopsi dari `pdf/` dan `App_bulk_pdf_export.php` PerfexCRM.
 * Mendukung generate single (sync) dan bulk export via Job (queue).
 *
 * Endpoint:
 *   POST /api/pdf/generate         -> generate satu PDF dari view, simpan ke storage
 *   POST /api/pdf/from-html        -> render HTML string menjadi PDF
 *   POST /api/pdf/bulk-export      -> dispatch job bulk PDF (background)
 *
 * @group api/v1
     * @subgroup Pdf
 */
class PdfController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PdfService $pdf
    ) {}

    /**
     * Generate satu PDF dari Blade view dan simpan ke storage.
     *
     * @authenticated
     *
     * @bodyParam view string required Nama view Blade. Example: pdf.invoice
     * @bodyParam data array Data yang diteruskan ke view.
     * @bodyParam filename string Nama file (tanpa ekstensi). Example: INV-0001
     * @bodyParam rel_type string Tipe entity (invoice, estimate, contract). Example: invoice
     * @bodyParam rel_id integer ID entity. Example: 10
     * @bodyParam tenant_id integer optional ID tenant. Example: 1
     * @bodyParam paper string Ukuran kertas (a4, letter). Example: a4
     * @bodyParam orientation string portrait|landscape. Example: portrait
     *
     * @response {
     *   "data": { "path": "tenants/1/invoice/10/pdf/INV-0001_20260828_123456.pdf" }
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'view' => ['required', 'string'],
            'data' => ['nullable', 'array'],
            'filename' => ['nullable', 'string', 'max:120'],
            'rel_type' => ['nullable', 'string', 'max:50'],
            'rel_id' => ['nullable', 'integer', 'min:0'],
            'tenant_id' => ['nullable', 'integer', 'min:1'],
            'paper' => ['nullable', 'string', 'max:20'],
            'orientation' => ['nullable', 'in:portrait,landscape'],
        ]);

        $path = $this->pdf->generate($validated);

        return $this->created(['path' => $path]);
    }

    /**
     * Render string HTML menjadi PDF (binary, tanpa simpan).
     *
     * @authenticated
     *
     * @bodyParam html string required Konten HTML.
     * @bodyParam paper string Ukuran kertas. Example: a4
     * @bodyParam orientation string portrait|landscape. Example: portrait
     *
     * @response data: "string" (binary PDF)
     */
    public function fromHtml(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'html' => ['required', 'string'],
            'paper' => ['nullable', 'string', 'max:20'],
            'orientation' => ['nullable', 'in:portrait,landscape'],
        ]);

        $binary = $this->pdf->fromHtml($validated);

        return $this->ok(base64_encode($binary));
    }

    /**
     * Bulk export banyak dokumen PDF menjadi satu ZIP (dijalankan via queue).
     *
     * @authenticated
     *
     * @bodyParam documents array required Daftar dokumen.
     * @bodyParam documents[].filename string Nama file per dokumen. Example: INV-0001
     * @bodyParam documents[].html string required HTML dokumen. Example: <h1>Invoice</h1>
     * @bodyParam prefix string Prefix nama ZIP. Example: invoices-2026
     *
     * @response {
     *   "data": { "job": "App\\Jobs\\PdfExportJob", "queued": true }
     * }
     */
    public function bulkExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.filename' => ['nullable', 'string', 'max:120'],
            'documents.*.html' => ['required', 'string'],
            'prefix' => ['nullable', 'string', 'max:120'],
        ]);

        PdfExportJob::dispatch(
            $validated['documents'],
            $validated['prefix'] ?? 'export'
        );

        return $this->accepted([
            'job' => PdfExportJob::class,
            'queued' => true,
        ]);
    }

    protected function accepted(mixed $data = null): JsonResponse
    {
        return response()->json(['data' => $data], 202);
    }
}
