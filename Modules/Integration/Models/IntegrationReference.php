<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;

/** Operación de la web ya aplicada en el sistema (llave de idempotencia). */
class IntegrationReference extends Model
{
    public const KIND_CONSUMPTION = 'consumption';

    protected $fillable = ['reference', 'kind'];
}
