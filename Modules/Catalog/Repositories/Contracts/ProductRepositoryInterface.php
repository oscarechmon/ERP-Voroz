<?php

declare(strict_types=1);

namespace Modules\Catalog\Repositories\Contracts;

use App\Core\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /** Busca un producto por su código de barras (principal o adicional). */
    public function findByBarcode(string $barcode): ?Model;

    /** Genera el siguiente código interno correlativo (PRD-000001…). */
    public function nextCode(): string;
}
