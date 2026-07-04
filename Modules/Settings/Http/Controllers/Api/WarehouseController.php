<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Settings\Http\Requests\WarehouseRequest;
use Modules\Settings\Models\Warehouse;

/**
 * Almacenes. `index` sirve tanto de listado para Configuración como de fuente de
 * opciones para los selects de inventario, compras y POS.
 */
class WarehouseController extends ApiController
{
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->with('branch:id,name')
            ->orderByDesc('is_default')
            ->get(['id', 'branch_id', 'name', 'code', 'is_default', 'is_active'])
            ->map(fn (Warehouse $w) => $this->format($w));

        return $this->ok($warehouses);
    }

    public function store(WarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());

        return $this->created($this->format($warehouse->load('branch')), 'Almacén creado.');
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());

        return $this->ok($this->format($warehouse->load('branch')), 'Almacén actualizado.');
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return $this->noContent('Almacén eliminado.');
    }

    private function format(Warehouse $w): array
    {
        return [
            'id' => $w->id,
            'branch_id' => $w->branch_id,
            'name' => $w->name,
            'code' => $w->code,
            'branch' => $w->branch?->name,
            'is_default' => $w->is_default,
            'is_active' => $w->is_active,
        ];
    }
}
