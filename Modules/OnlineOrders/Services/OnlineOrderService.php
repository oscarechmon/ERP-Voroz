<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\OnlineOrders\Events\OnlineOrderStatusChanged;
use Modules\OnlineOrders\Models\OnlineOrder;
use Modules\Sales\Services\SaleService;

/**
 * Seguimiento de los pedidos online: el personal los avanza (preparación,
 * envío o listo para recoger, entrega) o los anula.
 *
 * Anular un pedido cobrado anula su venta, que devuelve el stock. El reembolso
 * del dinero se hace desde el panel de Izipay.
 */
class OnlineOrderService
{
    public function __construct(private readonly SaleService $sales) {}

    public function changeStatus(OnlineOrder $order, string $to, ?string $note = null): OnlineOrder
    {
        return DB::transaction(function () use ($order, $to, $note): OnlineOrder {
            /** @var OnlineOrder $locked */
            $locked = OnlineOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($to, $locked->nextStatuses(), true)) {
                throw new BusinessException('El pedido no puede pasar de «'.self::label($locked->status).'» a «'.self::label($to).'».');
            }

            $wasPaid = $locked->isPaid();
            $locked->update(['status' => $to]);

            if ($to === OnlineOrder::CANCELLED && $wasPaid && $locked->sale && $locked->sale->status === 'completed') {
                $this->sales->cancel($locked->sale, $note ?: "Pedido web {$locked->code} anulado");
            }

            $user = auth()->user();
            $history = $locked->histories()->create([
                'status' => $to,
                'note' => $note,
                'internal' => false,
                'source' => 'sistema',
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'happened_at' => now(),
            ]);

            OnlineOrderStatusChanged::dispatch($locked, $history);

            return $locked->fresh(['items', 'histories', 'customer', 'sale']);
        });
    }

    public static function label(string $status): string
    {
        return match ($status) {
            OnlineOrder::PENDING_PAYMENT => 'Pendiente de pago',
            OnlineOrder::PAYMENT_FAILED => 'Pago rechazado',
            OnlineOrder::PAID => 'Pagado',
            OnlineOrder::PREPARING => 'En preparación',
            OnlineOrder::SHIPPED => 'En camino',
            OnlineOrder::READY_FOR_PICKUP => 'Listo para recoger',
            OnlineOrder::DELIVERED => 'Entregado',
            OnlineOrder::CANCELLED => 'Anulado',
            default => $status,
        };
    }
}
