<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\OnlineOrders\Http\Resources\OnlineOrderResource;
use Modules\OnlineOrders\Models\OnlineOrder;
use Modules\OnlineOrders\Services\OnlineOrderService;
use Modules\OnlineOrders\Services\ShippingRates;

/** Pedidos de la tienda online y su seguimiento. */
class OnlineOrderController extends ApiController
{
    public function __construct(private readonly OnlineOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_merge(OnlineOrder::STATUSES, ['active']))],
            'fulfillment' => ['nullable', Rule::in(OnlineOrder::FULFILLMENTS)],
            'per_page' => ['nullable', 'integer'],
        ]);

        $orders = OnlineOrder::with('customer:id,name')
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('code', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "%{$term}%")
                ->orWhere('recipient_name', 'like', "%{$term}%")
                ->orWhere('document_number', 'like', "%{$term}%")))
            // "active" = cobrados y aún sin entregar: lo que el personal tiene que mover.
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $q) => $q->whereIn('status', [OnlineOrder::PAID, OnlineOrder::PREPARING, OnlineOrder::SHIPPED, OnlineOrder::READY_FOR_PICKUP]))
            ->when(($filters['status'] ?? 'active') !== 'active', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['fulfillment'] ?? null, fn (Builder $q, string $f) => $q->where('fulfillment', $f))
            ->orderByDesc('ordered_at')->orderByDesc('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 15), 1), 100));

        return $this->ok(OnlineOrderResource::collection($orders)->response()->getData(true));
    }

    public function show(int $order): JsonResponse
    {
        return $this->ok(new OnlineOrderResource(OnlineOrder::with(['items', 'histories', 'customer', 'sale'])->findOrFail($order)));
    }

    public function status(Request $request, int $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(OnlineOrder::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->service->changeStatus(OnlineOrder::findOrFail($order), $data['status'], $data['note'] ?? null);

        return $this->ok(new OnlineOrderResource($result), 'Pedido '.$result->code.': '.OnlineOrderService::label($result->status).'.');
    }

    public function shipping(ShippingRates $rates): JsonResponse
    {
        return $this->ok($rates->all());
    }

    public function updateShipping(Request $request, ShippingRates $rates): JsonResponse
    {
        $data = $request->validate([
            'delivery' => ['required', 'array'],
            'delivery.enabled' => ['required', 'boolean'],
            'delivery.fee' => ['required', 'numeric', 'min:0', 'max:9999'],
            'province' => ['required', 'array'],
            'province.enabled' => ['required', 'boolean'],
            'province.fee' => ['required', 'numeric', 'min:0', 'max:9999'],
        ], [
            'delivery.fee.required' => 'Indica el costo del delivery en Lima.',
            'province.fee.required' => 'Indica el costo del envío a provincia.',
        ]);

        return $this->ok($rates->update($data), 'Costos de envío guardados. La web los cobra desde ahora.');
    }
}
