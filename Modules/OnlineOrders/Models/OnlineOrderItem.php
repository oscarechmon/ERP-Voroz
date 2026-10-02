<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;

/** Línea de un pedido online (nombre y precio congelados al comprar). */
class OnlineOrderItem extends Model
{
    protected $fillable = ['online_order_id', 'product_id', 'item_type', 'name', 'unit_price', 'quantity', 'subtotal'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
