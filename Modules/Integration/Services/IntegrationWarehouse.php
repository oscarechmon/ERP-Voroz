<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Modules\Inventory\Services\DefaultWarehouse;

/**
 * Almacén del que vende la web: el almacén por defecto del sistema. Su stock
 * es el que ve la tienda online y el que descuentan sus pedidos.
 */
class IntegrationWarehouse extends DefaultWarehouse {}
