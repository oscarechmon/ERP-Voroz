<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use App\Core\Exceptions\BusinessException;
use Modules\Settings\Models\Warehouse;

/**
 * Almacén del que vende la web: el marcado por defecto (o el primero activo).
 * Su stock es el que ve la tienda online y el que descuentan sus pedidos.
 */
class IntegrationWarehouse
{
    private ?int $id = null;

    public function id(): int
    {
        return $this->id ??= (int) (Warehouse::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id')
            ?? throw new BusinessException('No hay ningún almacén activo en el sistema.', 409));
    }
}
