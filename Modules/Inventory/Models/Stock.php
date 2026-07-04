<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;
use Modules\Settings\Models\Warehouse;

/** Existencia de un producto en un almacén (cantidad + costo promedio). */
class Stock extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'quantity', 'avg_cost'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'avg_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
