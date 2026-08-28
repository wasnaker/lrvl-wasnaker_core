<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * GenericExport — export data array menjadi file Excel/CSV.
 *
 * Diadopsi dari `import/` PerfexCRM (Import_customers, Import_items, Import_leads).
 * Kolom diambil dari key array pertama / headings yang diberikan.
 *
 * @implements FromArray
 */
class GenericExport implements FromArray, ShouldAutoSize, WithCustomCsvSettings, WithHeadings
{
    /**
     * @param  list<array<string, mixed>>  $data
     * @param  list<string>  $headings
     */
    public function __construct(
        private array $data,
        private array $headings = []
    ) {}

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        if (! empty($this->headings)) {
            return $this->headings;
        }

        return array_keys($this->data[0] ?? []);
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
        ];
    }
}
