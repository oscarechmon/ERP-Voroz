<?php

declare(strict_types=1);

namespace Modules\Packages\Listeners;

use App\Core\Exceptions\BusinessException;
use Modules\Packages\Models\CustomerPackage;
use Modules\Sales\Events\SaleCancelled;

/**
 * Anular la venta de un paquete anula el paquete del cliente. Si ya usó
 * sesiones, no se puede deshacer: la venta sigue vigente.
 */
class CancelCustomerPackagesOfSale
{
    public function handle(SaleCancelled $event): void
    {
        $packages = CustomerPackage::where('sale_id', $event->sale->id)->lockForUpdate()->get();

        $used = $packages->firstWhere('used_sessions', '>', 0);
        if ($used) {
            throw new BusinessException("No se puede anular: el paquete «{$used->package_name}» ya tiene sesiones usadas.");
        }

        foreach ($packages as $package) {
            $package->update(['status' => CustomerPackage::STATUS_CANCELLED]);
        }
    }
}
