<?php

declare(strict_types=1);

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Sales\Models\Sale;

/**
 * Venta anulada. Se despacha dentro de la transacción de la anulación: un
 * módulo que no pueda deshacer lo suyo (un paquete con sesiones ya usadas)
 * lanza una excepción y la venta sigue vigente.
 */
class SaleCancelled
{
    use Dispatchable;

    public function __construct(public readonly Sale $sale) {}
}
