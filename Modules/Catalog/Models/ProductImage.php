<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Foto adicional de un producto o servicio (la principal está en el producto). */
class ProductImage extends Model
{
    /** Fotos adicionales por ítem, además de la principal. */
    public const MAX = 8;

    protected $fillable = ['product_id', 'path', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
