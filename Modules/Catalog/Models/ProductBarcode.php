<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código de barras adicional de un producto. */
class ProductBarcode extends Model
{
    protected $fillable = ['product_id', 'barcode', 'type'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
