<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Operación o registro de la web ya aplicado en el sistema (llave de
 * idempotencia). Los registros importados apuntan al modelo creado aquí.
 */
class IntegrationReference extends Model
{
    public const KIND_CONSUMPTION = 'consumption';

    /** Registro de la web enlazado con uno de aquí (`web:{tipo}:{id}`). */
    public const KIND_LINK = 'link';

    protected $fillable = ['reference', 'kind', 'model_type', 'model_id'];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
