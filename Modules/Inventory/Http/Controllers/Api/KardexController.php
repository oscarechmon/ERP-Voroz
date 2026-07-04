<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Resources\MovementResource;
use Modules\Inventory\Models\InventoryMovement;

/** Kardex: historial de movimientos de un producto (o global) por almacén. */
class KardexController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $movements = InventoryMovement::query()
            ->with(['product:id,code,name', 'warehouse:id,name', 'user:id,name'])
            ->when($request->filled('product_id'), fn (Builder $q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn (Builder $q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->string('type')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return $this->ok(MovementResource::collection($movements)->response()->getData(true));
    }
}
