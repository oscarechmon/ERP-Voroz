<?php

declare(strict_types=1);

namespace Modules\Packages\Listeners;

use App\Core\Exceptions\BusinessException;
use Modules\Catalog\Models\Product;
use Modules\Packages\Models\Package;
use Modules\Packages\Services\CustomerPackageService;
use Modules\Sales\Events\SaleCompleted;

/**
 * Vender un paquete crea su saldo de sesiones para el cliente (uno por unidad
 * vendida). Sin cliente no hay a quién dárselo, así que la venta no procede.
 */
class CreateCustomerPackagesFromSale
{
    public function __construct(private readonly CustomerPackageService $customerPackages) {}

    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        $lines = $sale->items()
            ->whereHas('product', fn ($q) => $q->withTrashed()->where('type', Product::TYPE_PACKAGE))
            ->get();

        if ($lines->isEmpty()) {
            return;
        }

        if (! $sale->customer_id) {
            throw new BusinessException('Para vender un paquete, selecciona el cliente.');
        }

        foreach ($lines as $line) {
            $package = Package::withTrashed()->where('product_id', $line->product_id)->first();
            if (! $package) {
                continue;
            }

            $units = max(1, (int) floor((float) $line->quantity));
            $unitPrice = round((float) $line->subtotal / $units, 2);

            for ($i = 0; $i < $units; $i++) {
                $this->customerPackages->assign($package, (int) $sale->customer_id, $unitPrice, $sale->id);
            }
        }
    }
}
