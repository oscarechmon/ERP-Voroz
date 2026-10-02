<?php

declare(strict_types=1);

namespace Modules\Attendances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;

/** Insumo que un servicio usa normalmente, con su cantidad sugerida. */
class ServiceSupply extends Model
{
    protected $fillable = ['service_id', 'supply_id', 'default_quantity'];

    protected $casts = ['default_quantity' => 'decimal:2'];

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'supply_id')->withTrashed();
    }
}
