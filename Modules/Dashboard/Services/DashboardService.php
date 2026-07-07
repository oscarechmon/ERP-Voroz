<?php

declare(strict_types=1);

namespace Modules\Dashboard\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Models\Product;
use Modules\Contacts\Models\Customer;
use Modules\Inventory\Models\Stock;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;

/**
 * Agrega las métricas del dashboard a partir de las ventas e inventario reales.
 * Cada consulta está acotada y agrupada en la base de datos (no en PHP) para
 * mantener el rendimiento con grandes volúmenes.
 */
class DashboardService
{
    public function metrics(): array
    {
        $today = Carbon::today();

        return [
            'sales_today' => $this->salesTotal(fn ($q) => $q->whereDate('sold_at', $today)),
            'sales_month' => $this->salesTotal(fn ($q) => $q->whereYear('sold_at', $today->year)->whereMonth('sold_at', $today->month)),
            'sales_year' => $this->salesTotal(fn ($q) => $q->whereYear('sold_at', $today->year)),
            'sales_count_today' => Sale::completed()->whereDate('sold_at', $today)->count(),
            'products_sold' => (float) SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completed')
                ->whereYear('sold_at', $today->year)->whereMonth('sold_at', $today->month))->sum('quantity'),
            'new_customers' => Customer::whereYear('created_at', $today->year)->whereMonth('created_at', $today->month)->count(),
            'out_of_stock' => Stock::where('quantity', '<=', 0)->count(),
            'low_stock' => Stock::whereRaw('stocks.quantity <= (select stock_min from products where products.id = stocks.product_id)')
                ->where('quantity', '>', 0)->count(),
            'profit_today' => $this->profit(fn ($q) => $q->whereDate('sold_at', $today)),
            'profit_month' => $this->profit(fn ($q) => $q->whereYear('sold_at', $today->year)->whereMonth('sold_at', $today->month)),
            'products_stock' => $this->productsStock(),
            'sales_by_day' => $this->salesByDay(),
            'sales_by_category' => $this->salesByCategory(),
            'top_products' => $this->topProducts(),
            'top_sellers' => $this->topSellers(),
            'recent_sales' => $this->recentSales(),
        ];
    }

    private function salesTotal(callable $scope): float
    {
        $query = Sale::completed();
        $scope($query);

        return round((float) $query->sum('total'), 2);
    }

    /** Utilidad = suma de (precio - costo) * cantidad de los ítems de ventas completadas. */
    private function profit(callable $scope): float
    {
        return round((float) SaleItem::whereHas('sale', function ($q) use ($scope): void {
            $q->where('status', 'completed');
            $scope($q);
        })->sum(DB::raw('(price - cost) * quantity')), 2);
    }

    /**
     * Stock actual de cada producto activo (para el carrusel del dashboard).
     * Suma las existencias de todos los almacenes en una sola consulta agregada.
     */
    private function productsStock(): array
    {
        return Product::query()
            ->leftJoin('stocks', 'stocks.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->groupBy('products.id', 'products.name', 'products.stock_min', 'products.image_path')
            ->orderBy('products.name')
            ->limit(60)
            ->get([
                'products.name',
                'products.stock_min',
                'products.image_path',
                DB::raw('COALESCE(SUM(stocks.quantity), 0) as stock'),
            ])
            ->map(fn ($r) => [
                'name' => $r->name,
                'stock' => round((float) $r->stock, 2),
                'stock_min' => (float) $r->stock_min,
                'image_url' => $r->image_path ? Storage::url($r->image_path) : null,
            ])
            ->all();
    }

    /** Ventas de los últimos 14 días agrupadas por fecha. */
    private function salesByDay(): array
    {
        $from = Carbon::today()->subDays(13);

        $rows = Sale::completed()
            ->where('sold_at', '>=', $from)
            ->groupBy('d')
            ->orderBy('d')
            ->get([DB::raw('DATE(sold_at) as d'), DB::raw('SUM(total) as total')])
            ->keyBy('d');

        // Rellena los días sin ventas con 0 para una serie continua.
        $series = [];
        for ($i = 0; $i < 14; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => Carbon::parse($date)->format('d/m'),
                'total' => round((float) ($rows[$date]->total ?? 0), 2),
            ];
        }

        return $series;
    }

    private function salesByCategory(): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.status', 'completed')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get([
                DB::raw('COALESCE(categories.name, "Sin categoría") as category'),
                DB::raw('SUM(sale_items.subtotal) as total'),
            ])
            ->map(fn ($r) => ['category' => $r->category, 'total' => round((float) $r->total, 2)])
            ->all();
    }

    private function topProducts(): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->groupBy('sale_items.product_id', 'sale_items.description')
            ->orderByDesc('qty')
            ->limit(5)
            ->get([
                'sale_items.description as name',
                DB::raw('SUM(sale_items.quantity) as qty'),
                DB::raw('SUM(sale_items.subtotal) as total'),
            ])
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (float) $r->qty, 'total' => round((float) $r->total, 2)])
            ->all();
    }

    private function topSellers(): array
    {
        return Sale::query()
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->where('sales.status', 'completed')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get(['users.name', DB::raw('SUM(sales.total) as total'), DB::raw('COUNT(*) as count')])
            ->map(fn ($r) => ['name' => $r->name, 'total' => round((float) $r->total, 2), 'count' => (int) $r->count])
            ->all();
    }

    private function recentSales(): array
    {
        return Sale::completed()
            ->with('customer:id,name')
            ->orderByDesc('sold_at')
            ->limit(6)
            ->get()
            ->map(fn (Sale $s) => [
                'full_number' => $s->full_number,
                'customer' => $s->customer?->name ?? 'Público general',
                'total' => (float) $s->total,
                'sold_at' => $s->sold_at?->toIso8601String(),
            ])
            ->all();
    }
}
