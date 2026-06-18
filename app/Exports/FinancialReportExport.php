<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinancialReportExport implements FromCollection, WithHeadings
{
    public function __construct(
        private array $fields,
        private array $headings,
        private array|Collection $rows,
        private string $title = ''
    ) {
    }

    public function collection(): Collection
    {
        $fields = $this->fields;

        return collect($this->rows)->map(function ($row) use ($fields) {
            if (! is_array($row)) {
                return [$row];
            }

            return array_map(fn ($column) => $row[$column] ?? '', $fields);
        });
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
