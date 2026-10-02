<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use App\Core\Exceptions\BusinessException;
use Modules\Settings\Models\Warehouse;

/**
 * Almacén por defecto (el marcado como tal, o el primero activo). De él salen
 * los insumos de las atenciones y lo que vende la web.
 */
class DefaultWarehouse
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
