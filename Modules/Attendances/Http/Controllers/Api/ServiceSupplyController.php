<?php

declare(strict_types=1);

namespace Modules\Attendances\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Attendances\Models\ServiceSupply;
use Modules\Catalog\Models\Product;

/** Insumos que usa normalmente cada servicio (se proponen al registrar la atención). */
class ServiceSupplyController extends ApiController
{
    public function index(int $service): JsonResponse
    {
        Product::where('type', Product::TYPE_SERVICE)->findOrFail($service);

        return $this->ok($this->present($service));
    }

    public function update(Request $request, int $service): JsonResponse
    {
        Product::where('type', Product::TYPE_SERVICE)->findOrFail($service);

        $data = $request->validate([
            'supplies' => ['present', 'array'],
            'supplies.*.supply_id' => ['required', 'distinct', Rule::exists('products', 'id')->where('type', Product::TYPE_PRODUCT)],
            'supplies.*.default_quantity' => ['required', 'numeric', 'gt:0'],
        ], ['supplies.*.supply_id.distinct' => 'Un insumo está repetido.']);

        DB::transaction(function () use ($service, $data): void {
            ServiceSupply::where('service_id', $service)->delete();
            foreach ($data['supplies'] as $row) {
                ServiceSupply::create(['service_id' => $service] + $row);
            }
        });

        return $this->ok($this->present($service), 'Insumos del servicio guardados.');
    }

    /** @return list<array<string, mixed>> */
    private function present(int $service): array
    {
        return ServiceSupply::with('supply:id,name,track_stock')
            ->where('service_id', $service)
            ->get()
            ->map(fn (ServiceSupply $s) => [
                'supply_id' => $s->supply_id,
                'name' => $s->supply?->name,
                'default_quantity' => (float) $s->default_quantity,
            ])
            ->values()
            ->all();
    }
}
