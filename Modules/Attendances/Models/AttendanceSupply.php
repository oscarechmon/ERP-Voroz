<?php

declare(strict_types=1);

namespace Modules\Attendances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;

/** Insumo usado en una atención. */
class AttendanceSupply extends Model
{
    protected $fillable = ['attendance_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
