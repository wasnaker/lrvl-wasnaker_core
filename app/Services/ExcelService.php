<?php

declare(strict_types=1);

namespace App\Services;

use App\Exports\GenericExport;
use App\Imports\GenericImport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ExcelService — import/export data Excel/CSV.
 *
 * Diadopsi dari `import/` PerfexCRM (Import_customers, Import_items, Import_leads).
 * Pola: import/export berat dijalankan via Job + queue (prinsip proses berat).
 *
 * REF: docs/analisis-library-perfex.md (import/ — ❌ BELUM)
 */
class ExcelService
{
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('excel.disk', 'local'));
    }

    /**
     * Export data array menjadi file Excel/CSV di storage.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  string  $filename  tanpa ekstensi
     * @param  string  $extension  xlsx | csv
     * @param  list<string>  $headings
     * @return array{path: string, count: int}
     */
    public function export(array $rows, string $filename, string $extension = 'xlsx', array $headings = []): array
    {
        $name = $this->dir().'/'.$filename.'.'.$extension;

        Excel::store(
            new GenericExport($rows, $headings),
            $name,
            (string) config('excel.disk', 'local'),
            $extension === 'csv'
                ? \Maatwebsite\Excel\Excel::CSV
                : \Maatwebsite\Excel\Excel::XLSX
        );

        return [
            'path' => $name,
            'count' => count($rows),
        ];
    }

    /**
     * Import file Excel/CSV menjadi array baris (associative, key = heading).
     *
     * @param  string  $path  path relatif di storage disk excel
     * @return array{rows: list<array<string, mixed>>, count: int}
     */
    public function import(string $path): array
    {
        $import = new GenericImport;
        $fullPath = $this->disk()->path($path);

        if (! file_exists($fullPath)) {
            return ['rows' => [], 'count' => 0];
        }

        Excel::import($import, $fullPath);

        return [
            'rows' => $import->rows,
            'count' => count($import->rows),
        ];
    }

    /**
     * Upload file lalu langsung import (konvenien).
     *
     * @return array{rows: list<array<string, mixed>>, count: int}
     */
    public function importUpload(UploadedFile $file): array
    {
        $import = new GenericImport;
        Excel::import($import, $file);

        return [
            'rows' => $import->rows,
            'count' => count($import->rows),
        ];
    }

    private function dir(): string
    {
        return (string) config('excel.dir', 'excel');
    }
}
