<?php

declare(strict_types=1);

namespace Modules\Catalog\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación del catálogo de productos a Excel/CSV.
 * Recibe la colección ya filtrada (respeta el buscador de la tabla) y la
 * materializa como hoja con encabezado en negrita y ancho automático.
 */
class ProductsExport implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    /** @param Collection<int,\Modules\Catalog\Models\Product> $products */
    public function __construct(private readonly Collection $products)
    {
    }

    public function array(): array
    {
        return $this->products->map(fn ($p): array => [
            $p->code,
            $p->barcode,
            $p->name,
            $p->category?->name,
            $p->brand?->name,
            $p->unit?->abbreviation,
            (float) $p->cost,
            (float) $p->price,
            $p->stock_min,
            $p->is_active ? 'Activo' : 'Inactivo',
        ])->all();
    }

    public function headings(): array
    {
        return ['Código', 'Cód. barras', 'Nombre', 'Categoría', 'Marca', 'Unidad', 'Costo', 'Precio', 'Stock mín.', 'Estado'];
    }

    public function title(): string
    {
        return 'Productos';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
