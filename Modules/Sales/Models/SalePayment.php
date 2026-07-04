<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago aplicado a una venta (método + monto). */
class SalePayment extends Model
{
    protected $fillable = ['sale_id', 'method', 'amount', 'reference'];

    protected $casts = ['amount' => 'decimal:2'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
