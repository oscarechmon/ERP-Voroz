<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Http\Resources\StockResource;
use Modules\Inventory\Models\Stock;

/** Consulta del stock actual por producto/almacén, con alertas de stock bajo. */
class StockController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $query = Stock::query()
            ->with(['product:id,code,name,stock_min,price', 'warehouse:id,name'])
            ->when($request->filled('warehouse_id'), fn (Builder $q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $term = $request->string('search');
                $q->whereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
            })
            // Filtro de stock bajo: cantidad <= stock mínimo del producto.
            ->when($request->boolean('low'), fn (Builder $q) => $q->whereRaw(
                'stocks.quantity <= (select stock_min from products where products.id = stocks.product_id)',
            ))
            ->orderByDesc('id');

        $stocks = $query->paginate($perPage)->withQueryString();

        return $this->ok(StockResource::collection($stocks)->response()->getData(true));
    }

    /** Resumen para el dashboard/alertas: productos sin stock y con stock bajo. */
    public function summary(): JsonResponse
    {
        $outOfStock = Stock::where('quantity', '<=', 0)->count();
        $lowStock = Stock::whereRaw('stocks.quantity <= (select stock_min from products where products.id = stocks.product_id)')
            ->where('quantity', '>', 0)
            ->count();

        return $this->ok([
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'total_valued' => round((float) Stock::sum(DB::raw('quantity * avg_cost')), 2),
        ]);
    }
}
