<?php

declare(strict_types=1);

namespace Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;

/** Línea de detalle de una compra. */
class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'description', 'quantity', 'cost', 'subtotal'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
