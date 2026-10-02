<?php

declare(strict_types=1);

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Sales\Models\Sale;

/**
 * Venta registrada (no cotización), con su detalle ya guardado.
 *
 * Se despacha dentro de la transacción del checkout: si un módulo que escucha
 * lanza una excepción (p. ej. un paquete sin cliente), la venta no se guarda.
 */
class SaleCompleted
{
    use Dispatchable;

    public function __construct(public readonly Sale $sale) {}
}
