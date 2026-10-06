<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Services;

use Modules\OnlineOrders\Models\OnlineOrder;
use Modules\OnlineOrders\Models\StoreSetting;

/**
 * Costos de envío de la tienda online: delivery en Lima y envío a provincia
 * por agencia. Se cambian aquí; la web los lee al armar el pedido.
 */
class ShippingRates
{
    /** Lo que se cobra si nadie lo cambió todavía. */
    private const DEFAULT_FEES = [OnlineOrder::DELIVERY => '10.00', OnlineOrder::PROVINCE => '12.00'];

    /** @return array<string, array{enabled: bool, fee: float, label: string}> */
    public function all(): array
    {
        $rates = [];

        foreach (self::DEFAULT_FEES as $zone => $default) {
            $rates[$zone] = [
                'enabled' => filter_var(StoreSetting::get("{$zone}_enabled", '1'), FILTER_VALIDATE_BOOL),
                'fee' => round((float) StoreSetting::get("{$zone}_fee", $default), 2),
                'label' => OnlineOrder::fulfillmentLabel($zone),
            ];
        }

        return $rates;
    }

    /**
     * @param  array<string, array{enabled: bool, fee: float|int|string}>  $data
     * @return array<string, array{enabled: bool, fee: float, label: string}>
     */
    public function update(array $data): array
    {
        foreach (array_keys(self::DEFAULT_FEES) as $zone) {
            if (! isset($data[$zone])) {
                continue;
            }

            StoreSetting::put("{$zone}_enabled", $data[$zone]['enabled'] ? '1' : '0');
            StoreSetting::put("{$zone}_fee", number_format((float) $data[$zone]['fee'], 2, '.', ''));
        }

        return $this->all();
    }
}
