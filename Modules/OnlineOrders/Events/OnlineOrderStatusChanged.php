<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\OnlineOrders\Models\OnlineOrder;
use Modules\OnlineOrders\Models\OnlineOrderStatusHistory;

/** El personal movió un pedido en su seguimiento (la integración se lo avisa a la web). */
class OnlineOrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly OnlineOrder $order,
        public readonly OnlineOrderStatusHistory $history,
    ) {}
}
