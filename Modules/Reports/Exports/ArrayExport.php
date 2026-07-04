<?php

declare(strict_types=1);

namespace Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación genérica a Excel/CSV a partir de encabezados + filas.
 * Reutilizable por todos los reportes (DRY): el ReportService produce los datos
 * y esta clase los materializa como hoja de cálculo.
 */
class ArrayExport implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly array $rows,
        private readonly array $headings,
        private readonly string $title = 'Reporte',
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        // Encabezado en negrita.
        return [1 => ['font' => ['bold' => true]]];
    }
}
