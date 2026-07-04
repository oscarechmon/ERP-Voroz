<?php

declare(strict_types=1);

namespace Modules\Reports\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Stock;
use Modules\Purchases\Models\Purchase;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;

/**
 * Genera los conjuntos de datos de los reportes. Cada método devuelve una
 * estructura uniforme { title, headings, rows, records, summary } que sirve tanto
 * para el frontend (records) como para la exportación (headings + rows).
 */
class ReportService
{
    /** Reporte de ventas por rango de fechas. */
    public function sales(?string $from, ?string $to): array
    {
        [$from, $to] = $this->range($from, $to);

        $sales = Sale::completed()
            ->with(['customer:id,name', 'user:id,name'])
            ->whereBetween('sold_at', [$from, $to])
            ->orderBy('sold_at')
            ->get();

        $rows = $sales->map(fn (Sale $s) => [
            $s->full_number,
            $s->doc_type,
            optional($s->sold_at)->format('d/m/Y H:i'),
            $s->customer?->name ?? 'Público general',
            $s->user?->name ?? '',
            (float) $s->subtotal,
            (float) $s->tax,
            (float) $s->total,
        ])->all();

        return [
            'title' => 'Reporte de ventas',
            'headings' => ['Comprobante', 'Tipo', 'Fecha', 'Cliente', 'Vendedor', 'Base', 'IGV', 'Total'],
            'rows' => $rows,
            'records' => $sales->map(fn (Sale $s) => [
                'full_number' => $s->full_number,
                'doc_type' => $s->doc_type,
                'sold_at' => $s->sold_at?->toIso8601String(),
                'customer' => $s->customer?->name ?? 'Público general',
                'user' => $s->user?->name,
                'subtotal' => (float) $s->subtotal,
                'tax' => (float) $s->tax,
                'total' => (float) $s->total,
            ])->all(),
            'summary' => [
                'count' => $sales->count(),
                'subtotal' => round((float) $sales->sum('subtotal'), 2),
                'tax' => round((float) $sales->sum('tax'), 2),
                'total' => round((float) $sales->sum('total'), 2),
            ],
        ];
    }

    /** Reporte de utilidad por producto en un rango. */
    public function profit(?string $from, ?string $to): array
    {
        [$from, $to] = $this->range($from, $to);

        $data = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->groupBy('sale_items.product_id', 'sale_items.description')
            ->orderByDesc('profit')
            ->get([
                'sale_items.description as name',
                DB::raw('SUM(sale_items.quantity) as qty'),
                DB::raw('SUM(sale_items.subtotal) as revenue'),
                DB::raw('SUM(sale_items.cost * sale_items.quantity) as cost'),
                DB::raw('SUM(sale_items.subtotal - (sale_items.cost * sale_items.quantity)) as profit'),
            ]);

        $rows = $data->map(fn ($r) => [
            $r->name,
            (float) $r->qty,
            round((float) $r->revenue, 2),
            round((float) $r->cost, 2),
            round((float) $r->profit, 2),
            $r->revenue > 0 ? round(($r->profit / $r->revenue) * 100, 1) . '%' : '0%',
        ])->all();

        return [
            'title' => 'Reporte de utilidad',
            'headings' => ['Producto', 'Cantidad', 'Ingresos', 'Costo', 'Utilidad', 'Margen'],
            'rows' => $rows,
            'records' => $data->map(fn ($r) => [
                'name' => $r->name,
                'qty' => (float) $r->qty,
                'revenue' => round((float) $r->revenue, 2),
                'cost' => round((float) $r->cost, 2),
                'profit' => round((float) $r->profit, 2),
                'margin' => $r->revenue > 0 ? round(($r->profit / $r->revenue) * 100, 1) : 0,
            ])->all(),
            'summary' => [
                'revenue' => round((float) $data->sum('revenue'), 2),
                'cost' => round((float) $data->sum('cost'), 2),
                'profit' => round((float) $data->sum('profit'), 2),
            ],
        ];
    }

    /** Reporte de stock valorizado (existencias actuales). */
    public function stock(): array
    {
        $stocks = Stock::with(['product:id,code,name', 'warehouse:id,name'])
            ->where('quantity', '>', 0)
            ->get();

        $rows = $stocks->map(fn (Stock $s) => [
            $s->product?->code,
            $s->product?->name,
            $s->warehouse?->name,
            (float) $s->quantity,
            (float) $s->avg_cost,
            round((float) $s->quantity * (float) $s->avg_cost, 2),
        ])->all();

        return [
            'title' => 'Stock valorizado',
            'headings' => ['Código', 'Producto', 'Almacén', 'Cantidad', 'Costo prom.', 'Valorizado'],
            'rows' => $rows,
            'records' => $stocks->map(fn (Stock $s) => [
                'code' => $s->product?->code,
                'name' => $s->product?->name,
                'warehouse' => $s->warehouse?->name,
                'quantity' => (float) $s->quantity,
                'avg_cost' => (float) $s->avg_cost,
                'valued' => round((float) $s->quantity * (float) $s->avg_cost, 2),
            ])->all(),
            'summary' => ['total_valued' => round((float) $stocks->sum(fn ($s) => $s->quantity * $s->avg_cost), 2)],
        ];
    }

    /** Reporte de ventas por vendedor. */
    public function sellers(?string $from, ?string $to): array
    {
        [$from, $to] = $this->range($from, $to);

        $data = Sale::query()
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get(['users.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(sales.total) as total')]);

        $rows = $data->map(fn ($r) => [$r->name, (int) $r->count, round((float) $r->total, 2)])->all();

        return [
            'title' => 'Ventas por vendedor',
            'headings' => ['Vendedor', 'N° ventas', 'Total'],
            'rows' => $rows,
            'records' => $data->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count, 'total' => round((float) $r->total, 2)])->all(),
            'summary' => ['total' => round((float) $data->sum('total'), 2)],
        ];
    }

    /** Reporte de compras por rango. */
    public function purchases(?string $from, ?string $to): array
    {
        [$from, $to] = $this->range($from, $to);

        $purchases = Purchase::with('supplier:id,name')
            ->where('status', 'completed')
            ->whereBetween('purchased_at', [$from, $to])
            ->orderBy('purchased_at')
            ->get();

        $rows = $purchases->map(fn (Purchase $p) => [
            $p->number,
            optional($p->purchased_at)->format('d/m/Y'),
            $p->supplier?->name ?? '',
            $p->supplier_doc ?? '',
            (float) $p->total,
        ])->all();

        return [
            'title' => 'Reporte de compras',
            'headings' => ['Número', 'Fecha', 'Proveedor', 'Documento', 'Total'],
            'rows' => $rows,
            'records' => $purchases->map(fn (Purchase $p) => [
                'number' => $p->number,
                'purchased_at' => $p->purchased_at?->toIso8601String(),
                'supplier' => $p->supplier?->name,
                'supplier_doc' => $p->supplier_doc,
                'total' => (float) $p->total,
            ])->all(),
            'summary' => ['count' => $purchases->count(), 'total' => round((float) $purchases->sum('total'), 2)],
        ];
    }

    /** Normaliza el rango de fechas (por defecto: mes actual). */
    private function range(?string $from, ?string $to): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth();
        $to = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        return [$from, $to];
    }
}
