<?php

declare(strict_types=1);

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * GenericImport — import file Excel/CSV menjadi collection baris.
 *
 * Baris pertama (heading) dipakai sebagai key asosiatif tiap baris.
 *
 * Diadopsi dari `import/` PerfexCRM.
 *
 * @implements ToCollection
 */
class GenericImport implements ToCollection, WithHeadingRow
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $rows = [];

    public function collection(Collection $collection): void
    {
        foreach ($collection as $row) {
            if (! is_array($row->toArray())) {
                continue;
            }

            $this->rows[] = $row->toArray();
        }
    }
}
