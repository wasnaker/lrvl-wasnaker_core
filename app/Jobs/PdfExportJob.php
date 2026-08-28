<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * PdfExportJob — generate banyak PDF + zip secara background (proses berat).
 *
 * Sesuai prinsip: proses berat tidak dijalankan langsung di HTTP request.
 * Client mendapatkan path hasil setelah job selesai (polling/query).
 *
 * Diadopsi dari `App_bulk_pdf_export.php` PerfexCRM.
 */
class PdfExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  list<array{filename?: string, html: string}>  $documents
     */
    public function __construct(
        public array $documents,
        public string $prefix = 'export'
    ) {}

    public function handle(PdfService $pdf): void
    {
        $result = $pdf->bulkExport([
            'documents' => $this->documents,
            'prefix' => $this->prefix,
        ]);

        // Hasil dicatat agar bisa dipantau (opsional: simpan ke cache/event).
        Cache::put(
            'pdf_export_result_'.md5(json_encode($this->documents)),
            $result,
            now()->addHours(24)
        );
    }
}
